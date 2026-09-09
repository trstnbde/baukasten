<?php
/**
 * Visibility control in the post editor.
 *
 * @package Baukasten\ContentVisibility
 */

namespace Baukasten\ContentVisibility;

defined( 'ABSPATH' ) || exit;

use WP_Post;

/**
 * Adds a visibility meta box to the editor.
 *
 * A classic meta box is used deliberately. The block editor renders it in the
 * settings sidebar, and it works in the classic editor unchanged, without
 * pulling in a JavaScript build step for a single radio pair.
 */
final class Meta_Box {

	/**
	 * Meta box identifier.
	 */
	const BOX_ID = 'baukasten-content-visibility';

	/**
	 * Nonce action.
	 */
	const NONCE_ACTION = 'baukasten_content_visibility_save';

	/**
	 * Nonce field name.
	 */
	const NONCE_FIELD = 'baukasten_content_visibility_nonce';

	/**
	 * Registers the meta box hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * Adds the meta box to every supported post type.
	 *
	 * @return void
	 */
	public static function add(): void {
		foreach ( Visibility::get_post_types() as $post_type ) {
			add_meta_box(
				self::BOX_ID,
				__( 'Content Visibility', 'baukasten-content-visibility' ),
				array( __CLASS__, 'render' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Renders the meta box.
	 *
	 * @param WP_Post $post Post being edited.
	 * @return void
	 */
	public static function render( $post ): void {
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$current  = Visibility::get( $post->ID );
		$editable = current_user_can( 'edit_post', $post->ID );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$choices = array(
			Visibility::VISIBILITY_PUBLIC  => array(
				__( 'Public', 'baukasten-content-visibility' ),
				__( 'Visible to everyone.', 'baukasten-content-visibility' ),
			),
			Visibility::VISIBILITY_PRIVATE => array(
				__( 'Private', 'baukasten-content-visibility' ),
				__( 'Visible to logged-in users only, regardless of role.', 'baukasten-content-visibility' ),
			),
		);

		echo '<div class="baukasten-visibility-box">';

		foreach ( $choices as $value => $labels ) {
			printf(
				'<p><label><input type="radio" name="%1$s" value="%2$s" %3$s %4$s /> <strong>%5$s</strong></label><br /><span class="description">%6$s</span></p>',
				esc_attr( Visibility::META_KEY ),
				esc_attr( $value ),
				checked( $current, $value, false ),
				disabled( $editable, false, false ),
				esc_html( $labels[0] ),
				esc_html( $labels[1] )
			);
		}

		echo '</div>';
	}

	/**
	 * Persists the selected value.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save( $post_id, $post ): void {
		$post_id = (int) $post_id;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! $post instanceof WP_Post || ! Visibility::is_supported( $post->post_type ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST[ Visibility::META_KEY ] ) ) {
			return;
		}

		$value = sanitize_text_field( wp_unslash( (string) $_POST[ Visibility::META_KEY ] ) );

		if ( ! in_array( $value, Visibility::values(), true ) ) {
			return;
		}

		Visibility::set( $post_id, $value );
	}
}
