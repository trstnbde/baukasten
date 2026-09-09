<?php
/**
 * Blocking for the emoji detection script.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Stops WordPress loading its emoji polyfill from s.w.org.
 *
 * The detection script is inline, but the twemoji bundle it pulls in is not:
 * it comes from `s.w.org`, so every visitor to a site with an emoji anywhere
 * makes a request to a WordPress.org server before consenting to anything.
 *
 * There is nothing to restore later — the polyfill only backfills emoji on
 * browsers that predate colour emoji fonts — so this is a plain removal
 * rather than a deferred block.
 */
final class Blocking_Emoji {

	/**
	 * Removes the emoji hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_action( 'embed_head', 'print_emoji_detection_script' );

		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		add_filter( 'emoji_svg_url', '__return_false' );
		add_filter( 'wp_resource_hints', array( __CLASS__, 'remove_hint' ), 10, 2 );
	}

	/**
	 * Drops the dns-prefetch hint for the emoji CDN.
	 *
	 * @param array<int|string, mixed> $urls          Hints for this relation type.
	 * @param string                   $relation_type Relation type.
	 * @return array<int|string, mixed> Filtered hints.
	 */
	public static function remove_hint( $urls, $relation_type ): array {
		$urls = (array) $urls;

		if ( 'dns-prefetch' !== (string) $relation_type ) {
			return $urls;
		}

		return array_filter(
			$urls,
			static function ( $url ): bool {
				$href = is_array( $url ) ? (string) ( $url['href'] ?? '' ) : (string) $url;

				return ! str_contains( $href, 's.w.org' );
			}
		);
	}
}
