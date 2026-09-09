<?php
/**
 * Generates the WordPress.org asset images.
 *
 * Writes icon-128x128.png, icon-256x256.png, banner-772x250.png and
 * banner-1544x500.png into <slug>/.wordpress-org/assets/. Those files belong
 * in the SVN `assets` directory, not in the plugin archive.
 *
 * The banner wordmark is the plugin's `Plugin Name` header, the two lines
 * below it are its readme.txt short description.
 *
 * Requires ext-gd. Usage: php -d extension=gd bin/make-assets.php <slug>
 *
 * @package Baukasten
 */

declare( strict_types = 1 );

if ( ! extension_loaded( 'gd' ) ) {
	fwrite( STDERR, "ext-gd is required. Run: php -d extension=gd bin/make-assets.php\n" );
	exit( 1 );
}

$repo = dirname( __DIR__ );
$slug = isset( $argv[1] ) ? basename( rtrim( str_replace( '\\', '/', (string) $argv[1] ), '/' ) ) : 'baukasten';
$dir  = $repo . '/' . $slug;
$main = $dir . '/' . $slug . '.php';

if ( ! is_file( $main ) ) {
	fwrite( STDERR, "No main file at $slug/$slug.php\n" );
	exit( 1 );
}

$out  = $dir . '/.wordpress-org/assets';
$bold = getenv( 'BAUKASTEN_FONT_BOLD' ) ?: 'C:/Windows/Fonts/seguisb.ttf';
$body = getenv( 'BAUKASTEN_FONT' ) ?: 'C:/Windows/Fonts/segoeui.ttf';

if ( ! is_dir( $out ) ) {
	mkdir( $out, 0755, true );
}

$wordmark = $slug;

if ( preg_match( '/^\s*\*\s*Plugin Name:\s*(.+)$/mi', (string) file_get_contents( $main ), $matches ) ) {
	$wordmark = trim( $matches[1] );
}

$tagline = array( '', '' );
$readme  = $dir . '/readme.txt';

if ( is_readable( $readme ) ) {
	$lines = (array) file( $readme, FILE_IGNORE_NEW_LINES );
	$seen  = false;

	foreach ( $lines as $line ) {
		$line = trim( (string) $line );

		if ( '' === $line ) {
			$seen = true;
			continue;
		}

		if ( $seen && '=' !== $line[0] ) {
			$words   = explode( ' ', $line );
			$half    = (int) ceil( count( $words ) / 2 );
			$tagline = array(
				implode( ' ', array_slice( $words, 0, $half ) ),
				implode( ' ', array_slice( $words, $half ) ),
			);
			break;
		}
	}
}

/**
 * Allocates a colour from a hex string.
 *
 * @param GdImage $image Target image.
 * @param string  $hex   Six digit hex colour.
 * @return int Colour identifier.
 */
function hex_color( GdImage $image, string $hex ): int {
	return (int) imagecolorallocate(
		$image,
		(int) hexdec( substr( $hex, 0, 2 ) ),
		(int) hexdec( substr( $hex, 2, 2 ) ),
		(int) hexdec( substr( $hex, 4, 2 ) )
	);
}

/**
 * Draws a rounded rectangle.
 *
 * @param GdImage $image  Target image.
 * @param int     $x      Left edge.
 * @param int     $y      Top edge.
 * @param int     $width  Width.
 * @param int     $height Height.
 * @param int     $radius Corner radius.
 * @param int     $color  Fill colour.
 * @return void
 */
function rounded_rect( GdImage $image, int $x, int $y, int $width, int $height, int $radius, int $color ): void {
	imagefilledrectangle( $image, $x + $radius, $y, $x + $width - $radius, $y + $height, $color );
	imagefilledrectangle( $image, $x, $y + $radius, $x + $width, $y + $height - $radius, $color );

	$d = $radius * 2;

	imagefilledellipse( $image, $x + $radius, $y + $radius, $d, $d, $color );
	imagefilledellipse( $image, $x + $width - $radius, $y + $radius, $d, $d, $color );
	imagefilledellipse( $image, $x + $radius, $y + $height - $radius, $d, $d, $color );
	imagefilledellipse( $image, $x + $width - $radius, $y + $height - $radius, $d, $d, $color );
}

/**
 * Returns the width of a string at a given font size.
 *
 * @param float  $size Font size in points.
 * @param string $font Path to the font file.
 * @param string $text Text to measure.
 * @return int Width in pixels.
 */
function text_width( float $size, string $font, string $text ): int {
	$box = imagettfbbox( $size, 0, $font, $text );

	return false === $box ? 0 : (int) ( max( $box[2], $box[4] ) - min( $box[0], $box[6] ) );
}

/**
 * Shrinks a font size until the text fits into the available width.
 *
 * @param float  $size      Preferred font size in points.
 * @param string $font      Path to the font file.
 * @param string $text      Text to fit.
 * @param int    $available Width in pixels.
 * @return float Font size that fits.
 */
function fit_size( float $size, string $font, string $text, int $available ): float {
	while ( $size > 6 && text_width( $size, $font, $text ) > $available ) {
		$size -= 0.5;
	}

	return $size;
}

/**
 * Breaks a sentence into lines that fit into the available width.
 *
 * @param string $text      Text to wrap.
 * @param float  $size      Font size in points.
 * @param string $font      Path to the font file.
 * @param int    $available Width in pixels.
 * @param int    $lines     Maximum number of lines.
 * @return string[] Lines, the last one ellipsised if the text did not fit.
 */
function wrap_text( string $text, float $size, string $font, int $available, int $lines ): array {
	$words  = preg_split( '/\s+/', trim( $text ) ) ?: array();
	$result = array();
	$line   = '';

	foreach ( $words as $word ) {
		$candidate = '' === $line ? $word : $line . ' ' . $word;

		if ( text_width( $size, $font, $candidate ) <= $available ) {
			$line = $candidate;
			continue;
		}

		if ( '' !== $line ) {
			$result[] = $line;
		}

		$line = $word;

		if ( count( $result ) === $lines ) {
			break;
		}
	}

	if ( '' !== $line && count( $result ) < $lines ) {
		$result[] = $line;
	}

	if ( count( $result ) === $lines && '' !== $line && end( $result ) !== $line ) {
		$result[ $lines - 1 ] = rtrim( $result[ $lines - 1 ], ' .,' ) . '…';
	}

	return $result;
}

/**
 * Draws the stacked blocks mark.
 *
 * The mark reads as three modules slotted into a container: two settled in the
 * bottom row, one still hovering above the gap.
 *
 * @param GdImage $image Target image.
 * @param int     $cx    Horizontal centre of the mark.
 * @param int     $cy    Vertical centre of the mark.
 * @param int     $unit  Size of one block.
 * @return void
 */
function draw_mark( GdImage $image, int $cx, int $cy, int $unit ): void {
	$gap    = max( 2, (int) round( $unit * 0.14 ) );
	$radius = max( 2, (int) round( $unit * 0.18 ) );

	$filled = hex_color( $image, 'ffffff' );
	$ghost  = hex_color( $image, '9ec9f0' );

	$span = ( $unit * 2 ) + $gap;
	$left = $cx - (int) round( $span / 2 );
	$top  = $cy - (int) round( $span / 2 );

	// Bottom row: two settled modules.
	rounded_rect( $image, $left, $top + $unit + $gap, $unit, $unit, $radius, $filled );
	rounded_rect( $image, $left + $unit + $gap, $top + $unit + $gap, $unit, $unit, $radius, $filled );

	// Top row: one settled, one still being slotted in.
	rounded_rect( $image, $left, $top, $unit, $unit, $radius, $filled );
	rounded_rect( $image, $left + $unit + $gap, $top, $unit, $unit, $radius, $ghost );
}

/**
 * Renders one square icon.
 *
 * @param int    $size Edge length.
 * @param string $path Output path.
 * @return void
 */
function make_icon( int $size, string $path ): void {
	$image = imagecreatetruecolor( $size, $size );

	imageantialias( $image, true );

	$background = hex_color( $image, '1e4b7a' );

	imagefilledrectangle( $image, 0, 0, $size, $size, $background );

	// Subtle diagonal lift on the top left.
	$lift = imagecolorallocatealpha( $image, 255, 255, 255, 110 );
	imagefilledpolygon( $image, array( 0, 0, $size, 0, 0, $size ), (int) $lift );

	draw_mark( $image, (int) round( $size / 2 ), (int) round( $size / 2 ), (int) round( $size * 0.28 ) );

	imagepng( $image, $path );
	imagedestroy( $image );

	echo 'wrote ', basename( $path ), PHP_EOL;
}

/**
 * Renders one banner.
 *
 * @param int    $width  Banner width.
 * @param int    $height Banner height.
 * @param string $path   Output path.
 * @param string   $bold     Path to the bold font file.
 * @param string   $body     Path to the regular font file.
 * @param string   $wordmark Text drawn as the wordmark.
 * @param string[] $tagline  Two lines drawn below the wordmark.
 * @return void
 */
function make_banner( int $width, int $height, string $path, string $bold, string $body, string $wordmark, array $tagline ): void {
	$image = imagecreatetruecolor( $width, $height );

	imageantialias( $image, true );

	$background = hex_color( $image, '1e4b7a' );

	imagefilledrectangle( $image, 0, 0, $width, $height, $background );

	// A soft diagonal wedge behind the mark, kept clear of the wordmark.
	$wash = imagecolorallocatealpha( $image, 255, 255, 255, 118 );
	imagefilledpolygon( $image, array( 0, 0, (int) round( $width * 0.30 ), 0, 0, $height ), (int) $wash );

	$mark_unit = (int) round( $height * 0.17 );
	draw_mark( $image, (int) round( $width * 0.145 ), (int) round( $height / 2 ), $mark_unit );

	$title    = hex_color( $image, 'ffffff' );
	$subtitle = hex_color( $image, 'c9dff2' );

	$text_x    = (int) round( $width * 0.27 );
	$available = $width - $text_x - (int) round( $width * 0.04 );

	if ( is_readable( $bold ) ) {
		$title_size = fit_size( $height * 0.20, $bold, $wordmark, $available );

		imagettftext( $image, $title_size, 0, $text_x, (int) round( $height * 0.50 ), $title, $bold, $wordmark );
	}

	if ( is_readable( $body ) ) {
		$sub_size = $height * 0.072;
		$lines    = wrap_text( implode( ' ', $tagline ), $sub_size, $body, $available, 2 );

		foreach ( $lines as $index => $line ) {
			imagettftext(
				$image,
				$sub_size,
				0,
				$text_x + 3,
				(int) round( $height * ( 0.66 + ( 0.12 * $index ) ) ),
				$subtitle,
				$body,
				$line
			);
		}
	}

	imagepng( $image, $path );
	imagedestroy( $image );

	echo 'wrote ', basename( $path ), PHP_EOL;
}

make_icon( 128, $out . '/icon-128x128.png' );
make_icon( 256, $out . '/icon-256x256.png' );
make_banner( 772, 250, $out . '/banner-772x250.png', $bold, $body, $wordmark, $tagline );
make_banner( 1544, 500, $out . '/banner-1544x500.png', $bold, $body, $wordmark, $tagline );
