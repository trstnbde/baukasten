<?php
/**
 * The card's icons.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * Inline SVG glyphs, drawn here rather than fetched or bundled.
 *
 * They are deliberately generic shapes — a handset, an envelope, a globe —
 * and never a company's logo. Brand marks are trademarks with their own usage
 * rules, and shipping a set of them in a GPL plugin invites a problem that a
 * circle with a letter in it does not. Networks are labelled by name instead.
 *
 * Everything is a 24×24 viewBox with `currentColor`, so a card's palette
 * reaches the icons without a second set of variables.
 */
final class Icons {

	/**
	 * Renders one glyph.
	 *
	 * @param string $name Glyph name.
	 * @return string SVG markup, or an empty string when there is no such glyph.
	 */
	public static function get( string $name ): string {
		$paths = self::paths();

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return '<svg class="bkbc-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
			. $paths[ $name ]
			. '</svg>';
	}

	/**
	 * The initial a network button shows.
	 *
	 * @param string $network Network key, such as `network_linkedin`.
	 * @return string One or two characters.
	 */
	public static function initial( string $network ): string {
		$name = str_replace( 'network_', '', $network );

		return strtoupper( substr( $name, 0, 1 ) );
	}

	/**
	 * The path data for every glyph.
	 *
	 * @return array<string, string> Glyph name to SVG children.
	 */
	private static function paths(): array {
		return array(
			'phone'    => '<path d="M6.5 3h3l1.5 4-2 1.5a12 12 0 0 0 6.5 6.5L17 13l4 1.5v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4 5.2 2 2 0 0 1 6 3Z"/>',
			'mail'     => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
			'chat'     => '<path d="M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12Z"/>',
			'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/>',
			'mobile'   => '<rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M11 18.5h2"/>',
			'pin'      => '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
			'download' => '<path d="M12 3v12"/><path d="m7 11 5 5 5-5"/><path d="M4 20h16"/>',
			'contact'  => '<rect x="2.5" y="4.5" width="19" height="15" rx="2"/><circle cx="9" cy="11" r="2.5"/><path d="M4.8 16.5a4.6 4.6 0 0 1 8.4 0M15 9.5h4M15 13h4"/>',
			'wallet'   => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H19a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5.5A2.5 2.5 0 0 1 3 16.5Z"/><path d="M3 8h18"/><circle cx="17" cy="13" r="1.2"/>',
			'qr'       => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14h1M14 20h3M20 17v4"/>',
			'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
			'link'     => '<path d="M10 13a5 5 0 0 0 7.1 0l2.4-2.4a5 5 0 0 0-7.1-7.1L11 4.9"/><path d="M14 11a5 5 0 0 0-7.1 0l-2.4 2.4a5 5 0 0 0 7.1 7.1L13 19.1"/>',
			'close'    => '<path d="m6 6 12 12M18 6 6 18"/>',
		);
	}
}
