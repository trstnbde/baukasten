<?php
/**
 * The card post type and its route.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `baukasten_card` and keeps its rewrite rules in step with the base.
 *
 * The route is the post type's own permastruct rather than an `add_rewrite_rule()`
 * of our own. That is not a style preference: `get_permalink()` reads
 * `WP_Rewrite::get_extra_permastruct()`, and only `WP_Post_Type::add_rewrite_rules()`
 * ever fills that in. A hand-written rule would serve the pretty URL and then
 * have `redirect_canonical()` bounce it straight back to `?baukasten_card=…`,
 * because that is what `get_permalink()` would still be returning.
 */
final class Post_Type {

	/**
	 * Post type key.
	 */
	const POST_TYPE = 'baukasten_card';

	/**
	 * Option recording which base and version the stored rewrite rules were built for.
	 */
	const OPTION_STAMP = 'baukasten_business_cards_rewrite_stamp';

	/**
	 * Registers the hooks the post type needs.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 5 );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );

		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'disable_block_editor' ), 10, 2 );
		add_filter( 'baukasten/content_visibility/post_types', array( __CLASS__, 'opt_out_of_content_visibility' ) );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'hide_from_sitemap' ) );
	}

	/**
	 * Registers the post type.
	 *
	 * Called from `init` and again, directly, on activation — at which point
	 * `init` has long since passed and nothing else would have registered it.
	 *
	 * @return void
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => self::labels(),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => false,
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_icon'          => 'dashicons-id-alt',
				'menu_position'      => 25,
				'query_var'          => self::POST_TYPE,
				'capability_type'    => 'post',
				'supports'           => array( 'title' ),
				'rewrite'            => array(
					'slug'       => Settings::base(),
					'with_front' => false,
					'feeds'      => false,
					'pages'      => false,
					// A card has no comments, no feed, no pagination and
					// nothing worth embedding, and every endpoint left on is a
					// rewrite rule matched against every request on the site.
					'ep_mask'    => EP_NONE,
				),
			)
		);
	}

	/**
	 * Flushes the rewrite rules once, after the base or the plugin version changed.
	 *
	 * The Permalinks screen flushes on its own, but the base can also be changed
	 * by WP-CLI or a migration, and then nothing would. A stamp is cheaper than
	 * guessing: one autoloaded option compared against the current base.
	 *
	 * The flush is soft. This can run on a front end request, and a hard flush
	 * would rewrite .htaccess there.
	 *
	 * @return void
	 */
	public static function maybe_flush(): void {
		$stamp = Settings::base() . '|' . VERSION;

		if ( (string) get_option( self::OPTION_STAMP, '' ) === $stamp ) {
			return;
		}

		flush_rewrite_rules( false );

		update_option( self::OPTION_STAMP, $stamp, true );
	}

	/**
	 * Keeps both editors off the card screen.
	 *
	 * The card has no content field to edit; everything lives in meta boxes,
	 * and the block editor would render an empty canvas above them.
	 *
	 * @param bool   $use_block_editor Whether to use the block editor.
	 * @param string $post_type        Post type being edited.
	 * @return bool False for cards, untouched otherwise.
	 */
	public static function disable_block_editor( $use_block_editor, $post_type ): bool {
		return self::POST_TYPE === $post_type ? false : (bool) $use_block_editor;
	}

	/**
	 * Takes cards out of the Content Visibility addon's reach.
	 *
	 * That addon defaults every new post of a supported type to "private", which
	 * for a business card is precisely backwards: the whole point is a link you
	 * can hand to someone who is not logged in. A site that wants cards behind a
	 * login can say so through the filter below.
	 *
	 * @param mixed $post_types Post types Content Visibility manages.
	 * @return string[] The list without cards.
	 */
	public static function opt_out_of_content_visibility( $post_types ): array {
		$post_types = array_map( 'strval', (array) $post_types );

		/**
		 * Filters whether the Content Visibility addon may manage cards.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $respect True to let Content Visibility treat cards like any other post type.
		 */
		if ( (bool) apply_filters( 'baukasten/business_cards/respect_content_visibility', false ) ) {
			return $post_types;
		}

		return array_values( array_diff( $post_types, array( self::POST_TYPE ) ) );
	}

	/**
	 * Keeps cards out of the sitemap.
	 *
	 * A card's address is eight random characters so that it cannot be found by
	 * guessing. Listing every one of them in `wp-sitemap.xml` would undo that
	 * on the next crawl.
	 *
	 * @param mixed $post_types Post types included in the sitemap, keyed by name.
	 * @return array<string, mixed> The list without cards.
	 */
	public static function hide_from_sitemap( $post_types ): array {
		$post_types = (array) $post_types;

		if ( ! Renderer::discourage_indexing() ) {
			return $post_types;
		}

		unset( $post_types[ self::POST_TYPE ] );

		return $post_types;
	}

	/**
	 * The post type labels.
	 *
	 * @return array<string, string> Labels.
	 */
	private static function labels(): array {
		return array(
			'name'                  => _x( 'Business Cards', 'post type general name', 'baukasten-business-cards' ),
			'singular_name'         => _x( 'Business Card', 'post type singular name', 'baukasten-business-cards' ),
			'menu_name'             => _x( 'Business Cards', 'admin menu', 'baukasten-business-cards' ),
			'add_new'               => __( 'Add Card', 'baukasten-business-cards' ),
			'add_new_item'          => __( 'Add New Card', 'baukasten-business-cards' ),
			'edit_item'             => __( 'Edit Card', 'baukasten-business-cards' ),
			'new_item'              => __( 'New Card', 'baukasten-business-cards' ),
			'view_item'             => __( 'View Card', 'baukasten-business-cards' ),
			'view_items'            => __( 'View Cards', 'baukasten-business-cards' ),
			'search_items'          => __( 'Search Cards', 'baukasten-business-cards' ),
			'not_found'             => __( 'No cards yet.', 'baukasten-business-cards' ),
			'not_found_in_trash'    => __( 'No cards in the trash.', 'baukasten-business-cards' ),
			'all_items'             => __( 'All Cards', 'baukasten-business-cards' ),
			'archives'              => __( 'Card Archives', 'baukasten-business-cards' ),
			'insert_into_item'      => __( 'Insert into card', 'baukasten-business-cards' ),
			'uploaded_to_this_item' => __( 'Uploaded to this card', 'baukasten-business-cards' ),
			'item_published'        => __( 'Card published.', 'baukasten-business-cards' ),
			'item_updated'          => __( 'Card updated.', 'baukasten-business-cards' ),
			'item_link'             => _x( 'Card Link', 'navigation link block title', 'baukasten-business-cards' ),
			'item_link_description' => _x( 'A link to a card.', 'navigation link block description', 'baukasten-business-cards' ),
		);
	}
}
