<?php
/**
 * Admin notices about the plugin's dependencies.
 *
 * @package Baukasten\TwoFactor
 */

namespace Baukasten\TwoFactor;

defined( 'ABSPATH' ) || exit;

/**
 * Explains the two ways this plugin can end up doing nothing.
 *
 * Both of them are silent otherwise, which is the worst way for a plugin to
 * fail: it activates, it appears in the list, and no second factor ever
 * appears. So each gets a notice that says what happened and what to do.
 */
final class Notices {

	/**
	 * User meta recording that the allowlist notice was dismissed.
	 */
	const DISMISSED_META = 'baukasten_2fa_notice_dismissed';

	/**
	 * `admin_post` action that dismisses the notice.
	 */
	const ACTION_DISMISS = 'baukasten_2fa_dismiss_notice';

	/**
	 * Nonce action for the dismissal.
	 */
	const NONCE_DISMISS = 'baukasten_2fa_dismiss';

	/**
	 * Registers the notices.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION_DISMISS, array( __CLASS__, 'dismiss' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
	}

	/**
	 * Prints whichever notice applies.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! class_exists( 'Two_Factor_Core' ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__(
					'Baukasten Two-Factor Approval needs the Two Factor plugin. Install and activate it to offer confirmation from a second session.',
					'baukasten-2fa'
				)
			);

			return;
		}

		if ( ! self::is_blocked_by_allowlist() ) {
			return;
		}

		if ( get_user_meta( get_current_user_id(), self::DISMISSED_META, true ) ) {
			return;
		}

		$dismiss = wp_nonce_url(
			add_query_arg( 'action', self::ACTION_DISMISS, admin_url( 'admin-post.php' ) ),
			self::NONCE_DISMISS
		);

		printf(
			'<div class="notice notice-warning"><p>%1$s</p><p><a class="button button-primary" href="%2$s">%3$s</a> <a class="button" href="%4$s">%5$s</a></p></div>',
			esc_html__(
				'Confirmation in another session is switched off site-wide: the Two Factor settings list which methods this site allows, and this one is not on it. Nobody can select it until it is.',
				'baukasten-2fa'
			),
			esc_url( Settings_Tab::allow_url() ),
			esc_html__( 'Allow this method site-wide', 'baukasten-2fa' ),
			esc_url( $dismiss ),
			esc_html__( 'Dismiss', 'baukasten-2fa' )
		);
	}

	/**
	 * Whether Two Factor's site-wide allowlist is filtering this plugin out.
	 *
	 * The option only exists once an administrator has saved that screen. An
	 * absent option means every method is allowed, which is the default.
	 *
	 * @return bool True when the allowlist exists and omits this provider.
	 */
	public static function is_blocked_by_allowlist(): bool {
		$allowed = get_option( 'two_factor_enabled_providers', null );

		if ( ! is_array( $allowed ) ) {
			return false;
		}

		return ! in_array( PROVIDER_KEY, $allowed, true );
	}

	/**
	 * Remembers that this user does not want to see the notice again.
	 *
	 * @return void
	 */
	public static function dismiss(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'baukasten-2fa' ), 403 );
		}

		check_admin_referer( self::NONCE_DISMISS );

		update_user_meta( get_current_user_id(), self::DISMISSED_META, 1 );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
