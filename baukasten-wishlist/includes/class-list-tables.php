<?php
/**
 * The admin lists of lists and wishes.
 *
 * @package Baukasten\Wishlist
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * Extra columns, and a filter of wishes by list.
 */
final class List_Tables {

	/**
	 * Query argument of the list filter.
	 */
	const FILTER_ARG = 'baukasten_wishlist_list';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'manage_' . Post_Types::LIST_TYPE . '_posts_columns', array( __CLASS__, 'list_columns' ) );
		add_action( 'manage_' . Post_Types::LIST_TYPE . '_posts_custom_column', array( __CLASS__, 'list_column' ), 10, 2 );
		add_filter( 'manage_' . Post_Types::ITEM_TYPE . '_posts_columns', array( __CLASS__, 'item_columns' ) );
		add_action( 'manage_' . Post_Types::ITEM_TYPE . '_posts_custom_column', array( __CLASS__, 'item_column' ), 10, 2 );
		add_filter( 'manage_edit-' . Post_Types::ITEM_TYPE . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'list_filter' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_list_filter' ) );
		add_action( 'quick_edit_custom_box', array( __CLASS__, 'quick_edit_box' ), 10, 2 );
	}

	/**
	 * Lets priority and price be sorted.
	 *
	 * @param mixed $columns Sortable columns.
	 * @return array<string, mixed> Sortable columns.
	 */
	public static function sortable_columns( $columns ): array {
		$columns = (array) $columns;

		$columns['baukasten_wl_priority'] = 'baukasten_wl_priority';
		$columns['baukasten_wl_price']    = 'baukasten_wl_price';

		return $columns;
	}

	/**
	 * Prints the wish's fields in Quick Edit.
	 *
	 * Printed once, for the list column; the script fills it from the row.
	 * The fields carry the same names and nonce as the editing screen, so
	 * Meta_Boxes::save_item() stores them, and only what is posted.
	 *
	 * @param mixed $column    Column key.
	 * @param mixed $post_type Post type of the screen.
	 * @return void
	 */
	public static function quick_edit_box( $column, $post_type ): void {
		if ( Post_Types::ITEM_TYPE !== $post_type || 'baukasten_wl_list' !== $column ) {
			return;
		}

		$baukasten_wl_lists = Items::lists();
		$baukasten_wl_prios = Fields::priorities();

		require PLUGIN_DIR . 'admin/views/quick-edit.php';
	}

	/**
	 * Columns of the lists.
	 *
	 * @param mixed $columns Existing columns.
	 * @return array<string, string> Columns.
	 */
	public static function list_columns( $columns ): array {
		return self::insert_after_title(
			$columns,
			array(
				'baukasten_wl_occasion'  => __( 'Occasion', 'baukasten-wishlist' ),
				'baukasten_wl_wishes'    => __( 'Wishes', 'baukasten-wishlist' ),
				'baukasten_wl_pages'     => __( 'Shown on', 'baukasten-wishlist' ),
				'baukasten_wl_shortcode' => __( 'Shortcode', 'baukasten-wishlist' ),
			)
		);
	}

	/**
	 * Prints a column of the lists.
	 *
	 * @param mixed $column  Column key.
	 * @param mixed $post_id List ID.
	 * @return void
	 */
	public static function list_column( $column, $post_id ): void {
		$post_id = (int) $post_id;

		switch ( $column ) {
			case 'baukasten_wl_occasion':
				$fields = Fields::load_list( $post_id );
				$parts  = array_filter( array( $fields['person'], $fields['occasion'] ) );

				if ( '' !== $fields['date'] ) {
					$day = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $fields['date'] . ' 12:00', wp_timezone() );

					if ( false !== $day ) {
						$parts[] = (string) wp_date( (string) get_option( 'date_format' ), $day->getTimestamp() );
					}
				}

				echo esc_html( implode( ' · ', $parts ) );
				break;

			case 'baukasten_wl_wishes':
				$items  = Items::of_list( $post_id, true );
				$total  = 0;
				$bought = 0;

				foreach ( $items as $item ) {
					$data    = Items::data( $item );
					$total  += (int) $data['quantity'];
					$bought += (int) $data['bought'];
				}

				printf(
					'<a href="%1$s">%2$s</a><br /><span class="description">%3$s</span>',
					esc_url( add_query_arg( self::FILTER_ARG, $post_id, admin_url( 'edit.php?post_type=' . Post_Types::ITEM_TYPE ) ) ),
					esc_html(
						sprintf(
							/* translators: %s: number of wishes. */
							_n( '%s wish', '%s wishes', count( $items ), 'baukasten-wishlist' ),
							number_format_i18n( count( $items ) )
						)
					),
					esc_html(
						sprintf(
							/* translators: 1: things bought. 2: things wished for. */
							__( '%1$s of %2$s bought', 'baukasten-wishlist' ),
							number_format_i18n( $bought ),
							number_format_i18n( $total )
						)
					)
				);
				break;

			case 'baukasten_wl_pages':
				$pages = Items::embedded_on( $post_id );

				if ( array() === $pages ) {
					echo '<span class="description">' . esc_html__( 'Not shown yet', 'baukasten-wishlist' ) . '</span>';
					break;
				}

				echo '<ul class="baukasten-wl-admin-pages">';

				foreach ( $pages as $page ) {
					Meta_Boxes::print_page_link( $page );
				}

				echo '</ul>';
				break;

			case 'baukasten_wl_shortcode':
				printf( '<code>%s</code>', esc_html( Shortcode::for_list( $post_id ) ) );
				break;
		}
	}

	/**
	 * Columns of the wishes.
	 *
	 * @param mixed $columns Existing columns.
	 * @return array<string, string> Columns.
	 */
	public static function item_columns( $columns ): array {
		$columns = array_map( 'strval', (array) $columns );
		$out     = array();

		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$out['baukasten_wl_image'] = '<span class="screen-reader-text">' . esc_html__( 'Image', 'baukasten-wishlist' ) . '</span>';
			}

			$out[ $key ] = $label;

			if ( 'title' === $key ) {
				$out['baukasten_wl_list']     = __( 'List', 'baukasten-wishlist' );
				$out['baukasten_wl_priority'] = __( 'Priority', 'baukasten-wishlist' );
				$out['baukasten_wl_price']    = __( 'Price', 'baukasten-wishlist' );
				$out['baukasten_wl_bought']   = __( 'Bought', 'baukasten-wishlist' );
			}
		}

		return $out;
	}

	/**
	 * Prints a column of the wishes.
	 *
	 * @param mixed $column  Column key.
	 * @param mixed $post_id Wish ID.
	 * @return void
	 */
	public static function item_column( $column, $post_id ): void {
		$post_id = (int) $post_id;

		if ( 0 !== strpos( (string) $column, 'baukasten_wl_' ) ) {
			return;
		}

		$fields = Fields::load_item( $post_id );

		switch ( $column ) {
			case 'baukasten_wl_image':
				echo get_the_post_thumbnail( $post_id, array( 48, 48 ) );
				break;

			case 'baukasten_wl_list':
				$list_id = (int) $fields['list'];

				if ( 0 < $list_id ) {
					printf(
						'<a href="%1$s">%2$s</a>',
						esc_url( add_query_arg( self::FILTER_ARG, $list_id ) ),
						esc_html( get_the_title( $list_id ) )
					);
				} else {
					echo '<span aria-hidden="true">—</span>';
				}

				// What Quick Edit starts from.
				printf(
					'<div class="hidden baukasten-wl-inline" data-list="%1$s" data-quantity="%2$s" data-price="%3$s" data-priority="%4$s"></div>',
					esc_attr( (string) $list_id ),
					esc_attr( $fields['quantity'] ),
					esc_attr( '' === $fields['price'] ? '' : number_format_i18n( (float) $fields['price'], 2 ) ),
					esc_attr( $fields['priority'] )
				);
				break;

			case 'baukasten_wl_priority':
				echo esc_html( Fields::priorities()[ (int) $fields['priority'] ] ?? '' );
				break;

			case 'baukasten_wl_price':
				echo esc_html( Settings::format_price( $fields['price'] ) );
				break;

			case 'baukasten_wl_bought':
				$quantity = (int) $fields['quantity'];
				$bought   = min( Claims::count( $post_id ), $quantity );

				if ( $bought >= $quantity ) {
					echo '<strong class="baukasten-wl-admin-done">' . esc_html__( 'Already bought', 'baukasten-wishlist' ) . '</strong><br />';
				}

				printf(
					/* translators: 1: number bought. 2: number wished for. */
					esc_html__( '%1$s of %2$s', 'baukasten-wishlist' ),
					esc_html( number_format_i18n( $bought ) ),
					esc_html( number_format_i18n( $quantity ) )
				);
				break;
		}
	}

	/**
	 * Prints the list filter above the wishes.
	 *
	 * @param mixed $post_type Post type of the screen.
	 * @return void
	 */
	public static function list_filter( $post_type ): void {
		if ( Post_Types::ITEM_TYPE !== $post_type ) {
			return;
		}

		$current = isset( $_GET[ self::FILTER_ARG ] ) ? absint( wp_unslash( $_GET[ self::FILTER_ARG ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a list filter.

		printf( '<label class="screen-reader-text" for="baukasten-wl-filter">%s</label>', esc_html__( 'Filter by list', 'baukasten-wishlist' ) );
		printf( '<select name="%1$s" id="baukasten-wl-filter"><option value="0">%2$s</option>', esc_attr( self::FILTER_ARG ), esc_html__( 'All lists', 'baukasten-wishlist' ) );

		foreach ( Items::lists() as $id => $title ) {
			printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $id, selected( $current, $id, false ), esc_html( $title ) );
		}

		echo '</select>';
	}

	/**
	 * Applies the list filter.
	 *
	 * @param mixed $query The query.
	 * @return void
	 */
	public static function apply_list_filter( $query ): void {
		if ( ! $query instanceof \WP_Query || ! is_admin() || ! $query->is_main_query() || Post_Types::ITEM_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$list_id = isset( $_GET[ self::FILTER_ARG ] ) ? absint( wp_unslash( $_GET[ self::FILTER_ARG ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a list filter.
		$orderby = (string) $query->get( 'orderby' );
		$meta    = array();

		if ( 0 < $list_id ) {
			$meta[] = array(
				'key'   => Fields::meta_key( 'list' ),
				'value' => (string) $list_id,
			);
		}

		$sort_keys = array(
			'baukasten_wl_priority' => 'priority',
			'baukasten_wl_price'    => 'price',
		);

		if ( isset( $sort_keys[ $orderby ] ) ) {
			// A named clause that also matches wishes without the field, so
			// sorting by price does not hide the wishes that have none.
			$meta['baukasten_wl_sort'] = array(
				'relation'               => 'OR',
				'baukasten_wl_sort_has'  => array(
					'key'     => Fields::meta_key( $sort_keys[ $orderby ] ),
					'compare' => 'EXISTS',
					'type'    => 'DECIMAL(10,2)',
				),
				'baukasten_wl_sort_none' => array(
					'key'     => Fields::meta_key( $sort_keys[ $orderby ] ),
					'compare' => 'NOT EXISTS',
				),
			);

			$query->set(
				'orderby',
				array(
					'baukasten_wl_sort_has' => 'asc' === strtolower( (string) $query->get( 'order' ) ) ? 'ASC' : 'DESC',
					'title'                 => 'ASC',
				)
			);
		}

		if ( array() !== $meta ) {
			$query->set( 'meta_query', $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the admin list, paged.
		}
	}

	/**
	 * Puts columns right after the title.
	 *
	 * @param mixed                 $columns Existing columns.
	 * @param array<string, string> $extra   Columns to add.
	 * @return array<string, string> Columns.
	 */
	private static function insert_after_title( $columns, array $extra ): array {
		$columns = array_map( 'strval', (array) $columns );
		$out     = array();

		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;

			if ( 'title' === $key ) {
				$out = array_merge( $out, $extra );
			}
		}

		return $out;
	}
}
