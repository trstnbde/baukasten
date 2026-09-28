<?php
/**
 * Honeypot and minimum fill time (F9).
 *
 * @package Baukasten\FormPrivacy
 */

namespace Baukasten\FormPrivacy;

defined( 'ABSPATH' ) || exit;

/**
 * Spam protection with no request to anybody, no cookie and no JavaScript.
 *
 * Two checks, both answered by what the browser posts:
 *
 * - A text field, `_bk_hp`, that people never see and bots fill in. It is a
 *   real text field — bots skip `type="hidden"` — moved off screen with CSS
 *   rather than `display: none`, taken out of the tab order, hidden from
 *   assistive technology on its wrapper, and labelled "Leave this field
 *   empty" in case a screen reader reaches it anyway.
 * - A signed timestamp, `_bk_ts`, written when the form is rendered. A form
 *   sent back sooner than `min_fill_seconds` was not filled in by a person.
 *   There is no maximum age, so the form keeps working behind a page cache
 *   and in a tab left open overnight.
 *
 * Checked on `wpcf7_spam` at priority 8, before Contact Form 7's own checks on
 * 9 and 10. Fields whose name starts with an underscore never make it into
 * Contact Form 7's posted data, so neither shows up in a mail or in Flamingo.
 *
 * This does not stop a person sending spam by hand. Should measurable spam get
 * through, Contact Form 7's Turnstile module is the next step — a decision for
 * the site owner, because it brings in a third party.
 */
final class Honeypot {

	/**
	 * Name of the honeypot field.
	 */
	const FIELD = '_bk_hp';

	/**
	 * Name of the timestamp field.
	 */
	const TIMESTAMP = '_bk_ts';

	/**
	 * Agent name in Contact Form 7's spam log.
	 */
	const AGENT = 'baukasten-form-privacy';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! Settings::enabled( 'honeypot' ) ) {
			return;
		}

		add_filter( 'wpcf7_form_elements', array( __CLASS__, 'add_field' ) );
		add_filter( 'wpcf7_form_hidden_fields', array( __CLASS__, 'add_timestamp' ) );
		add_filter( 'wpcf7_spam', array( __CLASS__, 'check' ), 8, 2 );
	}

	/**
	 * Appends the honeypot to the form.
	 *
	 * @param mixed $elements Rendered form elements.
	 * @return string Elements with the honeypot.
	 */
	public static function add_field( $elements ): string {
		$field = sprintf(
			'<div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;"><label>%1$s <input type="text" name="%2$s" value="" tabindex="-1" autocomplete="off" /></label></div>',
			esc_html__( 'Leave this field empty', 'baukasten-form-privacy' ),
			esc_attr( self::FIELD )
		);

		return (string) $elements . $field;
	}

	/**
	 * Adds the signed render time to the form's hidden fields.
	 *
	 * @param mixed $fields Hidden fields.
	 * @return array<string, string> Hidden fields with the timestamp.
	 */
	public static function add_timestamp( $fields ): array {
		$fields = (array) $fields;
		$form   = class_exists( '\WPCF7_ContactForm' ) ? \WPCF7_ContactForm::get_current() : null;
		$id     = $form instanceof \WPCF7_ContactForm ? (int) $form->id() : 0;

		$fields[ self::TIMESTAMP ] = self::stamp( time(), $id );

		return $fields;
	}

	/**
	 * Builds a timestamp value.
	 *
	 * @param int $time    Unix timestamp.
	 * @param int $form_id Contact Form 7 form ID.
	 * @return string `{time}.{signature}`.
	 */
	public static function stamp( int $time, int $form_id ): string {
		return $time . '.' . wp_hash( $time . '|' . $form_id );
	}

	/**
	 * Marks a submission as spam when either check fails.
	 *
	 * @param mixed $spam       Spam verdict so far.
	 * @param mixed $submission The submission.
	 * @return bool True for spam.
	 */
	public static function check( $spam, $submission ): bool {
		if ( $spam ) {
			return true;
		}

		if ( ! $submission instanceof \WPCF7_Submission ) {
			return false;
		}

		$reason = self::reason( $submission );

		if ( '' === $reason ) {
			return false;
		}

		$submission->add_spam_log(
			array(
				'agent'  => self::AGENT,
				'reason' => $reason,
			)
		);

		return true;
	}

	/**
	 * Why a submission is spam, if it is.
	 *
	 * @param \WPCF7_Submission $submission The submission.
	 * @return string `honeypot`, `timestamp missing`, `timestamp invalid`, `too fast`, or ''.
	 */
	public static function reason( \WPCF7_Submission $submission ): string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Contact Form 7 verified the request; these are read-only checks.
		$honeypot = isset( $_POST[ self::FIELD ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::FIELD ] ) ) : '';
		$stamp    = isset( $_POST[ self::TIMESTAMP ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::TIMESTAMP ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$form = $submission->get_contact_form();

		return self::judge( $honeypot, $stamp, $form instanceof \WPCF7_ContactForm ? (int) $form->id() : 0, time() );
	}

	/**
	 * Why posted values are spam, if they are.
	 *
	 * @param string $honeypot Value of the honeypot field.
	 * @param string $stamp    Value of the timestamp field.
	 * @param int    $form_id  Form that was submitted.
	 * @param int    $now      Current Unix time.
	 * @return string `honeypot`, `timestamp missing`, `timestamp invalid`, `too fast`, or ''.
	 */
	public static function judge( string $honeypot, string $stamp, int $form_id, int $now ): string {
		if ( '' !== $honeypot ) {
			return 'honeypot';
		}

		if ( '' === $stamp ) {
			return 'timestamp missing';
		}

		$parts = explode( '.', $stamp, 2 );
		$time  = (int) $parts[0];

		if ( 2 !== count( $parts ) || ! hash_equals( self::stamp( $time, $form_id ), $stamp ) ) {
			return 'timestamp invalid';
		}

		if ( $now - $time < Settings::min_fill_seconds() ) {
			return 'too fast';
		}

		return '';
	}
}
