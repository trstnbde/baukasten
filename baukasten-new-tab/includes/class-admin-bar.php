<?php
/**
 * The link to the new tab page in the admin bar.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * A node in the admin bar that opens the new tab page.
 *
 * Every signed-in user who has the bar gets it, whatever their role: the page
 * is meant to be opened from anywhere, by anybody who is signed in. Whether
 * the bar shows at all stays up to WordPress and to the user's own "Show
 * Toolbar" setting, which this does not touch.
 *
 * On the new tab page itself there is no bar, as before.
 */
final class Admin_Bar {

	/**
	 * Node ID.
	 */
	const NODE = 'baukasten-new-tab';

	/**
	 * Handle of the small stylesheet.
	 */
	const HANDLE = 'baukasten-new-tab-admin-bar';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_node' ), 35 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Adds the node.
	 *
	 * @param mixed $bar The admin bar.
	 * @return void
	 */
	public static function add_node( $bar ): void {
		if ( ! $bar instanceof \WP_Admin_Bar || ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
			return;
		}

		$page_id = Settings::page_id();

		if ( 0 >= $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return;
		}

		$link = get_permalink( $page_id );

		if ( ! is_string( $link ) || '' === $link ) {
			return;
		}

		$bar->add_node(
			array(
				'id'    => self::NODE,
				'title' => '<span class="ab-icon dashicons dashicons-admin-site-alt" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'New Tab', 'baukasten-new-tab' ) . '</span>',
				'href'  => $link,
				'meta'  => array(
					'title' => __( 'Open the new tab page', 'baukasten-new-tab' ),
				),
			)
		);
	}

	/**
	 * Loads the stylesheet that keeps the node in the phone-sized bar.
	 *
	 * WordPress hides every node of the bar below 783 pixels except a few of
	 * its own, so this shows the icon again. Only when the bar is there.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		wp_enqueue_style( self::HANDLE, plugin_url( 'assets/css/admin-bar.css' ), array( 'admin-bar' ), Renderer::asset_version( 'assets/css/admin-bar.css' ) );
	}
}
