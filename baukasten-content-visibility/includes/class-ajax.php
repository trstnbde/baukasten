<?php
/**
 * Ajax endpoint for the visibility toggle.
 *
 * @package Baukasten\ContentVisibility
 */

namespace Baukasten\ContentVisibility;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the switch in the post list tables.
 */
final class Ajax {

	/**
	 * Ajax action name.
	 */
	const ACTION = 'baukasten_content_visibility_toggle';

	/**
	 * Nonce action.
	 */
	const NONCE = 'baukasten_content_visibility';

	/**
	 * Registers the endpoint.
	 *
	 * Only the logged-in variant is registered: there is no case in which an
	 * anonymous request should reach this handler.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Validates and applies a visibility change.
	 *
	 * @return void
	 */
	public static function handle(): void {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Your session has expired. Please reload the page.', 'baukasten-content-visibility' ) ),
				403
			);
		}

		$post_id = isset( $_POST['post'] ) ? absint( wp_unslash( $_POST['post'] ) ) : 0;
		$post    = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! $post instanceof \WP_Post ) {
			wp_send_json_error(
				array( 'message' => __( 'This content could not be found.', 'baukasten-content-visibility' ) ),
				404
			);
		}

		if ( ! Visibility::is_supported( $post->post_type ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Visibility cannot be set for this content type.', 'baukasten-content-visibility' ) ),
				400
			);
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to edit this content.', 'baukasten-content-visibility' ) ),
				403
			);
		}

		$requested = isset( $_POST['visibility'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['visibility'] ) )
			: '';

		if ( ! in_array( $requested, Visibility::values(), true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unknown visibility value.', 'baukasten-content-visibility' ) ),
				400
			);
		}

		$stored = Visibility::set( $post->ID, $requested );

		wp_send_json_success(
			array(
				'post'       => $post->ID,
				'visibility' => $stored,
				'label'      => Visibility::VISIBILITY_PUBLIC === $stored
					? __( 'Public', 'baukasten-content-visibility' )
					: __( 'Private', 'baukasten-content-visibility' ),
				'message'    => __( 'Visibility saved.', 'baukasten-content-visibility' ),
			)
		);
	}
}
