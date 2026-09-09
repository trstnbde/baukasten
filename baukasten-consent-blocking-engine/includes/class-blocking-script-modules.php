<?php
/**
 * Blocking for script modules.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Neutralises ES modules that need consent.
 *
 * Script modules — the Interactivity API and block view scripts — never pass
 * through `script_loader_tag`; they are printed by `WP_Script_Modules` and
 * need their own path. What they do go through is `wp_print_script_tag()`, and
 * that applies `wp_script_attributes`, so the module tag can be disarmed the
 * same way a classic script is.
 *
 * Known gap: `print_script_module_preloads()` prints its
 * `<link rel="modulepreload">` tags with `printf()` and no filter, so a
 * blocked module's *dependencies* may still be fetched. Script modules are
 * first-party in every case WordPress ships, so this costs a request rather
 * than leaking anything to a third party, but it is a gap and is written down
 * here rather than glossed over.
 */
final class Blocking_Script_Modules {

	/**
	 * Registers the filters.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_script_attributes', array( __CLASS__, 'filter_attributes' ) );
	}

	/**
	 * Disarms a module tag whose id needs consent.
	 *
	 * @param array<string, mixed> $attributes Attributes of the script tag.
	 * @return array<string, mixed> Filtered attributes.
	 */
	public static function filter_attributes( $attributes ): array {
		$attributes = (array) $attributes;

		if ( is_admin() || 'module' !== ( $attributes['type'] ?? '' ) ) {
			return $attributes;
		}

		$id  = isset( $attributes['id'] ) ? (string) $attributes['id'] : '';
		$src = isset( $attributes['src'] ) ? (string) $attributes['src'] : '';

		// WP_Script_Modules names the tag "{$module_id}-js-module".
		$module_id = (string) preg_replace( '/-js-module$/', '', $id );

		$category = Categories::for_handle( $module_id, $src );

		if ( Categories::is_required( $category ) ) {
			return $attributes;
		}

		$attributes['type']                        = 'text/plain';
		$attributes[ Blocking_Scripts::ATTRIBUTE ] = $category;
		$attributes['data-baukasten-module']       = 'module';

		return $attributes;
	}
}
