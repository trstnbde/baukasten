<?php
/**
 * Option names and URL resolution for the three legal pages.
 *
 * @package Baukasten\LoginLegalPages
 */

namespace Baukasten\LoginLegalPages;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the three legal pages to URLs.
 *
 * A page only counts as usable when it exists and is published. A draft would
 * 404 for the logged-out visitor the login footer is aimed at, and a broken
 * link on the login screen is the first thing every user of the site sees.
 */
final class Legal_Pages {

	/**
	 * Option holding the terms page ID.
	 */
	const OPTION_TERMS = 'baukasten_page_for_terms';

	/**
	 * Option holding the imprint page ID.
	 */
	const OPTION_IMPRINT = 'baukasten_page_for_imprint';

	/**
	 * Returns the configured page ID for a stored option.
	 *
	 * @param string $option Option name.
	 * @return int Page ID, or 0 when unset.
	 */
	public static function get_page_id( string $option ): int {
		return absint( get_option( $option, 0 ) );
	}

	/**
	 * Returns the URL of a published page stored in an option.
	 *
	 * Mirrors what core's `get_privacy_policy_url()` does for the privacy
	 * policy: an unset, missing or unpublished page yields an empty string.
	 *
	 * @param string $option Option name.
	 * @return string Permalink, or an empty string.
	 */
	public static function get_url( string $option ): string {
		$page_id = self::get_page_id( $option );

		if ( $page_id <= 0 ) {
			return '';
		}

		$page = get_post( $page_id );

		if ( null === $page || 'publish' !== get_post_status( $page ) ) {
			return '';
		}

		$permalink = get_permalink( $page );

		return is_string( $permalink ) ? $permalink : '';
	}

	/**
	 * Returns the URL of the terms page.
	 *
	 * @return string Permalink, or an empty string.
	 */
	public static function get_terms_url(): string {
		/**
		 * Filters the terms of service URL.
		 *
		 * @since 1.0.0
		 *
		 * @param string $url Permalink, or an empty string.
		 */
		return (string) apply_filters(
			'baukasten/login_legal_pages/terms_url',
			self::get_url( self::OPTION_TERMS )
		);
	}

	/**
	 * Returns the URL of the imprint page.
	 *
	 * @return string Permalink, or an empty string.
	 */
	public static function get_imprint_url(): string {
		/**
		 * Filters the imprint URL.
		 *
		 * @since 1.0.0
		 *
		 * @param string $url Permalink, or an empty string.
		 */
		return (string) apply_filters(
			'baukasten/login_legal_pages/imprint_url',
			self::get_url( self::OPTION_IMPRINT )
		);
	}

	/**
	 * Returns the URL of the privacy policy.
	 *
	 * Delegates to core, which reads the `wp_page_for_privacy_policy` option
	 * and applies the same published check.
	 *
	 * @return string Permalink, or an empty string.
	 */
	public static function get_privacy_url(): string {
		return (string) get_privacy_policy_url();
	}

	/**
	 * Returns the links that actually have a page behind them.
	 *
	 * @return array<int, array{url: string, label: string}> Usable links.
	 */
	public static function get_links(): array {
		$candidates = array(
			array(
				'url'   => self::get_privacy_url(),
				'label' => __( 'Privacy Policy', 'baukasten-login-legal-pages' ),
			),
			array(
				'url'   => self::get_terms_url(),
				'label' => __( 'Terms of Service', 'baukasten-login-legal-pages' ),
			),
			array(
				'url'   => self::get_imprint_url(),
				'label' => __( 'Imprint', 'baukasten-login-legal-pages' ),
			),
		);

		$links = array();

		foreach ( $candidates as $candidate ) {
			if ( '' !== trim( (string) $candidate['url'] ) ) {
				$links[] = $candidate;
			}
		}

		/**
		 * Filters the legal links shown in the login footer.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array{url: string, label: string}> $links Usable links.
		 */
		return (array) apply_filters( 'baukasten/login_legal_pages/links', $links );
	}
}
