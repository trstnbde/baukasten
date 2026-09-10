<?php
/**
 * The random card slug.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * Gives every card an eight character slug that says nothing about it.
 *
 * A card's title is an internal label — "Sales, Berlin" — and must not end up
 * in a URL that gets handed to strangers. The slug is assigned once, on the
 * first save, and never changes afterwards, so a printed QR code stays valid.
 *
 * The alphabet is lowercase base36, not base62, and that is not a shortcut.
 * `WP_Query::parse_query()` runs the requested name through
 * `sanitize_title_for_query()`, which lowercases unconditionally, so a stored
 * `Ab3Kf9Zq` would be looked up as `ab3kf9zq` and the card would 404 while the
 * admin list happily showed a permalink pointing at it.
 */
final class Slug {

	/**
	 * Number of characters in a slug.
	 */
	const LENGTH = 8;

	/**
	 * Characters a slug is built from.
	 */
	const ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

	/**
	 * Characters the first position is built from.
	 */
	const LETTERS = 'abcdefghijklmnopqrstuvwxyz';

	/**
	 * How often a colliding slug is redrawn before a suffix is added.
	 */
	const ATTEMPTS = 10;

	/**
	 * Registers the hooks the slug needs.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'assign' ), 10, 2 );
	}

	/**
	 * Replaces the title-derived slug with a random one, once.
	 *
	 * This filter is applied after `wp_unique_post_slug()` has already run, so
	 * whatever it returns is what reaches the database — nothing downstream can
	 * append a "-2". And because the returned `post_name` is never empty,
	 * `wp_insert_post()`'s title fallback for a newly published post never fires.
	 *
	 * @param mixed $data    Slashed and sanitised post data.
	 * @param mixed $postarr Sanitised but unmodified post data.
	 * @return array<string, mixed> Post data with a card slug.
	 */
	public static function assign( $data, $postarr ): array {
		$data = is_array( $data ) ? $data : array();

		if ( ! isset( $data['post_type'] ) || Post_Type::POST_TYPE !== $data['post_type'] ) {
			return $data;
		}

		// Trashing appends "__trashed" and untrashing puts the old slug back.
		// Both are core's business; overwriting the name breaks the round trip.
		if ( isset( $data['post_status'] ) && 'trash' === $data['post_status'] ) {
			return $data;
		}

		$postarr  = is_array( $postarr ) ? $postarr : array();
		$post_id  = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		$existing = 0 < $post_id ? (string) get_post_field( 'post_name', $post_id, 'raw' ) : '';

		if ( str_ends_with( $existing, '__trashed' ) ) {
			return $data;
		}

		if ( '' !== $existing ) {
			// Also what neutralises the editor's permalink field: whatever was
			// typed there is discarded in favour of the slug already stored.
			$data['post_name'] = $existing;

			return $data;
		}

		$data['post_name'] = self::generate();

		return $data;
	}

	/**
	 * A slug no other card holds.
	 *
	 * @return string Eight base36 characters, the first one a letter.
	 */
	private static function generate(): string {
		for ( $attempt = 0; $attempt < self::ATTEMPTS; $attempt++ ) {
			$slug = self::random();

			if ( ! self::taken( $slug ) ) {
				return $slug;
			}
		}

		// Unreachable in practice at 36^8. Still better than knowingly
		// returning a slug that is already in use.
		return self::random() . wp_generate_password( 4, false, false );
	}

	/**
	 * Draws a random slug.
	 *
	 * `random_int()` per character rather than `random_bytes()` and a modulo:
	 * the former rejects out-of-range draws internally, the latter is biased.
	 * The first character is a letter so no slug is all digits.
	 *
	 * @return string A candidate slug.
	 */
	private static function random(): string {
		$slug = self::LETTERS[ random_int( 0, strlen( self::LETTERS ) - 1 ) ];

		for ( $index = 1; $index < self::LENGTH; $index++ ) {
			$slug .= self::ALPHABET[ random_int( 0, strlen( self::ALPHABET ) - 1 ) ];
		}

		return $slug;
	}

	/**
	 * Whether a card already uses this slug.
	 *
	 * Scoped to the post type, the way `wp_unique_post_slug()` scopes its own
	 * check for non-hierarchical types: the route resolves to cards only, so a
	 * page that happens to be called `ab3kf9zq` is not a conflict.
	 *
	 * @param string $slug Candidate slug.
	 * @return bool True when it is taken.
	 */
	private static function taken( string $slug ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a uniqueness probe on a value that has never been seen before cannot be served from a cache.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s LIMIT 1",
				$slug,
				Post_Type::POST_TYPE
			)
		);

		return null !== $found;
	}
}
