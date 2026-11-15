<?php
/**
 * Site-wide settings.
 *
 * @package Baukasten\Wishlist
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * The two options the addon has. Everything else belongs to one list.
 */
final class Settings {

	/**
	 * Currency symbol printed after a price.
	 */
	const OPTION_CURRENCY = 'baukasten_wishlist_currency';

	/**
	 * Purchases one address may report or take back per window.
	 */
	const OPTION_RATE_LIMIT = 'baukasten_wishlist_rate_limit';

	/**
	 * Default currency symbol.
	 */
	const DEFAULT_CURRENCY = '€';

	/**
	 * Default number of reports per window.
	 */
	const DEFAULT_RATE_LIMIT = 20;

	/**
	 * Length of the rate limit window in seconds.
	 */
	const RATE_WINDOW = 600;

	/**
	 * The currency symbol.
	 *
	 * @return string Symbol, at most eight characters.
	 */
	public static function currency(): string {
		$value = get_option( self::OPTION_CURRENCY, self::DEFAULT_CURRENCY );

		return self::sanitize_currency( is_string( $value ) ? $value : self::DEFAULT_CURRENCY );
	}

	/**
	 * Reports per window, 0 switching the limit off.
	 *
	 * @return int Limit.
	 */
	public static function rate_limit(): int {
		return self::sanitize_rate_limit( get_option( self::OPTION_RATE_LIMIT, self::DEFAULT_RATE_LIMIT ) );
	}

	/**
	 * Cleans a currency symbol.
	 *
	 * @param mixed $value Raw value.
	 * @return string Symbol; the default when empty.
	 */
	public static function sanitize_currency( $value ): string {
		$value = trim( sanitize_text_field( (string) $value ) );
		$value = mb_substr( $value, 0, 8 );

		return '' === $value ? self::DEFAULT_CURRENCY : $value;
	}

	/**
	 * Cleans a rate limit.
	 *
	 * @param mixed $value Raw value.
	 * @return int Between 0 and 1000.
	 */
	public static function sanitize_rate_limit( $value ): int {
		if ( ! is_numeric( $value ) ) {
			return self::DEFAULT_RATE_LIMIT;
		}

		return max( 0, min( 1000, (int) $value ) );
	}

	/**
	 * A price as the page prints it.
	 *
	 * @param string $price Normalised price, such as `12.99`, or empty.
	 * @return string Formatted price, or an empty string.
	 */
	public static function format_price( string $price ): string {
		if ( '' === $price || ! is_numeric( $price ) ) {
			return '';
		}

		$value    = (float) $price;
		$decimals = floor( $value ) === $value ? 0 : 2;

		return number_format_i18n( $value, $decimals ) . "\u{00A0}" . self::currency();
	}
}
