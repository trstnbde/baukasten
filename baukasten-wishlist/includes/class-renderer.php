<?php
/**
 * Prints a list.
 *
 * @package Baukasten\Wishlist
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a list into markup, for the block and the shortcode alike.
 *
 * The server prints every wish in the chosen order with its current state, so
 * the list reads correctly without the script. The script adds sorting,
 * filtering and the buttons that report a purchase, and refreshes the state,
 * because a page cache may have kept an old one.
 */
final class Renderer {

	/**
	 * Sort orders the page offers.
	 *
	 * @return array<string, string> Key to label.
	 */
	public static function sort_options(): array {
		$options = array(
			'priority'   => __( 'Most wanted first', 'baukasten-wishlist' ),
			'open'       => __( 'Still wanted first', 'baukasten-wishlist' ),
			'price-asc'  => __( 'Price, lowest first', 'baukasten-wishlist' ),
			'price-desc' => __( 'Price, highest first', 'baukasten-wishlist' ),
			'title'      => __( 'Name', 'baukasten-wishlist' ),
			'order'      => __( 'As arranged', 'baukasten-wishlist' ),
		);

		/**
		 * Filters the labels of the sort orders.
		 *
		 * Only labels can change and orders be removed; the script knows how
		 * to sort by exactly these keys.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $options Key to label.
		 */
		$filtered = apply_filters( 'baukasten/wishlist/sort_options', $options );
		$filtered = is_array( $filtered ) ? array_intersect_key( array_map( 'strval', $filtered ), $options ) : $options;

		return array() === $filtered ? $options : $filtered;
	}

	/**
	 * The status filter a list starts with when nothing else is chosen.
	 *
	 * "Still wanted": once a guest reports a purchase, the wish leaves the
	 * list for everybody, which is what keeps it from being bought twice.
	 */
	const DEFAULT_STATUS = 'open';

	/**
	 * The sort orders that change something on this list.
	 *
	 * "As arranged" only when the wishes have different positions, and the
	 * price orders only when prices are shown. The order the list starts with
	 * is always offered, so the select can show it.
	 *
	 * @param array<int, array<string, mixed>> $items       Wish data.
	 * @param string                           $current     Sort the list starts with.
	 * @param bool                             $show_prices Whether prices are shown.
	 * @return array<string, string> Key to label.
	 */
	public static function visible_sorts( array $items, string $current, bool $show_prices ): array {
		$options = self::sort_options();
		$orders  = count( array_unique( array_column( $items, 'order' ) ) );

		foreach ( array_keys( $options ) as $key ) {
			$useless = ( 'order' === $key && $orders < 2 ) || ( ! $show_prices && str_starts_with( $key, 'price' ) );

			if ( $useless && $key !== $current ) {
				unset( $options[ $key ] );
			}
		}

		return $options;
	}

	/**
	 * Status filters the page offers.
	 *
	 * @return array<string, string> Key to label.
	 */
	public static function status_options(): array {
		return array(
			'open' => __( 'Still wanted', 'baukasten-wishlist' ),
			'all'  => __( 'All wishes', 'baukasten-wishlist' ),
			'done' => __( 'Already bought', 'baukasten-wishlist' ),
		);
	}

	/**
	 * Cleans the display arguments.
	 *
	 * @param array<string, mixed> $args Raw arguments.
	 * @return array{list: int, sort: string, status: string, controls: bool} Clean arguments.
	 */
	public static function normalise( array $args ): array {
		$sort   = isset( $args['sort'] ) ? sanitize_key( (string) $args['sort'] ) : 'priority';
		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : self::DEFAULT_STATUS;

		$controls = $args['controls'] ?? true;

		if ( is_string( $controls ) ) {
			$controls = ! in_array( strtolower( trim( $controls ) ), array( '0', 'no', 'false', 'off', 'nein' ), true );
		}

		return array(
			'list'     => isset( $args['list'] ) ? absint( $args['list'] ) : 0,
			'sort'     => array_key_exists( $sort, self::sort_options() ) ? $sort : 'priority',
			'status'   => array_key_exists( $status, self::status_options() ) ? $status : self::DEFAULT_STATUS,
			'controls' => (bool) $controls,
		);
	}

	/**
	 * The markup of one list.
	 *
	 * @param array<string, mixed> $args List ID and display options.
	 * @return string Markup, or an empty string when there is nothing to show.
	 */
	public static function render( array $args ): string {
		$args    = self::normalise( $args );
		$list_id = $args['list'];

		if ( 0 >= $list_id ) {
			return self::notice( __( 'Choose a wishlist to show.', 'baukasten-wishlist' ) );
		}

		if ( ! Items::list_is_visible( $list_id ) ) {
			return self::notice( __( 'This wishlist does not exist or is not published.', 'baukasten-wishlist' ) );
		}

		// The content of a locked post is never rendered, but a shortcode can
		// be run elsewhere — a widget, a template part. The surrounding post's
		// password still applies.
		$host = get_post();

		if ( $host instanceof \WP_Post && Post_Types::LIST_TYPE !== $host->post_type && post_password_required( $host ) ) {
			return '';
		}

		$list   = get_post( $list_id );
		$fields = Fields::load_list( $list_id );
		$items  = array_map( array( Items::class, 'data' ), Items::of_list( $list_id ) );
		$items  = self::sorted( $items, $args['sort'], $args['status'] );
		$open   = Claims::is_open( $list_id );

		Assets::enqueue();

		$baukasten_wl_list       = $list;
		$baukasten_wl_fields     = $fields;
		$baukasten_wl_items      = $items;
		$baukasten_wl_args       = $args;
		$baukasten_wl_open       = $open;
		$baukasten_wl_sorts      = self::visible_sorts( $items, $args['sort'], '1' === $fields['show_prices'] );
		$baukasten_wl_statuses   = self::status_options();
		$baukasten_wl_prios      = Fields::priorities();
		$baukasten_wl_prio_count = count( array_unique( array_column( $items, 'priority' ) ) );
		$baukasten_wl_prices     = '1' === $fields['show_prices'] ? self::price_steps( $items ) : array();
		$baukasten_wl_days       = self::days_until( $fields['date'] );

		ob_start();
		require PLUGIN_DIR . 'templates/list.php';

		return (string) ob_get_clean();
	}

	/**
	 * Sorts wishes the way the script would.
	 *
	 * Showing every wish, the bought ones go after the ones still wanted in
	 * every order; within each group the chosen order applies.
	 *
	 * @param array<int, array<string, mixed>> $items  Wish data.
	 * @param string                           $sort   Sort key.
	 * @param string                           $status Status filter.
	 * @return array<int, array<string, mixed>> Sorted wish data.
	 */
	public static function sorted( array $items, string $sort, string $status = self::DEFAULT_STATUS ): array {
		usort(
			$items,
			static function ( array $a, array $b ) use ( $sort, $status ): int {
				$price_a = '' === $a['price'] ? null : (float) $a['price'];
				$price_b = '' === $b['price'] ? null : (float) $b['price'];

				if ( 'all' === $status ) {
					$group = ( 0 >= $a['remaining'] ) <=> ( 0 >= $b['remaining'] );

					if ( 0 !== $group ) {
						return $group;
					}
				}

				switch ( $sort ) {
					case 'priority':
						$result = $a['priority'] <=> $b['priority'];
						break;
					case 'open':
						$result = ( 0 === $a['remaining'] ) <=> ( 0 === $b['remaining'] );
						$result = 0 === $result ? $a['priority'] <=> $b['priority'] : $result;
						break;
					case 'price-asc':
					case 'price-desc':
						// Wishes without a price go last either way.
						if ( null === $price_a || null === $price_b ) {
							$result = ( null === $price_a ) <=> ( null === $price_b );
						} else {
							$result = 'price-asc' === $sort ? $price_a <=> $price_b : $price_b <=> $price_a;
						}
						break;
					case 'title':
						$result = strcasecmp( remove_accents( (string) ( $a['raw_title'] ?? $a['title'] ) ), remove_accents( (string) ( $b['raw_title'] ?? $b['title'] ) ) );
						break;
					default:
						$result = 0;
				}

				if ( 0 !== $result ) {
					return $result;
				}

				$result = $a['order'] <=> $b['order'];

				return 0 !== $result ? $result : strcasecmp( (string) $a['title'], (string) $b['title'] );
			}
		);

		return $items;
	}

	/**
	 * Upper bounds for the price filter that actually divide this list.
	 *
	 * @param array<int, array<string, mixed>> $items Wish data.
	 * @return int[] Bounds, ascending; empty when there is nothing to divide.
	 */
	private static function price_steps( array $items ): array {
		$prices = array();

		foreach ( $items as $item ) {
			if ( '' !== $item['price'] ) {
				$prices[] = (float) $item['price'];
			}
		}

		if ( count( $prices ) < 2 ) {
			return array();
		}

		$steps = array();

		foreach ( array( 10, 20, 30, 50, 100, 200, 500 ) as $step ) {
			if ( $step > min( $prices ) && $step < max( $prices ) ) {
				$steps[] = $step;
			}
		}

		return $steps;
	}

	/**
	 * Days until the list's date, in the site's time zone.
	 *
	 * @param string $date Date as `Y-m-d`, or empty.
	 * @return int|null Days, negative once past; null without a date.
	 */
	private static function days_until( string $date ): ?int {
		if ( '' === $date ) {
			return null;
		}

		$zone  = wp_timezone();
		$today = new \DateTimeImmutable( 'today', $zone );
		$day   = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $zone );

		if ( false === $day ) {
			return null;
		}

		return (int) $today->diff( $day )->format( '%r%a' );
	}

	/**
	 * A note for editors where a list cannot be shown.
	 *
	 * Visitors see nothing at all; only somebody who can fix it gets a reason.
	 *
	 * @param string $message Reason.
	 * @return string Markup.
	 */
	private static function notice( string $message ): string {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}

		return '<p class="baukasten-wl-notice">' . esc_html( $message ) . '</p>';
	}
}
