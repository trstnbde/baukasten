<?php
/**
 * The one setting this plugin has.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * The URL segment cards are served from.
 *
 * It is one option, but validating it is the fiddly part: the segment sits at
 * the root of the site and competes with pages, other post types, taxonomies
 * and a dozen rewrite bases WordPress reserves for itself. There is no core
 * helper that answers "is this segment free" — `wp_is_reserved_term()` does not
 * exist — so the list is assembled here, and `sanitize_base()` is honest about
 * being thorough rather than exhaustive.
 */
final class Settings {

	/**
	 * Option holding the base segment.
	 */
	const OPTION_BASE = 'baukasten_business_cards_base';

	/**
	 * Base used until someone changes it.
	 */
	const DEFAULT_BASE = 'b';

	/**
	 * The base segment cards are served from.
	 *
	 * @return string A single path segment, never empty.
	 */
	public static function base(): string {
		$stored = get_option( self::OPTION_BASE, self::DEFAULT_BASE );
		$base   = is_string( $stored ) ? trim( $stored, '/' ) : '';

		if ( '' === $base ) {
			return self::DEFAULT_BASE;
		}

		/**
		 * Filters the base segment cards are served from.
		 *
		 * @since 1.0.0
		 *
		 * @param string $base One path segment, without slashes.
		 */
		$base = (string) apply_filters( 'baukasten/business_cards/base', $base );

		return '' === $base ? self::DEFAULT_BASE : $base;
	}

	/**
	 * Whether the site serves pretty permalinks.
	 *
	 * With a plain structure no permastruct is registered at all, and cards are
	 * reachable through the post type's query var instead. The base is then
	 * meaningless, so the field that sets it is disabled.
	 *
	 * @return bool True when a permalink structure is set.
	 */
	public static function pretty_permalinks(): bool {
		return '' !== (string) get_option( 'permalink_structure', '' );
	}

	/**
	 * Reduces a submitted base to a usable segment, or rejects it.
	 *
	 * @param string $raw What the user typed.
	 * @return string The accepted segment, or an empty string when it is taken.
	 */
	public static function sanitize_base( string $raw ): string {
		$trimmed = trim( $raw, "/ \t\n\r\0\x0B" );

		// Checked before `sanitize_title()`, which turns a second segment into
		// a dash and would quietly accept a base nobody asked for.
		if ( str_contains( $trimmed, '/' ) ) {
			return '';
		}

		$base = sanitize_title( $trimmed );

		if ( '' === $base ) {
			return '';
		}

		if ( in_array( $base, self::reserved(), true ) ) {
			return '';
		}

		if ( self::claimed_by_a_type( $base ) ) {
			return '';
		}

		if ( null !== get_page_by_path( $base, OBJECT, array( 'page', 'post' ) ) ) {
			return '';
		}

		return $base;
	}

	/**
	 * Segments WordPress keeps for itself.
	 *
	 * Assembled by hand: core exposes the individual pieces but no list, and
	 * the "reserved terms" table in the handbook is documentation, not code.
	 *
	 * @return string[] Lowercase segments.
	 */
	private static function reserved(): array {
		global $wp, $wp_rewrite;

		$reserved = array(
			'wp-admin',
			'wp-content',
			'wp-includes',
			'wp-json',
			'embed',
			'trackback',
			'comments',
			'index',
		);

		if ( $wp instanceof \WP ) {
			$reserved = array_merge( $reserved, (array) $wp->public_query_vars );
		}

		if ( $wp_rewrite instanceof \WP_Rewrite ) {
			$reserved = array_merge(
				$reserved,
				(array) $wp_rewrite->feeds,
				array(
					$wp_rewrite->pagination_base,
					$wp_rewrite->author_base,
					$wp_rewrite->search_base,
					$wp_rewrite->comments_base,
				)
			);
		}

		// The options are empty strings until someone changes them, and an
		// empty category base means WordPress uses `category`, not nothing.
		$category   = trim( (string) get_option( 'category_base', '' ), '/' );
		$tag        = trim( (string) get_option( 'tag_base', '' ), '/' );
		$reserved[] = '' !== $category ? $category : 'category';
		$reserved[] = '' !== $tag ? $tag : 'tag';
		$reserved[] = rest_get_url_prefix();

		return array_values( array_filter( array_map( 'strval', $reserved ) ) );
	}

	/**
	 * Whether another post type or taxonomy already answers to this segment.
	 *
	 * @param string $base Candidate segment.
	 * @return bool True when it is taken.
	 */
	private static function claimed_by_a_type( string $base ): bool {
		foreach ( get_post_types( array(), 'objects' ) as $type ) {
			if ( Post_Type::POST_TYPE === $type->name ) {
				continue;
			}

			if ( $base === $type->name || self::rewrite_slug( $type->rewrite ) === $base ) {
				return true;
			}
		}

		foreach ( get_taxonomies( array(), 'objects' ) as $taxonomy ) {
			if ( self::rewrite_slug( $taxonomy->rewrite ) === $base ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The slug out of a post type's or taxonomy's rewrite argument.
	 *
	 * @param mixed $rewrite The `rewrite` property, which is `false` or an array.
	 * @return string The slug, or an empty string.
	 */
	private static function rewrite_slug( $rewrite ): string {
		return is_array( $rewrite ) && isset( $rewrite['slug'] ) ? (string) $rewrite['slug'] : '';
	}
}
