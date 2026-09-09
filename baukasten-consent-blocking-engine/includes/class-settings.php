<?php
/**
 * Stored settings.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's own options, with defaults that block rather than allow.
 */
final class Settings {

	/**
	 * Option holding the settings.
	 */
	const OPTION = 'baukasten_consent_settings';

	/**
	 * Returns the default settings.
	 *
	 * @return array<string, mixed> Defaults.
	 */
	public static function defaults(): array {
		return array(
			'block_scripts'             => true,
			'block_styles'              => true,
			'block_script_modules'      => true,
			'block_resource_hints'      => true,
			'block_oembed'              => true,
			'block_gravatar'            => true,
			'block_emoji'               => true,

			// Measures from the privacy audit: not consent gated, because
			// there is no meaningful decision for a visitor to make about
			// them. See Privacy_Audit.
			'block_comment_ip'          => true,
			'block_speculative_loading' => true,
			'block_dashboard_requests'  => true,
			'warn_insecure_urls'        => true,

			'external_default'          => 'functional',
			'oembed_category'           => 'functional',
			'gravatar_category'         => 'functional',
			'policy_version'            => '1',
			'cookie_notes'              => '',
		);
	}

	/**
	 * Returns the stored settings, merged over the defaults.
	 *
	 * @return array<string, mixed> Settings.
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Returns one setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed Setting value, or null when unknown.
	 */
	public static function get( string $key ) {
		$all = self::all();

		return $all[ $key ] ?? null;
	}

	/**
	 * Whether a blocking measure is switched on.
	 *
	 * @param string $key Setting name.
	 * @return bool True when enabled.
	 */
	public static function enabled( string $key ): bool {
		return (bool) self::get( $key );
	}

	/**
	 * Returns the version of the banner and policy texts in force.
	 *
	 * Stored with every logged decision, so a later change to the wording can
	 * be told apart from the consent that was given before it.
	 *
	 * @return string Version label.
	 */
	public static function policy_version(): string {
		$version = (string) self::get( 'policy_version' );

		return '' === $version ? '1' : $version;
	}

	/**
	 * Normalises a settings array.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array<string, mixed> Sanitised settings.
	 */
	public static function sanitize( array $input ): array {
		$clean = self::defaults();

		foreach ( array_keys( $clean ) as $key ) {
			if ( is_bool( $clean[ $key ] ) ) {
				$clean[ $key ] = ! empty( $input[ $key ] );
				continue;
			}

			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}

			if ( 'cookie_notes' === $key ) {
				$clean[ $key ] = sanitize_textarea_field( (string) $input[ $key ] );
				continue;
			}

			if ( 'external_default' === $key || str_ends_with( $key, '_category' ) ) {
				$slug          = sanitize_key( (string) $input[ $key ] );
				$clean[ $key ] = Categories::exists( $slug ) ? $slug : $clean[ $key ];
				continue;
			}

			$clean[ $key ] = sanitize_text_field( (string) $input[ $key ] );
		}

		return $clean;
	}
}
