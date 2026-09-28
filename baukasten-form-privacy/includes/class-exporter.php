<?php
/**
 * Personal data exporter for stored submissions (F7).
 *
 * @package Baukasten\FormPrivacy
 */

namespace Baukasten\FormPrivacy;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Flamingo's messages to Tools → Export Personal Data.
 *
 * Flamingo registers an eraser but no exporter, so a request for access under
 * Article 15 GDPR came back without the one place a site keeps what visitors
 * wrote to it. Messages are found by their sender address, `_from_email`, in
 * every status, 50 per page.
 */
final class Exporter {

	/**
	 * Messages per page of the export.
	 */
	const PER_PAGE = 50;

	/**
	 * Registers the exporter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'add_exporter' ) );
	}

	/**
	 * Adds this exporter to core's list.
	 *
	 * @param mixed $exporters Registered exporters.
	 * @return array<string, array<string, mixed>> Exporters.
	 */
	public static function add_exporter( $exporters ): array {
		$exporters = (array) $exporters;

		$exporters['baukasten-form-privacy'] = array(
			'exporter_friendly_name' => __( 'Contact form messages (Flamingo)', 'baukasten-form-privacy' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Exports one page of messages sent from an address.
	 *
	 * @param string $email_address Address of the person asking.
	 * @param int    $page          Page, from 1.
	 * @return array{data: array<int, array<string, mixed>>, done: bool} Export page.
	 */
	public static function export( $email_address, $page = 1 ): array {
		$email_address = sanitize_email( (string) $email_address );
		$page          = max( 1, (int) $page );

		if ( '' === $email_address || ! Settings::has_flamingo() ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$ids = get_posts(
			array(
				'post_type'        => Retention::POST_TYPE,
				'post_status'      => Retention::STATUSES,
				'numberposts'      => self::PER_PAGE,
				'paged'            => $page,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'fields'           => 'ids',
				'suppress_filters' => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the only way to find a sender's messages.
				'meta_query'       => array(
					array(
						'key'   => '_from_email',
						'value' => $email_address,
					),
				),
			)
		);

		$data = array();

		foreach ( $ids as $id ) {
			$data[] = self::item( (int) $id );
		}

		return array(
			'data' => $data,
			'done' => count( $ids ) < self::PER_PAGE,
		);
	}

	/**
	 * One message as an export item.
	 *
	 * @param int $post_id Message post ID.
	 * @return array<string, mixed> Export item.
	 */
	private static function item( int $post_id ): array {
		$message = new \Flamingo_Inbound_Message( $post_id );
		$post    = get_post( $post_id );

		$data = array(
			array(
				'name'  => __( 'Subject', 'baukasten-form-privacy' ),
				'value' => (string) $message->subject,
			),
			array(
				'name'  => __( 'Received', 'baukasten-form-privacy' ),
				'value' => $post instanceof \WP_Post ? (string) $post->post_date : '',
			),
		);

		foreach ( (array) $message->fields as $name => $value ) {
			$data[] = array(
				'name'  => (string) $name,
				'value' => self::to_text( $value ),
			);
		}

		foreach ( (array) $message->meta as $name => $value ) {
			$data[] = array(
				/* translators: %s: name of a metadata field. */
				'name'  => sprintf( __( 'Metadata: %s', 'baukasten-form-privacy' ), (string) $name ),
				'value' => self::to_text( $value ),
			);
		}

		return array(
			'group_id'    => 'baukasten-form-privacy',
			'group_label' => __( 'Contact form messages', 'baukasten-form-privacy' ),
			'item_id'     => 'baukasten-form-privacy-' . $post_id,
			'data'        => $data,
		);
	}

	/**
	 * Flattens a stored value into text.
	 *
	 * @param mixed $value Field or meta value.
	 * @return string Text.
	 */
	private static function to_text( $value ): string {
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( array( __CLASS__, 'to_text' ), $value ) );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}
}
