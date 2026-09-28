<?php
/**
 * The plugin's tab on the Baukasten settings screen (F10).
 *
 * @package Baukasten\FormPrivacy
 */

namespace Baukasten\FormPrivacy;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves the Form Privacy tab, and runs the cleanup.
 */
final class Settings_Tab {

	/**
	 * Tab id.
	 */
	const TAB = 'form-privacy';

	/**
	 * Tab position. 60 belongs to Two-Factor Approval.
	 */
	const POSITION = 70;

	/**
	 * `admin_post` action that saves the settings.
	 */
	const ACTION_SAVE = 'baukasten_form_privacy_save';

	/**
	 * `admin_post` action that runs the cleanup.
	 */
	const ACTION_CLEAN = 'baukasten_form_privacy_clean';

	/**
	 * Nonce action shared by the tab's forms.
	 */
	const NONCE = 'baukasten_form_privacy';

	/**
	 * Transient holding the last cleanup report, per user.
	 */
	const REPORT_TRANSIENT = 'baukasten_form_privacy_report_';

	/**
	 * Capability the tab requires. Flamingo itself requires `edit_users`.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Registers the tab and its form handlers.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'save' ) );
		add_action( 'admin_post_' . self::ACTION_CLEAN, array( __CLASS__, 'clean' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PLUGIN_FILE ), array( __CLASS__, 'plugin_action_links' ) );

		if ( ! class_exists( '\\Baukasten\\Addons' ) ) {
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
				'title'       => __( 'Form Privacy', 'baukasten-form-privacy' ),
				'plugin_file' => PLUGIN_FILE,
				'capability'  => self::CAPABILITY,
				'position'    => self::POSITION,
				'render'      => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * Adds a settings shortcut to the plugin list row.
	 *
	 * @param mixed $links Existing action links.
	 * @return array<int|string, string> Filtered action links.
	 */
	public static function plugin_action_links( $links ): array {
		$links = (array) $links;

		if ( ! class_exists( '\\Baukasten\\Admin' ) || ! current_user_can( self::CAPABILITY ) ) {
			return $links;
		}

		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( \Baukasten\Admin::page_url( self::TAB ) ),
				esc_html__( 'Settings', 'baukasten-form-privacy' )
			)
		);

		return $links;
	}

	/**
	 * Renders the tab.
	 *
	 * @return void
	 */
	public static function render(): void {
		$settings     = Settings::all();
		$has_flamingo = Settings::has_flamingo();
		$stats        = $has_flamingo ? Retention::stats() : array(
			'count'  => 0,
			'oldest' => '',
		);
		$contacts     = $has_flamingo ? Address_Book::count() : 0;
		$next_purge   = (int) wp_next_scheduled( Retention::CRON_HOOK );
		$report       = get_transient( self::REPORT_TRANSIENT . get_current_user_id() );
		$report       = is_array( $report ) ? $report : array();

		if ( array() !== $report ) {
			delete_transient( self::REPORT_TRANSIENT . get_current_user_id() );
		}

		require PLUGIN_DIR . 'admin/views/settings-tab.php';
	}

	/**
	 * Saves the settings.
	 *
	 * @return void
	 */
	public static function save(): void {
		self::check_capability();

		check_admin_referer( self::NONCE );

		$raw = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
			? map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_text_field' )
			: array();

		// Without Flamingo the storage settings are not on the form; keep them.
		if ( ! Settings::has_flamingo() ) {
			$raw = array_merge( Settings::all(), (array) $raw );
		}

		update_option( Settings::OPTION, Settings::sanitize( (array) $raw ), true );

		self::go_back( 'success', __( 'Settings saved.', 'baukasten-form-privacy' ) );
	}

	/**
	 * Runs the cleanup.
	 *
	 * @return void
	 */
	public static function clean(): void {
		self::check_capability();

		check_admin_referer( self::NONCE );

		$report = Cleanup::run();

		set_transient( self::REPORT_TRANSIENT . get_current_user_id(), $report, MINUTE_IN_SECONDS );

		$changed = count( array_filter( array_column( $report, 'changed' ) ) );

		self::go_back(
			'success',
			0 === $changed
				? __( 'Nothing to do — every step reported no change.', 'baukasten-form-privacy' )
				: sprintf(
					/* translators: 1: steps that changed something, 2: all steps. */
					__( 'Cleanup done: %1$d of %2$d steps changed something.', 'baukasten-form-privacy' ),
					$changed,
					count( $report )
				)
		);
	}

	/**
	 * Stops the request unless the user may change these settings.
	 *
	 * @return void
	 */
	private static function check_capability(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'baukasten-form-privacy' ), 403 );
		}
	}

	/**
	 * Returns to the tab with a notice, and ends the request.
	 *
	 * @param string $type    `success` or `error`.
	 * @param string $message Notice text.
	 * @return void
	 */
	private static function go_back( string $type, string $message ): void {
		if ( class_exists( '\\Baukasten\\Admin' ) ) {
			\Baukasten\Admin::redirect_to_tab( self::TAB, $type, $message );
		}

		wp_safe_redirect( admin_url() );

		exit;
	}
}
