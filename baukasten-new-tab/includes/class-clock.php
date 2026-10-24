<?php
/**
 * Today, as far as the greeting is concerned.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * The current date in the site's time zone, with a way to fake it.
 *
 * Ten holidays a year are not something anyone waits for to test a change.
 * `?bk_date=YYYY-MM-DD` moves the page to another day, for administrators
 * only: for anybody else it would be a way to make the page say things on
 * the wrong day, which is harmless, but there is no reason to offer it.
 */
final class Clock {

	/**
	 * Query argument that overrides the date.
	 */
	const QUERY_ARG = 'bk_date';

	/**
	 * The moment the page is rendered for.
	 *
	 * @return \DateTimeImmutable Now, in the site's time zone.
	 */
	public static function now(): \DateTimeImmutable {
		$timezone = wp_timezone();
		$now      = new \DateTimeImmutable( 'now', $timezone );
		$override = self::override();

		if ( '' !== $override ) {
			$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $override, $timezone );

			if ( $date instanceof \DateTimeImmutable && $date->format( 'Y-m-d' ) === $override ) {
				$now = $date->setTime( 12, 0 );
			}
		}

		/**
		 * Filters the moment the New Tab Page is rendered for.
		 *
		 * Moves the greeting to another day; useful in tests.
		 *
		 * @since 1.0.0
		 *
		 * @param \DateTimeImmutable $now Now, in the site's time zone.
		 */
		$filtered = apply_filters( 'baukasten/new_tab/now', $now );

		return $filtered instanceof \DateTimeImmutable ? $filtered->setTimezone( $timezone ) : $now;
	}

	/**
	 * The date an administrator asked for, if any.
	 *
	 * @return string `YYYY-MM-DD`, or an empty string.
	 */
	public static function override(): string {
		// A read-only display parameter that changes nothing that is stored.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ self::QUERY_ARG ] ) || ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$value = sanitize_text_field( wp_unslash( (string) $_GET[ self::QUERY_ARG ] ) );

		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}
}
