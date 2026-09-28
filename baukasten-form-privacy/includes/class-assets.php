<?php
/**
 * Contact Form 7's assets, only where a form is (F1).
 *
 * @package Baukasten\FormPrivacy
 */

namespace Baukasten\FormPrivacy;

defined( 'ABSPATH' ) || exit;

/**
 * Loads Contact Form 7's script and stylesheet only on pages with a form, and
 * keeps the captcha services off every other page.
 *
 * Contact Form 7 enqueues its files on every page, and once Turnstile or
 * reCAPTCHA has keys it enqueues their scripts site-wide as well — which sends
 * every visitor's address to Cloudflare or Google on pages that have no form.
 * `wpcf7_load_js` and `wpcf7_load_css` switch the first part off; the second
 * is not covered by them, so those handles are dequeued here.
 *
 * Where a form is, is found out three ways:
 *
 * - `wpcf7_shortcode_callback`, which fires for every rendered form, the
 *   Contact Form 7 block included (it saves a shortcode). A block theme
 *   renders the whole template before `wp_head`, so on Twenty Twenty-Five and
 *   the like the form is known before `wp_enqueue_scripts` runs.
 * - For classic themes, which render after the head: a look at the content of
 *   the entry being shown, for the shortcode or the block.
 * - The `baukasten/form_privacy/page_has_form` filter, for plugins that print
 *   a form from a template of their own, as Business Cards does.
 *
 * Contact Form 7 registers its handles on `wp_enqueue_scripts`, so enqueueing
 * them from the shortcode callback of a block theme would come too early and
 * be ignored. The callback only takes note; the enqueueing happens on
 * `wp_enqueue_scripts` at priority 20, or right away when the form turns up
 * later than that.
 */
final class Assets {

	/**
	 * Captcha handles Contact Form 7 enqueues on every page.
	 *
	 * @var string[]
	 */
	const CAPTCHA_HANDLES = array( 'cloudflare-turnstile', 'google-recaptcha', 'wpcf7-recaptcha' );

	/**
	 * Whether a form was rendered on this request so far.
	 *
	 * @var bool
	 */
	private static bool $rendered = false;

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! Settings::enabled( 'assets_on_demand' ) ) {
			return;
		}

		add_filter( 'wpcf7_load_js', '__return_false' );
		add_filter( 'wpcf7_load_css', '__return_false' );
		add_action( 'wpcf7_shortcode_callback', array( __CLASS__, 'on_form' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'match_assets_to_page' ), 20 );
	}

	/**
	 * Whether this plugin decides where Contact Form 7's assets load.
	 *
	 * Business Cards asks, and leaves the job to this plugin when it does.
	 *
	 * @return bool True when F1 is active.
	 */
	public static function manages_cf7_assets(): bool {
		return Settings::enabled( 'assets_on_demand' ) && function_exists( 'wpcf7_enqueue_scripts' );
	}

	/**
	 * Takes note of a rendered form, and loads the assets if it is late.
	 *
	 * @return void
	 */
	public static function on_form(): void {
		self::$rendered = true;

		if ( did_action( 'wp_enqueue_scripts' ) ) {
			self::enqueue();
		}
	}

	/**
	 * Loads the assets on a page with a form, and drops the captchas elsewhere.
	 *
	 * @return void
	 */
	public static function match_assets_to_page(): void {
		if ( self::page_has_form() ) {
			self::enqueue();

			return;
		}

		foreach ( self::CAPTCHA_HANDLES as $handle ) {
			wp_dequeue_script( $handle );
		}
	}

	/**
	 * Whether the current page shows a form.
	 *
	 * @return bool True when one was rendered, is in the content, or a filter says so.
	 */
	public static function page_has_form(): bool {
		$has_form = self::$rendered || self::content_has_form();

		/**
		 * Filters whether the current page shows a Contact Form 7 form.
		 *
		 * For forms printed from a template rather than from the content.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $has_form Whether a form was found.
		 */
		return (bool) apply_filters( 'baukasten/form_privacy/page_has_form', $has_form );
	}

	/**
	 * Whether the entry being shown has the shortcode or the block in it.
	 *
	 * @return bool True for a singular view with a form in its content.
	 */
	private static function content_has_form(): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'contact-form-7' )
			|| has_block( 'contact-form-7/contact-form-selector', $post );
	}

	/**
	 * Enqueues Contact Form 7's script and stylesheet. Idempotent.
	 *
	 * @return void
	 */
	private static function enqueue(): void {
		if ( function_exists( 'wpcf7_enqueue_scripts' ) ) {
			wpcf7_enqueue_scripts();
		}

		if ( function_exists( 'wpcf7_enqueue_styles' ) ) {
			wpcf7_enqueue_styles();
		}
	}
}
