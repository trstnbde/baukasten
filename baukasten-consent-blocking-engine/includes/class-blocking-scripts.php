<?php
/**
 * Blocking for classic scripts and styles.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Neutralises enqueued scripts and stylesheets that need consent.
 *
 * The rewrite is deliberately the same for every visitor: the server never
 * looks at the consent cookie here, so the HTML is identical whoever asks for
 * it and a full page cache can store it. `consent-bootstrap.js` restores what
 * the visitor allowed once the page is in the browser.
 *
 * A script is disarmed by giving it `type="text/plain"`, which is what the
 * whole industry does: browsers do not fetch or run a script of an unknown
 * type, and the `src` can stay where it is. A stylesheet has no such type, so
 * its `href` and `rel` are moved into data attributes instead — a `<link>`
 * without either fetches nothing.
 */
final class Blocking_Scripts {

	/**
	 * Attribute naming the category a blocked asset waits for.
	 */
	const ATTRIBUTE = 'data-baukasten-consent';

	/**
	 * Registers the filters.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( Settings::enabled( 'block_scripts' ) ) {
			add_filter( 'script_loader_tag', array( __CLASS__, 'filter_script' ), 10, 3 );
			add_filter( 'wp_inline_script_attributes', array( __CLASS__, 'filter_inline_script' ), 10, 2 );
		}

		if ( Settings::enabled( 'block_styles' ) ) {
			add_filter( 'style_loader_tag', array( __CLASS__, 'filter_style' ), 10, 4 );
		}
	}

	/**
	 * Disarms a script tag whose handle needs consent.
	 *
	 * @param string $tag    The complete script tag.
	 * @param string $handle Script handle.
	 * @param string $src    Script source URL.
	 * @return string Filtered tag.
	 */
	public static function filter_script( $tag, $handle, $src ): string {
		$tag = (string) $tag;

		if ( is_admin() ) {
			return $tag;
		}

		$category = Categories::for_handle( (string) $handle, (string) $src );

		if ( Categories::is_required( $category ) ) {
			return $tag;
		}

		return self::disarm_script_tag( $tag, $category );
	}

	/**
	 * Disarms the inline script attached to a blocked handle.
	 *
	 * WordPress prints `wp_add_inline_script()` output as its own tag whose id
	 * is `{handle}-js-before` or `{handle}-js-after`, which is the only place
	 * the handle survives into this filter. Without this, the configuration
	 * object of a blocked tracker would still run.
	 *
	 * @param array<string, mixed> $attributes Attributes of the inline tag.
	 * @param string               $data       The script body.
	 * @return array<string, mixed> Filtered attributes.
	 */
	public static function filter_inline_script( $attributes, $data ): array {
		unset( $data );

		$attributes = (array) $attributes;

		if ( is_admin() || empty( $attributes['id'] ) ) {
			return $attributes;
		}

		$id = (string) $attributes['id'];

		if ( ! preg_match( '/^(.+)-js-(before|after|extra|translations)$/', $id, $matches ) ) {
			return $attributes;
		}

		$category = Categories::for_handle( $matches[1], self::src_of( $matches[1] ) );

		if ( Categories::is_required( $category ) ) {
			return $attributes;
		}

		$attributes['type']            = 'text/plain';
		$attributes[ self::ATTRIBUTE ] = $category;

		return $attributes;
	}

	/**
	 * Disarms a stylesheet whose handle needs consent.
	 *
	 * @param string $tag    The complete link tag.
	 * @param string $handle Style handle.
	 * @param string $href   Stylesheet URL.
	 * @param string $media  Media attribute.
	 * @return string Filtered tag.
	 */
	public static function filter_style( $tag, $handle, $href, $media ): string {
		unset( $media );

		$tag = (string) $tag;

		if ( is_admin() ) {
			return $tag;
		}

		$category = Categories::for_handle( (string) $handle, (string) $href );

		if ( Categories::is_required( $category ) ) {
			return $tag;
		}

		// The two rel="stylesheet" needles below are markup, not an enqueue.
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		$blocked = str_replace(
			array( " href='", ' href="', " rel='stylesheet'", ' rel="stylesheet"' ),
			array( " data-baukasten-href='", ' data-baukasten-href="', '', '' ),
			$tag
		);
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet

		return str_replace(
			'<link ',
			'<link ' . self::ATTRIBUTE . '="' . esc_attr( $category ) . '" ',
			$blocked
		);
	}

	/**
	 * Rewrites every script tag in a blob so the browser ignores them.
	 *
	 * `script_loader_tag` is not handed one tag. WP_Scripts::do_item() glues
	 * the translations, the `before` inline script, the actual `<script src>`
	 * and the `after` inline script into one string and filters that, so
	 * rewriting only the first tag would leave the tracker itself running and
	 * disarm its configuration instead — exactly backwards.
	 *
	 * Tags already carrying the marker are skipped: the inline ones have been
	 * through `wp_inline_script_attributes` by the time they get here.
	 *
	 * @param string $tag      One or more script tags.
	 * @param string $category Category the scripts wait for.
	 * @return string Filtered markup.
	 */
	private static function disarm_script_tag( string $tag, string $category ): string {
		$marker = self::ATTRIBUTE . '="' . esc_attr( $category ) . '"';

		return (string) preg_replace_callback(
			'/<script\b[^>]*>/i',
			static function ( array $matches ) use ( $marker ): string {
				$open = $matches[0];

				if ( str_contains( $open, self::ATTRIBUTE ) ) {
					return $open;
				}

				if ( preg_match( '/\stype=([\'"])(.*?)\1/', $open, $type ) ) {
					$open = str_replace( $type[0], ' type="text/plain"', $open );
				} else {
					$open = (string) preg_replace( '/^<script/i', '<script type="text/plain"', $open, 1 );
				}

				return (string) preg_replace( '/^<script/i', '<script ' . $marker, $open, 1 );
			},
			$tag
		);
	}

	/**
	 * Returns the registered source of a script handle.
	 *
	 * @param string $handle Script handle.
	 * @return string Source URL, or an empty string.
	 */
	private static function src_of( string $handle ): string {
		$scripts = wp_scripts();

		return isset( $scripts->registered[ $handle ] ) ? (string) $scripts->registered[ $handle ]->src : '';
	}
}
