<?php
/**
 * Keeping addresses and numbers out of a scraper's reach.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * Writes an email address or a phone number as HTML character references.
 *
 * A business card is a public page with a person's real address and real number
 * on it, which is exactly what an address harvester is looking for. Every
 * character is written as `&#NN;` instead of itself, so the plain string never
 * appears in the source and the regular expressions those crawlers run find
 * nothing.
 *
 * **This is a speed bump, not a lock.** Anything that renders the page — a
 * headless browser, or a crawler that bothers to decode entities — reads it
 * perfectly well, and nothing on a public page can prevent that. What it stops
 * is the cheap, high-volume end, which is most of it.
 *
 * It is done this way, and not by assembling the address in JavaScript, because
 * a business card has to work without JavaScript: entities are decoded by the
 * HTML parser itself, so the text renders and the link works either way, and
 * copy-and-paste gives the real value. The only cost is about six bytes a
 * character in the markup.
 *
 * Every character is encoded rather than a random half of them — the WordPress
 * way, in `antispambot()` — because a partially encoded string still has to be
 * escaped for the characters left alone, and "escaped except where it wasn't"
 * is a worse thing to reason about than "no literal characters at all".
 */
final class Obfuscate {

	/**
	 * Writes a string as HTML character references.
	 *
	 * The result is markup, not text: print it unescaped. It carries no literal
	 * `<`, `>`, `&`, `"` or `'`, because it carries no literal character of any
	 * kind, so there is nothing left in it to escape.
	 *
	 * @param string $value Plain text.
	 * @return string The same text as `&#NN;` references.
	 */
	public static function html( string $value ): string {
		$characters = preg_split( '//u', $value, -1, PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $characters ) ) {
			return esc_html( $value );
		}

		$out = '';

		foreach ( $characters as $character ) {
			$out .= '&#' . self::code_point( (string) $character ) . ';';
		}

		return $out;
	}

	/**
	 * Writes a `tel:` address for a number.
	 *
	 * The scheme is left readable and only the number is encoded. A crawler
	 * looking for `tel:` finds the attribute and nothing usable inside it, and
	 * leaving the scheme alone keeps the href obviously a telephone link to
	 * anything that inspects it for good reasons — a browser, a screen reader.
	 *
	 * @param string $number Phone number as it was typed.
	 * @return string An href value, already encoded.
	 */
	public static function tel( string $number ): string {
		return 'tel:' . self::html( (string) preg_replace( '/[^0-9+]/', '', $number ) );
	}

	/**
	 * Writes a `mailto:` address.
	 *
	 * @param string $email Email address.
	 * @return string An href value, already encoded.
	 */
	public static function mailto( string $email ): string {
		return 'mailto:' . self::html( $email );
	}

	/**
	 * The Unicode code point of one character.
	 *
	 * Decoded from the UTF-8 bytes by hand rather than with `mb_ord()`:
	 * WordPress does not require ext-mbstring, and a contact card that dropped
	 * its phone number on a host without it would be a poor trade for four
	 * lines of arithmetic.
	 *
	 * @param string $character One UTF-8 character.
	 * @return int Code point.
	 */
	private static function code_point( string $character ): int {
		$bytes  = array_map( 'ord', str_split( $character ) );
		$length = count( $bytes );

		if ( 1 === $length ) {
			return $bytes[0];
		}

		if ( 2 === $length ) {
			return ( ( $bytes[0] & 0x1F ) << 6 ) | ( $bytes[1] & 0x3F );
		}

		if ( 3 === $length ) {
			return ( ( $bytes[0] & 0x0F ) << 12 ) | ( ( $bytes[1] & 0x3F ) << 6 ) | ( $bytes[2] & 0x3F );
		}

		if ( 4 === $length ) {
			return ( ( $bytes[0] & 0x07 ) << 18 ) | ( ( $bytes[1] & 0x3F ) << 12 )
				| ( ( $bytes[2] & 0x3F ) << 6 ) | ( $bytes[3] & 0x3F );
		}

		// Not valid UTF-8. A question mark is better than a broken reference.
		return 63;
	}
}
