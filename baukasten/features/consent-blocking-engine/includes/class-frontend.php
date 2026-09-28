<?php
/**
 * Front end assets.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the bootstrap script and the placeholder styling.
 *
 * The bootstrap script is the one asset on the page that must never be
 * blocked, so `Categories::for_handle()` pins it to the necessary category
 * whatever the settings say.
 */
final class Frontend {

	/**
	 * Handle of the bootstrap script. Never blocked.
	 */
	const HANDLE = 'baukasten-consent-bootstrap';

	/**
	 * Handle of the placeholder stylesheet.
	 */
	const STYLE_HANDLE = 'baukasten-consent-blocked';

	/**
	 * Whether the assets were enqueued on this request.
	 *
	 * @var bool
	 */
	private static bool $enqueued = false;

	/**
	 * Registers the hooks.
	 *
	 * Decides at the very end of `wp_enqueue_scripts`, once everything else
	 * has queued what it needs. Anything blocked later — an embed rendered in
	 * the content, a script printed from a shortcode — pulls the assets in
	 * through `need()` and they print in the footer instead.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ), PHP_INT_MAX );
	}

	/**
	 * Enqueues the assets when this page will need them.
	 *
	 * A page with nothing from another host and no embed has nothing to block
	 * and nothing to restore, so it gets neither the script nor the styles.
	 *
	 * @return void
	 */
	public static function maybe_enqueue(): void {
		if ( Settings::enabled( 'always_load_frontend' ) || self::queue_is_external() || self::has_embed() ) {
			self::enqueue();
		}
	}

	/**
	 * Called wherever something is blocked, so the page can restore it.
	 *
	 * @return void
	 */
	public static function need(): void {
		if ( ! self::$enqueued && did_action( 'wp_enqueue_scripts' ) ) {
			self::enqueue();
		}
	}

	/**
	 * Enqueues the bootstrap script and the placeholder styling.
	 *
	 * In the head, not the footer, whenever the decision falls before the
	 * head is printed: everything it unblocks is in the document already, and
	 * waiting for the footer would show the placeholders first and then swap
	 * them, which flickers. The script is moved to the front of the queue so
	 * it runs before anything it has to hold back.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		if ( self::$enqueued ) {
			return;
		}

		self::$enqueued = true;

		wp_enqueue_script(
			self::HANDLE,
			PLUGIN_URL . 'assets/js/consent-bootstrap.js',
			array(),
			VERSION,
			false
		);

		wp_localize_script(
			self::HANDLE,
			'baukastenConsent',
			array(
				'cookie'   => Consent_State::COOKIE,
				'restUrl'  => esc_url_raw( rest_url( REST_Controller::NAMESPACE ) ),
				'policy'   => Settings::policy_version(),
				'optional' => Categories::optional_slugs(),
			)
		);

		wp_enqueue_style(
			self::STYLE_HANDLE,
			PLUGIN_URL . 'assets/css/blocked.css',
			array(),
			VERSION
		);

		$scripts = wp_scripts();

		if ( ! did_action( 'wp_head' ) ) {
			$scripts->queue = array_values( array_unique( array_merge( array( self::HANDLE ), $scripts->queue ) ) );
		}
	}

	/**
	 * Whether anything queued so far, dependencies included, comes from
	 * another host.
	 *
	 * @return bool True if at least one script or stylesheet is external.
	 */
	private static function queue_is_external(): bool {
		foreach ( array( wp_scripts(), wp_styles() ) as $dependencies ) {
			$pending = (array) $dependencies->queue;
			$seen    = array();

			while ( array() !== $pending ) {
				$handle = (string) array_shift( $pending );

				if ( isset( $seen[ $handle ] ) || ! isset( $dependencies->registered[ $handle ] ) ) {
					continue;
				}

				$seen[ $handle ] = true;
				$item            = $dependencies->registered[ $handle ];
				$src             = is_string( $item->src ) ? $item->src : '';

				if ( '' !== $src && Categories::is_external( $src ) ) {
					return true;
				}

				$pending = array_merge( $pending, (array) $item->deps );
			}
		}

		return false;
	}

	/**
	 * Whether the entry being shown contains an embed.
	 *
	 * An embed block, or a URL on a line of its own, which WordPress turns
	 * into an embed when the content is rendered.
	 *
	 * @return bool True for a singular view with an embed in it.
	 */
	private static function has_embed(): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		return has_block( 'core/embed', $post )
			|| (bool) preg_match( '#^\s*https?://\S+\s*$#im', (string) $post->post_content );
	}
}
