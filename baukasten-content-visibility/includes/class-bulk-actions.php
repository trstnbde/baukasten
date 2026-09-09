<?php
/**
 * Bulk actions for the post list tables.
 *
 * @package Baukasten\ContentVisibility
 */

namespace Baukasten\ContentVisibility;

defined( 'ABSPATH' ) || exit;

/**
 * Adds "Set to public" and "Set to private" to the bulk actions dropdown.
 */
final class Bulk_Actions {

	/**
	 * Bulk action name for making content public.
	 */
	const ACTION_PUBLIC = 'baukasten_visibility_public';

	/**
	 * Bulk action name for making content private.
	 */
	const ACTION_PRIVATE = 'baukasten_visibility_private';

	/**
	 * Query argument carrying the number of changed posts.
	 */
	const ARG_CHANGED = 'baukasten_visibility_changed';

	/**
	 * Query argument carrying the number of skipped posts.
	 */
	const ARG_SKIPPED = 'baukasten_visibility_skipped';

	/**
	 * Registers the bulk action hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_init', array( __CLASS__, 'add_hooks' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
	}

	/**
	 * Hooks the actions into each supported list table.
	 *
	 * @return void
	 */
	public static function add_hooks(): void {
		foreach ( Visibility::get_post_types() as $post_type ) {
			add_filter( "bulk_actions-edit-{$post_type}", array( __CLASS__, 'add_actions' ) );
			add_filter( "handle_bulk_actions-edit-{$post_type}", array( __CLASS__, 'handle' ), 10, 3 );
		}
	}

	/**
	 * Adds the two actions to the dropdown.
	 *
	 * @param array<string, string> $actions Existing bulk actions.
	 * @return array<string, string> Filtered actions.
	 */
	public static function add_actions( $actions ): array {
		$actions = is_array( $actions ) ? $actions : array();

		$actions[ self::ACTION_PUBLIC ]  = __( 'Set visibility to public', 'baukasten-content-visibility' );
		$actions[ self::ACTION_PRIVATE ] = __( 'Set visibility to private', 'baukasten-content-visibility' );

		return $actions;
	}

	/**
	 * Applies the bulk action.
	 *
	 * WordPress verifies the bulk action nonce before this filter runs. The
	 * capability is still checked per post, because a bulk selection can
	 * contain content the current user may not edit.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action      Action being applied.
	 * @param int[]  $post_ids    Selected post IDs.
	 * @return string Redirect URL.
	 */
	public static function handle( $redirect_to, $action, $post_ids ): string {
		$redirect_to = (string) $redirect_to;

		if ( self::ACTION_PUBLIC !== $action && self::ACTION_PRIVATE !== $action ) {
			return $redirect_to;
		}

		$value = self::ACTION_PUBLIC === $action
			? Visibility::VISIBILITY_PUBLIC
			: Visibility::VISIBILITY_PRIVATE;

		$changed = 0;
		$skipped = 0;

		foreach ( (array) $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			$post    = get_post( $post_id );

			if ( ! $post instanceof \WP_Post || ! Visibility::is_supported( $post->post_type ) ) {
				++$skipped;
				continue;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				++$skipped;
				continue;
			}

			Visibility::set( $post_id, $value );
			++$changed;
		}

		$redirect_to = remove_query_arg( array( self::ARG_CHANGED, self::ARG_SKIPPED ), $redirect_to );

		return add_query_arg(
			array(
				self::ARG_CHANGED => $changed,
				self::ARG_SKIPPED => $skipped,
			),
			$redirect_to
		);
	}

	/**
	 * Shows the result of the bulk action.
	 *
	 * @return void
	 */
	public static function render_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display of the redirect result.
		if ( ! isset( $_GET[ self::ARG_CHANGED ] ) ) {
			return;
		}

		$changed = absint( wp_unslash( $_GET[ self::ARG_CHANGED ] ) );
		$skipped = isset( $_GET[ self::ARG_SKIPPED ] ) ? absint( wp_unslash( $_GET[ self::ARG_SKIPPED ] ) ) : 0;
		// phpcs:enable

		$message = sprintf(
			/* translators: %s: number of updated items. */
			_n(
				'Visibility updated for %s item.',
				'Visibility updated for %s items.',
				$changed,
				'baukasten-content-visibility'
			),
			number_format_i18n( $changed )
		);

		if ( $skipped > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %s: number of skipped items. */
				_n(
					'%s item was skipped because you may not edit it.',
					'%s items were skipped because you may not edit them.',
					$skipped,
					'baukasten-content-visibility'
				),
				number_format_i18n( $skipped )
			);
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $skipped > 0 ? 'warning' : 'success' ),
			esc_html( $message )
		);
	}
}
