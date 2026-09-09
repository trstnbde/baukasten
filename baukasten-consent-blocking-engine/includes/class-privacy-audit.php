<?php
/**
 * Privacy measures that are not consent gated.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Things that leak, that no visitor can meaningfully consent to.
 *
 * The rest of this plugin blocks what a visitor may later allow. The measures
 * here are different in kind: a commenter cannot usefully be asked whether
 * their IP address may be written into the database, and nobody browses a
 * dashboard in order to see WordPress.org's news feed. So these are switched
 * off outright rather than parked behind a category.
 */
final class Privacy_Audit {

	/**
	 * Registers the enabled measures.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( Settings::enabled( 'block_comment_ip' ) ) {
			add_filter( 'pre_comment_user_ip', '__return_empty_string' );
		}

		if ( Settings::enabled( 'block_speculative_loading' ) ) {
			add_filter( 'wp_speculation_rules_configuration', '__return_null', PHP_INT_MAX );
		}

		if ( ! is_admin() ) {
			return;
		}

		if ( Settings::enabled( 'block_dashboard_requests' ) ) {
			add_action( 'wp_dashboard_setup', array( __CLASS__, 'remove_dashboard_widgets' ), PHP_INT_MAX );
			add_filter( 'pre_site_transient_browser_' . md5( self::user_agent() ), array( __CLASS__, 'fake_browser_check' ) );
		}

		if ( Settings::enabled( 'warn_insecure_urls' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_insecure_url_notice' ) );
		}
	}

	/**
	 * Removes the dashboard widgets that fetch from WordPress.org.
	 *
	 * "WordPress Events and News" calls api.wordpress.org on every dashboard
	 * load, sending the site's IP and, for the events list, its location.
	 *
	 * Update checks are deliberately left alone. Switching those off would
	 * stop security updates reaching the site, and no reading of data
	 * protection law makes that a good trade.
	 *
	 * @return void
	 */
	public static function remove_dashboard_widgets(): void {
		remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
	}

	/**
	 * Answers the browser version check from cache, so it never goes out.
	 *
	 * `wp_check_browser_version()` posts the user agent to api.wordpress.org
	 * to decide whether to nag about an outdated browser. Returning a value
	 * for its site transient short circuits the request.
	 *
	 * @param mixed $value Existing transient value.
	 * @return mixed Cached browser data.
	 */
	public static function fake_browser_check( $value ) {
		if ( false !== $value ) {
			return $value;
		}

		return array(
			'name'            => '',
			'version'         => '',
			'platform'        => '',
			'update_url'      => '',
			'img_src'         => '',
			'img_src_ssl'     => '',
			'current_version' => '',
			'upgrade'         => false,
			'insecure'        => false,
			'mobile'          => false,
		);
	}

	/**
	 * Warns when the site is not served over HTTPS.
	 *
	 * Consent, and every other guarantee this plugin makes, travels over the
	 * same connection as the page. Over plain HTTP none of it is worth much.
	 *
	 * Not shown in a local environment: a development machine on
	 * `http://localhost` is not a finding, and a warning that is always wrong
	 * is a warning nobody reads.
	 *
	 * @return void
	 */
	public static function render_insecure_url_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
			return;
		}

		$insecure = array();

		foreach ( array( 'siteurl', 'home' ) as $option ) {
			if ( 'https' !== wp_parse_url( (string) get_option( $option ), PHP_URL_SCHEME ) ) {
				$insecure[] = $option;
			}
		}

		if ( array() === $insecure ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
			esc_html__( 'This site is not served over HTTPS.', 'baukasten-consent-blocking-engine' ),
			esc_html(
				sprintf(
					/* translators: %s: comma separated list of option names. */
					__( 'Consent decisions, logins and everything else travel unencrypted while %s still use http.', 'baukasten-consent-blocking-engine' ),
					implode( ', ', $insecure )
				)
			),
			esc_url( admin_url( 'options-general.php' ) ),
			esc_html__( 'Change the site address', 'baukasten-consent-blocking-engine' )
		);
	}

	/**
	 * Returns the current user agent, for the browser check transient key.
	 *
	 * @return string User agent string, possibly empty.
	 */
	private static function user_agent(): string {
		return isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) )
			: '';
	}
}
