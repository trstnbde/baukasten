<?php
/**
 * Privacy policy text (F8).
 *
 * @package Baukasten\FormPrivacy
 */

namespace Baukasten\FormPrivacy;

defined( 'ABSPATH' ) || exit;

/**
 * Suggests a paragraph for the privacy policy, written from the settings.
 *
 * It appears under Settings → Privacy → Policy Guide, where core collects what
 * plugins suggest. It says what this site actually does with contact form
 * data, so it changes when the settings do.
 */
final class Policy_Text {

	/**
	 * Registers the suggestion.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_init', array( __CLASS__, 'add' ) );
	}

	/**
	 * Hands the text to core.
	 *
	 * @return void
	 */
	public static function add(): void {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( 'Baukasten Addon: Form Privacy', wp_kses_post( wpautop( self::text(), false ) ) );
		}
	}

	/**
	 * The suggested text.
	 *
	 * @return string Plain paragraphs, separated by blank lines.
	 */
	public static function text(): string {
		$paragraphs = array();

		if ( Settings::has_flamingo() && Settings::enabled( 'store_submissions' ) ) {
			$paragraphs[] = __( 'When you send us a message through a contact form, we store what you entered in the form — typically your name, your email address and your message — together with the date and time, the address of the page you sent it from and the title of that page, so that we can answer and follow up on your message.', 'baukasten-form-privacy' );

			$paragraphs[] = sprintf(
				/* translators: %s: number of days. */
				_n(
					'Stored messages are deleted automatically after %s day.',
					'Stored messages are deleted automatically after %s days.',
					Settings::retention_days(),
					'baukasten-form-privacy'
				),
				number_format_i18n( Settings::retention_days() )
			);
		} else {
			$paragraphs[] = __( 'When you send us a message through a contact form, it is sent to us by email. The website itself does not keep a copy.', 'baukasten-form-privacy' );
		}

		if ( Settings::enabled( 'strip_ip' ) ) {
			$paragraphs[] = __( 'Your IP address is not collected when you send the form.', 'baukasten-form-privacy' );
		}

		$paragraphs[] = __( 'We do not keep an address book of people who contacted us.', 'baukasten-form-privacy' );

		$paragraphs[] = __( 'Our contact forms are protected against spam without an external service: no data is sent to a third party for that purpose, and no cookie is set.', 'baukasten-form-privacy' );

		return implode( "\n\n", $paragraphs );
	}
}
