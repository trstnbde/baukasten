<?php
/**
 * Visibility column and toggle in the post list tables.
 *
 * @package Baukasten\ContentVisibility
 */

namespace Baukasten\ContentVisibility;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a Visibility column with a switch to every supported list table.
 */
final class Admin_Column {

	/**
	 * Column identifier.
	 */
	const COLUMN = 'baukasten_visibility';

	/**
	 * Registers the column hooks for every supported post type.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_init', array( __CLASS__, 'add_column_hooks' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Hooks the column into each supported list table.
	 *
	 * @return void
	 */
	public static function add_column_hooks(): void {
		foreach ( Visibility::get_post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( __CLASS__, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( __CLASS__, 'render_column' ), 10, 2 );
		}
	}

	/**
	 * Inserts the column after the title.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Filtered columns.
	 */
	public static function add_column( $columns ): array {
		$columns = is_array( $columns ) ? $columns : array();
		$out     = array();

		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;

			if ( 'title' === $key ) {
				$out[ self::COLUMN ] = __( 'Visibility', 'baukasten-content-visibility' );
			}
		}

		if ( ! isset( $out[ self::COLUMN ] ) ) {
			$out[ self::COLUMN ] = __( 'Visibility', 'baukasten-content-visibility' );
		}

		return $out;
	}

	/**
	 * Renders the toggle for one row.
	 *
	 * @param string $column  Column identifier.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function render_column( $column, $post_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$post_id = (int) $post_id;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup is escaped inside.
		echo self::toggle_markup( $post_id, current_user_can( 'edit_post', $post_id ) );
	}

	/**
	 * Builds the switch markup for a post.
	 *
	 * @param int  $post_id  Post ID.
	 * @param bool $editable Whether the current user may change it.
	 * @return string Escaped markup.
	 */
	public static function toggle_markup( int $post_id, bool $editable ): string {
		$is_public = Visibility::VISIBILITY_PUBLIC === Visibility::get( $post_id );
		$input_id  = 'baukasten-visibility-' . $post_id;

		$label = $is_public
			? __( 'Public', 'baukasten-content-visibility' )
			: __( 'Private', 'baukasten-content-visibility' );

		if ( ! $editable ) {
			return sprintf(
				'<span class="baukasten-visibility baukasten-visibility--readonly is-%1$s">%2$s</span>',
				esc_attr( $is_public ? 'public' : 'private' ),
				esc_html( $label )
			);
		}

		return sprintf(
			'<span class="baukasten-visibility is-%1$s" data-post="%2$d">
				<input type="checkbox" class="baukasten-visibility__input" id="%3$s" %4$s
					aria-describedby="%3$s-state" />
				<label class="baukasten-visibility__switch" for="%3$s">
					<span class="screen-reader-text">%5$s</span>
				</label>
				<span class="baukasten-visibility__state" id="%3$s-state" aria-live="polite">%6$s</span>
				<span class="spinner"></span>
			</span>',
			esc_attr( $is_public ? 'public' : 'private' ),
			$post_id,
			esc_attr( $input_id ),
			checked( $is_public, true, false ),
			esc_html__( 'Make this content publicly visible', 'baukasten-content-visibility' ),
			esc_html( $label )
		);
	}

	/**
	 * Loads the assets on the supported list tables only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ): void {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}

		$screen = get_current_screen();

		if ( null === $screen || ! Visibility::is_supported( (string) $screen->post_type ) ) {
			return;
		}

		self::enqueue_assets();
	}

	/**
	 * Registers and enqueues the module assets.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		$base = PLUGIN_URL . 'assets/';

		wp_enqueue_style(
			'baukasten-content-visibility',
			$base . 'css/toggle.css',
			array(),
			VERSION
		);

		wp_enqueue_script(
			'baukasten-content-visibility',
			$base . 'js/toggle.js',
			array( 'wp-a11y' ),
			VERSION,
			true
		);

		wp_localize_script(
			'baukasten-content-visibility',
			'baukastenContentVisibility',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => Ajax::ACTION,
				'nonce'   => wp_create_nonce( Ajax::NONCE ),
				'i18n'    => array(
					'public'  => __( 'Public', 'baukasten-content-visibility' ),
					'private' => __( 'Private', 'baukasten-content-visibility' ),
					'saving'  => __( 'Saving…', 'baukasten-content-visibility' ),
					'saved'   => __( 'Visibility saved.', 'baukasten-content-visibility' ),
					'error'   => __( 'The visibility could not be saved. Please reload the page and try again.', 'baukasten-content-visibility' ),
				),
			)
		);
	}
}
