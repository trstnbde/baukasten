<?php
/**
 * Consent categories and the mapping of assets onto them.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Knows which categories exist and which category a given asset belongs to.
 *
 * Two questions, deliberately kept apart from the blocking itself:
 *
 * 1. What may a visitor consent to? The definitions, editable as an option and
 *    filterable, so a site can add its own without touching code.
 * 2. Which category does this script, style or host fall into? An explicit
 *    mapping first, then a default: anything served from another host is not
 *    necessary until somebody says otherwise, anything first-party is.
 *
 * The second rule is what makes the plugin useful on a site nobody has
 * categorised yet — a third-party asset is exactly the thing that must not
 * load before consent.
 */
final class Categories {

	/**
	 * Option holding the category definitions.
	 */
	const OPTION_DEFINITIONS = 'baukasten_consent_category_definitions';

	/**
	 * Option holding the handle to category mapping.
	 */
	const OPTION_MAP = 'baukasten_consent_categories';

	/**
	 * The category that is always allowed and cannot be refused.
	 */
	const NECESSARY = 'necessary';

	/**
	 * Cached definitions for this request.
	 *
	 * @var array<string, array{label: string, description: string, required: bool}>|null
	 */
	private static ?array $definitions = null;

	/**
	 * Cached handle map for this request.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $map = null;

	/**
	 * Returns the built-in category definitions.
	 *
	 * @return array<string, array{label: string, description: string, required: bool}> Definitions.
	 */
	public static function defaults(): array {
		return array(
			self::NECESSARY => array(
				'label'       => __( 'Necessary', 'baukasten-consent-blocking-engine' ),
				'description' => __( 'Required for the site to work. Cannot be refused.', 'baukasten-consent-blocking-engine' ),
				'required'    => true,
			),
			'functional'    => array(
				'label'       => __( 'Functional', 'baukasten-consent-blocking-engine' ),
				'description' => __( 'Embedded content, maps, fonts and other conveniences loaded from other providers.', 'baukasten-consent-blocking-engine' ),
				'required'    => false,
			),
			'statistics'    => array(
				'label'       => __( 'Statistics', 'baukasten-consent-blocking-engine' ),
				'description' => __( 'Measures how the site is used.', 'baukasten-consent-blocking-engine' ),
				'required'    => false,
			),
			'marketing'     => array(
				'label'       => __( 'Marketing', 'baukasten-consent-blocking-engine' ),
				'description' => __( 'Tracks visitors across sites to build advertising profiles.', 'baukasten-consent-blocking-engine' ),
				'required'    => false,
			),
		);
	}

	/**
	 * Returns all category definitions.
	 *
	 * @return array<string, array{label: string, description: string, required: bool}> Definitions.
	 */
	public static function all(): array {
		if ( null !== self::$definitions ) {
			return self::$definitions;
		}

		$stored = get_option( self::OPTION_DEFINITIONS, array() );
		$stored = is_array( $stored ) && array() !== $stored ? $stored : self::defaults();

		/**
		 * Filters the consent categories a visitor can decide on.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, array{label: string, description: string, required: bool}> $categories Definitions.
		 */
		$stored = (array) apply_filters( 'baukasten/consent/categories', $stored );

		$clean = array();

		foreach ( $stored as $slug => $definition ) {
			$slug = sanitize_key( (string) $slug );

			if ( '' === $slug || ! is_array( $definition ) ) {
				continue;
			}

			$clean[ $slug ] = array(
				'label'       => isset( $definition['label'] ) ? (string) $definition['label'] : $slug,
				'description' => isset( $definition['description'] ) ? (string) $definition['description'] : '',
				'required'    => ! empty( $definition['required'] ) || self::NECESSARY === $slug,
			);
		}

		if ( ! isset( $clean[ self::NECESSARY ] ) ) {
			$clean = array( self::NECESSARY => self::defaults()[ self::NECESSARY ] ) + $clean;
		}

		self::$definitions = $clean;

		return self::$definitions;
	}

	/**
	 * Returns the slugs of every known category.
	 *
	 * @return string[] Category slugs.
	 */
	public static function slugs(): array {
		return array_keys( self::all() );
	}

	/**
	 * Returns the slugs a visitor can refuse.
	 *
	 * @return string[] Category slugs.
	 */
	public static function optional_slugs(): array {
		$slugs = array();

		foreach ( self::all() as $slug => $definition ) {
			if ( empty( $definition['required'] ) ) {
				$slugs[] = $slug;
			}
		}

		return $slugs;
	}

	/**
	 * Whether a category exists.
	 *
	 * @param string $slug Category slug.
	 * @return bool True when it is registered.
	 */
	public static function exists( string $slug ): bool {
		return isset( self::all()[ $slug ] );
	}

	/**
	 * Whether a category is always allowed.
	 *
	 * @param string $slug Category slug.
	 * @return bool True when the category cannot be refused.
	 */
	public static function is_required( string $slug ): bool {
		return ! empty( self::all()[ $slug ]['required'] );
	}

	/**
	 * Returns the stored handle to category mapping.
	 *
	 * @return array<string, string> Handle to category slug.
	 */
	public static function map(): array {
		if ( null !== self::$map ) {
			return self::$map;
		}

		$stored = get_option( self::OPTION_MAP, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$clean = array();

		foreach ( $stored as $handle => $slug ) {
			$handle = sanitize_key( (string) $handle );
			$slug   = sanitize_key( (string) $slug );

			if ( '' !== $handle && self::exists( $slug ) ) {
				$clean[ $handle ] = $slug;
			}
		}

		self::$map = $clean;

		return self::$map;
	}

	/**
	 * Stores a handle to category mapping.
	 *
	 * @param array<string, string> $map Handle to category slug.
	 * @return void
	 */
	public static function save_map( array $map ): void {
		$clean = array();

		foreach ( $map as $handle => $slug ) {
			$handle = sanitize_key( (string) $handle );
			$slug   = sanitize_key( (string) $slug );

			if ( '' !== $handle && self::exists( $slug ) ) {
				$clean[ $handle ] = $slug;
			}
		}

		ksort( $clean );

		update_option( self::OPTION_MAP, $clean, false );

		self::$map = $clean;
	}

	/**
	 * Returns the category external assets fall into when nothing is mapped.
	 *
	 * @return string Category slug.
	 */
	public static function external_default(): string {
		$slug = sanitize_key( (string) Settings::get( 'external_default' ) );

		if ( ! self::exists( $slug ) || self::is_required( $slug ) ) {
			$optional = self::optional_slugs();

			return array() === $optional ? self::NECESSARY : $optional[0];
		}

		return $slug;
	}

	/**
	 * Returns the category an asset belongs to.
	 *
	 * @param string $handle Script or style handle.
	 * @param string $src    Asset URL, possibly empty for inline assets.
	 * @return string Category slug.
	 */
	public static function for_handle( string $handle, string $src = '' ): string {
		$map    = self::map();
		$handle = sanitize_key( $handle );

		// The bootstrap script is what unblocks everything else. Blocking it
		// would leave the page stuck in the blocked state for good, so it is
		// necessary by definition and not open to configuration.
		if ( Frontend::HANDLE === $handle || Frontend::STYLE_HANDLE === $handle ) {
			return self::NECESSARY;
		}

		if ( isset( $map[ $handle ] ) ) {
			$category = $map[ $handle ];
		} elseif ( '' !== $src && self::is_external( $src ) ) {
			$category = self::external_default();
		} else {
			$category = self::NECESSARY;
		}

		/**
		 * Filters the consent category an asset belongs to.
		 *
		 * @since 1.0.0
		 *
		 * @param string $category Category slug.
		 * @param string $handle   Script or style handle.
		 * @param string $src      Asset URL, possibly empty.
		 */
		$category = (string) apply_filters( 'baukasten/consent/asset_category', $category, $handle, $src );

		return self::exists( $category ) ? $category : self::NECESSARY;
	}

	/**
	 * Whether a URL points at another host than this site.
	 *
	 * Protocol relative and relative URLs count as first-party.
	 *
	 * @param string $url Asset URL.
	 * @return bool True when the URL is on a different host.
	 */
	public static function is_external( string $url ): bool {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );

		if ( '' === $host ) {
			return false;
		}

		$site = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		if ( strtolower( $host ) === strtolower( $site ) ) {
			return false;
		}

		/**
		 * Filters the hosts that count as first-party.
		 *
		 * Useful for a CDN serving the site's own assets.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $hosts Host names.
		 */
		$allowed = (array) apply_filters( 'baukasten/consent/first_party_hosts', array( $site ) );
		$allowed = array_map( 'strtolower', array_map( 'strval', $allowed ) );

		return ! in_array( strtolower( $host ), $allowed, true );
	}

	/**
	 * Clears the request cache.
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$definitions = null;
		self::$map         = null;
	}
}
