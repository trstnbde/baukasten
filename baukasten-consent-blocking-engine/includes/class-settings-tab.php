<?php
/**
 * The plugin's tab on the Baukasten settings screen.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves the Consent tab.
 */
final class Settings_Tab {

	/**
	 * Tab id.
	 */
	const TAB = 'consent';

	/**
	 * `admin_post` action that saves the form.
	 */
	const ACTION = 'baukasten_consent_save';

	/**
	 * `admin_post` action that exports the log.
	 */
	const ACTION_EXPORT = 'baukasten_consent_export';

	/**
	 * `admin_post` action that clears the recorded handles.
	 */
	const ACTION_RESCAN = 'baukasten_consent_rescan';

	/**
	 * `admin_post` action that runs the privacy hardening.
	 */
	const ACTION_HARDEN = 'baukasten_consent_harden';

	/**
	 * Transient holding the last hardening report, for the current user.
	 */
	const REPORT_TRANSIENT = 'baukasten_consent_hardening_report_';

	/**
	 * Nonce action for the forms.
	 */
	const NONCE = 'baukasten_consent_settings';

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
		add_action( 'admin_post_' . self::ACTION_EXPORT, array( __CLASS__, 'export' ) );
		add_action( 'admin_post_' . self::ACTION_RESCAN, array( __CLASS__, 'rescan' ) );
		add_action( 'admin_post_' . self::ACTION_HARDEN, array( __CLASS__, 'harden' ) );
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
				'title'       => __( 'Consent', 'baukasten-consent-blocking-engine' ),
				'plugin_file' => PLUGIN_FILE,
				'capability'  => self::CAPABILITY,
				'position'    => 30,
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
				esc_html__( 'Settings', 'baukasten-consent-blocking-engine' )
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
				'The Consent Blocking Engine is blocking with its default settings, but its settings screen needs the Baukasten - Privacy Toolkit plugin. Install it to categorise the assets this site loads.',
				'baukasten-consent-blocking-engine'
			)
		);
	}

	/**
	 * Enqueues the tab stylesheet.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( 'settings_page_baukasten' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'baukasten-consent-admin',
			PLUGIN_URL . 'assets/css/admin.css',
			array( 'common' ),
			VERSION
		);
	}

	/**
	 * Renders the tab.
	 *
	 * @return void
	 */
	public static function render(): void {
		$settings   = Settings::all();
		$categories = Categories::all();
		$map        = Categories::map();
		$handles    = Inventory::all();
		$log_count  = Consent_Log::count();
		$report     = get_transient( self::REPORT_TRANSIENT . get_current_user_id() );
		$report     = is_array( $report ) ? $report : array();

		if ( array() !== $report ) {
			delete_transient( self::REPORT_TRANSIENT . get_current_user_id() );
		}

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
			? map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_textarea_field' )
			: array();

		update_option( Settings::OPTION, Settings::sanitize( (array) $raw ), false );

		$map = isset( $_POST['categories'] ) && is_array( $_POST['categories'] )
			? map_deep( wp_unslash( $_POST['categories'] ), 'sanitize_key' )
			: array();

		Categories::save_map( (array) $map );

		self::redirect_back( __( 'Settings saved.', 'baukasten-consent-blocking-engine' ) );
	}

	/**
	 * Forgets the recorded handles so the table can be rebuilt.
	 *
	 * @return void
	 */
	public static function rescan(): void {
		self::check_capability();

		check_admin_referer( self::NONCE );

		Inventory::reset();

		self::redirect_back(
			__( 'The list was cleared. Open a few pages on the front end to fill it again.', 'baukasten-consent-blocking-engine' )
		);
	}

	/**
	 * Applies the privacy hardening defaults.
	 *
	 * The report is parked in a transient rather than squeezed into the
	 * redirect: it is a table, not a sentence, and a query string is the wrong
	 * place for one.
	 *
	 * @return void
	 */
	public static function harden(): void {
		self::check_capability();

		check_admin_referer( self::NONCE );

		$report = Hardening::run_privacy_hardening_bulk_action();

		set_transient( self::REPORT_TRANSIENT . get_current_user_id(), $report, MINUTE_IN_SECONDS );

		$changed = count(
			array_filter(
				$report,
				static function ( array $row ): bool {
					return (bool) $row['changed'];
				}
			)
		);

		self::redirect_back(
			0 === $changed
				? __( 'Nothing to do — the site was already hardened.', 'baukasten-consent-blocking-engine' )
				: sprintf(
					/* translators: 1: number of changed steps, 2: total number of steps. */
					__( 'Hardening applied: %1$d of %2$d steps changed something.', 'baukasten-consent-blocking-engine' ),
					$changed,
					count( $report )
				)
		);
	}

	/**
	 * Sends the consent log as a download.
	 *
	 * @return void
	 */
	public static function export(): void {
		self::check_capability();

		check_admin_referer( self::NONCE );

		$format = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( (string) $_POST['format'] ) ) : 'csv';
		$format = 'json' === $format ? 'json' : 'csv';

		$filename = 'baukasten-consent-log-' . gmdate( 'Y-m-d' ) . '.' . $format;

		nocache_headers();
		header( 'Content-Type: ' . ( 'json' === $format ? 'application/json' : 'text/csv' ) . '; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		if ( 'json' === $format ) {
			Consent_Log::export_json();
		} else {
			Consent_Log::export_csv();
		}

		exit;
	}

	/**
	 * Returns to the tab with a success notice, and ends the request.
	 *
	 * @param string $message Notice text.
	 * @return void
	 */
	private static function redirect_back( string $message ): void {
		if ( class_exists( '\\Baukasten\\Admin' ) ) {
			\Baukasten\Admin::redirect_to_tab( self::TAB, 'success', $message );
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
				esc_html__( 'You are not allowed to change these settings.', 'baukasten-consent-blocking-engine' ),
				403
			);
		}
	}
}
