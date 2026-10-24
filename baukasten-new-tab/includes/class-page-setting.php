<?php
/**
 * The page that is the new tab page.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * Everything around the page that is the new tab page, except the choice.
 *
 * The choice itself is made on the addon's tab. Like the homepage and the
 * posts page, the chosen page is flagged in the page list and announced in
 * both editors, and the setting is reset when the page goes away.
 */
final class Page_Setting {

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
		add_filter( 'display_post_states', array( __CLASS__, 'post_state' ), 10, 2 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'classic_editor_notice' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'block_editor_notice' ) );

		add_action( 'wp_trash_post', array( __CLASS__, 'forget_page' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'forget_page' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'forget_unpublished' ), 10, 3 );
	}

	/**
	 * Checks a page choice.
	 *
	 * Accepts a published page that is not the posts page. The posts page is
	 * refused: WordPress renders it as the blog index, and taking it over
	 * would make the posts disappear. The homepage is fine — then the new tab
	 * page is the homepage.
	 *
	 * @param mixed $value Submitted value.
	 * @return array{0: int, 1: string} The page ID to store (the previous one on a refusal), and the reason it was refused, or an empty string.
	 */
	public static function validate( $value ): array {
		$page_id  = absint( $value );
		$previous = absint( get_option( Settings::OPTION_PAGE, 0 ) );

		if ( 0 === $page_id ) {
			return array( 0, '' );
		}

		$page = get_post( $page_id );

		if ( ! $page instanceof \WP_Post || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			return array( $previous, __( 'The new tab page has to be a published page. The previous choice was kept.', 'baukasten-new-tab' ) );
		}

		if ( (int) get_option( 'page_for_posts' ) === $page_id ) {
			return array( $previous, __( 'The posts page cannot be the new tab page as well: its posts would no longer be shown. The previous choice was kept.', 'baukasten-new-tab' ) );
		}

		return array( $page_id, '' );
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
	 * @param \WP_Post|null $post The page being edited.
	 * @return string Notice.
	 */
	private static function notice_text( ?\WP_Post $post = null ): string {
		$text = __( 'You are editing the new tab page. Its content is not shown.', 'baukasten-new-tab' );

		if ( $post instanceof \WP_Post && '' !== (string) $post->post_password ) {
			$text .= ' ' . __( 'It is password protected: visitors who are not signed in see only the password form until they enter it.', 'baukasten-new-tab' );
		}

		return $text;
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
			esc_html( self::notice_text( $post ) )
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
			'window.baukastenNewTabNotice = ' . wp_json_encode( self::notice_text( $post ), JSON_HEX_TAG | JSON_HEX_AMP ) . ';',
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
