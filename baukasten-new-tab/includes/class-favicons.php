<?php
/**
 * Icons for the favourites bar.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * Fetches each favourite's icon once and serves it from this site.
 *
 * Version 2.0.0 proxied every icon on every page load. Here the server
 * fetches them when the favourites are saved and once a week afterwards, and
 * the page links files in `uploads/baukasten-new-tab/icons/`: no request to
 * the favourite's site from the visitor's browser, and none from the server
 * while a page is being rendered.
 *
 * Only addresses from the settings are ever fetched, so this is no proxy
 * anybody can point somewhere. `wp_safe_remote_get()` refuses private and
 * loopback addresses as well, and every answer is capped at 100 KB and
 * checked by its first bytes. SVG is not accepted: a file in `uploads/` is
 * served from this origin, and an SVG opened directly runs its scripts there.
 * Where nothing usable is found, the bar draws a monogram instead.
 */
final class Favicons {

	/**
	 * Option mapping favourite URLs to stored icons.
	 */
	const OPTION = 'baukasten_new_tab_icons';

	/**
	 * Weekly refresh.
	 */
	const CRON_HOOK = 'baukasten/new_tab/refresh_icons';

	/**
	 * Directory below the uploads directory.
	 */
	const DIRECTORY = 'baukasten-new-tab/icons';

	/**
	 * Largest file accepted, in bytes.
	 */
	const MAX_BYTES = 100 * KB_IN_BYTES;

	/**
	 * Seconds per request.
	 */
	const TIMEOUT = 3;

	/**
	 * Accepted formats: extension to leading bytes.
	 */
	const SIGNATURES = array(
		'png'  => "\x89PNG\r\n\x1a\n",
		'ico'  => "\x00\x00\x01\x00",
		'gif'  => 'GIF8',
		'jpg'  => "\xFF\xD8\xFF",
		'webp' => 'RIFF',
	);

	/**
	 * Registers the cron hook.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( self::CRON_HOOK, array( __CLASS__, 'refresh_all' ) );
		add_action( 'admin_init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Schedules the weekly refresh, unless it already is.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::CRON_HOOK );
		}
	}

	/**
	 * Fetches the icons of every favourite again.
	 *
	 * @return void
	 */
	public static function refresh_all(): void {
		self::refresh( (array) Settings::get( 'favorites' ), true );
	}

	/**
	 * Fetches the icons a list of favourites is missing.
	 *
	 * Keeps the icons of favourites that are still there, and removes the
	 * files of those that are not.
	 *
	 * @param array<int, array<string, string>> $favorites Favourites.
	 * @param bool                              $force     Fetch again even when an icon is stored.
	 * @return void
	 */
	public static function refresh( array $favorites, bool $force = false ): void {
		$stored = self::stored();
		$icons  = array();

		foreach ( $favorites as $favorite ) {
			$key = self::key( $favorite );

			if ( '' === $key || isset( $icons[ $key ] ) ) {
				continue;
			}

			if ( ! $force && isset( $stored[ $key ] ) ) {
				$icons[ $key ] = $stored[ $key ];

				continue;
			}

			$file = self::fetch( (string) ( $favorite['url'] ?? '' ), (string) ( $favorite['icon'] ?? '' ) );

			if ( '' !== $file ) {
				$icons[ $key ] = $file;
			} elseif ( isset( $stored[ $key ] ) && $force ) {
				// A site that is down this week keeps last week's icon.
				$icons[ $key ] = $stored[ $key ];
			} else {
				$icons[ $key ] = '';
			}
		}

		foreach ( $stored as $key => $file ) {
			if ( '' !== $file && ! in_array( $file, $icons, true ) ) {
				self::delete_file( (string) $file );
			}
		}

		update_option( self::OPTION, $icons, true );
	}

	/**
	 * The stored icon map.
	 *
	 * @return array<string, string> File name per favourite key.
	 */
	private static function stored(): array {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? array_map( 'strval', $stored ) : array();
	}

	/**
	 * Which icon a favourite uses: its own icon address, or its site.
	 *
	 * @param array<string, string> $favorite Favourite.
	 * @return string Key, or an empty string without an address.
	 */
	private static function key( array $favorite ): string {
		$url = (string) ( $favorite['url'] ?? '' );

		if ( '' === $url ) {
			return '';
		}

		$icon = (string) ( $favorite['icon'] ?? '' );

		return md5( '' !== $icon ? 'icon|' . $icon : 'site|' . strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
	}

	/**
	 * The icon to show for a favourite.
	 *
	 * @param array<string, string> $favorite Favourite.
	 * @return string URL of a stored file, or a monogram as a `data:` URI.
	 */
	public static function src( array $favorite ): string {
		$stored = self::stored();
		$key    = self::key( $favorite );
		$file   = $stored[ $key ] ?? '';

		if ( '' !== $file ) {
			$dir = self::directory();

			if ( is_readable( $dir['path'] . '/' . $file ) ) {
				return $dir['url'] . '/' . rawurlencode( $file );
			}
		}

		return self::monogram( (string) ( $favorite['title'] ?? '' ), (string) ( $favorite['url'] ?? '' ) );
	}

	/**
	 * A letter on a colour that belongs to the host.
	 *
	 * @param string $title Favourite title.
	 * @param string $url   Favourite address.
	 * @return string `data:image/svg+xml` URI.
	 */
	public static function monogram( string $title, string $url ): string {
		$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$host   = (string) preg_replace( '/^www\./', '', $host );
		$source = '' !== trim( $title ) ? trim( $title ) : $host;
		$letter = mb_strtoupper( mb_substr( '' !== $source ? $source : '?', 0, 1 ) );
		$hue    = crc32( '' !== $host ? $host : $source ) % 360;

		$svg = sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><rect width="16" height="16" rx="3" fill="hsl(%1$d,55%%,42%%)"/><text x="8" y="12" font-family="system-ui,sans-serif" font-size="10" font-weight="600" text-anchor="middle" fill="#fff">%2$s</text></svg>',
			$hue,
			htmlspecialchars( $letter, ENT_XML1 | ENT_QUOTES, 'UTF-8' )
		);

		return 'data:image/svg+xml,' . rawurlencode( $svg );
	}

	/**
	 * Fetches and stores one icon.
	 *
	 * @param string $url  The favourite's address.
	 * @param string $icon Optional. An icon address given in the settings.
	 * @return string File name, or an empty string.
	 */
	public static function fetch( string $url, string $icon = '' ): string {
		$candidates = array();

		if ( '' !== $icon ) {
			$candidates[] = $icon;
		} else {
			$candidates   = self::declared_icons( $url );
			$candidates[] = self::origin( $url ) . '/favicon.ico';
		}

		foreach ( array_unique( $candidates ) as $candidate ) {
			$file = self::download( $candidate );

			if ( '' !== $file ) {
				return $file;
			}
		}

		return '';
	}

	/**
	 * The icons a page names in its head.
	 *
	 * Small ones first, SVG left out.
	 *
	 * @param string $url Page address.
	 * @return string[] Absolute icon URLs.
	 */
	private static function declared_icons( string $url ): array {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => self::TIMEOUT,
				'redirection'         => 3,
				'limit_response_size' => self::MAX_BYTES,
				'user-agent'          => 'Mozilla/5.0 (compatible; Baukasten New Tab Page; +' . home_url( '/' ) . ')',
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$html = (string) wp_remote_retrieve_body( $response );

		if ( ! preg_match_all( '/<link\b[^>]*>/i', $html, $tags ) ) {
			return array();
		}

		$found = array();

		foreach ( $tags[0] as $tag ) {
			$rel  = strtolower( self::attribute( $tag, 'rel' ) );
			$href = html_entity_decode( self::attribute( $tag, 'href' ), ENT_QUOTES, 'UTF-8' );
			$type = strtolower( self::attribute( $tag, 'type' ) );

			if ( '' === $href || ! preg_match( '/(^|\s)(icon|apple-touch-icon)(\s|$)/', $rel ) ) {
				continue;
			}

			if ( 'image/svg+xml' === $type || preg_match( '/\.svg(\?|#|$)/i', $href ) ) {
				continue;
			}

			$size = 0;

			if ( preg_match( '/(\d+)x\d+/', self::attribute( $tag, 'sizes' ), $match ) ) {
				$size = (int) $match[1];
			}

			// 16 and 32 pixels are what the bar draws; apple-touch-icon is a
			// large but reliable last resort.
			$rank = str_contains( $rel, 'apple-touch-icon' ) ? 1000 : ( 0 === $size ? 64 : abs( $size - 32 ) );

			$found[] = array(
				'url'  => self::absolute( $href, $url ),
				'rank' => $rank,
			);
		}

		usort( $found, static fn( array $a, array $b ): int => $a['rank'] <=> $b['rank'] );

		return array_values( array_filter( array_column( $found, 'url' ) ) );
	}

	/**
	 * One attribute of an HTML tag.
	 *
	 * @param string $tag  Tag source.
	 * @param string $name Attribute name.
	 * @return string Value, or an empty string.
	 */
	private static function attribute( string $tag, string $name ): string {
		if ( preg_match( '/\s' . preg_quote( $name, '/' ) . '\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $match ) ) {
			return trim( (string) ( $match[2] ?? '' ) . (string) ( $match[3] ?? '' ) . (string) ( $match[4] ?? '' ) );
		}

		return '';
	}

	/**
	 * Resolves a link against the page it was found on.
	 *
	 * @param string $href Link as written.
	 * @param string $base Page address.
	 * @return string Absolute http(s) URL, or an empty string.
	 */
	private static function absolute( string $href, string $base ): string {
		if ( str_starts_with( $href, '//' ) ) {
			$href = 'https:' . $href;
		} elseif ( str_starts_with( $href, '/' ) ) {
			$href = self::origin( $base ) . $href;
		} elseif ( ! preg_match( '#^[a-z][a-z0-9+.-]*:#i', $href ) ) {
			$path = (string) wp_parse_url( $base, PHP_URL_PATH );
			$dir  = str_ends_with( $path, '/' ) ? $path : (string) preg_replace( '#/[^/]*$#', '/', $path );
			$href = self::origin( $base ) . ( '' !== $dir ? $dir : '/' ) . $href;
		}

		return esc_url_raw( $href, array( 'http', 'https' ) );
	}

	/**
	 * Scheme, host and port of an address.
	 *
	 * @param string $url Address.
	 * @return string Origin without a trailing slash.
	 */
	private static function origin( string $url ): string {
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}

		return ( $parts['scheme'] ?? 'https' ) . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	}

	/**
	 * Downloads an icon, checks it, and stores it.
	 *
	 * @param string $url Icon address.
	 * @return string File name, or an empty string.
	 */
	private static function download( string $url ): string {
		if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => self::TIMEOUT,
				'redirection'         => 3,
				'limit_response_size' => self::MAX_BYTES + 1,
				'user-agent'          => 'Mozilla/5.0 (compatible; Baukasten New Tab Page; +' . home_url( '/' ) . ')',
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$body = (string) wp_remote_retrieve_body( $response );

		if ( '' === $body || strlen( $body ) > self::MAX_BYTES ) {
			return '';
		}

		$extension = self::sniff( $body );

		if ( '' === $extension ) {
			return '';
		}

		$dir = self::directory();

		if ( ! wp_mkdir_p( $dir['path'] ) ) {
			return '';
		}

		// Named after the content: a changed icon gets a new name, so the
		// browser never shows a stale one from its cache.
		$name = substr( md5( $body ), 0, 16 ) . '.' . $extension;

		if ( ! is_file( $dir['path'] . '/' . $name ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( false === file_put_contents( $dir['path'] . '/' . $name, $body ) ) {
				return '';
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
			chmod( $dir['path'] . '/' . $name, 0644 );
		}

		return $name;
	}

	/**
	 * The format of a file, by its first bytes.
	 *
	 * @param string $body File contents.
	 * @return string Extension, or an empty string for anything not accepted.
	 */
	public static function sniff( string $body ): string {
		foreach ( self::SIGNATURES as $extension => $signature ) {
			if ( str_starts_with( $body, $signature ) ) {
				if ( 'webp' === $extension && 'WEBP' !== substr( $body, 8, 4 ) ) {
					continue;
				}

				return $extension;
			}
		}

		return '';
	}

	/**
	 * Where the icons are stored.
	 *
	 * @return array{path: string, url: string} Directory and its URL, without trailing slashes.
	 */
	public static function directory(): array {
		$uploads = wp_upload_dir( null, false );

		return array(
			'path' => untrailingslashit( (string) $uploads['basedir'] ) . '/' . self::DIRECTORY,
			'url'  => untrailingslashit( (string) $uploads['baseurl'] ) . '/' . self::DIRECTORY,
		);
	}

	/**
	 * Removes one stored icon.
	 *
	 * @param string $file File name.
	 * @return void
	 */
	private static function delete_file( string $file ): void {
		$file = sanitize_file_name( $file );
		$path = self::directory()['path'] . '/' . $file;

		if ( '' !== $file && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
