<?php
/**
 * Search suggestions, fetched by the server.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * Asks the chosen provider for suggestions, on behalf of the browser.
 *
 * The browser talks to this site only. The provider learns what was typed,
 * because it cannot suggest otherwise, but not who typed it: the request
 * comes from the server, without the visitor's address, cookies or user
 * agent. That is the whole reason for the detour.
 *
 * All three providers answer in the OpenSearch suggestion format,
 * `["query", ["suggestion", …]]` (checked on 24 October 2026).
 */
final class Suggest {

	/**
	 * Requests per client and minute.
	 */
	const RATE_LIMIT = 60;

	/**
	 * Length of the rate limit window, in seconds.
	 */
	const RATE_WINDOW = MINUTE_IN_SECONDS;

	/**
	 * How long an answer is reused, in seconds.
	 */
	const CACHE_TTL = 10 * MINUTE_IN_SECONDS;

	/**
	 * Most suggestions passed on.
	 */
	const MAX_SUGGESTIONS = 8;

	/**
	 * Prefix of the transients this class writes.
	 */
	const TRANSIENT_PREFIX = 'baukasten_new_tab_sg_';

	/**
	 * Prefix of the rate limit transients.
	 */
	const RATE_PREFIX = 'baukasten_new_tab_rl_';

	/**
	 * The provider URL for a query.
	 *
	 * @param string $provider Provider id.
	 * @param string $query    What was typed.
	 * @return string URL, or an empty string when suggestions are off.
	 */
	public static function provider_url( string $provider, string $query ): string {
		$locale   = determine_locale();
		$language = strtolower( (string) strtok( $locale, '_' ) );
		$region   = strtolower( (string) substr( (string) strstr( $locale, '_' ), 1, 2 ) );
		$region   = '' !== $region ? $region : $language;

		switch ( $provider ) {
			case 'google':
				return add_query_arg(
					array(
						'client' => 'firefox',
						'hl'     => $language,
						'ie'     => 'utf-8',
						'oe'     => 'utf-8',
						'q'      => rawurlencode( $query ),
					),
					'https://suggestqueries.google.com/complete/search'
				);

			case 'duckduckgo':
				return add_query_arg(
					array(
						'kl'   => $region . '-' . $language,
						'type' => 'list',
						'q'    => rawurlencode( $query ),
					),
					'https://duckduckgo.com/ac/'
				);

			case 'brave':
				return add_query_arg(
					array(
						'q' => rawurlencode( $query ),
					),
					'https://search.brave.com/api/suggest'
				);
		}

		return '';
	}

	/**
	 * Suggestions for a query.
	 *
	 * @param string $query What was typed.
	 * @return string[] Suggestions; empty when off, unknown or failed.
	 */
	public static function lookup( string $query ): array {
		$provider = (string) Settings::get( 'suggest' );
		$url      = self::provider_url( $provider, $query );

		if ( '' === $url || '' === trim( $query ) ) {
			return array();
		}

		$cache_key = self::TRANSIENT_PREFIX . md5( $provider . '|' . determine_locale() . '|' . $query );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 2,
				'redirection'         => 1,
				'limit_response_size' => 64 * KB_IN_BYTES,
				'user-agent'          => 'Mozilla/5.0 (compatible; Baukasten New Tab Page; +' . home_url( '/' ) . ')',
				'headers'             => array( 'Accept' => 'application/json, application/x-suggestions+json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$suggestions = self::parse( (string) wp_remote_retrieve_body( $response ) );

		set_transient( $cache_key, $suggestions, self::CACHE_TTL );

		return $suggestions;
	}

	/**
	 * Reads an OpenSearch suggestion answer.
	 *
	 * @param string $body Response body.
	 * @return string[] Suggestions.
	 */
	public static function parse( string $body ): array {
		if ( ! preg_match( '//u', $body ) ) {
			// Google ignores `oe` now and then and answers in Latin-1.
			$body = (string) mb_convert_encoding( $body, 'UTF-8', 'ISO-8859-1' );
		}

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data[1] ) || ! is_array( $data[1] ) ) {
			return array();
		}

		$suggestions = array();

		foreach ( $data[1] as $suggestion ) {
			$suggestion = is_string( $suggestion ) ? sanitize_text_field( $suggestion ) : '';

			if ( '' !== $suggestion && ! in_array( $suggestion, $suggestions, true ) ) {
				$suggestions[] = mb_substr( $suggestion, 0, Search::MAX_LENGTH );
			}

			if ( count( $suggestions ) >= self::MAX_SUGGESTIONS ) {
				break;
			}
		}

		return $suggestions;
	}

	/**
	 * Whether this client may ask again right now.
	 *
	 * Keyed by `wp_hash()` of the address, which is only ever part of a
	 * transient name and expires with it; the address itself is not stored.
	 *
	 * @return bool True when within the limit.
	 */
	public static function within_rate_limit(): bool {
		$address = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: '';

		if ( '' === $address ) {
			return true;
		}

		$key    = self::RATE_PREFIX . wp_hash( $address );
		$bucket = get_transient( $key );
		$now    = time();

		if ( ! is_array( $bucket ) || ! isset( $bucket['start'], $bucket['count'] ) || $now - (int) $bucket['start'] >= self::RATE_WINDOW ) {
			$bucket = array(
				'start' => $now,
				'count' => 0,
			);
		}

		if ( (int) $bucket['count'] >= self::RATE_LIMIT ) {
			return false;
		}

		++$bucket['count'];

		// The window is fixed from its first request; the transient only has
		// to outlive what is left of it.
		set_transient( $key, $bucket, max( 1, self::RATE_WINDOW - ( $now - (int) $bucket['start'] ) ) );

		return true;
	}
}
