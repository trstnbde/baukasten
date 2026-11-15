<?php
/**
 * Reading the wishes of a list.
 *
 * @package Baukasten\Wishlist
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * Queries and shapes wishes for the page, the REST routes and the admin.
 */
final class Items {

	/**
	 * Most wishes one list shows.
	 */
	const MAX_ITEMS = 300;

	/**
	 * Whether a list may be shown to the current visitor.
	 *
	 * Published, or a draft the current user may edit — so the editor preview
	 * works before the list goes live.
	 *
	 * @param int $list_id List post ID.
	 * @return bool True when it may be shown.
	 */
	public static function list_is_visible( int $list_id ): bool {
		$list = get_post( $list_id );

		if ( ! $list instanceof \WP_Post || Post_Types::LIST_TYPE !== $list->post_type ) {
			return false;
		}

		return 'publish' === $list->post_status || current_user_can( 'edit_post', $list_id );
	}

	/**
	 * Whether a list is published, which reports require.
	 *
	 * @param int $list_id List post ID.
	 * @return bool True when published.
	 */
	public static function list_is_published( int $list_id ): bool {
		$list = get_post( $list_id );

		return $list instanceof \WP_Post && Post_Types::LIST_TYPE === $list->post_type && 'publish' === $list->post_status;
	}

	/**
	 * The published wishes of a list, in the editor's order.
	 *
	 * @param int  $list_id     List post ID.
	 * @param bool $with_drafts Whether drafts are included (admin only).
	 * @return \WP_Post[] Wishes.
	 */
	public static function of_list( int $list_id, bool $with_drafts = false ): array {
		if ( 0 >= $list_id ) {
			return array();
		}

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one key, bounded by MAX_ITEMS.
		$posts = get_posts(
			array(
				'post_type'        => Post_Types::ITEM_TYPE,
				'post_status'      => $with_drafts ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : 'publish',
				'numberposts'      => self::MAX_ITEMS,
				'orderby'          => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'meta_query'       => array(
					array(
						'key'   => Fields::meta_key( 'list' ),
						'value' => (string) $list_id,
					),
				),
				'suppress_filters' => false,
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		$posts = array_values( array_filter( $posts, static fn( $post ) => $post instanceof \WP_Post ) );

		self::prime_images( $posts );

		return $posts;
	}

	/**
	 * Loads the attachments of all product images in one query.
	 *
	 * Without it, every card asks for its image's post and meta on its own.
	 *
	 * @param \WP_Post[] $posts Wishes.
	 * @return void
	 */
	private static function prime_images( array $posts ): void {
		$ids = array_filter( array_map( static fn( \WP_Post $post ) => (int) get_post_thumbnail_id( $post ), $posts ) );

		if ( array() !== $ids ) {
			_prime_post_caches( array_values( array_unique( $ids ) ), false, true );
		}
	}

	/**
	 * Pages and posts that show a list, through the block or the shortcode.
	 *
	 * @param int $list_id List post ID.
	 * @return \WP_Post[] Posts, newest first.
	 */
	public static function embedded_on( int $list_id ): array {
		static $cache = array();

		if ( isset( $cache[ $list_id ] ) ) {
			return $cache[ $list_id ];
		}

		global $wpdb;

		// A cheap pre-filter in SQL; the exact match is made below.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached per request; no API searches post content.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ('revision', 'attachment', %s, %s) AND post_status IN ('publish', 'draft', 'pending', 'private', 'future') AND ( post_content LIKE %s OR post_content LIKE %s ) ORDER BY post_date DESC LIMIT 50",
				Post_Types::LIST_TYPE,
				Post_Types::ITEM_TYPE,
				'%' . $wpdb->esc_like( 'wp:baukasten/wishlist' ) . '%',
				'%' . $wpdb->esc_like( '[' . Shortcode::TAG ) . '%'
			)
		);

		$found = array();

		foreach ( array_map( 'intval', (array) $ids ) as $id ) {
			$post = get_post( $id );

			if ( $post instanceof \WP_Post && in_array( $list_id, self::lists_in( $post->post_content ), true ) ) {
				$found[] = $post;
			}
		}

		$cache[ $list_id ] = $found;

		return $found;
	}

	/**
	 * The list IDs a piece of content shows.
	 *
	 * @param string $content Post content.
	 * @return int[] List IDs.
	 */
	public static function lists_in( string $content ): array {
		$ids = array();

		if ( has_blocks( $content ) ) {
			$walk = static function ( array $blocks ) use ( &$walk, &$ids ): void {
				foreach ( $blocks as $block ) {
					if ( Block::NAME === $block['blockName'] && ! empty( $block['attrs']['listId'] ) ) {
						$ids[] = (int) $block['attrs']['listId'];
					}

					if ( ! empty( $block['innerBlocks'] ) ) {
						$walk( $block['innerBlocks'] );
					}
				}
			};

			$walk( parse_blocks( $content ) );
		}

		if ( preg_match_all( '/' . get_shortcode_regex( array( Shortcode::TAG ) ) . '/', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$atts = shortcode_parse_atts( $match[3] );

				if ( is_array( $atts ) && isset( $atts['id'] ) ) {
					$ids[] = absint( $atts['id'] );
				}
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * One wish as the page and the routes need it.
	 *
	 * @param \WP_Post $item Wish.
	 * @return array<string, mixed> Wish data.
	 */
	public static function data( \WP_Post $item ): array {
		$fields   = Fields::load_item( $item->ID );
		$quantity = (int) $fields['quantity'];
		$bought   = Claims::count( $item->ID );

		$data = array(
			'id'        => $item->ID,
			'title'     => get_the_title( $item ),
			// Unfiltered, for sorting: typography plugins add soft hyphens to
			// the displayed title.
			'raw_title' => $item->post_title,
			'image'     => (int) get_post_thumbnail_id( $item ),
			'quantity'  => $quantity,
			'bought'    => min( $bought, $quantity ),
			'remaining' => max( 0, $quantity - $bought ),
			'price'     => $fields['price'],
			'priority'  => (int) $fields['priority'],
			'url'       => $fields['url'],
			'note'      => $fields['note'],
			'order'     => (int) $item->menu_order,
		);

		/**
		 * Filters one wish before it is shown.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $data Wish data.
		 * @param \WP_Post             $item Wish post.
		 */
		$filtered = apply_filters( 'baukasten/wishlist/item_data', $data, $item );

		return is_array( $filtered ) ? $filtered : $data;
	}

	/**
	 * The state of every wish of a list, for the page's refresh.
	 *
	 * @param int $list_id List post ID.
	 * @return array<string, mixed> Whether it is open, and per wish what is bought.
	 */
	public static function state( int $list_id ): array {
		$items = array();

		foreach ( self::of_list( $list_id ) as $item ) {
			$data = self::data( $item );

			$items[ (string) $item->ID ] = array(
				'quantity'  => (int) $data['quantity'],
				'bought'    => (int) $data['bought'],
				'remaining' => (int) $data['remaining'],
			);
		}

		return array(
			'open'  => Claims::is_open( $list_id ),
			'items' => (object) $items,
		);
	}

	/**
	 * Every list, for selects.
	 *
	 * @return array<int, string> List ID to title.
	 */
	public static function lists(): array {
		$posts = get_posts(
			array(
				'post_type'        => Post_Types::LIST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts'      => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- a select of lists; there are a handful.
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		$lists = array();

		foreach ( $posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$title              = get_the_title( $post );
				$lists[ $post->ID ] = '' === $title ? sprintf( '#%d', $post->ID ) : $title;
			}
		}

		return $lists;
	}
}
