<?php
/**
 * Front end routing.
 *
 * @package Baukasten\MultiDomain
 */

namespace Baukasten\MultiDomain;

defined( 'ABSPATH' ) || exit;

/**
 * Serves a different front page depending on the host the request came in on.
 *
 * WordPress decides what the front page is by reading two options. Both are
 * filterable before they are read, so the whole feature is four filters and a
 * lookup in an array that is already in memory — no query, no template
 * juggling, and nothing to keep in sync at request time.
 *
 * `home` and `siteurl` are rewritten to the incoming host as well. Without
 * that, every link, stylesheet and image on the page would point back at the
 * installation's primary domain, and the visitor would be bounced off the
 * domain they typed on the first click.
 */
final class Domain_Router {

	/**
	 * The page this host asks for, or 0. Resolved once per request.
	 *
	 * @var int|null
	 */
	private static ?int $page_id = null;

	/**
	 * Registers the filters.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'pre_option_show_on_front', array( __CLASS__, 'filter_show_on_front' ) );
		add_filter( 'pre_option_page_on_front', array( __CLASS__, 'filter_page_on_front' ) );
		add_filter( 'option_home', array( __CLASS__, 'filter_url' ) );
		add_filter( 'option_siteurl', array( __CLASS__, 'filter_url' ) );

		/*
		 * Asset URLs do not come from those two options at request time. They
		 * are built from WP_CONTENT_URL and friends, which WordPress defines
		 * from `siteurl` in wp-settings.php before a single plugin has loaded
		 * — too early for any filter to reach. Left alone, a page served on
		 * the customer's domain would pull its stylesheets, scripts and fonts
		 * from the installation's primary domain, which is both a needless
		 * cross-origin request and a privacy leak: the primary domain would
		 * see every visitor of every customer domain.
		 */
		add_filter( 'content_url', array( __CLASS__, 'filter_url' ) );
		add_filter( 'plugins_url', array( __CLASS__, 'filter_url' ) );
		add_filter( 'includes_url', array( __CLASS__, 'filter_url' ) );

		/*
		 * And a plugin that caches its own URL in a constant at include time
		 * is past even those filters, so the printed src is caught as a last
		 * pass. Safe because the swap only ever touches this site's own hosts.
		 */
		add_filter( 'script_loader_src', array( __CLASS__, 'filter_url' ) );
		add_filter( 'style_loader_src', array( __CLASS__, 'filter_url' ) );
		add_filter( 'redirect_canonical', array( __CLASS__, 'filter_canonical' ), 10, 2 );
	}

	/**
	 * Returns the page this request's host is the front page of.
	 *
	 * @return int Page ID, or 0 when this host is not mapped or not routable.
	 */
	public static function page_id(): int {
		if ( null !== self::$page_id ) {
			return self::$page_id;
		}

		// Seeded before resolving, not after: resolve() reads an option, and
		// an option read that came back through one of these filters would
		// otherwise recurse forever.
		self::$page_id = 0;
		self::$page_id = self::resolve();

		return self::$page_id;
	}

	/**
	 * Makes the site show a static front page.
	 *
	 * @param mixed $value Short circuit value.
	 * @return mixed 'page', or the value untouched.
	 */
	public static function filter_show_on_front( $value ) {
		return self::page_id() > 0 ? 'page' : $value;
	}

	/**
	 * Names the page to show.
	 *
	 * @param mixed $value Short circuit value.
	 * @return mixed Page ID, or the value untouched.
	 */
	public static function filter_page_on_front( $value ) {
		$page_id = self::page_id();

		return $page_id > 0 ? $page_id : $value;
	}

	/**
	 * Rewrites a URL of this site to the host the visitor is on.
	 *
	 * Only the host is replaced, and only when it is one of this site's own —
	 * the same filter runs over every enqueued script and stylesheet, and a
	 * URL pointing at a CDN or a third party has to come back untouched.
	 * Scheme and path are kept, so an install in a subdirectory or behind TLS
	 * keeps working, and running it twice changes nothing the second time.
	 *
	 * @param mixed $value A URL, or a stored option value.
	 * @return mixed Filtered URL.
	 */
	public static function filter_url( $value ) {
		if ( ! is_string( $value ) || '' === $value || self::page_id() <= 0 ) {
			return $value;
		}

		$host = Domain::current();

		if ( '' === $host ) {
			return $value;
		}

		$parts = wp_parse_url( $value );

		if ( empty( $parts['host'] ) ) {
			return $value;
		}

		if ( ! in_array( strtolower( (string) $parts['host'] ), self::original_hosts(), true ) ) {
			return $value;
		}

		return str_replace( '://' . $parts['host'], '://' . $host, $value );
	}

	/**
	 * The hosts this site is installed under, before any rewriting.
	 *
	 * Read with the two option filters lifted, because reading them through
	 * the filters would hand back the rewritten host and the comparison would
	 * never match.
	 *
	 * @return string[] Lower case host names.
	 */
	private static function original_hosts(): array {
		static $hosts = null;

		if ( null !== $hosts ) {
			return $hosts;
		}

		remove_filter( 'option_home', array( __CLASS__, 'filter_url' ) );
		remove_filter( 'option_siteurl', array( __CLASS__, 'filter_url' ) );

		$found = array();

		foreach ( array( 'home', 'siteurl' ) as $option ) {
			$host = (string) wp_parse_url( (string) get_option( $option ), PHP_URL_HOST );

			if ( '' !== $host ) {
				$found[] = strtolower( $host );
			}
		}

		add_filter( 'option_home', array( __CLASS__, 'filter_url' ) );
		add_filter( 'option_siteurl', array( __CLASS__, 'filter_url' ) );

		$hosts = array_values( array_unique( $found ) );

		return $hosts;
	}

	/**
	 * Stops WordPress redirecting the mapped front page somewhere else.
	 *
	 * `redirect_canonical()` likes to send a request for `/` on to the "real"
	 * permalink of whatever is being shown. On a mapped domain that is a
	 * redirect off the domain the visitor typed, or a loop.
	 *
	 * Only the front page is exempted. Every other canonical redirect on the
	 * site is useful and stays.
	 *
	 * @param string|false $redirect_url  Where WordPress wants to go.
	 * @param string       $requested_url Where the visitor asked to go.
	 * @return string|false The redirect, or false to cancel it.
	 */
	public static function filter_canonical( $redirect_url, $requested_url ) {
		unset( $requested_url );

		if ( self::page_id() > 0 && is_front_page() ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Works out whether this request should be routed, and where.
	 *
	 * @return int Page ID, or 0.
	 */
	private static function resolve(): int {
		// Only public page views. In the admin, during Ajax and inside the
		// REST API the site has to stay on its own address, or the editor
		// would be talking to a different host than the one it was loaded
		// from.
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return 0;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 0;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 0;
		}

		$host = Domain::current();

		if ( '' === $host ) {
			return 0;
		}

		$page_id = Domain_Map::page_for( $host );

		if ( $page_id <= 0 ) {
			return 0;
		}

		/**
		 * Filters the page a host is routed to.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $page_id Page ID, or 0 for no routing.
		 * @param string $host    Host name of the current request.
		 */
		return (int) apply_filters( 'baukasten/multi_domain/page_id', $page_id, $host );
	}
}
