<?php
/**
 * The visitor's consent, as a first-party cookie.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the consent cookie.
 *
 * Nothing on the server side reads this to decide what to block. The HTML is
 * always rendered in the blocked state, identical for every visitor, so it can
 * be cached whole; the bootstrap script in the browser reads this cookie and
 * unblocks what the visitor allowed. The cookie exists for that script and for
 * the REST endpoint, not for the renderer.
 *
 * The anonymous id in it is a random string. It is never linked to a user, an
 * IP or anything else, and its only purpose is to tie the rows in the consent
 * log for one browser together so a decision history can be reconstructed.
 */
final class Consent_State {

	/**
	 * Cookie name.
	 */
	const COOKIE = 'baukasten_consent';

	/**
	 * Cookie lifetime in seconds.
	 */
	const LIFETIME = 12 * MONTH_IN_SECONDS;

	/**
	 * Payload format version, so a future change can be recognised.
	 */
	const FORMAT = 1;

	/**
	 * Returns the visitor's stored decision.
	 *
	 * @return array{version: int, id: string, categories: string[], policy: string, time: int}|null Decision, or null.
	 */
	public static function read(): ?array {
		// The cookie is the visitor's own decision, not authentication, and is
		// validated field by field below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::COOKIE ] ) );

		$data = json_decode( base64_decode( $raw ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( ! is_array( $data ) ) {
			return null;
		}

		$categories = isset( $data['categories'] ) && is_array( $data['categories'] )
			? array_values( array_filter( array_map( 'sanitize_key', array_map( 'strval', $data['categories'] ) ) ) )
			: array();

		return array(
			'version'    => isset( $data['version'] ) ? (int) $data['version'] : 0,
			'id'         => isset( $data['id'] ) ? sanitize_key( (string) $data['id'] ) : '',
			'categories' => array_values( array_intersect( $categories, Categories::slugs() ) ),
			'policy'     => isset( $data['policy'] ) ? sanitize_text_field( (string) $data['policy'] ) : '',
			'time'       => isset( $data['time'] ) ? (int) $data['time'] : 0,
		);
	}

	/**
	 * Returns the categories the visitor currently allows.
	 *
	 * Required categories are always in the list, whatever the cookie says.
	 *
	 * @return string[] Category slugs.
	 */
	public static function allowed(): array {
		$stored = self::read();
		$given  = null === $stored ? array() : $stored['categories'];

		$allowed = array();

		foreach ( Categories::all() as $slug => $definition ) {
			if ( ! empty( $definition['required'] ) || in_array( $slug, $given, true ) ) {
				$allowed[] = $slug;
			}
		}

		return $allowed;
	}

	/**
	 * Stores a decision in the cookie and returns the payload written.
	 *
	 * @param string[] $categories Categories the visitor allows.
	 * @param string   $anonymous  Optional. Existing anonymous id to keep.
	 * @return array{version: int, id: string, categories: string[], policy: string, time: int} The stored decision.
	 */
	public static function write( array $categories, string $anonymous = '' ): array {
		$allowed = array();

		foreach ( Categories::all() as $slug => $definition ) {
			if ( ! empty( $definition['required'] ) || in_array( $slug, $categories, true ) ) {
				$allowed[] = $slug;
			}
		}

		$payload = array(
			'version'    => self::FORMAT,
			'id'         => '' === $anonymous ? self::new_anonymous_id() : $anonymous,
			'categories' => $allowed,
			'policy'     => Settings::policy_version(),
			'time'       => time(),
		);

		$value = base64_encode( (string) wp_json_encode( $payload ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE,
				$value,
				array(
					'expires'  => time() + self::LIFETIME,
					'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => false,
					'samesite' => 'Lax',
				)
			);
		}

		$_COOKIE[ self::COOKIE ] = $value;

		return $payload;
	}

	/**
	 * Returns a fresh anonymous id.
	 *
	 * @return string Twenty hexadecimal characters.
	 */
	public static function new_anonymous_id(): string {
		return substr( bin2hex( random_bytes( 16 ) ), 0, 20 );
	}
}
