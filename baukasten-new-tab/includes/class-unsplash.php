<?php
/**
 * The Unsplash photo pool.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a pool of thirty photos from the Unsplash API.
 *
 * One API call fills the pool for three hours, however many tabs are opened.
 * The access key stays on the server: it is read from its own option for that
 * call and never printed, never sent to the browser, never part of a REST
 * answer — version 1 of this page put it into the HTML, where anybody could
 * spend the application's rate limit.
 *
 * The photos themselves are hotlinked from images.unsplash.com, as the API
 * guidelines require for every photo obtained through the API: each view is
 * credited to the photographer that way. A proxy would hide the visitor's
 * address from Unsplash, but it would also break that rule, and Unsplash
 * withdraws API access for it. Hence the consent switch instead.
 */
final class Unsplash {

	/**
	 * Option holding the pool.
	 *
	 * An option rather than a transient, so a failed refresh can keep
	 * serving the old pool instead of an empty one.
	 */
	const OPTION_POOL = 'baukasten_new_tab_unsplash_pool';

	/**
	 * Lock against two requests refreshing at once.
	 */
	const LOCK = 'baukasten_new_tab_unsplash_lock';

	/**
	 * How long a pool is fresh, in seconds.
	 */
	const TTL = 3 * HOUR_IN_SECONDS;

	/**
	 * Photos per pool.
	 */
	const COUNT = 30;

	/**
	 * API endpoint for random photos.
	 */
	const ENDPOINT = 'https://api.unsplash.com/photos/random';

	/**
	 * The photos in the pool, refreshed when stale.
	 *
	 * @param bool $refresh Whether a stale pool may be refreshed in this request.
	 * @return array<int, array<string, mixed>> Photos.
	 */
	public static function pool( bool $refresh = true ): array {
		$pool   = get_option( self::OPTION_POOL, array() );
		$pool   = is_array( $pool ) ? $pool : array();
		$photos = isset( $pool['photos'] ) && is_array( $pool['photos'] ) ? $pool['photos'] : array();
		$stale  = ! isset( $pool['fetched'] ) || time() - (int) $pool['fetched'] >= self::TTL || ( $pool['query'] ?? '' ) !== self::signature();

		if ( $stale && $refresh && '' !== Settings::unsplash_key() && ! get_transient( self::LOCK ) ) {
			set_transient( self::LOCK, 1, 30 );

			$fresh = self::fetch();

			if ( is_array( $fresh ) && array() !== $fresh ) {
				$photos = $fresh;

				update_option(
					self::OPTION_POOL,
					array(
						'fetched' => time(),
						'query'   => self::signature(),
						'photos'  => $photos,
					),
					false
				);
			}

			delete_transient( self::LOCK );
		}

		return $photos;
	}

	/**
	 * Empties the pool, so the next request fetches a new one.
	 *
	 * @return void
	 */
	public static function clear(): void {
		delete_option( self::OPTION_POOL );
		delete_transient( self::LOCK );
	}

	/**
	 * What the pool was fetched for, so a changed search refreshes it.
	 *
	 * @return string Query and orientation.
	 */
	private static function signature(): string {
		return (string) Settings::get( 'unsplash_query' ) . '|' . (string) Settings::get( 'unsplash_orientation' );
	}

	/**
	 * Fetches a fresh pool.
	 *
	 * @return array<int, array<string, mixed>>|\WP_Error Photos, or the error.
	 */
	public static function fetch() {
		$args = array(
			'count'          => self::COUNT,
			'content_filter' => 'high',
		);

		$query = trim( (string) Settings::get( 'unsplash_query' ) );

		if ( '' !== $query ) {
			$args['query'] = rawurlencode( $query );
		}

		$orientation = (string) Settings::get( 'unsplash_orientation' );

		if ( in_array( $orientation, array( 'landscape', 'portrait', 'squarish' ), true ) ) {
			$args['orientation'] = $orientation;
		}

		$response = self::request( add_query_arg( $args, self::ENDPOINT ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'baukasten_new_tab_unsplash_body', __( 'Unsplash answered with something that is not a list of photos.', 'baukasten-new-tab' ) );
		}

		$photos = array();

		foreach ( $data as $photo ) {
			$clean = is_array( $photo ) ? self::clean_photo( $photo ) : null;

			if ( null !== $clean ) {
				$photos[] = $clean;
			}
		}

		return $photos;
	}

	/**
	 * Checks the key with the smallest request the API offers.
	 *
	 * @return array{ok: bool, message: string} Result.
	 */
	public static function test(): array {
		if ( '' === Settings::unsplash_key() ) {
			return array(
				'ok'      => false,
				'message' => __( 'No access key is stored.', 'baukasten-new-tab' ),
			);
		}

		$response = self::request( add_query_arg( array( 'count' => 1 ), self::ENDPOINT ) );

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => $response->get_error_message(),
			);
		}

		$remaining = (string) wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' );
		$limit     = (string) wp_remote_retrieve_header( $response, 'x-ratelimit-limit' );

		return array(
			'ok'      => true,
			'message' => '' !== $remaining && '' !== $limit
				/* translators: 1: requests left this hour. 2: requests allowed per hour. */
				? sprintf( __( 'Connected to Unsplash. %1$s of %2$s requests left this hour.', 'baukasten-new-tab' ), $remaining, $limit )
				: __( 'Connected to Unsplash.', 'baukasten-new-tab' ),
		);
	}

	/**
	 * Sends one authenticated request.
	 *
	 * @param string $url API URL.
	 * @return array<string, mixed>|\WP_Error Response, or the error.
	 */
	private static function request( string $url ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 5,
				'redirection'         => 0,
				'limit_response_size' => 512 * KB_IN_BYTES,
				'headers'             => array(
					'Authorization'  => 'Client-ID ' . Settings::unsplash_key(),
					'Accept-Version' => 'v1',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status ) {
			if ( 401 === $status ) {
				$message = __( 'Unsplash refused the access key (401). Check that it is the Access Key, not the Secret Key.', 'baukasten-new-tab' );
			} elseif ( 403 === $status ) {
				$message = __( 'Unsplash refused the request (403). The hourly rate limit may be used up.', 'baukasten-new-tab' );
			} else {
				/* translators: %d: HTTP status code. */
				$message = sprintf( __( 'Unsplash answered with HTTP status %d.', 'baukasten-new-tab' ), $status );
			}

			return new \WP_Error( 'baukasten_new_tab_unsplash_status', $message );
		}

		return $response;
	}

	/**
	 * Keeps what the page needs of one photo, and nothing that identifies the key.
	 *
	 * @param array<string, mixed> $photo Photo object from the API.
	 * @return array<string, mixed>|null Photo, or null when it lacks a usable URL.
	 */
	private static function clean_photo( array $photo ): ?array {
		$raw = isset( $photo['urls']['raw'] ) ? esc_url_raw( (string) $photo['urls']['raw'], array( 'https' ) ) : '';

		if ( '' === $raw || 'images.unsplash.com' !== wp_parse_url( $raw, PHP_URL_HOST ) ) {
			return null;
		}

		$color = isset( $photo['color'] ) ? sanitize_hex_color( (string) $photo['color'] ) : '';

		return array(
			'id'      => sanitize_key( (string) ( $photo['id'] ?? '' ) ),
			'raw'     => $raw,
			'width'   => absint( $photo['width'] ?? 0 ),
			'height'  => absint( $photo['height'] ?? 0 ),
			'color'   => null !== $color && '' !== $color ? $color : '',
			'alt'     => sanitize_text_field( (string) ( $photo['alt_description'] ?? '' ) ),
			'name'    => sanitize_text_field( (string) ( $photo['user']['name'] ?? '' ) ),
			'profile' => esc_url_raw( (string) ( $photo['user']['links']['html'] ?? '' ), array( 'https' ) ),
			'link'    => esc_url_raw( (string) ( $photo['links']['html'] ?? '' ), array( 'https' ) ),
		);
	}

	/**
	 * The `utm_source` the API guidelines ask for in every link back.
	 *
	 * @return string Application name.
	 */
	public static function app_name(): string {
		$name = sanitize_title( (string) Settings::get( 'unsplash_app_name' ) );

		return '' !== $name ? $name : sanitize_title( (string) get_bloginfo( 'name' ) );
	}
}
