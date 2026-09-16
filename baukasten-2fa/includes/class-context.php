<?php
/**
 * Where a login attempt came from.
 *
 * @package Baukasten\TwoFactor
 */

namespace Baukasten\TwoFactor;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the request into the two coarse labels the prompt shows.
 *
 * A prompt that cannot say where the login is coming from is one the user
 * cannot actually decide about — "is this you?" is unanswerable without a hint,
 * and the whole security value of this method rests on the user being able to
 * answer it. So some context is shown, and it is kept to the least that still
 * makes the question answerable:
 *
 * - the address goes through `wp_privacy_anonymize_ip()`, which drops the last
 *   octet of an IPv4 address and the host part of an IPv6 one;
 * - the user agent is reduced to a browser and a platform name from a fixed
 *   list, never stored raw.
 *
 * Both are deleted with the rest of the row once the retention window is up,
 * twenty-four hours by default.
 */
final class Context {

	/**
	 * Returns the anonymised client address.
	 *
	 * @return string Anonymised address, or an empty string.
	 */
	public static function ip_label(): string {
		$address = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: '';

		if ( '' === $address ) {
			return '';
		}

		return (string) wp_privacy_anonymize_ip( $address );
	}

	/**
	 * Returns a coarse "Browser · Platform" label.
	 *
	 * @return string Label, or an empty string.
	 */
	public static function agent_label(): string {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		if ( '' === $agent ) {
			return '';
		}

		$browser  = self::match( $agent, self::browsers() );
		$platform = self::match( $agent, self::platforms() );

		$parts = array_filter( array( $browser, $platform ) );

		return implode( ' · ', $parts );
	}

	/**
	 * Returns the first label whose needle appears in the agent string.
	 *
	 * @param string                $agent  User agent string.
	 * @param array<string, string> $needles Needle to label.
	 * @return string Label, or an empty string.
	 */
	private static function match( string $agent, array $needles ): string {
		foreach ( $needles as $needle => $label ) {
			if ( false !== stripos( $agent, $needle ) ) {
				return $label;
			}
		}

		return '';
	}

	/**
	 * Browser needles, most specific first.
	 *
	 * Order matters: every Chromium browser also says "Chrome", and Chrome
	 * itself still says "Safari".
	 *
	 * @return array<string, string> Needle to label.
	 */
	private static function browsers(): array {
		return array(
			'Edg/'     => 'Edge',
			'OPR/'     => 'Opera',
			'Vivaldi'  => 'Vivaldi',
			'Brave'    => 'Brave',
			'SamsungB' => 'Samsung Internet',
			'Firefox'  => 'Firefox',
			'Chrome'   => 'Chrome',
			'Safari'   => 'Safari',
		);
	}

	/**
	 * Platform needles, most specific first.
	 *
	 * @return array<string, string> Needle to label.
	 */
	private static function platforms(): array {
		return array(
			'Android'   => 'Android',
			'iPhone'    => 'iPhone',
			'iPad'      => 'iPad',
			'Windows'   => 'Windows',
			'Macintosh' => 'macOS',
			'CrOS'      => 'ChromeOS',
			'Linux'     => 'Linux',
		);
	}
}
