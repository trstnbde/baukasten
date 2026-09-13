<?php
/**
 * Where a card's legal links come from.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * One shape for the footer's three links, from whichever source has them.
 *
 * A site running the Login Legal Pages addon has already answered "where is the
 * imprint" once. Answering it again per card is how one card ends up pointing at
 * a page that was replaced two years ago, so when that plugin is present it is
 * the only source and the per-card fields are not offered at all — not offered
 * rather than ignored, because a field that saves a value nothing reads is a
 * trap.
 *
 * Without it, each card names its own three pages. Page IDs and not URLs: a page
 * that is renamed keeps its id, and a page that is unpublished stops being linked
 * instead of linking at a 404.
 */
final class Legal_Links {

	/**
	 * Whether the Login Legal Pages addon is answering.
	 *
	 * `class_exists()` rather than `is_plugin_active()`: the latter is only
	 * loaded in the admin and this runs on the front end. That plugin requires
	 * the class unconditionally from its main file, so its presence is exactly
	 * "the plugin is active".
	 *
	 * @return bool True when the site has one answer for all cards.
	 */
	public static function has_site_source(): bool {
		return class_exists( '\Baukasten\LoginLegalPages\Legal_Pages' );
	}

	/**
	 * The links to print at the foot of a card.
	 *
	 * Privacy, terms, imprint, in that order in both branches — it is the order
	 * Login Legal Pages uses, and matching it means the footer does not reshuffle
	 * itself when that plugin is switched on.
	 *
	 * The three individual getters are used rather than its `get_links()`.
	 * `get_links()` returns exactly this shape, but it also applies the filter
	 * `baukasten/login_legal_pages/links`, whose own docblock says it filters
	 * "the legal links shown in the login footer" — someone who adds a link
	 * there for the login screen has not asked for it on every business card.
	 * The per-page filters those getters apply are about the page itself, which
	 * is the right thing to inherit.
	 *
	 * @param array<string, mixed> $card Loaded card fields.
	 * @return array<int, array<string, string>> Usable links.
	 */
	public static function all( array $card ): array {
		$candidates = array(
			array(
				'url'   => self::url( $card, 'privacy' ),
				'label' => __( 'Privacy policy', 'baukasten-business-cards' ),
			),
			array(
				'url'   => self::url( $card, 'terms' ),
				'label' => __( 'Terms', 'baukasten-business-cards' ),
			),
			array(
				'url'   => self::url( $card, 'imprint' ),
				'label' => __( 'Imprint', 'baukasten-business-cards' ),
			),
		);

		$links = array();

		foreach ( $candidates as $candidate ) {
			if ( '' !== trim( (string) $candidate['url'] ) ) {
				$links[] = $candidate;
			}
		}

		/**
		 * Filters the legal links at the foot of a card.
		 *
		 * @since 1.1.0
		 *
		 * @param array<int, array<string, string>> $links Usable links.
		 * @param array<string, mixed>              $card  Loaded card fields.
		 */
		return (array) apply_filters( 'baukasten/business_cards/legal_links', $links, $card );
	}

	/**
	 * The address of one legal page.
	 *
	 * @param array<string, mixed> $card Loaded card fields.
	 * @param string               $name One of `privacy`, `terms` or `imprint`.
	 * @return string Permalink, or an empty string.
	 */
	public static function url( array $card, string $name ): string {
		if ( self::has_site_source() ) {
			return self::site_url( $name );
		}

		$field = array(
			'privacy' => 'footer_privacy_page',
			'terms'   => 'footer_terms_page',
			'imprint' => 'footer_imprint_page',
		);

		$url = isset( $field[ $name ] )
			? self::page_url( (int) ( $card[ $field[ $name ] ] ?? 0 ) )
			: '';

		if ( '' === $url && 'privacy' === $name ) {
			// The site already knows where its privacy policy is; a card that
			// does not name one falls back to it rather than to nothing.
			$url = (string) get_privacy_policy_url();
		}

		return $url;
	}

	/**
	 * The address of one legal page according to the Login Legal Pages addon.
	 *
	 * @param string $name One of `privacy`, `terms` or `imprint`.
	 * @return string Permalink, or an empty string.
	 */
	private static function site_url( string $name ): string {
		if ( 'terms' === $name ) {
			return (string) \Baukasten\LoginLegalPages\Legal_Pages::get_terms_url();
		}

		if ( 'imprint' === $name ) {
			return (string) \Baukasten\LoginLegalPages\Legal_Pages::get_imprint_url();
		}

		return (string) \Baukasten\LoginLegalPages\Legal_Pages::get_privacy_url();
	}

	/**
	 * The permalink of a page, if it is published.
	 *
	 * The same rule core applies to the privacy policy. A draft would be a 404
	 * for exactly the logged-out visitor a card is aimed at.
	 *
	 * @param int $page_id Page ID.
	 * @return string Permalink, or an empty string.
	 */
	private static function page_url( int $page_id ): string {
		if ( 0 >= $page_id ) {
			return '';
		}

		$page = get_post( $page_id );

		if ( ! $page instanceof \WP_Post || 'publish' !== get_post_status( $page ) ) {
			return '';
		}

		$permalink = get_permalink( $page );

		return is_string( $permalink ) ? $permalink : '';
	}
}
