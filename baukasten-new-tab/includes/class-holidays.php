<?php
/**
 * The ten holidays the greeting knows.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the anchor dates of a year, and the holiday a week belongs to.
 *
 * No request goes out for any of this. Easter and Carnival are computed,
 * five dates are fixed, the Jewish ones come from `jewishtojd()` when
 * ext-calendar is there and from a table otherwise, and the Islamic ones come
 * from a table, because a lunar calendar that depends on sighting the moon
 * cannot be computed. Both tables are written by `bin/fetch-holidays.php`.
 *
 * A holiday owns the ISO week its anchor falls into, so the greeting changes
 * on Monday and not on the day itself.
 */
final class Holidays {

	/**
	 * The holidays, in the order that settles a tie.
	 */
	const KEYS = array(
		'carnival_cologne',
		'easter',
		'eid_al_fitr',
		'europe_day',
		'eid_al_adha',
		'rosh_hashanah',
		'yom_kippur',
		'german_unity',
		'christmas',
		'new_years_eve',
	);

	/**
	 * Days from Weiberfastnacht to Easter Sunday.
	 */
	const CARNIVAL_OFFSET = 52;

	/**
	 * Difference between the Gregorian year and the Hebrew year that starts in it.
	 */
	const HEBREW_OFFSET = 3761;

	/**
	 * Loaded tables, keyed by file.
	 *
	 * @var array<string, array<string, string[]>>
	 */
	private static array $tables = array();

	/**
	 * The anchor dates of one year.
	 *
	 * @param int $year Gregorian year.
	 * @return array<int, array{key: string, date: \DateTimeImmutable}> Anchors in table order. A lunar feast can appear twice.
	 */
	public static function anchors( int $year ): array {
		$timezone = wp_timezone();
		$easter   = self::easter( $year );
		$dates    = array(
			'carnival_cologne' => array( $easter->modify( '-' . self::CARNIVAL_OFFSET . ' days' )->format( 'Y-m-d' ) ),
			'easter'           => array( $easter->format( 'Y-m-d' ) ),
			'eid_al_fitr'      => self::from_table( 'islamic', 'eid_al_fitr', $year ),
			'europe_day'       => array( sprintf( '%04d-05-09', $year ) ),
			'eid_al_adha'      => self::from_table( 'islamic', 'eid_al_adha', $year ),
			'rosh_hashanah'    => self::jewish( 1, $year, 'rosh_hashanah' ),
			'yom_kippur'       => self::jewish( 10, $year, 'yom_kippur' ),
			'german_unity'     => array( sprintf( '%04d-10-03', $year ) ),
			'christmas'        => array( sprintf( '%04d-12-24', $year ) ),
			'new_years_eve'    => array( sprintf( '%04d-12-31', $year ) ),
		);

		$anchors = array();

		foreach ( self::KEYS as $key ) {
			foreach ( $dates[ $key ] as $date ) {
				$day = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $timezone );

				if ( $day instanceof \DateTimeImmutable ) {
					$anchors[] = array(
						'key'  => $key,
						'date' => $day,
					);
				}
			}
		}

		return $anchors;
	}

	/**
	 * The holiday whose week a day falls into.
	 *
	 * When two anchors share the week — in 2027 Rosh Hashanah is on 2 October
	 * and German Unity Day on the 3rd — the one closer to the day wins, and on
	 * a tie the one earlier in `KEYS`. The neighbouring years are asked as well:
	 * New Year's Eve can fall into week 1 of the following year.
	 *
	 * @param \DateTimeImmutable $day The day.
	 * @return array{key: string, date: \DateTimeImmutable}|null The holiday, or null.
	 */
	public static function for_week( \DateTimeImmutable $day ): ?array {
		$day   = $day->setTimezone( wp_timezone() )->setTime( 0, 0 );
		$week  = $day->format( 'o-W' );
		$year  = (int) $day->format( 'Y' );
		$found = array();

		foreach ( array( $year - 1, $year, $year + 1 ) as $candidate_year ) {
			foreach ( self::anchors( $candidate_year ) as $anchor ) {
				if ( $anchor['date']->format( 'o-W' ) !== $week ) {
					continue;
				}

				$found[] = array(
					'anchor'   => $anchor,
					'distance' => (int) $day->diff( $anchor['date'] )->days,
					'order'    => (int) array_search( $anchor['key'], self::KEYS, true ),
				);
			}
		}

		if ( array() === $found ) {
			return null;
		}

		usort(
			$found,
			static fn( array $a, array $b ): int => array( $a['distance'], $a['order'] ) <=> array( $b['distance'], $b['order'] )
		);

		return $found[0]['anchor'];
	}

	/**
	 * Easter Sunday of a year, Gregorian calendar.
	 *
	 * The anonymous algorithm in Meeus' form, written out instead of
	 * `easter_date()`: that needs ext-calendar, and it returns a timestamp at
	 * local midnight of the server, which is not necessarily the site's.
	 *
	 * @param int $year Gregorian year.
	 * @return \DateTimeImmutable Easter Sunday, in the site's time zone.
	 */
	public static function easter( int $year ): \DateTimeImmutable {
		$a = $year % 19;
		$b = intdiv( $year, 100 );
		$c = $year % 100;
		$d = intdiv( $b, 4 );
		$e = $b % 4;
		$f = intdiv( $b + 8, 25 );
		$g = intdiv( $b - $f + 1, 3 );
		$h = ( 19 * $a + $b - $d - $g + 15 ) % 30;
		$i = intdiv( $c, 4 );
		$k = $c % 4;
		$l = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
		$m = intdiv( $a + 11 * $h + 22 * $l, 451 );

		$month = intdiv( $h + $l - 7 * $m + 114, 31 );
		$day   = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;

		return new \DateTimeImmutable( sprintf( '%04d-%02d-%02d', $year, $month, $day ), wp_timezone() );
	}

	/**
	 * A day of Tishri that falls into a Gregorian year.
	 *
	 * Tishri always falls into September or October, so the Hebrew year that
	 * begins in Gregorian year Y is Y + 3761.
	 *
	 * @param int    $day   Day of Tishri.
	 * @param int    $year  Gregorian year.
	 * @param string $key   Table key for the fallback.
	 * @return string[] `YYYY-MM-DD`, or nothing when neither source knows.
	 */
	private static function jewish( int $day, int $year, string $key ): array {
		if ( self::has_calendar() ) {
			list( $month, $mday, $gregorian_year ) = array_map( 'intval', explode( '/', jdtogregorian( jewishtojd( 1, $day, $year + self::HEBREW_OFFSET ) ) ) );

			return array( sprintf( '%04d-%02d-%02d', $gregorian_year, $month, $mday ) );
		}

		return self::from_table( 'jewish', $key, $year );
	}

	/**
	 * Whether ext-calendar is there to compute Jewish dates.
	 *
	 * @return bool True when `jewishtojd()` can be called.
	 */
	public static function has_calendar(): bool {
		/**
		 * Filters whether the Jewish dates are computed with ext-calendar.
		 *
		 * Return false to use the table; the integration test does, to check
		 * one against the other.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $computed True when ext-calendar is loaded.
		 */
		return (bool) apply_filters(
			'baukasten/new_tab/compute_jewish_dates',
			function_exists( 'jewishtojd' ) && function_exists( 'jdtogregorian' )
		);
	}

	/**
	 * The dates of a table entry that fall into a year.
	 *
	 * @param string $table `islamic` or `jewish`.
	 * @param string $key   Holiday key.
	 * @param int    $year  Gregorian year.
	 * @return string[] `YYYY-MM-DD`.
	 */
	private static function from_table( string $table, string $key, int $year ): array {
		$prefix = sprintf( '%04d-', $year );

		return array_values(
			array_filter(
				self::table( $table )[ $key ] ?? array(),
				static fn( string $date ): bool => str_starts_with( $date, $prefix )
			)
		);
	}

	/**
	 * One of the generated tables.
	 *
	 * @param string $table `islamic` or `jewish`.
	 * @return array<string, string[]> Dates keyed by holiday.
	 */
	public static function table( string $table ): array {
		if ( ! isset( self::$tables[ $table ] ) ) {
			$file = PLUGIN_DIR . 'data/holidays-' . sanitize_key( $table ) . '.php';
			$data = is_readable( $file ) ? require $file : array();

			self::$tables[ $table ] = is_array( $data ) ? array_map( static fn( $dates ): array => array_map( 'strval', (array) $dates ), $data ) : array();
		}

		return self::$tables[ $table ];
	}
}
