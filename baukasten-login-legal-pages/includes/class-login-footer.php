<?php
/**
 * Legal links in the login footer.
 *
 * @package Baukasten\LoginLegalPages
 */

namespace Baukasten\LoginLegalPages;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the legal links below the login form.
 *
 * The links replace core's own privacy policy link rather than being appended
 * separately. `wp-login.php` already prints that link inside `#login`, right
 * under the form, so adding a second block would show the privacy policy
 * twice and put the other two links far below it at the very bottom of the
 * page. The `the_privacy_policy_link` filter runs even when no privacy page
 * is configured, so it is a reliable place to render all three.
 *
 * Only links that resolve to a published page are printed. A link that points
 * nowhere is worse than a missing link here: the login screen is the first
 * thing every user of the site sees.
 */
final class Login_Footer {

	/**
	 * Registers the filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'the_privacy_policy_link', array( __CLASS__, 'replace_privacy_link' ), 10, 2 );
	}

	// The second parameter is part of the filter signature.
	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

	/**
	 * Replaces core's privacy link with the full set of legal links.
	 *
	 * @param string $link               Markup core assembled, possibly empty.
	 * @param string $privacy_policy_url URL of the privacy policy, possibly empty.
	 * @return string Markup to print.
	 */
	public static function replace_privacy_link( $link, $privacy_policy_url ): string {
		// This filter is also used on the front end. Only the login screen is
		// this module's business.
		if ( ! did_action( 'login_init' ) ) {
			return (string) $link;
		}

		return self::markup();
	}

	// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter

	/**
	 * Builds the navigation markup.
	 *
	 * @return string Markup, or an empty string when nothing is configured.
	 */
	public static function markup(): string {
		$links = Legal_Pages::get_links();

		if ( empty( $links ) ) {
			return '';
		}

		$items = '';

		foreach ( $links as $item ) {
			$items .= sprintf(
				'<li class="baukasten-login-legal__item"><a class="baukasten-login-legal__link" href="%1$s">%2$s</a></li>',
				esc_url( (string) $item['url'] ),
				esc_html( (string) $item['label'] )
			);
		}

		return sprintf(
			'<nav class="baukasten-login-legal" aria-label="%1$s"><ul class="baukasten-login-legal__list">%2$s</ul></nav>',
			esc_attr__( 'Legal', 'baukasten-login-legal-pages' ),
			$items
		);
	}
}
