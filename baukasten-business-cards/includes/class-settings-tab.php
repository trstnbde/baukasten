<?php
/**
 * The Baukasten tab.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the overview tab on the core plugin's settings screen.
 *
 * There is nothing to configure here that is not better placed elsewhere: the
 * base belongs next to the other bases on the Permalinks screen, and every
 * other setting belongs to one card. So the tab is an overview — what the base
 * is, how many cards there are, and what is and is not available on this site.
 */
final class Settings_Tab {

	/**
	 * Tab slug.
	 */
	const TAB = 'business-cards';

	/**
	 * Capability the tab requires.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Registers the hooks the tab needs.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter(
			'plugin_action_links_' . plugin_basename( PLUGIN_FILE ),
			array( __CLASS__, 'plugin_action_links' )
		);

		if ( ! class_exists( '\Baukasten\Addons' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_missing_core_notice' ) );

			return;
		}

		add_action( 'baukasten/register_addons', array( __CLASS__, 'register_addon' ) );
	}

	/**
	 * Registers the tab with the core plugin.
	 *
	 * @return void
	 */
	public static function register_addon(): void {
		\Baukasten\Addons::register(
			array(
				'id'          => self::TAB,
				'title'       => __( 'Business Cards', 'baukasten-business-cards' ),
				'plugin_file' => PLUGIN_FILE,
				'capability'  => self::CAPABILITY,
				'position'    => 50,
				'render'      => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * Adds a link to the card list next to Deactivate.
	 *
	 * @param mixed $links Existing action links.
	 * @return string[] Action links.
	 */
	public static function plugin_action_links( $links ): array {
		$links = array_map( 'strval', (array) $links );

		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'edit.php?post_type=' . Post_Type::POST_TYPE ) ),
				esc_html__( 'Cards', 'baukasten-business-cards' )
			)
		);

		return $links;
	}

	/**
	 * Warns that the core plugin is missing.
	 *
	 * Cards keep working without it — only the overview has nowhere to go.
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
				'Business Cards is serving cards, but its overview needs the Baukasten - Privacy Toolkit plugin. Cards can still be edited under Business Cards.',
				'baukasten-business-cards'
			)
		);
	}

	/**
	 * Prints the tab.
	 *
	 * @return void
	 */
	public static function render(): void {
		$base      = Settings::base();
		$pretty    = Settings::pretty_permalinks();
		$counts    = wp_count_posts( Post_Type::POST_TYPE );
		$published = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$drafts    = isset( $counts->draft ) ? (int) $counts->draft : 0;
		$has_cf7   = class_exists( '\WPCF7_ContactForm' );

		require PLUGIN_DIR . 'admin/views/settings-tab.php';
	}
}
