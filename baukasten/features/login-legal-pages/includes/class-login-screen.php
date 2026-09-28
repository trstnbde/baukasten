<?php
/**
 * The login screen itself.
 *
 * @package Baukasten\LoginLegalPages
 */

namespace Baukasten\LoginLegalPages;

defined( 'ABSPATH' ) || exit;

/**
 * Strips WordPress's own branding off `wp-login.php`.
 *
 * Two elements go: the header with the WordPress logo, and the
 * "&larr; Go to <site>" link under the form. Core prints both unconditionally
 * from `login_header()` and `login_footer()`, and neither element sits behind
 * a filter that could suppress it.
 *
 * What *is* filterable is their content, so the link text and the header text
 * are emptied first — no WordPress branding reaches the HTML at all — and the
 * two empty wrappers are then hidden by the stylesheet. Everything else about
 * the screen is left exactly as WordPress ships it.
 */
final class Login_Screen {

	/**
	 * Stylesheet handle.
	 */
	const HANDLE = 'baukasten-login-legal-pages';

	/**
	 * Registers the login hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'login_headertext', '__return_empty_string' );
		add_filter( 'login_site_html_link', '__return_empty_string' );
		add_filter( 'login_headerurl', array( __CLASS__, 'header_url' ) );
	}

	/**
	 * Keeps the emptied header link pointing at the site.
	 *
	 * The anchor is hidden and carries no text, so nobody follows it, but core
	 * defaults it to wordpress.org and there is no reason to ship an outbound
	 * link to another site in the markup.
	 *
	 * @return string Site URL.
	 */
	public static function header_url(): string {
		return home_url( '/' );
	}

	/**
	 * Enqueues the stylesheet.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		wp_enqueue_style(
			self::HANDLE,
			PLUGIN_URL . 'assets/css/login.css',
			array( 'login' ),
			VERSION
		);
	}
}
