<?php
/**
 * Stored settings.
 *
 * @package Baukasten\ContentVisibility
 */

namespace Baukasten\ContentVisibility;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the settings and feeds them into the plugin's own filters.
 *
 * The behaviour was filter-only before there was a settings screen. Rather
 * than replacing those filters with option lookups, the settings are applied
 * *through* them at the default priority, so a site that already filters
 * `baukasten/content_visibility/post_types` or `…/login_redirect` keeps the
 * last word.
 */
final class Settings {

	/**
	 * Option holding the settings.
	 */
	const OPTION = 'baukasten_content_visibility_settings';

	/**
	 * Value of `blocked_response` that redirects to the login form.
	 */
	const RESPONSE_LOGIN = 'login';

	/**
	 * Value of `blocked_response` that answers with 403.
	 */
	const RESPONSE_FORBIDDEN = 'forbidden';

	/**
	 * Registers the filters that apply the settings.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'baukasten/content_visibility/post_types', array( __CLASS__, 'filter_post_types' ) );
		add_filter( 'baukasten/content_visibility/login_redirect', array( __CLASS__, 'filter_login_redirect' ) );
	}

	/**
	 * Returns the default settings.
	 *
	 * An empty `post_types` list means "every public post type", which is what
	 * the plugin did before the setting existed. It is deliberately not filled
	 * with the post types found at activation: a post type registered later
	 * would then silently stay unprotected.
	 *
	 * @return array{post_types: string[], blocked_response: string} Defaults.
	 */
	public static function defaults(): array {
		return array(
			'post_types'       => array(),
			'blocked_response' => self::RESPONSE_LOGIN,
		);
	}

	/**
	 * Returns the stored settings, merged over the defaults.
	 *
	 * @return array{post_types: string[], blocked_response: string} Settings.
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return self::sanitize( array_merge( self::defaults(), $stored ) );
	}

	/**
	 * Normalises a settings array.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array{post_types: string[], blocked_response: string} Sanitised settings.
	 */
	public static function sanitize( array $input ): array {
		$post_types = isset( $input['post_types'] ) && is_array( $input['post_types'] )
			? array_values( array_intersect( Visibility::detect_post_types(), array_map( 'strval', $input['post_types'] ) ) )
			: array();

		$response = isset( $input['blocked_response'] ) ? (string) $input['blocked_response'] : self::RESPONSE_LOGIN;

		return array(
			'post_types'       => $post_types,
			'blocked_response' => self::RESPONSE_FORBIDDEN === $response ? self::RESPONSE_FORBIDDEN : self::RESPONSE_LOGIN,
		);
	}

	/**
	 * Narrows the managed post types down to the configured selection.
	 *
	 * @param string[] $post_types Post types the plugin detected.
	 * @return string[] Post types to manage.
	 */
	public static function filter_post_types( $post_types ): array {
		$post_types = array_map( 'strval', (array) $post_types );
		$selected   = self::all()['post_types'];

		if ( array() === $selected ) {
			return $post_types;
		}

		return array_values( array_intersect( $post_types, $selected ) );
	}

	/**
	 * Turns the redirect into a 403 when the site asked for one.
	 *
	 * @param string $redirect Login URL including the redirect_to argument.
	 * @return string Login URL, or an empty string for a 403.
	 */
	public static function filter_login_redirect( $redirect ): string {
		if ( self::RESPONSE_FORBIDDEN === self::all()['blocked_response'] ) {
			return '';
		}

		return (string) $redirect;
	}
}
