<?php
/**
 * The plugin's tab on the Baukasten settings screen.
 *
 * @package Baukasten\TwoFactor
 */

namespace Baukasten\TwoFactor;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves the Two-Factor tab.
 */
final class Settings_Tab {

	/**
	 * Tab id.
	 */
	const TAB = '2fa';

	/**
	 * `admin_post` action that saves the form.
	 */
	const ACTION = 'baukasten_2fa_save';

	/**
	 * `admin_post` action that adds this provider to Two Factor's allowlist.
	 */
	const ACTION_ALLOW = 'baukasten_2fa_allow_provider';

	/**
	 * Nonce action for the forms.
	 */
	const NONCE = 'baukasten_2fa_settings';

	/**
	 * Capability required to change these settings.
	 */
	const CAPABILITY = 'manage_baukasten';

	/**
	 * Registers the tab and its form handlers.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'save' ) );
		add_action( 'admin_post_' . self::ACTION_ALLOW, array( __CLASS__, 'allow_provider' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( PLUGIN_FILE ),
			array( __CLASS__, 'plugin_action_links' )
		);

		if ( ! class_exists( '\\Baukasten\\Addons' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_missing_core_notice' ) );

			return;
		}

		add_action( 'baukasten/register_addons', array( __CLASS__, 'register_addon' ) );
	}

	/**
	 * Registers the tab with the Baukasten core.
	 *
	 * @return void
	 */
	public static function register_addon(): void {
		\Baukasten\Addons::register(
			array(
				'id'          => self::TAB,
				'title'       => __( 'Two-Factor', 'baukasten-2fa' ),
				'plugin_file' => PLUGIN_FILE,
				'capability'  => self::CAPABILITY,
				'position'    => 60,
				'render'      => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * Adds a settings shortcut to the plugin list row.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[] Filtered action links.
	 */
	public static function plugin_action_links( array $links ): array {
		if ( ! class_exists( '\\Baukasten\\Admin' ) || ! current_user_can( self::CAPABILITY ) ) {
			return $links;
		}

		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( \Baukasten\Admin::page_url( self::TAB ) ),
				esc_html__( 'Settings', 'baukasten-2fa' )
			)
		);

		return $links;
	}

	/**
	 * Points out that the settings live in the core plugin, when it is missing.
	 *
	 * @return void
	 */
	public static function render_missing_core_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html__(
				'Confirmation in another session is working with its default settings, but its settings screen needs the Baukasten - Privacy Toolkit plugin.',
				'baukasten-2fa'
			)
		);
	}

	/**
	 * Returns the URL that adds this provider to Two Factor's allowlist.
	 *
	 * @return string Nonced URL.
	 */
	public static function allow_url(): string {
		return wp_nonce_url(
			add_query_arg( 'action', self::ACTION_ALLOW, admin_url( 'admin-post.php' ) ),
			self::NONCE
		);
	}

	/**
	 * Renders the tab.
	 *
	 * @return void
	 */
	public static function render(): void {
		$settings = Settings::all();
		$blocked  = Notices::is_blocked_by_allowlist();

		require PLUGIN_DIR . 'admin/views/settings-tab.php';
	}

	/**
	 * Saves the form.
	 *
	 * @return void
	 */
	public static function save(): void {
		self::check_capability();

		check_admin_referer( self::NONCE );

		$raw = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
			? map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_text_field' )
			: array();

		update_option( Settings::OPTION, Settings::sanitize( (array) $raw ), false );

		self::redirect_back( 'success', __( 'Settings saved.', 'baukasten-2fa' ) );
	}

	/**
	 * Adds this provider to Two Factor's site-wide allowlist.
	 *
	 * Only ever on an explicit click: the option belongs to another plugin and
	 * records a decision an administrator made deliberately.
	 *
	 * @return void
	 */
	public static function allow_provider(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'baukasten-2fa' ), 403 );
		}

		check_admin_referer( self::NONCE );

		$allowed = get_option( 'two_factor_enabled_providers', null );

		if ( ! is_array( $allowed ) ) {
			// Never saved, so nothing is being filtered and there is nothing
			// to fix.
			self::redirect_back( 'success', __( 'All two-factor methods were already allowed.', 'baukasten-2fa' ) );
		}

		if ( ! in_array( PROVIDER_KEY, $allowed, true ) ) {
			$allowed[] = PROVIDER_KEY;
			update_option( 'two_factor_enabled_providers', array_values( array_unique( $allowed ) ) );
		}

		self::redirect_back(
			'success',
			__( 'This method is now allowed site-wide. Users can select it on their profile.', 'baukasten-2fa' )
		);
	}

	/**
	 * Returns to the tab with a notice, and ends the request.
	 *
	 * @param string $type    Notice type.
	 * @param string $message Notice text.
	 * @return void
	 */
	private static function redirect_back( string $type, string $message ): void {
		if ( class_exists( '\\Baukasten\\Admin' ) ) {
			\Baukasten\Admin::redirect_to_tab( self::TAB, $type, $message );
		}

		wp_safe_redirect( admin_url() );
		exit;
	}

	/**
	 * Ends the request unless the user may change these settings.
	 *
	 * @return void
	 */
	private static function check_capability(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You are not allowed to change these settings.', 'baukasten-2fa' ),
				403
			);
		}
	}
}
