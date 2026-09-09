<?php
/**
 * Blocking for Gravatar.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Serves a local avatar instead of one from gravatar.com.
 *
 * Every avatar on a page is a request to Automattic carrying the visitor's IP
 * and, in the URL, a hash of the commenter's email address. Unlike a script
 * this cannot be deferred to the browser: the URL is the leak. So the avatar
 * is replaced server-side with an image from this plugin, and the bootstrap
 * script swaps it back if the visitor allowed the category — the real URL
 * travels in a data attribute until then.
 */
final class Blocking_Gravatar {

	/**
	 * Registers the filters.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'pre_get_avatar_data', array( __CLASS__, 'filter_data' ), PHP_INT_MAX, 2 );
		add_filter( 'get_avatar', array( __CLASS__, 'filter_markup' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Points the avatar URL at the local fallback.
	 *
	 * @param array<string, mixed>|null $args        Avatar data, or null to continue.
	 * @param mixed                     $id_or_email Whatever the avatar was requested for.
	 * @return array<string, mixed>|null Filtered data.
	 */
	public static function filter_data( $args, $id_or_email ) {
		unset( $id_or_email );

		if ( is_admin() || ! is_array( $args ) || ! self::blocked() ) {
			return $args;
		}

		$args['url']          = self::fallback_url();
		$args['found_avatar'] = true;

		return $args;
	}

	/**
	 * Marks the rendered avatar so the browser can restore it.
	 *
	 * @param string $avatar      The avatar markup.
	 * @param mixed  $id_or_email Whatever the avatar was requested for.
	 * @return string Filtered markup.
	 */
	public static function filter_markup( $avatar, $id_or_email ): string {
		$avatar = (string) $avatar;

		if ( is_admin() || '' === $avatar || ! self::blocked() ) {
			return $avatar;
		}

		$real = self::real_url( $id_or_email );

		if ( '' === $real ) {
			return $avatar;
		}

		return (string) preg_replace(
			'/^<img\s/',
			sprintf(
				'<img %1$s="%2$s" data-baukasten-src="%3$s" ',
				esc_attr( Blocking_Scripts::ATTRIBUTE ),
				esc_attr( (string) Settings::get( 'gravatar_category' ) ),
				esc_url( $real )
			),
			$avatar,
			1
		);
	}

	/**
	 * Whether avatars are currently withheld.
	 *
	 * @return bool True when the category still needs consent.
	 */
	private static function blocked(): bool {
		if ( ! Settings::enabled( 'block_gravatar' ) ) {
			return false;
		}

		$category = (string) Settings::get( 'gravatar_category' );

		return Categories::exists( $category ) && ! Categories::is_required( $category );
	}

	/**
	 * Returns the avatar URL that would have been used.
	 *
	 * @param mixed $id_or_email Whatever the avatar was requested for.
	 * @return string Avatar URL, or an empty string.
	 */
	private static function real_url( $id_or_email ): string {
		remove_filter( 'pre_get_avatar_data', array( __CLASS__, 'filter_data' ), PHP_INT_MAX );

		$url = get_avatar_url( $id_or_email );

		add_filter( 'pre_get_avatar_data', array( __CLASS__, 'filter_data' ), PHP_INT_MAX, 2 );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Returns the URL of the local placeholder image.
	 *
	 * @return string Image URL.
	 */
	public static function fallback_url(): string {
		/**
		 * Filters the image shown in place of a Gravatar.
		 *
		 * @since 1.0.0
		 *
		 * @param string $url Absolute image URL.
		 */
		return (string) apply_filters(
			'baukasten/consent/avatar_fallback',
			PLUGIN_URL . 'assets/img/avatar.svg'
		);
	}
}
