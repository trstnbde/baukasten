<?php
/**
 * The login screen's address.
 *
 * @package Baukasten\LoginLegalPages
 */

namespace Baukasten\LoginLegalPages;

defined( 'ABSPATH' ) || exit;

/**
 * Serves the login screen from `/login/` instead of `/wp-login.php`.
 *
 * `wp-login.php` is a file, and WordPress rewrite rules always resolve to
 * `index.php`, so a rewrite rule cannot point at it. The request is therefore
 * taken over directly: when the path matches the configured slug, the global
 * `$pagenow` is corrected early and `wp-login.php` is included on `wp_loaded`
 * — the last moment before WordPress parses the query, and the same point in
 * the bootstrap that `wp-login.php` reaches on its own.
 *
 * Every login URL WordPress generates is rewritten to match, so forms post
 * back to `/login/` and links point there. `wp-login.php` itself keeps
 * working and merely redirects: this is a tidier address, not a hiding place,
 * and nobody can be locked out by it.
 */
final class Login_URL {

	/**
	 * Option holding the login slug.
	 */
	const OPTION_SLUG = 'baukasten_login_slug';

	/**
	 * Slug used when nothing is configured.
	 */
	const DEFAULT_SLUG = 'login';

	/**
	 * Whether this request is the custom login URL.
	 *
	 * @var bool
	 */
	private static bool $serving = false;

	/**
	 * Registers the hooks.
	 *
	 * Called while the plugin file is being included, because the takeover has
	 * to be in place before `plugins_loaded`.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'claim_request' ), 0 );
		add_action( 'wp_loaded', array( __CLASS__, 'serve' ), 0 );

		add_filter( 'site_url', array( __CLASS__, 'filter_site_url' ), 10, 3 );
		add_filter( 'network_site_url', array( __CLASS__, 'filter_site_url' ), 10, 3 );
	}

	/**
	 * Returns the configured login slug.
	 *
	 * @return string Slug without slashes.
	 */
	public static function slug(): string {
		$slug = sanitize_title( (string) get_option( self::OPTION_SLUG, self::DEFAULT_SLUG ) );

		/**
		 * Filters the path the login screen is served from.
		 *
		 * @since 1.0.0
		 *
		 * @param string $slug Slug without slashes.
		 */
		$slug = sanitize_title( (string) apply_filters( 'baukasten/login_legal_pages/login_slug', $slug ) );

		return '' === $slug ? self::DEFAULT_SLUG : $slug;
	}

	/**
	 * The full URL of the login screen.
	 *
	 * @return string Login URL with a trailing slash.
	 */
	public static function url(): string {
		return home_url( '/' . self::slug() . '/' );
	}

	/**
	 * Whether the custom URL can be served at all.
	 *
	 * Without pretty permalinks the request never reaches WordPress, so the
	 * plugin leaves `wp-login.php` alone rather than pointing every login link
	 * at an address that 404s.
	 *
	 * @return bool True when pretty permalinks are on.
	 */
	public static function is_enabled(): bool {
		return '' !== (string) get_option( 'permalink_structure', '' );
	}

	/**
	 * Decides early what this request is.
	 *
	 * Corrects `$pagenow` for the custom URL so that code running on `init`
	 * sees the login screen it is actually on, and sends a plain visit to
	 * `wp-login.php` on to the custom URL.
	 *
	 * @return void
	 */
	public static function claim_request(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		global $pagenow;

		if ( self::request_matches_slug() ) {
			self::$serving = true;

			// Code running on `init` asks $pagenow which screen it is on, and
			// for this request the honest answer is wp-login.php.
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$pagenow = 'wp-login.php';

			return;
		}

		if ( 'wp-login.php' === $pagenow && self::is_plain_get() ) {
			$query = (string) wp_parse_url( self::request_uri(), PHP_URL_QUERY );
			$url   = self::url() . ( '' === $query ? '' : '?' . $query );

			wp_safe_redirect( $url, 302 );
			exit;
		}
	}

	/**
	 * Runs the login screen for the custom URL.
	 *
	 * `wp_loaded` is the last hook before the query is parsed, and everything
	 * `wp-login.php` expects — `init`, translations, rewrite rules — has run
	 * by then.
	 *
	 * @return void
	 */
	public static function serve(): void {
		if ( ! self::$serving ) {
			return;
		}

		/*
		 * wp-login.php is written to run at the top level of a request, where
		 * its variables are globals. Included from inside a method they would
		 * be locals instead, and login_header() and login_footer() — which
		 * read $error, $interim_login and $action through `global` — would see
		 * nothing. Declaring them here puts those names back in the global
		 * scope before the file assigns them.
		 */
		global $action, $error, $errors, $http_post, $interim_login, $lang,
			$login_link_separator, $reauth, $redirect_to, $requested_redirect_to,
			$rp_cookie, $rp_key, $rp_login, $rp_path, $secure_cookie,
			$switched_locale, $user, $user_login;

		require_once ABSPATH . 'wp-login.php';

		exit;
	}

	/**
	 * Rewrites the login URLs WordPress generates.
	 *
	 * Covers `wp_login_url()`, `wp_logout_url()`, `wp_lostpassword_url()`,
	 * `wp_registration_url()` and every login form action, because all of them
	 * are built from `site_url( 'wp-login.php', 'login' )`.
	 *
	 * @param string      $url    The complete site URL including scheme and path.
	 * @param string      $path   Path relative to the site URL.
	 * @param string|null $scheme Scheme to give the site URL context.
	 * @return string Filtered URL.
	 */
	public static function filter_site_url( $url, $path, $scheme ): string {
		$url = (string) $url;

		if ( ! self::is_enabled() ) {
			return $url;
		}

		if ( ! in_array( $scheme, array( 'login', 'login_post' ), true ) ) {
			return $url;
		}

		if ( ! str_contains( (string) $path, 'wp-login.php' ) ) {
			return $url;
		}

		// Rebuilt from home_url() rather than patched with str_replace: on an
		// install where WordPress lives in a subdirectory, site_url() and
		// home_url() differ, and only the home URL is a path the rewrite
		// rules actually reach.
		$parts = (array) wp_parse_url( $url );
		$login = self::url();

		if ( ! empty( $parts['scheme'] ) ) {
			$login = set_url_scheme( $login, (string) $parts['scheme'] );
		}

		if ( ! empty( $parts['query'] ) ) {
			$login .= '?' . $parts['query'];
		}

		return $login;
	}

	/**
	 * Whether the requested path is the configured login slug.
	 *
	 * @return bool True when it matches.
	 */
	private static function request_matches_slug(): bool {
		$path = (string) wp_parse_url( self::request_uri(), PHP_URL_PATH );
		$path = trim( $path, '/' );

		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home = trim( $home, '/' );

		if ( '' !== $home && str_starts_with( $path, $home . '/' ) ) {
			$path = substr( $path, strlen( $home ) + 1 );
		}

		return self::slug() === $path;
	}

	/**
	 * Whether this is a plain GET that can safely be redirected.
	 *
	 * A POST is left alone: the login form, the password protected post form
	 * and the password reset all post to `wp-login.php` on sites where an
	 * older form was cached, and answering those with a redirect would drop
	 * the request body.
	 *
	 * @return bool True for a GET request.
	 */
	private static function is_plain_get(): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';

		return 'GET' === $method;
	}

	/**
	 * The requested URI.
	 *
	 * @return string Request URI, or an empty string.
	 */
	private static function request_uri(): string {
		return isset( $_SERVER['REQUEST_URI'] )
			? esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '';
	}
}
