<?php
/**
 * The fields of a list and of a wish.
 *
 * @package Baukasten\Wishlist
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * One schema for both post types: key to type, the meta key it is stored
 * under, how it is cleaned, and its default.
 */
final class Fields {

	/**
	 * Prefix of every meta key.
	 */
	const PREFIX = '_baukasten_wishlist_';

	/**
	 * Highest priority (most wanted).
	 */
	const PRIORITY_HIGH = 1;

	/**
	 * Default priority.
	 */
	const PRIORITY_MEDIUM = 2;

	/**
	 * Lowest priority.
	 */
	const PRIORITY_LOW = 3;

	/**
	 * Highest quantity a wish can ask for.
	 */
	const MAX_QUANTITY = 99;

	/**
	 * Default accent colour.
	 */
	const DEFAULT_ACCENT = '#c2416b';

	/**
	 * Fields of a list.
	 *
	 * @return array<string, string> Key to type.
	 */
	public static function list_types(): array {
		return array(
			'person'      => 'text',
			'occasion'    => 'text',
			'date'        => 'date',
			'intro'       => 'textarea',
			'accent'      => 'color',
			'show_prices' => 'bool',
			'close_after' => 'bool',
		);
	}

	/**
	 * Fields of a wish.
	 *
	 * @return array<string, string> Key to type.
	 */
	public static function item_types(): array {
		return array(
			'list'     => 'list',
			'quantity' => 'quantity',
			'price'    => 'price',
			'priority' => 'priority',
			'url'      => 'url',
			'note'     => 'text',
		);
	}

	/**
	 * Defaults of a list that has not been saved yet.
	 *
	 * @return array<string, string> Key to value.
	 */
	public static function list_defaults(): array {
		return array(
			'person'      => '',
			'occasion'    => '',
			'date'        => '',
			'intro'       => '',
			'accent'      => self::DEFAULT_ACCENT,
			'show_prices' => '1',
			'close_after' => '0',
		);
	}

	/**
	 * Defaults of a wish that has not been saved yet.
	 *
	 * @return array<string, string> Key to value.
	 */
	public static function item_defaults(): array {
		return array(
			'list'     => '0',
			'quantity' => '1',
			'price'    => '',
			'priority' => (string) self::PRIORITY_MEDIUM,
			'url'      => '',
			'note'     => '',
		);
	}

	/**
	 * The meta key of a field.
	 *
	 * @param string $key Field key.
	 * @return string Meta key.
	 */
	public static function meta_key( string $key ): string {
		return self::PREFIX . $key;
	}

	/**
	 * Cleans a submitted value.
	 *
	 * @param string $type  Field type.
	 * @param mixed  $value Raw value, already unslashed.
	 * @return string Clean value; an empty string means "not set".
	 */
	public static function sanitize( string $type, $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value = (string) $value;

		switch ( $type ) {
			case 'bool':
				return '' !== $value && '0' !== $value ? '1' : '0';

			case 'date':
				$value = trim( $value );

				if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) {
					return '';
				}

				return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ? $value : '';

			case 'color':
				$color = sanitize_hex_color( trim( $value ) );

				return is_string( $color ) && '' !== $color ? strtolower( $color ) : self::DEFAULT_ACCENT;

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'list':
				$id   = absint( $value );
				$post = 0 < $id ? get_post( $id ) : null;

				return $post instanceof \WP_Post && Post_Types::LIST_TYPE === $post->post_type ? (string) $id : '0';

			case 'quantity':
				$number = is_numeric( trim( $value ) ) ? (int) $value : 1;

				return (string) max( 1, min( self::MAX_QUANTITY, $number ) );

			case 'price':
				return self::sanitize_price( $value );

			case 'priority':
				$number = (int) $value;

				return (string) ( in_array( $number, array( self::PRIORITY_HIGH, self::PRIORITY_MEDIUM, self::PRIORITY_LOW ), true ) ? $number : self::PRIORITY_MEDIUM );

			case 'url':
				$url = esc_url_raw( trim( $value ), array( 'http', 'https' ) );

				return is_string( $url ) ? $url : '';

			case 'text':
			default:
				return mb_substr( sanitize_text_field( $value ), 0, 200 );
		}
	}

	/**
	 * Normalises a price as typed by a German or an English hand.
	 *
	 * `12,99`, `12.99`, `1.299,00`, `1,299.00` and `12 €` all become a plain
	 * decimal with a point. Anything that is not a non-negative number is
	 * thrown away rather than guessed at.
	 *
	 * @param string $value Raw price.
	 * @return string Price such as `12.99`, or an empty string.
	 */
	public static function sanitize_price( string $value ): string {
		$value = preg_replace( '/[^\d.,]/', '', $value );
		$value = is_string( $value ) ? $value : '';

		if ( '' === $value ) {
			return '';
		}

		$comma = strrpos( $value, ',' );
		$point = strrpos( $value, '.' );

		// Whichever separator comes last is the decimal one, if it is
		// followed by one or two digits; every other separator groups
		// thousands.
		$last = max( false === $comma ? -1 : $comma, false === $point ? -1 : $point );

		if ( 0 <= $last && strlen( $value ) - $last - 1 <= 2 ) {
			$whole    = str_replace( array( ',', '.' ), '', substr( $value, 0, $last ) );
			$fraction = substr( $value, $last + 1 );
			$value    = ( '' === $whole ? '0' : $whole ) . ( '' === $fraction ? '' : '.' . $fraction );
		} else {
			$value = str_replace( array( ',', '.' ), '', $value );
		}

		if ( ! is_numeric( $value ) ) {
			return '';
		}

		$number = round( (float) $value, 2 );

		if ( 0 > $number || 1000000 <= $number ) {
			return '';
		}

		return number_format( $number, 2, '.', '' );
	}

	/**
	 * The fields of a list, with defaults for whatever is not stored.
	 *
	 * @param int $list_id List post ID.
	 * @return array<string, string> Key to value.
	 */
	public static function load_list( int $list_id ): array {
		return self::load( $list_id, self::list_defaults() );
	}

	/**
	 * The fields of a wish, with defaults for whatever is not stored.
	 *
	 * @param int $item_id Wish post ID.
	 * @return array<string, string> Key to value.
	 */
	public static function load_item( int $item_id ): array {
		return self::load( $item_id, self::item_defaults() );
	}

	/**
	 * Reads stored values over a set of defaults.
	 *
	 * @param int                   $post_id  Post ID.
	 * @param array<string, string> $defaults Key to default.
	 * @return array<string, string> Key to value.
	 */
	private static function load( int $post_id, array $defaults ): array {
		$values = $defaults;

		foreach ( array_keys( $defaults ) as $key ) {
			$stored = get_post_meta( $post_id, self::meta_key( $key ), true );

			if ( is_scalar( $stored ) && '' !== (string) $stored ) {
				$values[ $key ] = (string) $stored;
			}
		}

		return $values;
	}

	/**
	 * Labels of the three priorities.
	 *
	 * @return array<int, string> Priority to label.
	 */
	public static function priorities(): array {
		return array(
			self::PRIORITY_HIGH   => __( 'High', 'baukasten-wishlist' ),
			self::PRIORITY_MEDIUM => __( 'Medium', 'baukasten-wishlist' ),
			self::PRIORITY_LOW    => __( 'Low', 'baukasten-wishlist' ),
		);
	}
}
