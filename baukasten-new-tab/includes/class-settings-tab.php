<?php
/**
 * The Baukasten tab.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * Everything about the new tab page except which page it is.
 *
 * Which page it is sits on Settings → Reading, next to the homepage; this
 * tab links there and offers to create one. Search, background and
 * favourites are set here, saved through `admin-post.php` with the nonce
 * checked in each handler.
 */
final class Settings_Tab {

	/**
	 * Tab slug.
	 */
	const TAB = 'new-tab';

	/**
	 * Capability the tab requires.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * `admin-post` action that saves the settings.
	 */
	const ACTION_SAVE = 'baukasten_new_tab_save';

	/**
	 * `admin-post` action that creates and selects a page.
	 */
	const ACTION_CREATE_PAGE = 'baukasten_new_tab_create_page';

	/**
	 * `admin-post` action that empties the caches.
	 */
	const ACTION_CLEAR_CACHE = 'baukasten_new_tab_clear_cache';

	/**
	 * Nonce action for the tab's forms.
	 */
	const NONCE = 'baukasten_new_tab_tab';

	/**
	 * Slug of a page created from the tab.
	 */
	const PAGE_SLUG = 'start';

	/**
	 * Handle of the tab's script and stylesheet.
	 */
	const HANDLE = 'baukasten-new-tab-admin';

	/**
	 * Registers the hooks the tab needs.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'save' ) );
		add_action( 'admin_post_' . self::ACTION_CREATE_PAGE, array( __CLASS__, 'create_page' ) );
		add_action( 'admin_post_' . self::ACTION_CLEAR_CACHE, array( __CLASS__, 'clear_cache' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

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
				'title'       => __( 'New Tab Page', 'baukasten-new-tab' ),
				'plugin_file' => PLUGIN_FILE,
				'capability'  => self::CAPABILITY,
				'position'    => 80,
				'render'      => array( __CLASS__, 'render' ),
			)
		);
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
			esc_html__( 'New Tab Page serves the page chosen under Settings → Reading, but its settings need the Baukasten - Privacy Toolkit plugin.', 'baukasten-new-tab' )
		);
	}

	/**
	 * Loads the media dialog and the favourites editor on this tab only.
	 *
	 * @param mixed $hook_suffix Current admin page.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ): void {
		if ( 'settings_page_baukasten' !== $hook_suffix || ! class_exists( '\Baukasten\Admin' ) || self::TAB !== \Baukasten\Admin::current_tab() ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style( self::HANDLE, plugin_url( 'assets/css/admin.css' ), array(), Renderer::asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_script( self::HANDLE, plugin_url( 'assets/js/admin.js' ), array(), Renderer::asset_version( 'assets/js/admin.js' ), true );

		wp_localize_script(
			self::HANDLE,
			'baukastenNewTab',
			array(
				'i18n' => array(
					'choose'  => __( 'Choose background images', 'baukasten-new-tab' ),
					'use'     => __( 'Use these images', 'baukasten-new-tab' ),
					'up'      => __( 'Move up', 'baukasten-new-tab' ),
					'down'    => __( 'Move down', 'baukasten-new-tab' ),
					'remove'  => __( 'Remove', 'baukasten-new-tab' ),
					'removed' => __( 'Remove this image', 'baukasten-new-tab' ),
				),
			)
		);
	}

	/**
	 * Saves the tab.
	 *
	 * Every field is validated on its own. A field that fails keeps its old
	 * value and is named in the notice; the others are saved anyway, so one
	 * typo in a template does not throw away a rearranged favourites bar.
	 *
	 * @return void
	 */
	public static function save(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'baukasten-new-tab' ), 403 );
		}

		check_admin_referer( self::NONCE );

		$old      = Settings::all();
		$settings = $old;
		$errors   = array();

		// Engines.
		$posted_engines = isset( $_POST['engines'] ) && is_array( $_POST['engines'] ) ? wp_unslash( $_POST['engines'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised field by field below.
		$shortcuts      = array();

		foreach ( $old['engines'] as $id => $engine ) {
			$posted = isset( $posted_engines[ $id ] ) && is_array( $posted_engines[ $id ] ) ? $posted_engines[ $id ] : array();

			$label    = mb_substr( sanitize_text_field( (string) ( $posted['label'] ?? $engine['label'] ) ), 0, 40 );
			$shortcut = strtolower( sanitize_text_field( (string) ( $posted['shortcut'] ?? $engine['shortcut'] ) ) );
			$shortcut = ltrim( $shortcut, '@' );
			$template = trim( (string) ( $posted['template'] ?? $engine['template'] ) );

			if ( '' !== $shortcut && ( ! preg_match( '/^[a-z0-9]{1,8}$/', $shortcut ) || in_array( $shortcut, $shortcuts, true ) ) ) {
				/* translators: %s: search engine name. */
				$errors[] = sprintf( __( 'The shortcut of %s has to be up to eight letters or digits, different from the others.', 'baukasten-new-tab' ), $engine['label'] );
				$shortcut = $engine['shortcut'];
			}

			if ( ! Settings::is_valid_template( $template ) ) {
				/* translators: %s: search engine name. */
				$errors[] = sprintf( __( 'The address of %s has to start with https:// and contain {q}.', 'baukasten-new-tab' ), $engine['label'] );
				$template = $engine['template'];
			}

			if ( '' !== $shortcut ) {
				$shortcuts[] = $shortcut;
			}

			$settings['engines'][ $id ] = array(
				'label'    => '' !== $label ? $label : $engine['label'],
				'shortcut' => $shortcut,
				'template' => $template,
				'enabled'  => ! empty( $posted['enabled'] ),
			);
		}

		if ( array() === array_filter( array_column( $settings['engines'], 'enabled' ) ) ) {
			$errors[]                                    = __( 'At least one search engine has to stay switched on. Startpage was kept on.', 'baukasten-new-tab' );
			$settings['engines']['startpage']['enabled'] = true;
		}

		$default = isset( $_POST['default_engine'] ) ? sanitize_key( wp_unslash( (string) $_POST['default_engine'] ) ) : $old['default_engine'];

		if ( isset( $settings['engines'][ $default ] ) && $settings['engines'][ $default ]['enabled'] ) {
			$settings['default_engine'] = $default;
		} else {
			$errors[] = __( 'The default search engine has to be one that is switched on.', 'baukasten-new-tab' );
		}

		// The page.
		if ( isset( $_POST['page_id'] ) ) {
			list( $page_id, $problem ) = Page_Setting::validate( absint( wp_unslash( (string) $_POST['page_id'] ) ) );

			if ( '' !== $problem ) {
				$errors[] = $problem;
			}

			update_option( Settings::OPTION_PAGE, $page_id );
		}

		// Greetings.
		$settings['greeting']          = ! empty( $_POST['greeting'] );
		$settings['greeting_holidays'] = ! empty( $_POST['greeting_holidays'] );

		$custom                      = isset( $_POST['greeting_custom'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['greeting_custom'] ) ) : '';
		$custom                      = array_map( static fn( string $line ): string => mb_substr( trim( $line ), 0, 160 ), (array) preg_split( '/\R/u', $custom ) );
		$settings['greeting_custom'] = implode( "\n", array_slice( array_values( array_filter( $custom ) ), 0, 200 ) );

		// Background.
		$mode                   = isset( $_POST['background'] ) ? sanitize_key( wp_unslash( (string) $_POST['background'] ) ) : $old['background'];
		$settings['background'] = in_array( $mode, Settings::BACKGROUNDS, true ) ? $mode : $old['background'];

		$media_ids = isset( $_POST['media_ids'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['media_ids'] ) ) : '';
		$media_ids = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $media_ids ) ) ) ) );
		$media_ids = array_values( array_filter( $media_ids, 'wp_attachment_is_image' ) );

		$settings['media_ids'] = $media_ids;

		foreach ( $media_ids as $media_id ) {
			// Once, here, rather than while a visitor waits for the page.
			Backgrounds::color_of( (int) $media_id );
		}

		$settings['unsplash_query']    = isset( $_POST['unsplash_query'] ) ? mb_substr( sanitize_text_field( wp_unslash( (string) $_POST['unsplash_query'] ) ), 0, 100 ) : $old['unsplash_query'];
		$settings['unsplash_app_name'] = isset( $_POST['unsplash_app_name'] ) ? sanitize_title( wp_unslash( (string) $_POST['unsplash_app_name'] ) ) : $old['unsplash_app_name'];

		$orientation                      = isset( $_POST['unsplash_orientation'] ) ? sanitize_key( wp_unslash( (string) $_POST['unsplash_orientation'] ) ) : $old['unsplash_orientation'];
		$settings['unsplash_orientation'] = in_array( $orientation, Settings::ORIENTATIONS, true ) ? $orientation : $old['unsplash_orientation'];

		$interval             = isset( $_POST['interval'] ) ? absint( wp_unslash( (string) $_POST['interval'] ) ) : (int) $old['interval'];
		$settings['interval'] = min( 1440, $interval );

		$key = isset( $_POST['unsplash_key'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['unsplash_key'] ) ) ) : '';

		if ( ! empty( $_POST['unsplash_key_delete'] ) ) {
			delete_option( Settings::OPTION_KEY );
			Unsplash::clear();
		} elseif ( '' !== $key ) {
			if ( preg_match( Settings::KEY_PATTERN, $key ) ) {
				update_option( Settings::OPTION_KEY, $key, false );
				Unsplash::clear();
			} else {
				$errors[] = __( 'That does not look like an Unsplash access key (20 to 100 letters, digits, - or _). The stored key was kept.', 'baukasten-new-tab' );
			}
		}

		// Favourites.
		$posted_favorites = isset( $_POST['favorites'] ) && is_array( $_POST['favorites'] ) ? wp_unslash( $_POST['favorites'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised field by field below.
		$favorites        = array();

		foreach ( $posted_favorites as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$title = mb_substr( sanitize_text_field( (string) ( $row['title'] ?? '' ) ), 0, 60 );
			$raw   = trim( (string) ( $row['url'] ?? '' ) );
			$url   = Settings::clean_url( $raw );
			$icon  = Settings::clean_url( (string) ( $row['icon'] ?? '' ) );

			if ( '' === $raw && '' === $title ) {
				continue;
			}

			if ( '' === $url ) {
				/* translators: %s: the address as entered. */
				$errors[] = sprintf( __( 'The favourite "%s" was left out: only http and https addresses work.', 'baukasten-new-tab' ), '' !== $title ? $title : sanitize_text_field( $raw ) );

				continue;
			}

			if ( count( $favorites ) >= Settings::MAX_FAVORITES ) {
				/* translators: %d: maximum number of favourites. */
				$errors[] = sprintf( __( 'Only the first %d favourites were kept.', 'baukasten-new-tab' ), Settings::MAX_FAVORITES );

				break;
			}

			$favorites[] = array(
				'title' => $title,
				'url'   => $url,
				'icon'  => $icon,
			);
		}

		$settings['favorites'] = $favorites;

		Settings::save( $settings );
		Favicons::refresh( $favorites );

		if ( ! empty( $_POST['test_unsplash'] ) ) {
			$test = Unsplash::test();

			if ( ! $test['ok'] ) {
				$errors[] = $test['message'];
			} elseif ( array() === $errors ) {
				self::go_back( 'success', __( 'Settings saved.', 'baukasten-new-tab' ) . ' ' . $test['message'] );
			}
		}

		if ( array() !== $errors ) {
			self::go_back( 'error', __( 'Settings saved, with exceptions:', 'baukasten-new-tab' ) . ' ' . implode( ' ', array_unique( $errors ) ) );
		}

		self::go_back( 'success', __( 'Settings saved.', 'baukasten-new-tab' ) );
	}

	/**
	 * Creates a page called "New Tab" at /start/ and selects it.
	 *
	 * Only when no page is selected, and only when the slug is free: a
	 * page someone already has at /start/ is theirs, and quietly creating
	 * /start-2/ next to it would leave two pages nobody asked for.
	 *
	 * @return void
	 */
	public static function create_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) || ! current_user_can( 'publish_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'baukasten-new-tab' ), 403 );
		}

		check_admin_referer( self::NONCE );

		if ( 0 < Settings::page_id() && 'publish' === get_post_status( Settings::page_id() ) ) {
			self::go_back( 'error', __( 'A new tab page is already selected.', 'baukasten-new-tab' ) );
		}

		$existing = get_page_by_path( self::PAGE_SLUG, OBJECT, 'page' );

		if ( $existing instanceof \WP_Post ) {
			self::go_back(
				'error',
				sprintf(
					/* translators: 1: page title. 2: slug. */
					__( 'The page "%1$s" already uses the address /%2$s/. Select it under Settings → Reading, or choose another page there.', 'baukasten-new-tab' ),
					get_the_title( $existing ),
					self::PAGE_SLUG
				)
			);
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'New Tab', 'baukasten-new-tab' ),
				'post_name'    => self::PAGE_SLUG,
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $page_id ) || 0 >= (int) $page_id ) {
			self::go_back( 'error', __( 'The page could not be created.', 'baukasten-new-tab' ) );
		}

		update_option( Settings::OPTION_PAGE, (int) $page_id );

		self::go_back( 'success', __( 'Page created and selected as the new tab page.', 'baukasten-new-tab' ) );
	}

	/**
	 * Empties the Unsplash pool and the suggestion cache.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'baukasten-new-tab' ), 403 );
		}

		check_admin_referer( self::NONCE );

		Unsplash::clear();
		self::delete_transients( Suggest::TRANSIENT_PREFIX );

		self::go_back( 'success', __( 'Cache emptied. The next page load fetches a new photo pool.', 'baukasten-new-tab' ) );
	}

	/**
	 * Deletes every transient whose name starts with a prefix.
	 *
	 * @param string $prefix Transient name prefix.
	 * @return void
	 */
	public static function delete_transients( string $prefix ): void {
		global $wpdb;

		// One query for a cache whose keys are hashes: there is no list of
		// them to walk, and the object cache has no prefix delete.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . $prefix ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%'
			)
		);

		wp_cache_flush_group( 'transient' );
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

		wp_safe_redirect( admin_url( 'options-reading.php' ) );

		exit;
	}

	/**
	 * Prints the tab.
	 *
	 * @return void
	 */
	public static function render(): void {
		$page_id    = Settings::page_id();
		$page       = 0 < $page_id ? get_post( $page_id ) : null;
		$settings   = Settings::all();
		$has_key    = '' !== Settings::unsplash_key();
		$is_private = 0 < $page_id && Legal_Links::is_private( $page_id );
		$is_front   = 0 < $page_id && 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $page_id;
		$media      = array();

		foreach ( (array) $settings['media_ids'] as $media_id ) {
			$thumb = wp_get_attachment_image_src( (int) $media_id, 'thumbnail' );

			if ( is_array( $thumb ) ) {
				$media[ (int) $media_id ] = (string) $thumb[0];
			}
		}

		require PLUGIN_DIR . 'admin/views/settings-tab.php';
	}
}
