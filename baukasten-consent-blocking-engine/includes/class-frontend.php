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
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 1 );
	}

	/**
	 * Enqueues the bootstrap script and the placeholder styling.
	 *
	 * In the head, not the footer: everything it unblocks is in the document
	 * already, and waiting for the footer would show the placeholders first
	 * and then swap them, which flickers.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
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
				'nonce'    => wp_create_nonce( 'wp_rest' ),
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
	}
}
