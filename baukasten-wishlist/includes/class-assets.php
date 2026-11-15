<?php
/**
 * The front end's stylesheet and script.
 *
 * @package Baukasten\Wishlist
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the handles early and enqueues them only where a list is shown.
 *
 * A block theme renders the content before `wp_head`, a classic theme after
 * it; enqueueing from inside the render covers both, because WordPress prints
 * a stylesheet enqueued after `wp_head` in the footer.
 */
final class Assets {

	/**
	 * Handle of both the stylesheet and the script.
	 */
	const HANDLE = 'baukasten-wishlist';

	/**
	 * Whether the configuration was already attached.
	 *
	 * @var bool
	 */
	private static bool $configured = false;

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_handles' ), 20 );
	}

	/**
	 * Registers the stylesheet and the script.
	 *
	 * Versions come from the files' modification time, so a changed file is
	 * never served from a stale cache.
	 *
	 * @return void
	 */
	public static function register_handles(): void {
		wp_register_style( self::HANDLE, PLUGIN_URL . 'assets/css/wishlist.css', array(), self::version( 'assets/css/wishlist.css' ) );

		wp_register_script(
			self::HANDLE,
			PLUGIN_URL . 'assets/js/wishlist.js',
			array(),
			self::version( 'assets/js/wishlist.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Enqueues both, once, with the script's configuration.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		if ( ! wp_style_is( self::HANDLE, 'registered' ) ) {
			self::register_handles();
		}

		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );

		if ( self::$configured ) {
			return;
		}

		self::$configured = true;

		wp_localize_script(
			self::HANDLE,
			'baukastenWishlist',
			array(
				'rest' => esc_url_raw( rest_url( REST_Controller::NAMESPACE . '/' ) ),
				'i18n' => array(
					/* translators: 1: number still wanted. 2: number wanted in all. */
					'remaining' => __( '%1$s of %2$s still wanted', 'baukasten-wishlist' ),
					'oneLeft'   => __( 'Still wanted', 'baukasten-wishlist' ),
					'done'      => __( 'Already bought', 'baukasten-wishlist' ),
					'thanks'    => __( 'Thank you! Everybody now sees that this one is taken care of.', 'baukasten-wishlist' ),
					'undone'    => __( 'Your report was taken back.', 'baukasten-wishlist' ),
					'error'     => __( 'That did not work. Please try again in a moment.', 'baukasten-wishlist' ),
					'soldOut'   => __( 'Somebody has already bought this.', 'baukasten-wishlist' ),
					'tooMany'   => __( 'Too many reports from this connection. Please wait a few minutes.', 'baukasten-wishlist' ),
					'closed'    => __( 'This list no longer takes reports.', 'baukasten-wishlist' ),
					/* translators: 1: wishes shown. 2: wishes on the list. */
					'shown'     => __( '%1$s of %2$s wishes shown', 'baukasten-wishlist' ),
					'nothing'   => __( 'No wish matches this filter.', 'baukasten-wishlist' ),
					'today'     => __( 'Today!', 'baukasten-wishlist' ),
					'tomorrow'  => __( 'Tomorrow', 'baukasten-wishlist' ),
					/* translators: %s: number of days. */
					'daysLeft'  => __( 'in %s days', 'baukasten-wishlist' ),
					'undo'      => __( 'Undo', 'baukasten-wishlist' ),
					'allDone'   => __( 'Everything on this list has been bought. Thank you!', 'baukasten-wishlist' ),
					// The script picks `mineOne` for one wish and `mineOther` for
					// more. Two forms are enough for the languages this plugin
					// ships; a language with more would need each count's own
					// form from PHP.
					/* translators: %s: number of wishes. */
					'mineOne'   => _n( 'You reported %s wish here as bought.', 'You reported %s wishes here as bought.', 1, 'baukasten-wishlist' ),
					/* translators: %s: number of wishes. */
					'mineOther' => _n( 'You reported %s wish here as bought.', 'You reported %s wishes here as bought.', 2, 'baukasten-wishlist' ),
					'mineShow'  => __( 'Show them', 'baukasten-wishlist' ),
				),
			)
		);
	}

	/**
	 * A cache-busting version for a file of this plugin.
	 *
	 * @param string $relative Path below the plugin directory.
	 * @return string Version.
	 */
	public static function version( string $relative ): string {
		$time = @filemtime( PLUGIN_DIR . $relative ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file falls back to the plugin version.

		return false === $time ? VERSION : VERSION . '.' . $time;
	}
}
