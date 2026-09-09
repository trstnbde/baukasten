<?php
/**
 * The plugin's tab on the Baukasten settings screen.
 *
 * @package Baukasten\MultiDomain
 */

namespace Baukasten\MultiDomain;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the whole map on one screen.
 *
 * Domains are assigned in the page list, one page at a time, which is the
 * right place to make the decision and the wrong place to check the result:
 * nothing there answers "which domains does this install serve, and does the
 * lookup table still agree with the pages?". This tab does, and offers the way
 * back when it does not.
 */
final class Settings_Tab {

	/**
	 * Tab id.
	 */
	const TAB = 'multi-domain';

	/**
	 * `admin_post` action that rebuilds the map.
	 */
	const ACTION_REBUILD = 'baukasten_multi_domain_rebuild';

	/**
	 * Nonce action.
	 */
	const NONCE = 'baukasten_multi_domain_settings';

	/**
	 * Capability required.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Registers the tab and its handler.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION_REBUILD, array( __CLASS__, 'rebuild' ) );
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
				'title'       => __( 'Multi-Domain', 'baukasten-multi-domain' ),
				'plugin_file' => PLUGIN_FILE,
				'capability'  => self::CAPABILITY,
				'position'    => 40,
				'render'      => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * Adds a shortcut to the plugin list row.
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
				esc_html__( 'Settings', 'baukasten-multi-domain' )
			)
		);

		return $links;
	}

	/**
	 * Points out that the overview lives in the core plugin, when it is gone.
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
				'Multi-Domain is routing, but its overview needs the Baukasten - Privacy Toolkit plugin. Domains can still be assigned in the page list.',
				'baukasten-multi-domain'
			)
		);
	}

	/**
	 * Renders the tab.
	 *
	 * @return void
	 */
	public static function render(): void {
		$map = Domain_Map::all();

		require PLUGIN_DIR . 'admin/views/settings-tab.php';
	}

	/**
	 * Rebuilds the map from the post meta.
	 *
	 * @return void
	 */
	public static function rebuild(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'baukasten-multi-domain' ), 403 );
		}

		check_admin_referer( self::NONCE );

		$count = Domain_Map::rebuild();

		$message = sprintf(
			/* translators: %s: number of pages. */
			_n(
				'The map was rebuilt from %s page.',
				'The map was rebuilt from %s pages.',
				$count,
				'baukasten-multi-domain'
			),
			number_format_i18n( $count )
		);

		if ( class_exists( '\\Baukasten\\Admin' ) ) {
			\Baukasten\Admin::redirect_to_tab( self::TAB, 'success', $message );
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=page' ) );
		exit;
	}
}
