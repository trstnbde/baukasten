<?php
/**
 * The plugin's tab on the Baukasten settings screen.
 *
 * @package Baukasten\ContentVisibility
 */

namespace Baukasten\ContentVisibility;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves the Content Visibility tab.
 *
 * The tab lives inside Baukasten's settings page, so the form posts to
 * `admin-post.php` with its own nonce and capability check and comes back
 * through `Baukasten\Admin::redirect_to_tab()`.
 */
final class Settings_Tab {

	/**
	 * Tab id, and the last path segment of the tab URL.
	 */
	const TAB = 'content-visibility';

	/**
	 * `admin_post` action that saves the form.
	 */
	const ACTION = 'baukasten_content_visibility_save';

	/**
	 * `admin_post` action that runs the migration again.
	 */
	const ACTION_MIGRATE = 'baukasten_content_visibility_migrate';

	/**
	 * Nonce action for both forms.
	 */
	const NONCE = 'baukasten_content_visibility_settings';

	/**
	 * Capability required to change these settings.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Registers the tab and its form handlers.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'save' ) );
		add_action( 'admin_post_' . self::ACTION_MIGRATE, array( __CLASS__, 'migrate' ) );
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
				'title'       => __( 'Content Visibility', 'baukasten-content-visibility' ),
				'plugin_file' => PLUGIN_FILE,
				'capability'  => self::CAPABILITY,
				'position'    => 10,
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
				esc_html__( 'Settings', 'baukasten-content-visibility' )
			)
		);

		return $links;
	}

	/**
	 * Points out that the settings live in the core plugin, when it is missing.
	 *
	 * WordPress refuses to activate this plugin without Baukasten from 6.5 on,
	 * so this only shows up on a site where the core was removed from disk.
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
				'Content Visibility is running, but its settings screen needs the Baukasten - Privacy Toolkit plugin. Install it to configure which post types the visibility switch applies to.',
				'baukasten-content-visibility'
			)
		);
	}

	/**
	 * Renders the tab.
	 *
	 * @return void
	 */
	public static function render(): void {
		$settings   = Settings::all();
		$post_types = Visibility::detect_post_types();
		$migration  = get_option( Visibility::OPTION_MIGRATION, array() );
		$migration  = is_array( $migration ) ? $migration : array();

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

		$post_types = array();

		if ( isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ) {
			$post_types = array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) );
		}

		$response = isset( $_POST['blocked_response'] )
			? sanitize_key( wp_unslash( (string) $_POST['blocked_response'] ) )
			: Settings::RESPONSE_LOGIN;

		update_option(
			Settings::OPTION,
			Settings::sanitize(
				array(
					'post_types'       => $post_types,
					'blocked_response' => $response,
				)
			),
			false
		);

		self::redirect_back( __( 'Settings saved.', 'baukasten-content-visibility' ) );
	}

	/**
	 * Marks every post without a stored value public again.
	 *
	 * Useful after adding a post type that already has content: without this,
	 * that content would count as private from one moment to the next.
	 *
	 * @return void
	 */
	public static function migrate(): void {
		self::check_capability();

		check_admin_referer( self::NONCE );

		$migrated = Visibility::migrate_existing_to_public();

		update_option(
			Visibility::OPTION_MIGRATION,
			array(
				'time'  => time(),
				'count' => $migrated,
			),
			false
		);

		self::redirect_back(
			sprintf(
				/* translators: %s: number of entries. */
				_n(
					'%s entry was marked public.',
					'%s entries were marked public.',
					$migrated,
					'baukasten-content-visibility'
				),
				number_format_i18n( $migrated )
			)
		);
	}

	/**
	 * Returns to the tab with a success notice, and ends the request.
	 *
	 * Falls back to the dashboard if the core plugin disappeared between
	 * rendering the form and submitting it.
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
				esc_html__( 'You are not allowed to change these settings.', 'baukasten-content-visibility' ),
				403
			);
		}
	}
}
