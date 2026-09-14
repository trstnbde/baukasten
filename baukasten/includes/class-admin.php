<?php
/**
 * Admin screen.
 *
 * @package Baukasten
 */

namespace Baukasten;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the settings screen and renders the tab frame.
 *
 * The screen itself owns nothing but the overview tab. Every other tab is
 * rendered by the addon that registered it; this class only decides which tab
 * is current, checks the capability and hands over.
 */
final class Admin {

	/**
	 * Menu and page slug.
	 */
	const PAGE_SLUG = 'baukasten';

	/**
	 * Id of the tab shown when none is requested.
	 */
	const DEFAULT_TAB = 'overview';

	/**
	 * Query argument carrying the result of the last action.
	 */
	const NOTICE_ARG = 'baukasten_notice';

	/**
	 * Hook suffix of the settings page.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( PLUGIN_FILE ),
			array( $this, 'plugin_action_links' )
		);
	}

	/**
	 * Adds the settings page.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		$hook = add_options_page(
			__( 'Baukasten - Privacy Toolkit', 'baukasten' ),
			__( 'Baukasten', 'baukasten' ),
			CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		if ( is_string( $hook ) ) {
			$this->hook = $hook;

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}
	}

	/**
	 * Adds a settings shortcut to the plugin list row.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[] Filtered action links.
	 */
	public function plugin_action_links( array $links ): array {
		if ( ! current_user_can( CAPABILITY ) ) {
			return $links;
		}

		$link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( self::page_url() ),
			esc_html__( 'Settings', 'baukasten' )
		);

		array_unshift( $links, $link );

		return $links;
	}

	/**
	 * Enqueues the admin stylesheet on the settings page only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook ) {
			return;
		}

		wp_enqueue_style(
			'baukasten-admin',
			PLUGIN_URL . 'admin/css/admin.css',
			array( 'common' ),
			VERSION
		);
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Baukasten.', 'baukasten' ), 403 );
		}

		$tabs    = self::tabs();
		$current = self::current_tab();
		$notice  = self::current_notice();

		require PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	/**
	 * Renders the overview tab.
	 *
	 * The catalog is the spine of the list, because it is the only thing that
	 * knows about an addon this site has not installed. Anything registered on
	 * top of it is appended: a third-party addon is as entitled to a row as
	 * ours, and the catalog will never hear about one.
	 *
	 * @return void
	 */
	public static function render_overview(): void {
		$addons = Catalog::all();

		foreach ( Addons::all() as $baukasten_id => $baukasten_addon ) {
			if ( isset( $addons[ $baukasten_id ] ) ) {
				continue;
			}

			$baukasten_data = Addons::plugin_data( $baukasten_addon );

			$addons[ $baukasten_id ] = array(
				'id'          => $baukasten_id,
				'slug'        => '',
				'file'        => '',
				'title'       => (string) $baukasten_addon['title'],
				'description' => $baukasten_data['description'],
				'position'    => (int) $baukasten_addon['position'],
				'status'      => Catalog::ACTIVE,
				'version'     => $baukasten_data['version'],
				'has_tab'     => true,
			);
		}

		require PLUGIN_DIR . 'admin/views/overview.php';
	}

	/**
	 * Returns every tab on the settings screen, the overview first.
	 *
	 * Addon tabs whose capability the current user does not have are dropped.
	 * The capability guarding the page itself has already been checked, so an
	 * addon capability can only narrow access, never widen it.
	 *
	 * @return array<string, array{title: string, render: callable}> Tabs keyed by id.
	 */
	public static function tabs(): array {
		$tabs = array(
			self::DEFAULT_TAB => array(
				'title'  => __( 'Overview', 'baukasten' ),
				'render' => array( __CLASS__, 'render_overview' ),
			),
		);

		foreach ( Addons::all() as $id => $addon ) {
			if ( ! current_user_can( (string) $addon['capability'] ) ) {
				continue;
			}

			$tabs[ $id ] = array(
				'title'  => (string) $addon['title'],
				'render' => $addon['render'],
			);
		}

		return $tabs;
	}

	/**
	 * Returns the id of the tab to render.
	 *
	 * @return string Tab id, always one that exists.
	 */
	public static function current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : '';

		return isset( self::tabs()[ $requested ] ) ? $requested : self::DEFAULT_TAB;
	}

	/**
	 * Returns the URL of the settings page.
	 *
	 * @param string $tab Optional. Tab to link to.
	 * @return string Settings page URL.
	 */
	public static function page_url( string $tab = '' ): string {
		$url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );

		if ( '' !== $tab && self::DEFAULT_TAB !== $tab ) {
			$url = add_query_arg( 'tab', sanitize_key( $tab ), $url );
		}

		return $url;
	}

	/**
	 * Stores a notice for the current user, to be shown after a redirect.
	 *
	 * Addons use this from their own `admin_post_*` handlers so a saved form
	 * reports back on the tab it was submitted from.
	 *
	 * @param string $type    Either `success` or `error`.
	 * @param string $message Message text.
	 * @return void
	 */
	public static function add_notice( string $type, string $message ): void {
		set_transient(
			self::notice_key(),
			array( 'error' === $type ? 'error' : 'success', $message ),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Redirects back to a tab, optionally carrying a notice.
	 *
	 * Ends the request.
	 *
	 * @param string $tab     Tab to return to.
	 * @param string $type    Optional. Either `success` or `error`.
	 * @param string $message Optional. Message text.
	 * @return void
	 */
	public static function redirect_to_tab( string $tab, string $type = '', string $message = '' ): void {
		$url = self::page_url( $tab );

		if ( '' !== $message ) {
			self::add_notice( $type, $message );

			$url = add_query_arg( self::NOTICE_ARG, '1', $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Reads and clears the notice stored for the current user.
	 *
	 * @return array{0: string, 1: string}|null Notice tuple or null.
	 */
	private static function current_notice(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ self::NOTICE_ARG ] ) ) {
			return null;
		}

		$notice = get_transient( self::notice_key() );

		delete_transient( self::notice_key() );

		if ( ! is_array( $notice ) || 2 !== count( $notice ) ) {
			return null;
		}

		return array( (string) $notice[0], (string) $notice[1] );
	}

	/**
	 * Transient key for the current user's notice.
	 *
	 * @return string Transient key.
	 */
	private static function notice_key(): string {
		return 'baukasten_notice_' . get_current_user_id();
	}
}
