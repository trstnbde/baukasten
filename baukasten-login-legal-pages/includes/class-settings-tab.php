<?php
/**
 * The plugin's tab on the Baukasten settings screen.
 *
 * @package Baukasten\LoginLegalPages
 */

namespace Baukasten\LoginLegalPages;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves the Login & Legal tab.
 *
 * Before 1.0.0 these two page pickers were injected into
 * `options-privacy.php` through `admin_notices` and moved into position with
 * JavaScript, because that screen does not use the Settings API and offers no
 * hook. Rebuilding somebody else's admin screen is exactly what the plugin
 * guidelines ask you not to do, and Baukasten now has a tab to put them on.
 */
final class Settings_Tab {

	/**
	 * Tab id.
	 */
	const TAB = 'login-legal-pages';

	/**
	 * `admin_post` action that saves the form.
	 */
	const ACTION = 'baukasten_login_legal_pages_save';

	/**
	 * Nonce action for the form.
	 */
	const NONCE = 'baukasten_login_legal_pages_settings';

	/**
	 * Capability required to change these settings.
	 *
	 * The same one core requires for the privacy policy picker next door.
	 */
	const CAPABILITY = 'manage_privacy_options';

	/**
	 * Registers the tab and its form handler.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'save' ) );
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
				'title'       => __( 'Login & Legal', 'baukasten-login-legal-pages' ),
				'plugin_file' => PLUGIN_FILE,
				'capability'  => self::CAPABILITY,
				'position'    => 20,
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
				esc_html__( 'Settings', 'baukasten-login-legal-pages' )
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
				'Login Legal Pages is running, but its settings screen needs the Baukasten - Privacy Toolkit plugin. Install it to pick the terms and imprint pages.',
				'baukasten-login-legal-pages'
			)
		);
	}

	/**
	 * Renders the tab.
	 *
	 * @return void
	 */
	public static function render(): void {
		$unreachable  = self::unreachable_pages();
		$slug         = Login_URL::slug();
		$login_url    = Login_URL::url();
		$permalinks   = Login_URL::is_enabled();
		$slug_taken   = self::page_with_slug( $slug );
		$privacy_page = (int) get_option( 'wp_page_for_privacy_policy', 0 );

		require PLUGIN_DIR . 'admin/views/settings-tab.php';
	}

	/**
	 * Saves the form.
	 *
	 * @return void
	 */
	public static function save(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You are not allowed to change these settings.', 'baukasten-login-legal-pages' ),
				403
			);
		}

		check_admin_referer( self::NONCE );

		foreach ( array( Legal_Pages::OPTION_TERMS, Legal_Pages::OPTION_IMPRINT ) as $option ) {
			// A field that was not submitted is left alone. Treating it as an
			// empty selection would let a partial request silently clear a
			// setting the user never touched.
			if ( ! isset( $_POST[ $option ] ) ) {
				continue;
			}

			$value = absint( wp_unslash( $_POST[ $option ] ) );

			// Only a real page may be stored; anything else clears the field.
			if ( $value > 0 && 'page' !== get_post_type( $value ) ) {
				$value = 0;
			}

			update_option( $option, $value, false );
		}

		if ( isset( $_POST[ Login_URL::OPTION_SLUG ] ) ) {
			$slug = sanitize_title( wp_unslash( (string) $_POST[ Login_URL::OPTION_SLUG ] ) );

			update_option( Login_URL::OPTION_SLUG, '' === $slug ? Login_URL::DEFAULT_SLUG : $slug, true );
		}

		self::redirect_back( __( 'Settings saved.', 'baukasten-login-legal-pages' ) );
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
	 * Returns the titles of selected pages a logged-out visitor cannot open.
	 *
	 * A published page is not automatically a public one. The sibling Content
	 * Visibility addon, for instance, defaults new content to logged-in users
	 * only, which would bounce a visitor following a login footer link right
	 * back to the login form. The integration is optional and guarded: without
	 * that addon the check simply finds nothing.
	 *
	 * @return string[] Page titles.
	 */
	private static function unreachable_pages(): array {
		$pages = array(
			(int) get_option( 'wp_page_for_privacy_policy', 0 ),
			Legal_Pages::get_page_id( Legal_Pages::OPTION_TERMS ),
			Legal_Pages::get_page_id( Legal_Pages::OPTION_IMPRINT ),
		);

		$titles = array();

		foreach ( array_filter( $pages ) as $page_id ) {
			/**
			 * Filters whether a selected legal page is reachable anonymously.
			 *
			 * @since 1.0.0
			 *
			 * @param bool $reachable True when a logged-out visitor can open it.
			 * @param int  $page_id   Page ID.
			 */
			$reachable = (bool) apply_filters(
				'baukasten/login_legal_pages/page_is_public',
				! self::is_hidden_by_content_visibility( $page_id ),
				$page_id
			);

			if ( ! $reachable ) {
				$titles[] = (string) get_the_title( $page_id );
			}
		}

		return $titles;
	}

	/**
	 * Whether the Content Visibility addon hides a page from visitors.
	 *
	 * @param int $page_id Page ID.
	 * @return bool True when that addon is active and marks the page private.
	 */
	private static function is_hidden_by_content_visibility( int $page_id ): bool {
		$helper = 'Baukasten\\ContentVisibility\\is_private';

		if ( ! function_exists( $helper ) ) {
			return false;
		}

		return (bool) call_user_func( $helper, $page_id );
	}

	/**
	 * Returns the title of a published post that already uses a slug.
	 *
	 * Such a post is unreachable while the login screen answers on that path,
	 * which is worth saying out loud rather than leaving to be discovered.
	 *
	 * @param string $slug Slug to look for.
	 * @return string Post title, or an empty string.
	 */
	private static function page_with_slug( string $slug ): string {
		$post = get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) );

		return $post instanceof \WP_Post ? (string) get_the_title( $post ) : '';
	}
}
