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

		return array_values( array_filter( $posts, static fn( $post ) => $post instanceof \WP_Post ) );
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
