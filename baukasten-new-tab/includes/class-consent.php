<?php
/**
 * Whether the visitor allowed Unsplash.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * Asks the Consent Blocking Engine, or a cookie of its own without it.
 *
 * A photo hotlinked from images.unsplash.com hands the visitor's address to a
 * third party, so it waits for consent like any other external resource of
 * the site, in the category the engine gives external resources by default.
 *
 * The engine's own rule is that the server never reads the cookie while
 * rendering, so a full page cache cannot serve one visitor's consent to
 * another. The New Tab Page is never cached — it sends `no-cache, private`
 * and is different on every request anyway — so it may, and does: knowing
 * the answer while rendering saves swapping the background after load.
 */
final class Consent {

	/**
	 * Cookie used when the Consent Blocking Engine is not there.
	 */
	const FALLBACK_COOKIE = 'baukasten_new_tab_unsplash';

	/**
	 * Category used when the engine has no setting for it.
	 */
	const DEFAULT_CATEGORY = 'functional';

	/**
	 * Whether the Consent Blocking Engine answers.
	 *
	 * @return bool True when its classes are loaded.
	 */
	public static function has_engine(): bool {
		return class_exists( '\Baukasten\ConsentBlockingEngine\Consent_State' )
			&& class_exists( '\Baukasten\ConsentBlockingEngine\Settings' );
	}

	/**
	 * The consent category Unsplash falls into.
	 *
	 * @return string Category slug.
	 */
	public static function category(): string {
		if ( ! self::has_engine() ) {
			return self::DEFAULT_CATEGORY;
		}

		$category = sanitize_key( (string) \Baukasten\ConsentBlockingEngine\Settings::get( 'external_default' ) );

		return '' !== $category ? $category : self::DEFAULT_CATEGORY;
	}

	/**
	 * Whether the visitor allowed photos from Unsplash.
	 *
	 * @return bool True when the photo may load.
	 */
	public static function allowed(): bool {
		if ( self::has_engine() ) {
			return in_array( self::category(), \Baukasten\ConsentBlockingEngine\Consent_State::allowed(), true );
		}

		// The visitor's own switch, not authentication.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_COOKIE[ self::FALLBACK_COOKIE ] ) && '1' === $_COOKIE[ self::FALLBACK_COOKIE ];
	}

	/**
	 * What the page's script needs to ask for consent.
	 *
	 * @return array<string, mixed> Mode, category, cookie and REST addresses.
	 */
	public static function for_script(): array {
		if ( ! self::has_engine() ) {
			return array(
				'engine' => false,
				'cookie' => self::FALLBACK_COOKIE,
			);
		}

		return array(
			'engine'   => true,
			'category' => self::category(),
			'cookie'   => \Baukasten\ConsentBlockingEngine\Consent_State::COOKIE,
			// Relative, so the request stays on the host the page was opened
			// on and the route's origin check accepts it.
			'state'    => self::relative( rest_url( 'baukasten-consent/v1/consent-state' ) ),
			'save'     => self::relative( rest_url( 'baukasten-consent/v1/consent' ) ),
		);
	}

	/**
	 * Turns an absolute URL into one relative to the host.
	 *
	 * @param string $url Absolute URL.
	 * @return string Path and query.
	 */
	public static function relative( string $url ): string {
		$path  = (string) wp_parse_url( $url, PHP_URL_PATH );
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );

		return ( '' !== $path ? $path : '/' ) . ( '' !== $query ? '?' . $query : '' );
	}
}
