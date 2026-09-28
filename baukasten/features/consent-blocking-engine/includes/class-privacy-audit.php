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
 *
 * The same goes for what gives away who runs the site: the REST user list,
 * author archives and the author fields of oEmbed responses all publish the
 * slug an administrator logs in with, and XML-RPC offers a second door to
 * the login form. And for updates, which are the one outgoing request a
 * site should always make: with `auto_update_all` every plugin and theme,
 * installed now or later, is updated automatically.
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

		// Updates run from WP-Cron, where is_admin() is false.
		if ( Settings::enabled( 'auto_update_all' ) ) {
			add_filter( 'auto_update_plugin', '__return_true', PHP_INT_MAX );
			add_filter( 'auto_update_theme', '__return_true', PHP_INT_MAX );
		}

		if ( Settings::enabled( 'hide_rest_users' ) ) {
			add_filter( 'rest_pre_dispatch', array( __CLASS__, 'hide_rest_users' ), 10, 3 );
		}

		if ( Settings::enabled( 'disable_author_archives' ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'disable_author_archives' ), 1 );
		}

		if ( Settings::enabled( 'strip_oembed_author' ) ) {
			add_filter( 'oembed_response_data', array( __CLASS__, 'strip_oembed_author' ), PHP_INT_MAX );
		}

		if ( Settings::enabled( 'disable_users_sitemap' ) ) {
			add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'remove_users_sitemap' ), 10, 2 );
		}

		if ( Settings::enabled( 'disable_xmlrpc' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'xmlrpc_methods', '__return_empty_array', PHP_INT_MAX );
			add_filter( 'wp_headers', array( __CLASS__, 'remove_pingback_header' ) );
			remove_action( 'wp_head', 'rsd_link' );
		}

		if ( Settings::enabled( 'disable_application_passwords' ) ) {
			add_filter( 'wp_is_application_passwords_available', '__return_false' );
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
	 * Answers the user endpoints with "no route" for anyone not logged in.
	 *
	 * Runs on `rest_pre_dispatch`, after authentication, so a request with a
	 * cookie but without a valid nonce is already treated as logged out. The
	 * block editor keeps its access: it is always logged in.
	 *
	 * @param mixed            $result  Response to short-circuit with, or null.
	 * @param \WP_REST_Server  $server  Server instance.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed Unchanged, or an error for the user routes.
	 */
	public static function hide_rest_users( $result, $server, $request ) {
		unset( $server );

		if ( null !== $result || is_user_logged_in() || ! $request instanceof \WP_REST_Request ) {
			return $result;
		}

		if ( ! preg_match( '#^/wp/v2/users(?:/|$)#', $request->get_route() ) ) {
			return $result;
		}

		return new \WP_Error(
			'rest_no_route',
			__( 'No route was found matching the URL and request method.', 'baukasten' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Answers author archives, `?author=N` included, with a 404.
	 *
	 * Runs before `redirect_canonical()`, which would otherwise turn
	 * `?author=1` into a redirect naming the author's slug.
	 *
	 * @return void
	 */
	public static function disable_author_archives(): void {
		global $wp_query;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check.
		if ( ! is_author() && ! isset( $_GET['author'] ) ) {
			return;
		}

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Removes the author fields from oEmbed responses.
	 *
	 * @param array<string, mixed> $data Response data.
	 * @return array<string, mixed> Data without author name and URL.
	 */
	public static function strip_oembed_author( array $data ): array {
		unset( $data['author_name'], $data['author_url'] );

		return $data;
	}

	/**
	 * Drops the users sitemap.
	 *
	 * @param \WP_Sitemaps_Provider|false $provider Provider instance.
	 * @param string                      $name     Provider name.
	 * @return \WP_Sitemaps_Provider|false False for the users provider.
	 */
	public static function remove_users_sitemap( $provider, string $name ) {
		return 'users' === $name ? false : $provider;
	}

	/**
	 * Removes the X-Pingback header, which advertises the XML-RPC endpoint.
	 *
	 * @param array<string, string> $headers Response headers.
	 * @return array<string, string> Headers without X-Pingback.
	 */
	public static function remove_pingback_header( array $headers ): array {
		unset( $headers['X-Pingback'] );

		return $headers;
	}

	/**
	 * Removes the dashboard widgets that fetch from WordPress.org.
	 *
	 * "WordPress Events and News" calls api.wordpress.org on every dashboard
	 * load, sending the site's IP and, for the events list, its location.
	 *
	 * Update checks are deliberately left alone. Switching those off would
	 * stop security updates reaching the site, and no reading of data
	 * protection law makes that a good trade. `auto_update_all` goes the
	 * other way and installs them without waiting for anyone.
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
			esc_html__( 'This site is not served over HTTPS.', 'baukasten' ),
			esc_html(
				sprintf(
					/* translators: %s: comma separated list of option names. */
					__( 'Consent decisions, logins and everything else travel unencrypted while %s still use http.', 'baukasten' ),
					implode( ', ', $insecure )
				)
			),
			esc_url( admin_url( 'options-general.php' ) ),
			esc_html__( 'Change the site address', 'baukasten' )
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
