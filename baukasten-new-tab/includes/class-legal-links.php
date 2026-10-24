<?php
/**
 * The footer's legal links.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * Privacy policy, terms and imprint, as Login Legal Pages knows them.
 *
 * The site has answered "where is the imprint" once, on the Login & Legal
 * tab, and a second answer here would drift from it. So there is no field
 * for these links; the tab points to that one.
 *
 * Its three getters are used, not `get_links()`: that one applies the filter
 * `baukasten/login_legal_pages/links`, which its docblock reserves for the
 * login screen, and a link added there for the login screen was not asked for
 * on the start page. The same reasoning as in Business Cards' `Legal_Links`.
 *
 * Each link is named after its page, so a terms page called
 * "Haftungsausschluss" reads exactly that. A page that is not published, or
 * that Content Visibility keeps from logged-out visitors, is left out; the
 * tab says so.
 */
final class Legal_Links {

	/**
	 * Whether Login Legal Pages is answering.
	 *
	 * @return bool True when its class is loaded.
	 */
	public static function has_source(): bool {
		return class_exists( '\Baukasten\LoginLegalPages\Legal_Pages' );
	}

	/**
	 * Every candidate with whether it can be shown.
	 *
	 * @return array<int, array{url: string, label: string, page_id: int, usable: bool, reason: string}> Candidates in footer order.
	 */
	public static function candidates(): array {
		if ( ! self::has_source() ) {
			$privacy = (int) get_option( 'wp_page_for_privacy_policy', 0 );

			return array( self::candidate( (string) get_privacy_policy_url(), $privacy, __( 'Privacy Policy', 'baukasten-new-tab' ) ) );
		}

		$pages = '\Baukasten\LoginLegalPages\Legal_Pages';

		return array(
			self::candidate(
				(string) $pages::get_privacy_url(),
				(int) get_option( 'wp_page_for_privacy_policy', 0 ),
				__( 'Privacy Policy', 'baukasten-new-tab' )
			),
			self::candidate(
				(string) $pages::get_terms_url(),
				(int) $pages::get_page_id( $pages::OPTION_TERMS ),
				__( 'Terms of Service', 'baukasten-new-tab' )
			),
			self::candidate(
				(string) $pages::get_imprint_url(),
				(int) $pages::get_page_id( $pages::OPTION_IMPRINT ),
				__( 'Imprint', 'baukasten-new-tab' )
			),
		);
	}

	/**
	 * The links the footer shows.
	 *
	 * @return array<int, array{url: string, label: string}> Links.
	 */
	public static function all(): array {
		$links = array();

		foreach ( self::candidates() as $candidate ) {
			if ( $candidate['usable'] ) {
				$links[] = array(
					'url'   => $candidate['url'],
					'label' => $candidate['label'],
				);
			}
		}

		/**
		 * Filters the legal links in the footer of the New Tab Page.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array{url: string, label: string}> $links Links.
		 */
		return (array) apply_filters( 'baukasten/new_tab/legal_links', $links );
	}

	/**
	 * Builds one candidate.
	 *
	 * @param string $url      Permalink, empty when the page is unset or unpublished.
	 * @param int    $page_id  Page ID, 0 when unset.
	 * @param string $fallback Label for a page without a title.
	 * @return array{url: string, label: string, page_id: int, usable: bool, reason: string} Candidate.
	 */
	private static function candidate( string $url, int $page_id, string $fallback ): array {
		$label = $fallback;

		if ( self::has_source() ) {
			$label = (string) \Baukasten\LoginLegalPages\Legal_Pages::get_label( $page_id, $fallback );
		} elseif ( 0 < $page_id ) {
			$title = trim( wp_strip_all_tags( get_the_title( $page_id ) ) );
			$label = '' !== $title ? $title : $fallback;
		}

		$reason = '';

		if ( 0 >= $page_id && '' === $url ) {
			$reason = 'unset';
		} elseif ( '' === $url ) {
			$reason = 'unpublished';
		} elseif ( 0 < $page_id && self::is_private( $page_id ) ) {
			$reason = 'private';
		}

		return array(
			'url'     => $url,
			'label'   => $label,
			'page_id' => $page_id,
			'usable'  => '' === $reason,
			'reason'  => $reason,
		);
	}

	/**
	 * Whether Content Visibility keeps a page from logged-out visitors.
	 *
	 * @param int $page_id Page ID.
	 * @return bool True when it does.
	 */
	public static function is_private( int $page_id ): bool {
		$helper = 'Baukasten\\ContentVisibility\\is_private';

		return function_exists( $helper ) && (bool) call_user_func( $helper, $page_id );
	}
}
