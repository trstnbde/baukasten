<?php
/**
 * The list of assets this site actually loads.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Remembers which script and style handles the front end enqueued.
 *
 * The settings screen needs something to put in its table, and there is no way
 * to ask WordPress "which handles does this site use" without rendering a page
 * — enqueueing is conditional, so the answer differs per template. So every
 * front end request notes what it printed, and the option grows into a picture
 * of the site.
 *
 * No scanning service is involved and nothing leaves the site. The option is
 * only written when a handle appears that was not in it, which on a settled
 * site is approximately never.
 */
final class Inventory {

	/**
	 * Option holding the seen handles.
	 */
	const OPTION = 'baukasten_consent_handles';

	/**
	 * Upper bound on stored handles, so a site generating dynamic handles
	 * cannot grow the option without limit.
	 */
	const LIMIT = 500;

	/**
	 * Registers the collector.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_footer', array( __CLASS__, 'collect' ), PHP_INT_MAX );
	}

	/**
	 * Records the handles printed on this request.
	 *
	 * @return void
	 */
	public static function collect(): void {
		if ( is_admin() ) {
			return;
		}

		$seen  = self::all();
		$found = array_merge( self::from_scripts(), self::from_styles() );
		$new   = array();

		// The plugin's own two handles are pinned to the necessary category
		// and cannot be reassigned, so offering a dropdown for them would only
		// invite a setting that is silently ignored.
		unset( $found[ Frontend::HANDLE ], $found[ Frontend::STYLE_HANDLE ] );

		foreach ( $found as $handle => $item ) {
			if ( ! isset( $seen[ $handle ] ) ) {
				$new[ $handle ] = $item;
			}
		}

		if ( array() === $new ) {
			return;
		}

		$merged = array_slice( $seen + $new, 0, self::LIMIT, true );

		ksort( $merged );

		update_option( self::OPTION, $merged, false );
	}

	/**
	 * Returns everything recorded so far.
	 *
	 * @return array<string, array{type: string, host: string}> Handles.
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Forgets everything recorded so far.
	 *
	 * @return void
	 */
	public static function reset(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Reads the printed script handles.
	 *
	 * @return array<string, array{type: string, host: string}> Handles.
	 */
	private static function from_scripts(): array {
		$scripts = wp_scripts();
		$found   = array();

		foreach ( (array) $scripts->done as $handle ) {
			$src = isset( $scripts->registered[ $handle ] ) ? (string) $scripts->registered[ $handle ]->src : '';

			$found[ (string) $handle ] = array(
				'type' => 'script',
				'host' => self::host( $src ),
			);
		}

		return $found;
	}

	/**
	 * Reads the printed style handles.
	 *
	 * @return array<string, array{type: string, host: string}> Handles.
	 */
	private static function from_styles(): array {
		$styles = wp_styles();
		$found  = array();

		foreach ( (array) $styles->done as $handle ) {
			$src = isset( $styles->registered[ $handle ] ) ? (string) $styles->registered[ $handle ]->src : '';

			$found[ (string) $handle ] = array(
				'type' => 'style',
				'host' => self::host( $src ),
			);
		}

		return $found;
	}

	/**
	 * Returns the host of an asset URL, empty for first-party assets.
	 *
	 * @param string $src Asset URL.
	 * @return string Host name, or an empty string.
	 */
	private static function host( string $src ): string {
		if ( '' === $src || ! Categories::is_external( $src ) ) {
			return '';
		}

		return (string) wp_parse_url( $src, PHP_URL_HOST );
	}
}
