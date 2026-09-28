<?php
/**
 * No IP address (F2).
 *
 * @package Baukasten\FormPrivacy
 */

namespace Baukasten\FormPrivacy;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the sender's IP address out of Contact Form 7 entirely.
 *
 * `WPCF7_Submission::get_remote_ip_addr()` is the one place Contact Form 7
 * reads the address; everything else — the `[_remote_ip]` mail tag, the
 * Flamingo meta, the spam checks — asks it. Returning an empty string there
 * means the address is never collected, rather than collected and deleted.
 *
 * Side effects, listed on the settings tab too:
 *
 * - IP rules on the Disallowed Comment Keys list no longer match.
 * - `[_remote_ip]` is empty in mails.
 * - The address is no longer part of `posted_data_hash`.
 */
final class Remote_Ip {

	/**
	 * Registers the filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( Settings::enabled( 'strip_ip' ) ) {
			add_filter( 'wpcf7_remote_ip_addr', '__return_empty_string', PHP_INT_MAX );
		}
	}
}
