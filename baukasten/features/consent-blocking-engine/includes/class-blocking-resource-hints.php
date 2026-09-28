<?php
/**
 * Blocking for resource hints.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Drops `dns-prefetch` and `preconnect` hints pointing at other hosts.
 *
 * A resource hint is a request. `<link rel="preconnect" href="https://…">`
 * opens a TCP and TLS connection to a third party before the visitor has
 * agreed to anything, which is precisely what the network tab check in the
 * acceptance criteria looks for. Hints to the site's own host are kept.
 */
final class Blocking_Resource_Hints {

	/**
	 * Hint types that cause an outgoing connection.
	 *
	 * @var string[]
	 */
	private const CONNECTING = array( 'dns-prefetch', 'preconnect', 'prefetch', 'prerender', 'preload' );

	/**
	 * Registers the filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_resource_hints', array( __CLASS__, 'filter_hints' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Removes third-party hints.
	 *
	 * @param array<int|string, mixed> $urls          Hints for this relation type.
	 * @param string                   $relation_type Relation type.
	 * @return array<int|string, mixed> Filtered hints.
	 */
	public static function filter_hints( $urls, $relation_type ): array {
		$urls = (array) $urls;

		if ( is_admin() || ! in_array( (string) $relation_type, self::CONNECTING, true ) ) {
			return $urls;
		}

		$kept = array();

		foreach ( $urls as $key => $url ) {
			$href = is_array( $url ) ? (string) ( $url['href'] ?? '' ) : (string) $url;
			$host = self::host_of( $href );

			if ( '' !== $host && Categories::is_external( 'https://' . $host ) ) {
				continue;
			}

			$kept[ $key ] = $url;
		}

		return $kept;
	}

	/**
	 * Returns the host a hint points at.
	 *
	 * Hints come in three shapes: a full URL, a protocol relative `//host`,
	 * and — for `dns-prefetch` — a bare host name with no scheme at all. A
	 * site relative path has no host and is never a third party.
	 *
	 * @param string $href Hint target.
	 * @return string Host name, or an empty string.
	 */
	private static function host_of( string $href ): string {
		$href = trim( $href );

		if ( '' === $href ) {
			return '';
		}

		if ( str_starts_with( $href, '//' ) ) {
			return (string) wp_parse_url( 'https:' . $href, PHP_URL_HOST );
		}

		if ( preg_match( '#^https?://#i', $href ) ) {
			return (string) wp_parse_url( $href, PHP_URL_HOST );
		}

		if ( str_starts_with( $href, '/' ) ) {
			return '';
		}

		return str_contains( $href, '.' ) ? (string) wp_parse_url( 'https://' . $href, PHP_URL_HOST ) : '';
	}
}
