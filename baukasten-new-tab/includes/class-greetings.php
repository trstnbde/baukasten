<?php
/**
 * The line above the search box.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * Picks the greeting: the holiday's, or one at random.
 *
 * The pool lives in `data/greetings-<locale>.php`, not in the .po file. A
 * joke is localised, not translated, and a translator handed "There is no
 * place like 127.0.0.1" as a string to translate can only make it worse.
 *
 * The server picks at random on every request. The browser remembers the
 * last three and swaps a repeat for another one from the pool, which is why
 * the pool travels with the page; on a holiday week there is nothing to
 * swap and the browser leaves the line alone.
 */
final class Greetings {

	/**
	 * Locale whose file is used when there is none for the current one.
	 */
	const FALLBACK_LOCALE = 'en_US';

	/**
	 * Loaded greetings, keyed by locale.
	 *
	 * @var array<string, array{random: string[], holidays: array<string, string>}>
	 */
	private static array $loaded = array();

	/**
	 * The greeting for a day.
	 *
	 * @param \DateTimeImmutable $day The day.
	 * @return array{text: string, holiday: string} Text, and the holiday key or an empty string.
	 */
	public static function for_day( \DateTimeImmutable $day ): array {
		$greetings = self::pool();
		$holiday   = Holidays::for_week( $day );

		/**
		 * Filters the holiday whose greeting the page shows.
		 *
		 * Return null for the random greeting instead.
		 *
		 * @since 1.0.0
		 *
		 * @param array{key: string, date: \DateTimeImmutable}|null $holiday The holiday of the week, or null.
		 * @param \DateTimeImmutable                                $day     The day.
		 */
		$holiday = apply_filters( 'baukasten/new_tab/holiday', $holiday, $day );

		if ( is_array( $holiday ) && isset( $holiday['key'], $holiday['date'], $greetings['holidays'][ $holiday['key'] ] ) && $holiday['date'] instanceof \DateTimeImmutable ) {
			return array(
				'text'    => self::fill( $greetings['holidays'][ $holiday['key'] ], $holiday['date'] ),
				'holiday' => (string) $holiday['key'],
			);
		}

		$random = $greetings['random'];

		return array(
			'text'    => array() === $random ? '' : $random[ wp_rand( 0, count( $random ) - 1 ) ],
			'holiday' => '',
		);
	}

	/**
	 * The greetings of the current locale.
	 *
	 * @return array{random: string[], holidays: array<string, string>} Random pool and holiday greetings.
	 */
	public static function pool(): array {
		$locale = determine_locale();

		if ( ! isset( self::$loaded[ $locale ] ) ) {
			$data = self::load( $locale );

			/**
			 * Filters the greetings.
			 *
			 * `random` is the pool picked from on an ordinary day, `holidays`
			 * the greeting per holiday key (see `Holidays::KEYS`).
			 *
			 * @since 1.0.0
			 *
			 * @param array{random: string[], holidays: array<string, string>} $data   Greetings.
			 * @param string                                                   $locale Locale they were loaded for.
			 */
			$data = (array) apply_filters( 'baukasten/new_tab/greetings', $data, $locale );

			self::$loaded[ $locale ] = array(
				'random'   => array_values( array_filter( array_map( 'strval', (array) ( $data['random'] ?? array() ) ) ) ),
				'holidays' => array_map( 'strval', (array) ( $data['holidays'] ?? array() ) ),
			);
		}

		return self::$loaded[ $locale ];
	}

	/**
	 * Reads the file for a locale, or the nearest one.
	 *
	 * `de_AT` and `de_DE_formal` get the German file, anything without a
	 * file of its language gets the English one.
	 *
	 * @param string $locale Locale.
	 * @return array<string, mixed> File contents.
	 */
	private static function load( string $locale ): array {
		$language   = strtolower( (string) strtok( $locale, '_' ) );
		$candidates = array_unique(
			array(
				$locale,
				$language . '_' . strtoupper( $language ),
				self::FALLBACK_LOCALE,
			)
		);

		foreach ( $candidates as $candidate ) {
			$file = PLUGIN_DIR . 'data/greetings-' . preg_replace( '/[^A-Za-z_]/', '', $candidate ) . '.php';

			if ( is_readable( $file ) ) {
				$data = require $file;

				return is_array( $data ) ? $data : array();
			}
		}

		return array();
	}

	/**
	 * Fills in the placeholders of a holiday greeting.
	 *
	 * Each is taken from the year of the anchor date, not of today, so that
	 * New Year's Eve still counts down to the right year when its week has
	 * already reached January.
	 *
	 * @param string             $text   Greeting.
	 * @param \DateTimeImmutable $anchor The holiday's anchor date.
	 * @return string Greeting.
	 */
	public static function fill( string $text, \DateTimeImmutable $anchor ): string {
		$year = (int) $anchor->format( 'Y' );

		return strtr(
			$text,
			array(
				'{hebrew_year}' => (string) ( $year + Holidays::HEBREW_OFFSET ),
				'{unity_years}' => (string) ( $year - 1990 ),
				'{next_year}'   => (string) ( $year + 1 ),
			)
		);
	}
}
