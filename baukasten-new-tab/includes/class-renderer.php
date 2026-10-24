<?php
/**
 * The page itself.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * Takes the selected page over and serves its own minimal HTML.
 *
 * A browser start page is opened dozens of times a day and has one job, so
 * it carries nothing of the site: no `wp_head()`, no `wp_footer()`, no theme
 * stylesheet, no block CSS, no admin bar. Business Cards detaches the theme
 * too but keeps those two calls, because a contact form registers itself
 * there; this page has nothing that needs them. That also keeps Statify's
 * script out, and its server-side counter is told to skip the page through
 * `statify__skip_tracking`: a new tab is not a visit.
 *
 * Order matters on `template_redirect`. Content Visibility guards private
 * content at priority 0, so a private new tab page sends a logged-out
 * visitor to the login screen before anything here runs. This class follows
 * at priority 1 — it answers the search without JavaScript and sends the
 * page's headers — and `template_include` swaps the template last.
 */
final class Renderer {

	/**
	 * The style that shows the background without JavaScript.
	 *
	 * The photo waits invisible until the script has decoded it, so it can
	 * fade in instead of building up line by line. Without the script that
	 * would be never; this rule, inside `<noscript>`, shows it right away.
	 */
	const NOJS_STYLE = '.bk-bg__img{opacity:1}.bk-bar__more,.bk-engine{display:none}';

	/**
	 * Values the template prints, built once per request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $view = null;

	/**
	 * The address an entry pointed at, for the interstitial page.
	 *
	 * @var string
	 */
	private static string $address = '';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp', array( __CLASS__, 'detach' ) );
		add_action( 'template_redirect', array( __CLASS__, 'take_over' ), 1 );
		add_filter( 'template_include', array( __CLASS__, 'use_template' ), 99 );
	}

	/**
	 * Whether this request is the new tab page.
	 *
	 * `is_page()` is true for the page whether it is reached by its own
	 * address or as the homepage, and whatever domain Multi-Domain serves it
	 * on.
	 *
	 * @return bool True on the selected page.
	 */
	public static function is_new_tab(): bool {
		$page_id = Settings::page_id();

		return 0 < $page_id && is_page( $page_id ) && ! is_embed() && ! is_feed();
	}

	/**
	 * Switches off what would otherwise attach itself to the page.
	 *
	 * On `wp`: the admin bar decides whether to exist at `template_redirect`
	 * priority 0, and Statify counts on `template_redirect` priority 10.
	 *
	 * @return void
	 */
	public static function detach(): void {
		if ( ! self::is_new_tab() ) {
			return;
		}

		add_filter( 'show_admin_bar', '__return_false' );
		add_filter( 'statify__skip_tracking', '__return_true' );
	}

	/**
	 * Answers a search without JavaScript, and sends the page's headers.
	 *
	 * @return void
	 */
	public static function take_over(): void {
		if ( ! self::is_new_tab() ) {
			return;
		}

		// A search is a GET by nature, and forwards to a host from the
		// settings only; there is nothing here a nonce would protect.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$entry  = isset( $_GET['q'] ) ? Search::clean_entry( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised by clean_entry().
		$engine = isset( $_GET['e'] ) ? sanitize_key( wp_unslash( (string) $_GET['e'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' !== trim( $entry ) ) {
			$search = Search::parse( $entry, $engine );

			// An `@` shortcut makes it a search, whatever follows.
			$address = trim( $entry ) === $search['terms'] ? Search::address( $entry ) : '';

			if ( '' !== $address ) {
				self::$address = $address;
			} else {
				self::forward( Search::url( $search['engine'], $search['terms'] ) );
			}
		}

		self::$view = self::build_view();

		self::send_headers( self::$view );
	}

	/**
	 * Forwards a search to its engine, and ends the request.
	 *
	 * `wp_safe_redirect()` with exactly the engine's host allowed, so nothing
	 * from the request can widen where this goes.
	 *
	 * @param string $url Engine URL.
	 * @return void
	 */
	private static function forward( string $url ): void {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );

		if ( '' === $host ) {
			return;
		}

		add_filter(
			'allowed_redirect_hosts',
			static function ( $hosts ) use ( $host ): array {
				$hosts   = (array) $hosts;
				$hosts[] = $host;

				return $hosts;
			}
		);

		header( 'Referrer-Policy: no-referrer' );
		header( 'Cache-Control: no-store, private' );
		header( 'X-Robots-Tag: noindex, nofollow' );

		if ( wp_safe_redirect( $url, 303, 'Baukasten New Tab Page' ) ) {
			exit;
		}
	}

	/**
	 * Swaps in the page's template.
	 *
	 * @param mixed $template Template WordPress would have loaded.
	 * @return string Template to load.
	 */
	public static function use_template( $template ): string {
		if ( ! self::is_new_tab() || null === self::$view ) {
			return (string) $template;
		}

		$file = PLUGIN_DIR . ( '' !== self::$address ? 'templates/address.php' : 'templates/new-tab.php' );

		/**
		 * Filters the template the New Tab Page is rendered with.
		 *
		 * @since 1.0.0
		 *
		 * @param string $file    Absolute path to the template.
		 * @param string $address The address an entry pointed at, or an empty string.
		 */
		$file = (string) apply_filters( 'baukasten/new_tab/template', $file, self::$address );

		return is_readable( $file ) ? $file : (string) $template;
	}

	/**
	 * The values the template prints.
	 *
	 * @return array<string, mixed> View.
	 */
	public static function view(): array {
		if ( null === self::$view ) {
			self::$view = self::build_view();
		}

		return self::$view;
	}

	/**
	 * The address the interstitial page links to.
	 *
	 * @return string URL.
	 */
	public static function address(): string {
		return self::$address;
	}

	/**
	 * Gathers everything the page shows.
	 *
	 * @return array<string, mixed> View.
	 */
	private static function build_view(): array {
		$page_id    = Settings::page_id();
		$greeting   = Greetings::for_day( Clock::now() );
		$background = Backgrounds::initial();
		$favorites  = array();

		foreach ( (array) Settings::get( 'favorites' ) as $favorite ) {
			if ( empty( $favorite['url'] ) ) {
				continue;
			}

			$favorites[] = array(
				'title' => (string) $favorite['title'],
				'url'   => (string) $favorite['url'],
				'icon'  => Favicons::src( $favorite ),
			);
		}

		$color = sanitize_hex_color( (string) $background['color'] );
		$color = null !== $color && '' !== $color ? $color : Backgrounds::FALLBACK_COLOR;

		$config = array(
			'rest'          => array(
				'suggest'     => Consent::relative( rest_url( REST_Controller::NAMESPACE . '/suggest' ) ),
				'backgrounds' => Consent::relative( rest_url( REST_Controller::NAMESPACE . '/backgrounds' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
			),
			'engines'       => Search::for_script(),
			'defaultEngine' => Settings::default_engine(),
			'suggest'       => 'off' !== Settings::get( 'suggest' ),
			'fileEndings'   => Search::FILE_ENDINGS,
			'greeting'      => array(
				'holiday' => '' !== $greeting['holiday'],
				'pool'    => '' !== $greeting['holiday'] ? array() : Greetings::pool()['random'],
			),
			'background'    => array(
				'mode'     => (string) $background['mode'],
				'interval' => max( 0, (int) Settings::get( 'interval' ) ),
				'count'    => (int) ( $background['count'] ?? 0 ),
				'allowed'  => ! empty( $background['allowed'] ),
				'photo'    => $background['photo'] ?? null,
			),
			'i18n'          => array(
				/* translators: %s: search engine name. */
				'engine'           => __( 'Search engine: %s', 'baukasten-new-tab' ),
				/* translators: %s: search engine name. */
				'placeholder'      => __( 'Search with %s or enter address', 'baukasten-new-tab' ),
				/* translators: %s: search engine name. */
				'placeholderShort' => __( 'Search with %s', 'baukasten-new-tab' ),
				'more'             => __( 'More favourites', 'baukasten-new-tab' ),
				/* translators: 1: photographer, linked. 2: "Unsplash", linked. */
				'photoBy'          => __( 'Photo: %1$s on %2$s', 'baukasten-new-tab' ),
				'consentFail'      => __( 'The choice could not be saved. Please try again.', 'baukasten-new-tab' ),
			),
		);

		if ( 'unsplash' === $background['mode'] ) {
			$config['consent'] = Consent::for_script();
		}

		$style = ':root{--bk-dominant:' . $color . '}';

		return array(
			'page_id'    => $page_id,
			'title'      => trim( wp_strip_all_tags( get_the_title( $page_id ) ) ),
			'lang'       => (string) get_bloginfo( 'language' ),
			'action'     => (string) get_permalink( $page_id ),
			'greeting'   => $greeting,
			'background' => $background,
			'favorites'  => $favorites,
			'engines'    => Search::for_script(),
			'engine'     => Settings::default_engine(),
			'legal'      => Legal_Links::all(),
			'config'     => $config,
			'style'      => $style,
			'nojs_style' => self::NOJS_STYLE,
			'css'        => self::asset( 'assets/css/new-tab.css' ),
			'js'         => self::asset( 'assets/js/new-tab.js' ),
			'font'       => plugin_url( 'assets/fonts/fira-sans-latin-600.woff2' ),
		);
	}

	/**
	 * Sends the page's headers.
	 *
	 * The two inline styles are allowed by their hashes, not by
	 * `unsafe-inline`: one carries the colour of this request's photo, the
	 * other is the `<noscript>` rule above. Everything else is a file from
	 * this site. `images.unsplash.com` is only allowed in the mode that
	 * needs it.
	 *
	 * @param array<string, mixed> $view View.
	 * @return void
	 */
	private static function send_headers( array $view ): void {
		if ( headers_sent() ) {
			return;
		}

		header( 'Content-Security-Policy: ' . self::content_security_policy( $view ) );
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Cache-Control: no-cache, private' );
		header( 'X-Content-Type-Options: nosniff' );
	}

	/**
	 * Builds the Content Security Policy.
	 *
	 * @param array<string, mixed> $view View.
	 * @return string Policy.
	 */
	public static function content_security_policy( array $view ): string {
		$own = array_merge( array( "'self'" ), self::extra_origins() );
		$img = array_merge( $own, array( 'data:' ) );

		if ( 'unsplash' === ( $view['background']['mode'] ?? '' ) ) {
			$img[] = 'https://images.unsplash.com';
		}

		$style = array_merge(
			$own,
			array(
				self::hash( (string) $view['style'] ),
				self::hash( (string) $view['nojs_style'] ),
			)
		);

		$directives = array(
			'default-src'     => $own,
			'script-src'      => $own,
			'style-src'       => $style,
			'img-src'         => $img,
			'font-src'        => $own,
			'connect-src'     => array( "'self'" ),
			'form-action'     => array( "'self'", 'https:' ),
			'frame-ancestors' => array( "'none'" ),
			'base-uri'        => array( "'none'" ),
			'object-src'      => array( "'none'" ),
		);

		$policy = array();

		foreach ( $directives as $name => $sources ) {
			$policy[] = $name . ' ' . implode( ' ', array_unique( $sources ) );
		}

		return implode( '; ', $policy );
	}

	/**
	 * Origins of this site's files that are not the page's own host.
	 *
	 * Usually none. On a domain served by Multi-Domain, the media library can
	 * still answer on the main host, because the uploads URL is taken from
	 * `WP_CONTENT_URL`, which no filter reaches.
	 *
	 * @return string[] Origins.
	 */
	private static function extra_origins(): array {
		$host    = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_HOST'] ) ) ) : '';
		$uploads = wp_upload_dir( null, false );
		$origins = array();

		foreach ( array( plugin_url( '' ), (string) $uploads['baseurl'] ) as $url ) {
			$parts = wp_parse_url( $url );

			if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
				continue;
			}

			$authority = strtolower( $parts['host'] ) . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );

			if ( $authority !== $host ) {
				$origins[] = ( $parts['scheme'] ?? 'https' ) . '://' . $authority;
			}
		}

		return array_values( array_unique( $origins ) );
	}

	/**
	 * A CSP source for an inline style.
	 *
	 * @param string $content Exact contents of the `<style>` element.
	 * @return string `'sha256-…'`.
	 */
	public static function hash( string $content ): string {
		return "'sha256-" . base64_encode( hash( 'sha256', $content, true ) ) . "'"; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * URL of one of the page's files, versioned by its contents.
	 *
	 * The version changes with the file, so the server may tell browsers to
	 * keep it for a year (`assets/.htaccess`) without ever serving a stale
	 * copy after an update.
	 *
	 * @param string $path Path relative to the plugin directory.
	 * @return string URL.
	 */
	public static function asset( string $path ): string {
		return add_query_arg( 'ver', self::asset_version( $path ), plugin_url( $path ) );
	}

	/**
	 * The version string of one of the page's files.
	 *
	 * @param string $path Path relative to the plugin directory.
	 * @return string Version.
	 */
	public static function asset_version( string $path ): string {
		$file = PLUGIN_DIR . $path;

		if ( ! is_readable( $file ) ) {
			return VERSION;
		}

		return VERSION . '.' . substr( md5( (string) filemtime( $file ) . ':' . (string) filesize( $file ) ), 0, 8 );
	}

	/**
	 * Compresses the page when the server does not.
	 *
	 * The page is one of the most often loaded on the site, and the web
	 * server it was built for compresses nothing on its own.
	 *
	 * @return void
	 */
	public static function start_compression(): void {
		if ( headers_sent() || ! function_exists( 'ob_gzhandler' ) || ini_get( 'zlib.output_compression' ) ) {
			return;
		}

		ob_start( 'ob_gzhandler' );
	}
}
