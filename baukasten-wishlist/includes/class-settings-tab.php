<?php
/**
 * The Baukasten tab.
 *
 * @package Baukasten\Wishlist
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * Overview of the lists, the two site-wide settings, and resetting a list.
 */
final class Settings_Tab {

	/**
	 * Tab slug.
	 */
	const TAB = 'wishlist';

	/**
	 * Capability the tab requires.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * `admin-post` action that saves the settings.
	 */
	const ACTION_SAVE = 'baukasten_wishlist_save_settings';

	/**
	 * `admin-post` action that resets every purchase of a list.
	 */
	const ACTION_RESET_LIST = 'baukasten_wishlist_reset_list';

	/**
	 * Nonce action for the tab's forms.
	 */
	const NONCE = 'baukasten_wishlist_tab';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'save' ) );
		add_action( 'admin_post_' . self::ACTION_RESET_LIST, array( __CLASS__, 'reset_list' ) );

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
				'title'       => __( 'Wishlist', 'baukasten-wishlist' ),
				'plugin_file' => PLUGIN_FILE,
				'capability'  => self::CAPABILITY,
				'position'    => 90,
				'render'      => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * Adds a link to the lists next to Deactivate.
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
				esc_url( admin_url( 'edit.php?post_type=' . Post_Types::LIST_TYPE ) ),
				esc_html__( 'Lists', 'baukasten-wishlist' )
			)
		);

		return $links;
	}

	/**
	 * Warns that the core plugin is missing.
	 *
	 * @return void
	 */
	public static function render_missing_core_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html__( 'Wishlist works, but its settings need the Baukasten - Privacy Toolkit plugin. Lists can still be edited under Wishlists.', 'baukasten-wishlist' )
		);
	}

	/**
	 * Saves the settings.
	 *
	 * @return void
	 */
	public static function save(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'baukasten-wishlist' ), 403 );
		}

		check_admin_referer( self::NONCE );

		$currency = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['currency'] ) ) : '';
		$limit    = isset( $_POST['rate_limit'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['rate_limit'] ) ) : '';

		update_option( Settings::OPTION_CURRENCY, Settings::sanitize_currency( $currency ), false );
		update_option( Settings::OPTION_RATE_LIMIT, Settings::sanitize_rate_limit( $limit ), false );

		self::go_back( 'success', __( 'Settings saved.', 'baukasten-wishlist' ) );
	}

	/**
	 * Resets every purchase on one list.
	 *
	 * @return void
	 */
	public static function reset_list(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'baukasten-wishlist' ), 403 );
		}

		check_admin_referer( self::NONCE );

		$list_id = isset( $_POST['list'] ) ? absint( wp_unslash( $_POST['list'] ) ) : 0;
		$list    = get_post( $list_id );

		if ( ! $list instanceof \WP_Post || Post_Types::LIST_TYPE !== $list->post_type ) {
			self::go_back( 'error', __( 'This list does not exist.', 'baukasten-wishlist' ) );
		}

		foreach ( Items::of_list( $list_id, true ) as $item ) {
			Claims::reset( $item->ID );
		}

		self::go_back(
			'success',
			sprintf(
				/* translators: %s: title of the list. */
				__( 'Every wish on "%s" is marked as not bought again.', 'baukasten-wishlist' ),
				get_the_title( $list )
			)
		);
	}

	/**
	 * Returns to the tab with a notice, and ends the request.
	 *
	 * @param string $type    Either `success` or `error`.
	 * @param string $message Notice text.
	 * @return void
	 */
	private static function go_back( string $type, string $message ): void {
		if ( class_exists( '\Baukasten\Admin' ) ) {
			\Baukasten\Admin::redirect_to_tab( self::TAB, $type, $message );
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Post_Types::LIST_TYPE ) );

		exit;
	}

	/**
	 * Prints the tab.
	 *
	 * @return void
	 */
	public static function render(): void {
		$baukasten_wl_lists    = array();
		$baukasten_wl_currency = Settings::currency();
		$baukasten_wl_limit    = Settings::rate_limit();

		foreach ( Items::lists() as $id => $title ) {
			$items  = Items::of_list( $id, true );
			$total  = 0;
			$bought = 0;

			foreach ( $items as $item ) {
				$data    = Items::data( $item );
				$total  += (int) $data['quantity'];
				$bought += (int) $data['bought'];
			}

			$baukasten_wl_lists[] = array(
				'id'     => $id,
				'title'  => $title,
				'status' => (string) get_post_status( $id ),
				'fields' => Fields::load_list( $id ),
				'wishes' => count( $items ),
				'total'  => $total,
				'bought' => $bought,
			);
		}

		require PLUGIN_DIR . 'admin/views/settings-tab.php';
	}
}
