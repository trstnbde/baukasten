<?php
/**
 * The two post types.
 *
 * @package Baukasten\Wishlist
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * A list is one occasion for one person; a wish is one thing on one list.
 *
 * Neither is public. A list has no address of its own: it is shown where a
 * block or shortcode puts it, so the page around it decides who sees it —
 * including its password and its Content Visibility.
 */
final class Post_Types {

	/**
	 * A list.
	 */
	const LIST_TYPE = 'baukasten_wishlist';

	/**
	 * A wish.
	 */
	const ITEM_TYPE = 'baukasten_wish_item';

	/**
	 * Image size of a card: wide enough for a card on a phone at twice the
	 * pixel density of its CSS width on a desktop, never cropped.
	 */
	const IMAGE_SIZE = 'baukasten-wishlist-card';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_post_types' ), 5 );
		add_action( 'after_setup_theme', array( __CLASS__, 'thumbnails' ), 20 );
		add_action( 'init', array( __CLASS__, 'image_size' ) );
		add_filter( 'baukasten/content_visibility/post_types', array( __CLASS__, 'leave_to_the_page' ) );
	}

	/**
	 * Registers both post types.
	 *
	 * `show_in_rest` stays off: the editing screens are classic meta boxes,
	 * and nothing on the front end reads the core routes.
	 *
	 * @return void
	 */
	public static function register_post_types(): void {
		register_post_type(
			self::LIST_TYPE,
			array(
				'labels'              => array(
					'name'                  => __( 'Wishlists', 'baukasten-wishlist' ),
					'singular_name'         => __( 'Wishlist', 'baukasten-wishlist' ),
					'menu_name'             => __( 'Wishlists', 'baukasten-wishlist' ),
					'all_items'             => __( 'All lists', 'baukasten-wishlist' ),
					'add_new'               => __( 'Add list', 'baukasten-wishlist' ),
					'add_new_item'          => __( 'Add list', 'baukasten-wishlist' ),
					'edit_item'             => __( 'Edit list', 'baukasten-wishlist' ),
					'new_item'              => __( 'New list', 'baukasten-wishlist' ),
					'search_items'          => __( 'Search lists', 'baukasten-wishlist' ),
					'not_found'             => __( 'No lists yet.', 'baukasten-wishlist' ),
					'not_found_in_trash'    => __( 'No lists in the trash.', 'baukasten-wishlist' ),
					'featured_image'        => __( 'Header image', 'baukasten-wishlist' ),
					'set_featured_image'    => __( 'Set header image', 'baukasten-wishlist' ),
					'remove_featured_image' => __( 'Remove header image', 'baukasten-wishlist' ),
					'use_featured_image'    => __( 'Use as header image', 'baukasten-wishlist' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'menu_position'       => 25,
				'menu_icon'           => 'dashicons-heart',
				'supports'            => array( 'title', 'thumbnail' ),
			)
		);

		register_post_type(
			self::ITEM_TYPE,
			array(
				'labels'              => array(
					'name'                  => __( 'Wishes', 'baukasten-wishlist' ),
					'singular_name'         => __( 'Wish', 'baukasten-wishlist' ),
					'menu_name'             => __( 'Wishes', 'baukasten-wishlist' ),
					'all_items'             => __( 'All wishes', 'baukasten-wishlist' ),
					'add_new'               => __( 'Add wish', 'baukasten-wishlist' ),
					'add_new_item'          => __( 'Add wish', 'baukasten-wishlist' ),
					'edit_item'             => __( 'Edit wish', 'baukasten-wishlist' ),
					'new_item'              => __( 'New wish', 'baukasten-wishlist' ),
					'search_items'          => __( 'Search wishes', 'baukasten-wishlist' ),
					'not_found'             => __( 'No wishes yet.', 'baukasten-wishlist' ),
					'not_found_in_trash'    => __( 'No wishes in the trash.', 'baukasten-wishlist' ),
					'featured_image'        => __( 'Product image', 'baukasten-wishlist' ),
					'set_featured_image'    => __( 'Set product image', 'baukasten-wishlist' ),
					'remove_featured_image' => __( 'Remove product image', 'baukasten-wishlist' ),
					'use_featured_image'    => __( 'Use as product image', 'baukasten-wishlist' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . self::LIST_TYPE,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'thumbnail', 'page-attributes' ),
			)
		);
	}

	/**
	 * Makes sure both post types can have a featured image.
	 *
	 * A theme that supports thumbnails for every type is left alone; one that
	 * names its types gets these two added.
	 *
	 * @return void
	 */
	public static function thumbnails(): void {
		if ( true === get_theme_support( 'post-thumbnails' ) ) {
			return;
		}

		add_theme_support( 'post-thumbnails', array( self::LIST_TYPE, self::ITEM_TYPE ) );
	}

	/**
	 * Registers the card image size.
	 *
	 * Existing images get it with `wp media regenerate --image_size=baukasten-wishlist-card`;
	 * until then the browser picks a larger size from the srcset.
	 *
	 * @return void
	 */
	public static function image_size(): void {
		add_image_size( self::IMAGE_SIZE, 480, 480, false );
	}

	/**
	 * Keeps both types out of Content Visibility.
	 *
	 * Lists and wishes have no address of their own; the page that shows them
	 * carries the visibility.
	 *
	 * @param mixed $types Post types Content Visibility manages.
	 * @return string[] Post types.
	 */
	public static function leave_to_the_page( $types ): array {
		$types = array_map( 'strval', (array) $types );

		return array_values( array_diff( $types, array( self::LIST_TYPE, self::ITEM_TYPE ) ) );
	}
}
