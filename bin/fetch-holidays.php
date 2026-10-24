<?php
/**
 * Writes the holiday tables of the New Tab Page addon.
 *
 * The page greets on ten holidays. Six are fixed or computed (Easter and
 * everything hanging off it), two follow the Jewish calendar, which is
 * arithmetic and computed at run time when ext-calendar is there, and two
 * follow the Islamic lunar calendar, which cannot be computed reliably at all.
 * Neither is fetched at run time: the addon makes no outbound request for a
 * greeting, so the dates are fetched here, once a year, and committed.
 *
 * - Eid al-Fitr (1 Shawwal) and Eid al-Adha (10 Dhu al-Hijjah) from AlAdhan,
 *   `calendarMethod=DIYANET` — the calendar of the Diyanet, which the DITIB
 *   congregations in Germany follow. AlAdhan declares that calendar valid
 *   only up to a date it publishes (January 2028 as of October 2026); after
 *   it, the script takes Umm al-Qura (`UAQ`), which gave the same dates as
 *   DIYANET for every feast from 1445 to 1448, and marks those lines. Running
 *   the script again once a year replaces them as the Diyanet range grows.
 * - Rosh Hashanah (1 Tishri) and Yom Kippur (10 Tishri) from Hebcal, whose
 *   content is CC BY 4.0. The table is the fallback for a server without
 *   ext-calendar and the reference the integration test checks
 *   `jewishtojd()` against.
 *
 * Usage: php -d extension=openssl bin/fetch-holidays.php [<first-year> <last-year>]
 *
 * Defaults to 2026–2040. Writes baukasten-new-tab/data/holidays-islamic.php
 * and baukasten-new-tab/data/holidays-jewish.php.
 *
 * @package Baukasten
 */

declare( strict_types = 1 );

$repo  = dirname( __DIR__ );
$first = isset( $argv[1] ) ? (int) $argv[1] : 2026;
$last  = isset( $argv[2] ) ? (int) $argv[2] : 2040;

if ( $first < 2000 || $last < $first || $last > 2100 ) {
	fwrite( STDERR, "Usage: php bin/fetch-holidays.php [<first-year> <last-year>]\n" );
	exit( 1 );
}

$data = $repo . '/baukasten-new-tab/data';

if ( ! is_dir( $data ) ) {
	fwrite( STDERR, "No directory at baukasten-new-tab/data\n" );
	exit( 1 );
}

/**
 * Fetches a URL and decodes its JSON body.
 *
 * @param string $url Address.
 * @return array<mixed> Decoded body.
 */
function fetch_json( string $url ): array {
	$context = stream_context_create(
		array(
			'http' => array(
				'timeout'       => 20,
				'ignore_errors' => true,
				'header'        => "Accept: application/json\r\nUser-Agent: baukasten-fetch-holidays (+https://github.com/trstnbde/baukasten)\r\n",
			),
		)
	);

	for ( $attempt = 1; $attempt <= 3; $attempt++ ) {
		$body = @file_get_contents( $url, false, $context ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( is_string( $body ) ) {
			$decoded = json_decode( $body, true );

			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		sleep( $attempt );
	}

	fwrite( STDERR, "Could not fetch $url\n" );
	exit( 1 );
}

/**
 * Renders a PHP file that returns an array.
 *
 * @param string                $comment Docblock lines, without the comment markers.
 * @param array<string, mixed>  $table   The table.
 * @param array<string, string> $notes   Optional. Line comment per date.
 * @return string File contents.
 */
function render_table( string $comment, array $table, array $notes = array() ): string {
	$lines = array( '<?php', '/**' );

	foreach ( explode( "\n", $comment ) as $line ) {
		$lines[] = rtrim( ' * ' . $line );
	}

	$lines[] = ' *';
	$lines[] = ' * @package Baukasten\NewTab';
	$lines[] = ' */';
	$lines[] = '';
	$lines[] = "defined( 'ABSPATH' ) || exit;";
	$lines[] = '';
	$lines[] = 'return array(';

	foreach ( $table as $key => $dates ) {
		$lines[] = "\t'" . $key . "' => array(";

		foreach ( $dates as $date ) {
			$lines[] = "\t\t'" . $date . "'," . ( isset( $notes[ $date ] ) ? ' // ' . $notes[ $date ] . '.' : '' );
		}

		$lines[] = "\t),";
	}

	$lines[] = ');';

	return implode( "\n", $lines ) . "\n";
}

/**
 * Converts a Hijri date with one of AlAdhan's calendars.
 *
 * @param string $hijri  `DD-MM-YYYY`.
 * @param string $method AlAdhan calendar method.
 * @return string `YYYY-MM-DD`, or an empty string when AlAdhan has no answer.
 */
function hijri_to_gregorian( string $hijri, string $method ): string {
	$answer = fetch_json( 'https://api.aladhan.com/v1/hToG/' . $hijri . '?calendarMethod=' . $method );
	$date   = (string) ( $answer['data']['gregorian']['date'] ?? '' );

	usleep( 250000 );

	if ( ! preg_match( '/^(\d{2})-(\d{2})-(\d{4})$/', $date, $parts ) ) {
		return '';
	}

	return $parts[3] . '-' . $parts[2] . '-' . $parts[1];
}

$today = gmdate( 'Y-m-d' );

/*
 * Islamic: walk the Hijri years that can touch the range and keep the dates
 * that fall into it. A Hijri year is eleven days shorter than a Gregorian
 * one, so every 33 years a feast falls twice into the same Gregorian year;
 * keyed by feast, not by year, that needs no special case.
 */
$islamic = array(
	'eid_al_fitr' => array(),
	'eid_al_adha' => array(),
);

/*
 * Where AlAdhan stops vouching for DIYANET. Its method list says so in prose,
 * "… to 29 Şaban 1449 AH (26 January 2028)"; the Gregorian date in the last
 * parentheses is the one that matters.
 */
$methods        = fetch_json( 'https://api.aladhan.com/v1/islamicCalendar/methods' );
$diyanet_valid  = '';

foreach ( (array) ( $methods['data'] ?? array() ) as $method ) {
	if ( 'DIYANET' === ( $method['id'] ?? '' ) && preg_match( '/\(([^()]+)\)\s*$/', (string) ( $method['validity'] ?? '' ), $match ) ) {
		$time = strtotime( $match[1] . ' UTC' );

		if ( false !== $time ) {
			$diyanet_valid = gmdate( 'Y-m-d', $time );
		}
	}
}

if ( '' === $diyanet_valid ) {
	fwrite( STDERR, "Could not read the validity of the DIYANET calendar from AlAdhan\n" );
	exit( 1 );
}

$islamic_notes = array();

$hijri_first = (int) floor( ( $first - 622 ) * 33 / 32 ) - 1;
$hijri_last  = (int) ceil( ( $last - 622 ) * 33 / 32 ) + 2;

for ( $hijri = $hijri_first; $hijri <= $hijri_last; $hijri++ ) {
	foreach ( array( 'eid_al_fitr' => '01-10-', 'eid_al_adha' => '10-12-' ) as $key => $prefix ) {
		$method = 'DIYANET';
		$iso    = hijri_to_gregorian( $prefix . $hijri, $method );

		if ( '' === $iso || $iso > $diyanet_valid ) {
			$method = 'UAQ';
			$iso    = hijri_to_gregorian( $prefix . $hijri, $method );
		}

		if ( '' === $iso ) {
			fwrite( STDERR, "Unexpected answer for $prefix$hijri\n" );
			exit( 1 );
		}

		$year = (int) substr( $iso, 0, 4 );

		if ( $year >= $first && $year <= $last ) {
			$islamic[ $key ][] = $iso;

			if ( 'DIYANET' !== $method ) {
				$islamic_notes[ $iso ] = 'Umm al-Qura, after the DIYANET range';
			}
		}
	}
}

foreach ( $islamic as $key => $dates ) {
	sort( $dates );
	$islamic[ $key ] = array_values( array_unique( $dates ) );
}

file_put_contents(
	$data . '/holidays-islamic.php',
	render_table(
		"Eid al-Fitr (1 Shawwal) and Eid al-Adha (10 Dhu al-Hijjah), {$first}–{$last}.\n\n"
		. "Source: AlAdhan API (Islamic Network), api.aladhan.com/v1/hToG with\n"
		. "calendarMethod=DIYANET, the calendar of the Diyanet that the DITIB\n"
		. "congregations in Germany follow. AlAdhan declares it valid up to\n"
		. "$diyanet_valid; later dates come from Umm al-Qura (UAQ) and are marked.\n"
		. "Fetched $today by bin/fetch-holidays.php.\n"
		. 'Generated file: run that script again rather than editing it.',
		$islamic,
		$islamic_notes
	)
);

$jewish = array(
	'rosh_hashanah' => array(),
	'yom_kippur'    => array(),
);

for ( $year = $first; $year <= $last; $year++ ) {
	$answer = fetch_json( 'https://www.hebcal.com/hebcal?v=1&cfg=json&maj=on&year=' . $year );

	foreach ( (array) ( $answer['items'] ?? array() ) as $item ) {
		$title = (string) ( $item['title'] ?? '' );
		$date  = substr( (string) ( $item['date'] ?? '' ), 0, 10 );

		if ( preg_match( '/^Rosh Hashana \d{4}$/', $title ) ) {
			$jewish['rosh_hashanah'][] = $date;
		} elseif ( 'Yom Kippur' === $title ) {
			$jewish['yom_kippur'][] = $date;
		}
	}

	usleep( 250000 );
}

foreach ( $jewish as $key => $dates ) {
	if ( count( $dates ) !== $last - $first + 1 ) {
		fwrite( STDERR, "Hebcal returned " . count( $dates ) . " dates for $key, expected " . ( $last - $first + 1 ) . "\n" );
		exit( 1 );
	}
}

file_put_contents(
	$data . '/holidays-jewish.php',
	render_table(
		"Rosh Hashanah (1 Tishri, the first full day) and Yom Kippur (10 Tishri), {$first}–{$last}.\n\n"
		. "Source: Hebcal, www.hebcal.com/hebcal (major holidays), licensed CC BY 4.0.\n"
		. "Fetched $today by bin/fetch-holidays.php. Only used when ext-calendar is\n"
		. "missing; otherwise the dates are computed with jewishtojd().\n"
		. 'Generated file: run that script again rather than editing it.',
		$jewish
	)
);

printf(
	"Wrote %d Islamic and %d Jewish dates for %d–%d.\n",
	count( $islamic['eid_al_fitr'] ) + count( $islamic['eid_al_adha'] ),
	count( $jewish['rosh_hashanah'] ) + count( $jewish['yom_kippur'] ),
	$first,
	$last
);
