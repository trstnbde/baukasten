<?php
/**
 * The choice of page, under Settings → Reading.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * Makes "which page is the new tab page" a setting like the homepage.
 *
 * Which page is the homepage and which lists the posts is chosen on the
 * Reading screen, flagged in the page list and announced in the editor. The
 * new tab page is a page with the same kind of role, so it gets the same
 * treatment in the same places: a field on that screen, a post state, an
 * editor notice, and a reset when the page goes away.
 *
 * The field is added through the Settings API, which puts it at the end of
 * the table — `do_settings_fields( 'reading', 'default' )` is printed there.
 * Moving it next to "Homepage" would take JavaScript reaching into core's
 * markup, and core's markup is left alone.
 */
final class Reading_Setting {

	/**
	 * Field id.
	 */
	const FIELD = 'baukasten_new_tab_page';

	/**
	 * Handle of the block editor notice.
	 */
	const EDITOR_HANDLE = 'baukasten-new-tab-editor';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_init', array( __CLASS__, 'add_field' ) );

		add_filter( 'display_post_states', array( __CLASS__, 'post_state' ), 10, 2 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'classic_editor_notice' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'block_editor_notice' ) );

		add_action( 'wp_trash_post', array( __CLASS__, 'forget_page' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'forget_page' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'forget_unpublished' ), 10, 3 );
	}

	/**
	 * Registers the option in the `reading` group.
	 *
	 * On `init`, not `admin_init`, so the sanitize callback also guards
	 * `update_option()` calls from anywhere else.
	 *
	 * @return void
	 */
	public static function register_setting(): void {
		register_setting(
			'reading',
			Settings::OPTION_PAGE,
			array(
				'type'              => 'integer',
				'description'       => __( 'The page shown as the new tab page.', 'baukasten-new-tab' ),
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => 0,
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Adds the field to the Reading screen.
	 *
	 * @return void
	 */
	public static function add_field(): void {
		add_settings_field(
			self::FIELD,
			__( 'New tab page', 'baukasten-new-tab' ),
			array( __CLASS__, 'render_field' ),
			'reading',
			'default',
			array( 'label_for' => self::FIELD )
		);
	}

	/**
	 * Prints the field.
	 *
	 * @return void
	 */
	public static function render_field(): void {
		$page_id = Settings::page_id();

		wp_dropdown_pages(
			array(
				'name'              => esc_attr( Settings::OPTION_PAGE ),
				'id'                => esc_attr( self::FIELD ),
				'selected'          => (int) $page_id,
				'show_option_none'  => esc_html__( '— Select —', 'baukasten-new-tab' ),
				'option_none_value' => '0',
				'post_status'       => 'publish',
			)
		);

		$tab = class_exists( '\Baukasten\Admin' ) ? \Baukasten\Admin::page_url( Settings_Tab::TAB ) : '';

		echo '<p class="description">';

		if ( '' !== $tab ) {
			printf(
				/* translators: %s: link to the New Tab Page tab. */
				esc_html__( 'This page shows search, favourites and a greeting instead of its content. Settings under %s.', 'baukasten-new-tab' ),
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( $tab ),
					esc_html__( 'Baukasten → New Tab Page', 'baukasten-new-tab' )
				)
			);
		} else {
			esc_html_e( 'This page shows search, favourites and a greeting instead of its content.', 'baukasten-new-tab' );
		}

		echo '</p>';

		if ( 0 < $page_id && 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $page_id ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'It is also the homepage, so the address of the site opens it.', 'baukasten-new-tab' )
			);
		}
	}

	/**
	 * Accepts a published page that is not the posts page.
	 *
	 * The posts page is refused: WordPress renders it as the blog index, and
	 * taking it over would make the posts disappear. Refusing keeps the old
	 * value. The homepage is fine — then the new tab page is the homepage.
	 *
	 * @param mixed $value Submitted value.
	 * @return int Page ID to store.
	 */
	public static function sanitize( $value ): int {
		$page_id  = absint( $value );
		$previous = absint( get_option( Settings::OPTION_PAGE, 0 ) );

		if ( 0 === $page_id ) {
			return 0;
		}

		$page = get_post( $page_id );

		if ( ! $page instanceof \WP_Post || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			self::error( 'not_published', __( 'The new tab page has to be a published page. The previous choice was kept.', 'baukasten-new-tab' ) );

			return $previous;
		}

		if ( (int) get_option( 'page_for_posts' ) === $page_id ) {
			self::error( 'posts_page', __( 'The posts page cannot be the new tab page as well: its posts would no longer be shown. The previous choice was kept.', 'baukasten-new-tab' ) );

			return $previous;
		}

		return $page_id;
	}

	/**
	 * Reports a refused value on the Reading screen.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @return void
	 */
	private static function error( string $code, string $message ): void {
		if ( function_exists( 'add_settings_error' ) ) {
			add_settings_error( Settings::OPTION_PAGE, 'baukasten_new_tab_' . $code, $message );
		}
	}

	/**
	 * Flags the page in the page list, next to "Front Page" and "Posts Page".
	 *
	 * @param mixed $states Post states.
	 * @param mixed $post   The post.
	 * @return array<string, string> Post states.
	 */
	public static function post_state( $states, $post ): array {
		$states = (array) $states;

		if ( $post instanceof \WP_Post && 'page' === $post->post_type && 0 < $post->ID && Settings::page_id() === (int) $post->ID ) {
			$states['baukasten_new_tab'] = __( 'New tab page', 'baukasten-new-tab' );
		}

		return $states;
	}

	/**
	 * The notice text in the editor.
	 *
	 * @return string Notice.
	 */
	private static function notice_text(): string {
		return __( 'You are editing the new tab page. Its content is not shown.', 'baukasten-new-tab' );
	}

	/**
	 * Warns in the classic editor, where core warns about the posts page.
	 *
	 * @param mixed $post The post being edited.
	 * @return void
	 */
	public static function classic_editor_notice( $post ): void {
		if ( ! $post instanceof \WP_Post || Settings::page_id() !== (int) $post->ID || 0 === (int) $post->ID ) {
			return;
		}

		printf(
			'<div class="notice notice-warning inline"><p>%s</p></div>',
			esc_html( self::notice_text() )
		);
	}

	/**
	 * Warns in the block editor, through its own notices.
	 *
	 * @return void
	 */
	public static function block_editor_notice(): void {
		$post = get_post();

		if ( ! $post instanceof \WP_Post || 0 === (int) $post->ID || Settings::page_id() !== (int) $post->ID ) {
			return;
		}

		wp_enqueue_script(
			self::EDITOR_HANDLE,
			plugin_url( 'assets/js/editor-notice.js' ),
			array( 'wp-data', 'wp-notices', 'wp-dom-ready' ),
			Renderer::asset_version( 'assets/js/editor-notice.js' ),
			true
		);

		wp_add_inline_script(
			self::EDITOR_HANDLE,
			'window.baukastenNewTabNotice = ' . wp_json_encode( self::notice_text(), JSON_HEX_TAG | JSON_HEX_AMP ) . ';',
			'before'
		);
	}

	/**
	 * Clears the setting when its page goes to the trash or is deleted.
	 *
	 * @param mixed $post_id Post ID.
	 * @return void
	 */
	public static function forget_page( $post_id ): void {
		if ( 0 < Settings::page_id() && Settings::page_id() === (int) $post_id ) {
			update_option( Settings::OPTION_PAGE, 0 );
		}
	}

	/**
	 * Clears the setting when its page stops being published.
	 *
	 * @param mixed $new_status New status.
	 * @param mixed $old_status Old status.
	 * @param mixed $post       The post.
	 * @return void
	 */
	public static function forget_unpublished( $new_status, $old_status, $post ): void {
		if ( 'publish' === $new_status || 'publish' !== $old_status || ! $post instanceof \WP_Post ) {
			return;
		}

		self::forget_page( $post->ID );
	}
}
