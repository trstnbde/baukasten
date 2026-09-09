<?php
/**
 * Domain names.
 *
 * @package Baukasten\MultiDomain
 */

namespace Baukasten\MultiDomain;

defined( 'ABSPATH' ) || exit;

/**
 * Turns whatever somebody typed into a host name, or into nothing.
 *
 * People paste `https://www.kunde.de/`, type `Kunde.de`, or copy an address
 * with a port on it. All of those mean the same host, and the map is keyed by
 * host, so everything is reduced to one before it is stored or compared.
 */
final class Domain {

	/**
	 * Reduces user input to a bare host name.
	 *
	 * @param string $raw Whatever was typed or pasted.
	 * @return string Host name in lower case, or an empty string when unusable.
	 */
	public static function sanitize( string $raw ): string {
		$value = strtolower( trim( sanitize_text_field( $raw ) ) );

		if ( '' === $value ) {
			return '';
		}

		// Scheme, protocol relative prefix, and anything after the host.
		$value = (string) preg_replace( '#^[a-z][a-z0-9+.-]*://#', '', $value );
		$value = ltrim( $value, '/' );
		$value = (string) preg_replace( '#[/?\#].*$#', '', $value );

		// Credentials and port.
		$value = (string) preg_replace( '#^[^@]*@#', '', $value );
		$value = (string) preg_replace( '#:\d+$#', '', $value );

		$value = trim( $value, '.' );

		if ( '' === $value || strlen( $value ) > 253 ) {
			return '';
		}

		// A host is labels of letters, digits and hyphens, separated by dots.
		// `localhost` and other single label hosts are allowed on purpose:
		// they are what a development machine is reached by.
		if ( ! preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/', $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Returns the host of the current request.
	 *
	 * @return string Host name in lower case, or an empty string.
	 */
	public static function current(): string {
		if ( empty( $_SERVER['HTTP_HOST'] ) ) {
			return '';
		}

		$host = sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_HOST'] ) );

		return self::sanitize( $host );
	}

	/**
	 * Returns the `www.` sibling of a host, if it has one.
	 *
	 * A visitor typing the address with or without `www.` means the same site,
	 * and expecting an editor to enter both is a way to collect support
	 * tickets. Hosts that are already prefixed, and single label hosts like
	 * `localhost`, get no sibling.
	 *
	 * @param string $domain Sanitised host name.
	 * @return string Sibling host, or an empty string.
	 */
	public static function sibling( string $domain ): string {
		if ( '' === $domain || str_starts_with( $domain, 'www.' ) || ! str_contains( $domain, '.' ) ) {
			return '';
		}

		return 'www.' . $domain;
	}
}
