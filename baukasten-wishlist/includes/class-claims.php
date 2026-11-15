<?php
/**
 * Reported purchases.
 *
 * @package Baukasten\Wishlist
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * One reported purchase is one meta row on the wish.
 *
 * Its value is the hash of a random token that only the browser which made
 * the report holds, so that browser — and no other — can take it back. No
 * name, no address and no account is stored: the list only ever knows how
 * many were bought, never by whom.
 *
 * One row per purchase rather than a counter or an array: `add_post_meta()`
 * is one INSERT, so two guests reporting at the same moment cannot overwrite
 * each other's report the way two read-modify-write updates of one value can.
 */
final class Claims {

	/**
	 * Meta key of a purchase.
	 */
	const META = '_baukasten_wishlist_claim';

	/**
	 * Length of a token in bytes.
	 */
	const TOKEN_BYTES = 16;

	/**
	 * How many of a wish were reported bought.
	 *
	 * @param int $item_id Wish post ID.
	 * @return int Count.
	 */
	public static function count( int $item_id ): int {
		return count( (array) get_post_meta( $item_id, self::META, false ) );
	}

	/**
	 * How many are still wanted.
	 *
	 * @param int $item_id Wish post ID.
	 * @return int Remaining, never below zero.
	 */
	public static function remaining( int $item_id ): int {
		$quantity = (int) Fields::load_item( $item_id )['quantity'];

		return max( 0, $quantity - self::count( $item_id ) );
	}

	/**
	 * Reports one purchase.
	 *
	 * @param int $item_id Wish post ID.
	 * @return string|\WP_Error The token that takes it back, or why not.
	 */
	public static function claim( int $item_id ) {
		$quantity = (int) Fields::load_item( $item_id )['quantity'];

		if ( self::stored_count( $item_id ) >= $quantity ) {
			return self::sold_out();
		}

		$token = bin2hex( random_bytes( self::TOKEN_BYTES ) );
		$hash  = self::hash( $token );

		if ( false === add_post_meta( $item_id, self::META, $hash ) ) {
			return new \WP_Error( 'baukasten_wishlist_not_saved', __( 'The purchase could not be saved. Please try again.', 'baukasten-wishlist' ), array( 'status' => 500 ) );
		}

		// Two reports for the last one can both pass the check above. Counting
		// again after the insert, straight from the table, catches the second,
		// which takes its own row back out.
		if ( self::stored_count( $item_id ) > $quantity ) {
			delete_post_meta( $item_id, self::META, $hash );

			return self::sold_out();
		}

		return $token;
	}

	/**
	 * Takes back one purchase.
	 *
	 * @param int    $item_id Wish post ID.
	 * @param string $token   Token handed out when it was reported.
	 * @return bool Whether a purchase with that token existed and was removed.
	 */
	public static function unclaim( int $item_id, string $token ): bool {
		if ( 1 !== preg_match( '/^[a-f0-9]{' . ( 2 * self::TOKEN_BYTES ) . '}$/', $token ) ) {
			return false;
		}

		$hash = self::hash( $token );

		if ( ! in_array( $hash, array_map( 'strval', (array) get_post_meta( $item_id, self::META, false ) ), true ) ) {
			return false;
		}

		return delete_post_meta( $item_id, self::META, $hash );
	}

	/**
	 * Removes every purchase of a wish.
	 *
	 * @param int $item_id Wish post ID.
	 * @return void
	 */
	public static function reset( int $item_id ): void {
		delete_post_meta( $item_id, self::META );
	}

	/**
	 * Whether a list still takes reports.
	 *
	 * @param int $list_id List post ID.
	 * @return bool True while open.
	 */
	public static function is_open( int $list_id ): bool {
		$fields = Fields::load_list( $list_id );
		$open   = true;

		if ( '1' === $fields['close_after'] && '' !== $fields['date'] ) {
			$open = wp_date( 'Y-m-d' ) <= $fields['date'];
		}

		/**
		 * Filters whether a list still takes reported purchases.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $open    Whether guests can still report a purchase.
		 * @param int  $list_id List post ID.
		 */
		return (bool) apply_filters( 'baukasten/wishlist/can_claim', $open, $list_id );
	}

	/**
	 * The stored form of a token.
	 *
	 * @param string $token Token.
	 * @return string Hash.
	 */
	private static function hash( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'nonce' ) );
	}

	/**
	 * Counts purchases in the table itself, past every cache.
	 *
	 * @param int $item_id Wish post ID.
	 * @return int Count.
	 */
	private static function stored_count( int $item_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the point is to see the table, not the cache.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $item_id, self::META ) );
	}

	/**
	 * The error for a wish that is fully bought.
	 *
	 * @return \WP_Error Error with status 409.
	 */
	private static function sold_out(): \WP_Error {
		return new \WP_Error( 'baukasten_wishlist_sold_out', __( 'Somebody has already bought this.', 'baukasten-wishlist' ), array( 'status' => 409 ) );
	}
}
