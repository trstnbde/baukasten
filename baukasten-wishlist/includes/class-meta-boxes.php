<?php
/**
 * The editing screens of lists and wishes.
 *
 * @package Baukasten\Wishlist
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * Classic meta boxes for both post types.
 *
 * The image of a list (its header) and of a wish (the product photo) is the
 * featured image, so the media library's own box does the picking.
 */
final class Meta_Boxes {

	/**
	 * Nonce action.
	 */
	const NONCE_ACTION = 'baukasten_wishlist_save';

	/**
	 * Nonce field name.
	 */
	const NONCE_FIELD = 'baukasten_wishlist_nonce';

	/**
	 * `admin-post` action that resets the purchases of one wish.
	 */
	const ACTION_RESET = 'baukasten_wishlist_reset_item';

	/**
	 * Query argument that carries the reset notice.
	 */
	const NOTICE_ARG = 'baukasten_wishlist_reset';

	/**
	 * Query argument that preselects the list of a new wish.
	 */
	const LIST_ARG = 'baukasten_wishlist';

	/**
	 * Stylesheet handle.
	 */
	const HANDLE = 'baukasten-wishlist-admin';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_' . Post_Types::LIST_TYPE, array( __CLASS__, 'add_list_boxes' ) );
		add_action( 'add_meta_boxes_' . Post_Types::ITEM_TYPE, array( __CLASS__, 'add_item_boxes' ) );
		add_action( 'edit_form_after_title', array( __CLASS__, 'print_nonce' ) );
		add_action( 'save_post_' . Post_Types::LIST_TYPE, array( __CLASS__, 'save_list' ), 10, 2 );
		add_action( 'save_post_' . Post_Types::ITEM_TYPE, array( __CLASS__, 'save_item' ), 10, 2 );
		add_action( 'admin_post_' . self::ACTION_RESET, array( __CLASS__, 'reset_item' ) );
		add_action( 'admin_notices', array( __CLASS__, 'reset_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
	}

	/**
	 * Adds the boxes of a list.
	 *
	 * @return void
	 */
	public static function add_list_boxes(): void {
		add_meta_box( 'baukasten-wishlist-occasion', __( 'Occasion', 'baukasten-wishlist' ), array( __CLASS__, 'render_list' ), Post_Types::LIST_TYPE, 'normal', 'high' );
		add_meta_box( 'baukasten-wishlist-items', __( 'Wishes on this list', 'baukasten-wishlist' ), array( __CLASS__, 'render_list_items' ), Post_Types::LIST_TYPE, 'normal', 'default' );
		add_meta_box( 'baukasten-wishlist-embed', __( 'Show on a page', 'baukasten-wishlist' ), array( __CLASS__, 'render_embed' ), Post_Types::LIST_TYPE, 'side', 'default' );
	}

	/**
	 * Adds the boxes of a wish.
	 *
	 * @return void
	 */
	public static function add_item_boxes(): void {
		add_meta_box( 'baukasten-wishlist-item', __( 'Details', 'baukasten-wishlist' ), array( __CLASS__, 'render_item' ), Post_Types::ITEM_TYPE, 'normal', 'high' );
		add_meta_box( 'baukasten-wishlist-claims', __( 'Bought', 'baukasten-wishlist' ), array( __CLASS__, 'render_claims' ), Post_Types::ITEM_TYPE, 'side', 'high' );
	}

	/**
	 * Prints the nonce every box shares, outside any box.
	 *
	 * A box can be hidden through Screen Options; the nonce must not be.
	 *
	 * @param mixed $post The post being edited.
	 * @return void
	 */
	public static function print_nonce( $post ): void {
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, array( Post_Types::LIST_TYPE, Post_Types::ITEM_TYPE ), true ) ) {
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
	}

	/**
	 * Placeholders for the title field.
	 *
	 * @param mixed $text Default placeholder.
	 * @param mixed $post The post being edited.
	 * @return string Placeholder.
	 */
	public static function title_placeholder( $text, $post ): string {
		if ( $post instanceof \WP_Post && Post_Types::LIST_TYPE === $post->post_type ) {
			return __( 'Title of the list, such as "Leni turns two"', 'baukasten-wishlist' );
		}

		if ( $post instanceof \WP_Post && Post_Types::ITEM_TYPE === $post->post_type ) {
			return __( 'Name of the product', 'baukasten-wishlist' );
		}

		return (string) $text;
	}

	/**
	 * Prints the occasion box of a list.
	 *
	 * @param mixed $post The list.
	 * @return void
	 */
	public static function render_list( $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$baukasten_wl_values = self::stored( $post->ID, Fields::list_types(), Fields::list_defaults() );

		require PLUGIN_DIR . 'admin/views/list-box.php';
	}

	/**
	 * Prints the wishes of a list.
	 *
	 * @param mixed $post The list.
	 * @return void
	 */
	public static function render_list_items( $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$baukasten_wl_items = 'auto-draft' === $post->post_status ? array() : Items::of_list( $post->ID, true );
		$baukasten_wl_new   = add_query_arg( self::LIST_ARG, $post->ID, admin_url( 'post-new.php?post_type=' . Post_Types::ITEM_TYPE ) );
		$baukasten_wl_saved = 'auto-draft' !== $post->post_status;
		$baukasten_wl_prios = Fields::priorities();

		require PLUGIN_DIR . 'admin/views/list-items.php';
	}

	/**
	 * Prints how to put a list on a page.
	 *
	 * @param mixed $post The list.
	 * @return void
	 */
	public static function render_embed( $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		echo '<p>';
		esc_html_e( 'Add the "Wishlist" block to any page or post and choose this list. Or paste the shortcode:', 'baukasten-wishlist' );
		echo '</p>';

		if ( 'auto-draft' === $post->post_status ) {
			echo '<p class="description">';
			esc_html_e( 'The shortcode appears once the list is saved.', 'baukasten-wishlist' );
			echo '</p>';

			return;
		}

		printf(
			'<p><input type="text" class="widefat code" readonly value="%s" onfocus="this.select();" aria-label="%s" /></p>',
			esc_attr( Shortcode::for_list( $post->ID ) ),
			esc_attr__( 'Shortcode', 'baukasten-wishlist' )
		);

		echo '<p class="description">';
		esc_html_e( 'Options: sort="priority|open|price-asc|price-desc|title|order", filter="open|all|done" (default "open": bought wishes leave the list), controls="no" to hide sorting and filters.', 'baukasten-wishlist' );
		echo '</p>';
	}

	/**
	 * Prints the details box of a wish.
	 *
	 * @param mixed $post The wish.
	 * @return void
	 */
	public static function render_item( $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$baukasten_wl_values = self::stored( $post->ID, Fields::item_types(), Fields::item_defaults() );

		// "Add wish" on a list's screen carries that list along.
		if ( 'auto-draft' === $post->post_status && isset( $_GET[ self::LIST_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only preselects a field.
			$baukasten_wl_values['list'] = Fields::sanitize( 'list', absint( wp_unslash( $_GET[ self::LIST_ARG ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- as above.
		}

		$baukasten_wl_lists = Items::lists();
		$baukasten_wl_prios = Fields::priorities();

		require PLUGIN_DIR . 'admin/views/item-box.php';
	}

	/**
	 * Prints what was bought of a wish.
	 *
	 * @param mixed $post The wish.
	 * @return void
	 */
	public static function render_claims( $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$quantity = (int) Fields::load_item( $post->ID )['quantity'];
		$bought   = Claims::count( $post->ID );

		echo '<p class="baukasten-wl-admin-claims">';
		printf(
			/* translators: 1: number reported bought. 2: number wished for. */
			esc_html__( '%1$s of %2$s reported bought.', 'baukasten-wishlist' ),
			'<strong>' . esc_html( number_format_i18n( $bought ) ) . '</strong>',
			esc_html( number_format_i18n( $quantity ) )
		);
		echo '</p>';

		echo '<p class="description">';
		esc_html_e( 'Guests report purchases anonymously on the page. Nobody, not even you, can see who bought what.', 'baukasten-wishlist' );
		echo '</p>';

		if ( 0 < $bought ) {
			$url = wp_nonce_url(
				add_query_arg(
					array(
						'action' => self::ACTION_RESET,
						'item'   => $post->ID,
					),
					admin_url( 'admin-post.php' )
				),
				self::ACTION_RESET . '_' . $post->ID
			);

			printf(
				'<p><a class="button" href="%1$s" onclick="return window.confirm( %2$s );">%3$s</a></p>',
				esc_url( $url ),
				esc_attr( (string) wp_json_encode( __( 'Remove every reported purchase of this wish? Guests who reported one can no longer take it back.', 'baukasten-wishlist' ) ) ),
				esc_html__( 'Reset to not bought', 'baukasten-wishlist' )
			);
		}
	}

	/**
	 * Stores the fields of a list.
	 *
	 * @param mixed $post_id The list.
	 * @param mixed $post    The post object.
	 * @return void
	 */
	public static function save_list( $post_id, $post ): void {
		if ( ! self::may_save( (int) $post_id, $post, Post_Types::LIST_TYPE ) ) {
			return;
		}

		self::store( (int) $post_id, Fields::list_types() );
	}

	/**
	 * Stores the fields of a wish.
	 *
	 * @param mixed $post_id The wish.
	 * @param mixed $post    The post object.
	 * @return void
	 */
	public static function save_item( $post_id, $post ): void {
		if ( ! self::may_save( (int) $post_id, $post, Post_Types::ITEM_TYPE ) ) {
			return;
		}

		self::store( (int) $post_id, Fields::item_types() );
	}

	/**
	 * Resets the purchases of one wish.
	 *
	 * @return void
	 */
	public static function reset_item(): void {
		$item_id = isset( $_GET['item'] ) ? absint( wp_unslash( $_GET['item'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified right below, the action names the item.

		if ( 0 >= $item_id || ! current_user_can( 'edit_post', $item_id ) ) {
			wp_die( esc_html__( 'You are not allowed to change this wish.', 'baukasten-wishlist' ), 403 );
		}

		check_admin_referer( self::ACTION_RESET . '_' . $item_id );

		$item = get_post( $item_id );

		if ( ! $item instanceof \WP_Post || Post_Types::ITEM_TYPE !== $item->post_type ) {
			wp_die( esc_html__( 'This wish does not exist.', 'baukasten-wishlist' ), 404 );
		}

		Claims::reset( $item_id );

		wp_safe_redirect( add_query_arg( self::NOTICE_ARG, '1', (string) get_edit_post_link( $item_id, 'raw' ) ) );
		exit;
	}

	/**
	 * Confirms a reset on the wish's screen.
	 *
	 * @return void
	 */
	public static function reset_notice(): void {
		if ( ! isset( $_GET[ self::NOTICE_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only shows a notice.
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || Post_Types::ITEM_TYPE !== $screen->post_type ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'The wish is marked as not bought again.', 'baukasten-wishlist' )
		);
	}

	/**
	 * Loads the stylesheet of the editing screens.
	 *
	 * @param mixed $hook_suffix Current admin screen.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ): void {
		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || ! in_array( $screen->post_type, array( Post_Types::LIST_TYPE, Post_Types::ITEM_TYPE ), true ) ) {
			return;
		}

		wp_enqueue_style( self::HANDLE, PLUGIN_URL . 'assets/css/admin.css', array(), Assets::version( 'assets/css/admin.css' ) );

		if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) && Post_Types::LIST_TYPE === $screen->post_type ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			wp_add_inline_script( 'wp-color-picker', 'jQuery( function ( $ ) { $( ".baukasten-wl-color" ).wpColorPicker(); } );' );
		}
	}

	/**
	 * Whether a save request may write fields.
	 *
	 * @param int    $post_id   Post ID.
	 * @param mixed  $post      Post object.
	 * @param string $post_type Expected post type.
	 * @return bool True when it may.
	 */
	private static function may_save( int $post_id, $post, string $post_type ): bool {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( ! $post instanceof \WP_Post || $post_type !== $post->post_type || wp_is_post_revision( $post_id ) ) {
			return false;
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Writes submitted fields.
	 *
	 * Only fields that were posted are written, so a box hidden through
	 * Screen Options keeps its values. Checkboxes post a hidden `0` before
	 * themselves, which is what tells "unticked" from "not on the screen".
	 *
	 * @param int                   $post_id Post ID.
	 * @param array<string, string> $types   Key to type.
	 * @return void
	 */
	private static function store( int $post_id, array $types ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in may_save().
		foreach ( $types as $key => $type ) {
			$meta_key = Fields::meta_key( $key );

			if ( ! isset( $_POST[ $meta_key ] ) ) {
				continue;
			}

			$value = Fields::sanitize( $type, wp_unslash( $_POST[ $meta_key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by type.

			if ( '' === $value ) {
				delete_post_meta( $post_id, $meta_key );

				continue;
			}

			update_post_meta( $post_id, $meta_key, $value );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * The values the boxes show.
	 *
	 * @param int                   $post_id  Post ID.
	 * @param array<string, string> $types    Key to type.
	 * @param array<string, string> $defaults Key to default.
	 * @return array<string, string> Key to value.
	 */
	private static function stored( int $post_id, array $types, array $defaults ): array {
		$values = array();

		foreach ( array_keys( $types ) as $key ) {
			$stored         = get_post_meta( $post_id, Fields::meta_key( $key ), true );
			$values[ $key ] = is_scalar( $stored ) && '' !== (string) $stored ? (string) $stored : $defaults[ $key ];
		}

		return $values;
	}
}
