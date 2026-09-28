<?php
/**
 * Data minimisation for stored submissions (F4).
 *
 * @package Baukasten\FormPrivacy
 */

namespace Baukasten\FormPrivacy;

defined( 'ABSPATH' ) || exit;

/**
 * Decides what Contact Form 7 hands Flamingo, and whether it hands anything.
 *
 * With every submission Contact Form 7 passes Flamingo nineteen pieces of
 * metadata — IP address, user agent, the logged-in user's name and address,
 * the site's title and admin address — plus the raw answers of Akismet,
 * reCAPTCHA and Turnstile. Only the keys on the whitelist are kept. The
 * captcha answers are dropped altogether.
 *
 * Runs at priority 20 on `wpcf7_flamingo_inbound_message_parameters`, after
 * Contact Form 7's own Turnstile module has added its entry on 10.
 *
 * The form's own means complement this and are explained on the tab:
 * `do_not_store: true` in a form's additional settings, and the form-tag
 * option `do-not-store` for a single field.
 */
final class Submissions {

	/**
	 * Registers the filters.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wpcf7_flamingo_inbound_message_parameters', array( __CLASS__, 'minimise' ), 20 );
		add_filter( 'wpcf7_flamingo_submit_if', array( __CLASS__, 'store_if' ), 20 );
	}

	/**
	 * Reduces a submission's metadata to the whitelist.
	 *
	 * @param mixed $args Arguments for `Flamingo_Inbound_Message::add()`.
	 * @return mixed Arguments with less in them.
	 */
	public static function minimise( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}

		$meta         = isset( $args['meta'] ) && is_array( $args['meta'] ) ? $args['meta'] : array();
		$args['meta'] = array_intersect_key( $meta, array_flip( Settings::meta_whitelist() ) );

		$args['akismet']   = array();
		$args['recaptcha'] = array();

		return $args;
	}

	/**
	 * The submission states Flamingo stores.
	 *
	 * @param mixed $cases States Contact Form 7 would store.
	 * @return array<int, string> States to store.
	 */
	public static function store_if( $cases ): array {
		if ( ! Settings::enabled( 'store_submissions' ) ) {
			return array();
		}

		$cases = array_values( array_map( 'strval', (array) $cases ) );

		if ( ! Settings::enabled( 'store_spam' ) ) {
			$cases = array_values( array_diff( $cases, array( 'spam' ) ) );
		}

		return $cases;
	}

	/**
	 * Reduces one stored message's metadata to the whitelist.
	 *
	 * Used by the cleanup for messages stored before this plugin was active.
	 *
	 * @param int $post_id Flamingo message post ID.
	 * @return bool True when anything was changed.
	 */
	public static function minimise_stored( int $post_id ): bool {
		$changed = false;
		$meta    = get_post_meta( $post_id, '_meta', true );

		if ( is_array( $meta ) ) {
			$kept = array_intersect_key( $meta, array_flip( Settings::meta_whitelist() ) );

			if ( $kept !== $meta ) {
				update_post_meta( $post_id, '_meta', $kept );
				$changed = true;
			}
		}

		foreach ( array( '_akismet', '_recaptcha' ) as $key ) {
			$value = get_post_meta( $post_id, $key, true );

			if ( ! empty( $value ) ) {
				update_post_meta( $post_id, $key, array() );
				$changed = true;
			}
		}

		return $changed;
	}
}
