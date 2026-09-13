<?php
/**
 * The card route and its detached template.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * Serves a card without the active theme.
 *
 * A card is not a page of the site. It is a thing you hand to someone, it has
 * to look the same whatever theme the site wears, and it has to stay small on
 * a phone. So the template is swapped wholesale and the theme's assets are
 * taken back out of the queue.
 *
 * `wp_head()` and `wp_footer()` still run, because a contact form and its
 * captcha register themselves there and would otherwise never load. That means
 * the removal has to be selective, and it is done in two passes for a reason:
 * the theme's own files are recognisable by their URL, but the block editor's
 * global styles are registered with no `src` at all and carry their payload as
 * inline CSS, so nothing about them can be matched on a URL.
 */
final class Renderer {

	/**
	 * Handle prefix for this plugin's own front end assets.
	 */
	const HANDLE = 'baukasten-business-cards';

	/**
	 * Handle of the skin stylesheet.
	 */
	const SKIN_HANDLE = 'baukasten-business-cards-skin';

	/**
	 * Handle of the bundled QR encoder.
	 */
	const QR_HANDLE = 'baukasten-business-cards-qr';

	/**
	 * Version of the bundled QR encoder.
	 *
	 * Its own version, not the plugin's, so the file is only re-fetched when
	 * the library actually changes. It lives in `assets/js/lib/` rather than
	 * `assets/js/vendor/` because `.distignore` excludes any path segment
	 * called `vendor` — which would quietly leave it out of the release
	 * archive and ship a QR button that does nothing.
	 */
	const QR_VERSION = '1.4.4';

	/**
	 * Core handles that cannot be recognised by their URL.
	 *
	 * `global-styles` and its relatives are registered with `src` set to false
	 * and deliver their CSS inline, so the only handle on them is the name.
	 */
	const CORE_BLOCK_HANDLES = array(
		'wp-block-library',
		'wp-block-library-theme',
		'global-styles',
		'global-styles-css-custom-properties',
		'wp-global-styles-placeholder',
		'core-block-supports',
		'core-block-supports-duotone',
		'classic-theme-styles',
		'wp-emoji-styles',
		'wp-block-template-skip-link',
	);

	/**
	 * Registers the hooks the route needs.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp', array( __CLASS__, 'detach' ) );
		add_filter( 'template_include', array( __CLASS__, 'use_card_template' ), 99 );
	}

	/**
	 * Whether the current request is a card.
	 *
	 * @return bool True on a single card that is not being embedded.
	 */
	public static function is_card_route(): bool {
		return is_singular( Post_Type::POST_TYPE ) && ! is_embed();
	}

	/**
	 * Takes the theme apart, once it is clear this is a card.
	 *
	 * On `wp` rather than `template_redirect`: the admin bar decides whether to
	 * exist at `template_redirect` priority 0, and `wp` is the last hook before
	 * that.
	 *
	 * @return void
	 */
	public static function detach(): void {
		if ( ! self::is_card_route() ) {
			return;
		}

		// Nothing is instantiated when this returns false, so there is no
		// admin bar markup, no admin bar CSS and no `html { margin-top }` bump.
		add_filter( 'show_admin_bar', '__return_false' );

		if ( self::discourage_indexing() ) {
			add_filter( 'wp_robots', 'wp_robots_no_robots' );
		}

		self::strip_head();

		// After every theme and plugin has had its say. `wp_enqueue_scripts`
		// runs from `wp_head` priority 1, well before styles are printed or
		// inlined, so a dequeue here still takes effect.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'strip_queue' ), PHP_INT_MAX );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 5 );

		// Before the stylesheets, so neither costs the other a round trip.
		add_action( 'wp_head', array( __CLASS__, 'preload_fonts' ), 2 );
		add_action( 'wp_head', array( __CLASS__, 'print_theme_script' ), 3 );
	}

	/**
	 * Swaps in the card template.
	 *
	 * Priority 99 only to beat other plugins: a block theme's own template is
	 * chosen inside `get_single_template()`, before this filter is applied, so
	 * returning a path here already wins.
	 *
	 * @param mixed $template Template WordPress would have loaded.
	 * @return string Template to load.
	 */
	public static function use_card_template( $template ): string {
		if ( ! self::is_card_route() ) {
			return (string) $template;
		}

		$card_template = PLUGIN_DIR . 'templates/card-view.php';

		/**
		 * Filters the template a card is rendered with.
		 *
		 * @since 1.0.0
		 *
		 * @param string $card_template Absolute path to the template.
		 */
		$card_template = (string) apply_filters( 'baukasten/business_cards/template', $card_template );

		if ( ! is_readable( $card_template ) ) {
			return (string) $template;
		}

		/*
		 * `locate_block_template()` adds this from inside `get_single_template()`,
		 * which runs before this filter and after `wp` — so this is the first
		 * point at which it can be removed. The card template writes its own
		 * viewport tag, with `viewport-fit=cover` on it, and two of them is a
		 * coin toss over which one the browser honours.
		 */
		remove_action( 'wp_head', '_block_template_viewport_meta_tag', 0 );

		return $card_template;
	}

	/**
	 * Loads the card's own assets.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		$card = Fields::load( get_queried_object_id() );
		$skin = Skins::get( (string) $card['card_layout'] );

		wp_enqueue_style( self::HANDLE, PLUGIN_URL . 'assets/css/card.css', array(), VERSION );

		/*
		 * Two files rather than one on purpose. The skeleton is byte-identical
		 * for every card on the site, so a visitor who opens two cards of
		 * different designs downloads it once; and a skin file is the unit a
		 * site forks when it wants a fourth design.
		 */
		wp_enqueue_style(
			self::SKIN_HANDLE,
			(string) $skin['stylesheet'],
			array( self::HANDLE ),
			VERSION
		);

		$needs_script = isset( $card['show_qr_modal'] ) || isset( $card['show_theme_toggle'] );

		if ( ! $needs_script ) {
			return;
		}

		$dependencies = array();

		if ( isset( $card['show_qr_modal'] ) ) {
			wp_enqueue_script( self::QR_HANDLE, PLUGIN_URL . 'assets/js/lib/qrcode.js', array(), self::QR_VERSION, true );

			$dependencies[] = self::QR_HANDLE;
		}

		wp_enqueue_script( self::HANDLE, PLUGIN_URL . 'assets/js/card.js', $dependencies, VERSION, true );
	}

	/**
	 * Preloads the two faces the card paints with.
	 *
	 * `crossorigin` is not optional and is not about the origin: a font is
	 * fetched in CORS mode wherever it comes from, and a preload without it
	 * fetches the file a second time instead of matching the one already on
	 * its way.
	 *
	 * @return void
	 */
	public static function preload_fonts(): void {
		if ( ! self::is_card_route() ) {
			return;
		}

		$card = Fields::load( get_queried_object_id() );
		$skin = Skins::get( (string) $card['card_layout'] );

		foreach ( (array) $skin['fonts'] as $font ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin />',
				esc_url( PLUGIN_URL . 'assets/fonts/' . (string) $font )
			);
		}
	}

	/**
	 * Settles light or dark before the first paint.
	 *
	 * Deliberately blocking and deliberately in the head. The stylesheet can
	 * express "the reader's setting, unless the switch says otherwise"; it
	 * cannot read `localStorage`, so a stored choice would arrive one paint
	 * late — and on a design that is dark by default, that paint is a white
	 * flash in someone's face.
	 *
	 * It writes `data-theme` unconditionally, which is also what makes the
	 * toggle honest: with the attribute always present, `card.js` never has to
	 * guess what the cascade decided.
	 *
	 * @return void
	 */
	public static function print_theme_script(): void {
		if ( ! self::is_card_route() ) {
			return;
		}

		$script = '(function(d){var r=d.documentElement,'
			. 'p=r.getAttribute("data-bkbc-scheme")||"system",'
			. 'f=r.getAttribute("data-bkbc-default")||"light",t="";'
			. 'if("light"===p||"dark"===p){t=p;}'
			. 'else{try{t=window.localStorage.getItem("baukasten-card-theme")||"";}catch(e){}'
			. 'if("light"!==t&&"dark"!==t){t=window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":f;}}'
			. 'r.setAttribute("data-theme",t);}(document));';

		wp_print_inline_script_tag( $script, array( 'id' => self::HANDLE . '-theme' ) );
	}

	/**
	 * Removes everything that prints straight into the head.
	 *
	 * @return void
	 */
	private static function strip_head(): void {
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_action( 'wp_head', 'wp_resource_hints', 2 );
		remove_action( 'wp_head', 'wp_preload_resources', 1 );
		remove_action( 'wp_head', 'locale_stylesheet' );
		remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );

		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		// theme.json. These build an expensive stylesheet and print it
		// themselves, so removing the callback beats dequeueing afterwards.
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
		remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_stored_styles' );
		remove_action( 'wp_footer', 'wp_enqueue_stored_styles', 1 );
		remove_action( 'wp_head', 'wp_print_font_faces', 50 );

		/*
		 * The one that bites. On a block theme `wp_enqueue_global_styles()`
		 * unhooks `wp_custom_css_cb` itself and folds the Customizer CSS into
		 * the global stylesheet. Having just removed that function, nothing
		 * unhooks it any more, and the Customizer CSS would print onto a card
		 * that has nothing else of the theme left on it.
		 */
		remove_action( 'wp_head', 'wp_custom_css_cb', 101 );

		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_block_template_skip_link' );
		remove_action( 'wp_footer', 'the_block_template_skip_link' );
	}

	/**
	 * Drops every queued asset that belongs to the theme.
	 *
	 * @return void
	 */
	public static function strip_queue(): void {
		$styles = wp_styles();

		foreach ( (array) $styles->queue as $handle ) {
			if ( ! self::keeps( $styles, (string) $handle ) ) {
				wp_dequeue_style( (string) $handle );
			}
		}

		$scripts = wp_scripts();

		foreach ( (array) $scripts->queue as $handle ) {
			if ( ! self::keeps( $scripts, (string) $handle ) ) {
				wp_dequeue_script( (string) $handle );
			}
		}
	}

	/**
	 * Whether a queued asset survives on a card.
	 *
	 * Anything not recognisable as the theme's is kept, deliberately: a card
	 * can carry a contact form and a captcha from plugins whose handles cannot
	 * be known in advance, and losing those silently would be worse than
	 * leaving a stray stylesheet.
	 *
	 * @param mixed  $deps   The style or script registry.
	 * @param string $handle Handle to test.
	 * @return bool True to keep it.
	 */
	private static function keeps( $deps, string $handle ): bool {
		if ( in_array( $handle, self::CORE_BLOCK_HANDLES, true ) ) {
			return false;
		}

		if ( str_starts_with( $handle, self::HANDLE ) ) {
			return true;
		}

		if ( ! $deps instanceof \WP_Dependencies || ! isset( $deps->registered[ $handle ] ) ) {
			return true;
		}

		$src = $deps->registered[ $handle ]->src;

		if ( ! is_string( $src ) || '' === $src ) {
			// Registered without a file: an inline-only handle, which is never
			// the theme's stylesheet.
			return true;
		}

		if ( ! preg_match( '#^(https?:)?//#', $src ) ) {
			$src = $deps->base_url . $src;
		}

		$src = set_url_scheme( $src );

		foreach ( self::theme_roots() as $root ) {
			if ( str_starts_with( $src, set_url_scheme( $root ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Every URL a theme's files can live under.
	 *
	 * A site can register more than one theme root, so `get_theme_root_uri()`
	 * on its own is not enough.
	 *
	 * @return string[] Root URLs, without a trailing slash.
	 */
	private static function theme_roots(): array {
		$roots = array(
			get_theme_root_uri(),
			get_stylesheet_directory_uri(),
			get_template_directory_uri(),
		);

		foreach ( array_unique( (array) get_theme_roots() ) as $root ) {
			$roots[] = content_url( ltrim( (string) $root, '/' ) );
		}

		$roots = array_map( 'untrailingslashit', array_map( 'strval', $roots ) );

		return array_values( array_unique( array_filter( $roots ) ) );
	}

	/**
	 * Whether cards should be kept out of search engines and the sitemap.
	 *
	 * A card's address is eight random characters precisely so that it is not
	 * findable, which a listing in the sitemap would undo in an afternoon.
	 *
	 * @return bool True to discourage indexing.
	 */
	public static function discourage_indexing(): bool {
		/**
		 * Filters whether cards are kept out of search engines and the sitemap.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $discourage True to send `noindex` and skip the sitemap.
		 */
		return (bool) apply_filters( 'baukasten/business_cards/discourage_indexing', true );
	}
}
