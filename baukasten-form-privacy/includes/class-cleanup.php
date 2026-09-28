<?php
/**
 * Cleanup of what was stored before (F6).
 *
 * @package Baukasten\FormPrivacy
 */

namespace Baukasten\FormPrivacy;

defined( 'ABSPATH' ) || exit;

/**
 * Brings existing Flamingo data in line with the settings, in one go.
 *
 * The filters of this plugin only affect what is stored from now on. This
 * deals with what is already there:
 *
 * 1. Every address book contact is deleted for good, and every contact tag.
 * 2. Every stored message's metadata is reduced to the whitelist, and the
 *    Akismet and reCAPTCHA answers are emptied.
 * 3. The retention period is applied right away.
 *
 * Built like the hardening routine of the core: each step compares before it
 * writes and reports whether it changed anything, so a second run says there
 * was nothing left to do. It deletes data that cannot be brought back, so it
 * never runs on its own — not on activation, not on update — only from the
 * button on the tab, which asks first, or from `wp baukasten form-privacy clean`.
 */
final class Cleanup {

	/**
	 * Messages handled per query in step 2.
	 */
	private const BATCH = 200;

	/**
	 * Runs every step and returns what happened.
	 *
	 * @return array<int, array{label: string, changed: bool, detail: string}> Report rows.
	 */
	public static function run(): array {
		if ( ! Settings::has_flamingo() ) {
			return array(
				self::row( __( 'Flamingo', 'baukasten-form-privacy' ), false, __( 'Flamingo is not active, so there is nothing to clean up.', 'baukasten-form-privacy' ) ),
			);
		}

		return array(
			self::delete_contacts(),
			self::minimise_messages(),
			self::apply_retention(),
		);
	}

	/**
	 * Deletes every address book contact and contact tag.
	 *
	 * @return array{label: string, changed: bool, detail: string} Report row.
	 */
	private static function delete_contacts(): array {
		$label   = __( 'Address book', 'baukasten-form-privacy' );
		$deleted = 0;

		do {
			$ids = get_posts(
				array(
					'post_type'        => 'flamingo_contact',
					'post_status'      => array_keys( get_post_stati() ),
					'numberposts'      => self::BATCH,
					'fields'           => 'ids',
					'suppress_filters' => true,
				)
			);

			$found = count( $ids );

			foreach ( $ids as $id ) {
				if ( wp_delete_post( (int) $id, true ) ) {
					++$deleted;
				}
			}
		} while ( self::BATCH === $found );

		$terms = get_terms(
			array(
				'taxonomy'   => 'flamingo_contact_tag',
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		$terms_deleted = 0;

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term_id ) {
				if ( true === wp_delete_term( (int) $term_id, 'flamingo_contact_tag' ) ) {
					++$terms_deleted;
				}
			}
		}

		if ( 0 === $deleted && 0 === $terms_deleted ) {
			return self::row( $label, false, __( 'No change: the address book is empty.', 'baukasten-form-privacy' ) );
		}

		return self::row(
			$label,
			true,
			sprintf(
				/* translators: 1: number of contacts, 2: number of tags. */
				__( '%1$s contacts and %2$s tags deleted.', 'baukasten-form-privacy' ),
				number_format_i18n( $deleted ),
				number_format_i18n( $terms_deleted )
			)
		);
	}

	/**
	 * Reduces the metadata of every stored message to the whitelist.
	 *
	 * @return array{label: string, changed: bool, detail: string} Report row.
	 */
	private static function minimise_messages(): array {
		$label   = __( 'Metadata of stored messages', 'baukasten-form-privacy' );
		$changed = 0;
		$paged   = 1;

		do {
			$ids = get_posts(
				array(
					'post_type'        => Retention::POST_TYPE,
					'post_status'      => Retention::STATUSES,
					'numberposts'      => self::BATCH,
					'paged'            => $paged,
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'fields'           => 'ids',
					'suppress_filters' => true,
				)
			);

			$found = count( $ids );

			foreach ( $ids as $id ) {
				if ( Submissions::minimise_stored( (int) $id ) ) {
					++$changed;
				}
			}

			++$paged;
		} while ( self::BATCH === $found );

		if ( 0 === $changed ) {
			return self::row( $label, false, __( 'No change: every message holds only the allowed metadata.', 'baukasten-form-privacy' ) );
		}

		return self::row(
			$label,
			true,
			sprintf(
				/* translators: %s: number of messages. */
				_n( '%s message reduced.', '%s messages reduced.', $changed, 'baukasten-form-privacy' ),
				number_format_i18n( $changed )
			)
		);
	}

	/**
	 * Applies the retention period now.
	 *
	 * @return array{label: string, changed: bool, detail: string} Report row.
	 */
	private static function apply_retention(): array {
		$label   = __( 'Retention period', 'baukasten-form-privacy' );
		$deleted = Retention::purge();

		if ( 0 === $deleted ) {
			return self::row( $label, false, __( 'No change: no message is older than the retention period.', 'baukasten-form-privacy' ) );
		}

		return self::row(
			$label,
			true,
			sprintf(
				/* translators: %s: number of messages. */
				_n( '%s message deleted.', '%s messages deleted.', $deleted, 'baukasten-form-privacy' ),
				number_format_i18n( $deleted )
			)
		);
	}

	/**
	 * Builds a report row.
	 *
	 * @param string $label   What the step did.
	 * @param bool   $changed Whether anything was written.
	 * @param string $detail  Human readable detail.
	 * @return array{label: string, changed: bool, detail: string} Report row.
	 */
	private static function row( string $label, bool $changed, string $detail ): array {
		return array(
			'label'   => $label,
			'changed' => $changed,
			'detail'  => $detail,
		);
	}
}
