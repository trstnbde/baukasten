<?php
/**
 * Stored settings.
 *
 * @package Baukasten\FormPrivacy
 */

namespace Baukasten\FormPrivacy;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's one option, with defaults that collect as little as possible.
 */
final class Settings {

	/**
	 * Option holding the settings.
	 */
	const OPTION = 'baukasten_form_privacy_settings';

	/**
	 * Meta keys Contact Form 7 hands Flamingo with every submission.
	 *
	 * The special mail tags of `wpcf7_flamingo_submit()`, without the leading
	 * underscore. Only the ones in the whitelist are stored.
	 *
	 * @var string[]
	 */
	const META_KEYS = array(
		'serial_number',
		'remote_ip',
		'user_agent',
		'url',
		'date',
		'time',
		'post_id',
		'post_name',
		'post_title',
		'post_url',
		'post_author',
		'post_author_email',
		'site_title',
		'site_description',
		'site_url',
		'site_admin_email',
		'user_login',
		'user_email',
		'user_display_name',
	);

	/**
	 * Returns the default settings.
	 *
	 * @return array{assets_on_demand: bool, strip_ip: bool, store_submissions: bool, store_spam: bool, meta_whitelist: string[], retention_days: int, honeypot: bool, min_fill_seconds: int}
	 */
	public static function defaults(): array {
		return array(
			'assets_on_demand'  => true,
			'strip_ip'          => true,
			'store_submissions' => true,
			'store_spam'        => false,
			'meta_whitelist'    => array( 'date', 'time', 'url', 'post_id', 'post_title' ),
			'retention_days'    => 90,
			'honeypot'          => true,
			'min_fill_seconds'  => 3,
		);
	}

	/**
	 * Returns the stored settings, merged over the defaults.
	 *
	 * @return array<string, mixed> Settings.
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Whether a switch is on.
	 *
	 * @param string $key Setting name.
	 * @return bool True when enabled.
	 */
	public static function enabled( string $key ): bool {
		return ! empty( self::all()[ $key ] );
	}

	/**
	 * Days a submission is kept.
	 *
	 * @return int Days, at least 1.
	 */
	public static function retention_days(): int {
		return max( 1, (int) self::all()['retention_days'] );
	}

	/**
	 * Seconds a form has to be open before it may be sent.
	 *
	 * @return int Seconds, at least 0.
	 */
	public static function min_fill_seconds(): int {
		return max( 0, (int) self::all()['min_fill_seconds'] );
	}

	/**
	 * Meta keys that may be stored with a submission.
	 *
	 * @return string[] Keys.
	 */
	public static function meta_whitelist(): array {
		$keys = (array) self::all()['meta_whitelist'];

		return array_values( array_intersect( self::META_KEYS, array_map( 'strval', $keys ) ) );
	}

	/**
	 * Normalises a settings array.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array<string, mixed> Sanitised settings.
	 */
	public static function sanitize( array $input ): array {
		$clean = self::defaults();

		foreach ( array( 'assets_on_demand', 'strip_ip', 'store_submissions', 'store_spam', 'honeypot' ) as $key ) {
			$clean[ $key ] = ! empty( $input[ $key ] );
		}

		$whitelist               = isset( $input['meta_whitelist'] ) ? (array) $input['meta_whitelist'] : array();
		$clean['meta_whitelist'] = array_values( array_intersect( self::META_KEYS, array_map( 'sanitize_key', array_map( 'strval', $whitelist ) ) ) );

		$clean['retention_days']   = min( 3650, max( 1, absint( $input['retention_days'] ?? 90 ) ) );
		$clean['min_fill_seconds'] = min( 600, absint( $input['min_fill_seconds'] ?? 3 ) );

		return $clean;
	}

	/**
	 * Whether Flamingo is active.
	 *
	 * @return bool True when its message class is loaded.
	 */
	public static function has_flamingo(): bool {
		return class_exists( 'Flamingo_Inbound_Message' );
	}
}
