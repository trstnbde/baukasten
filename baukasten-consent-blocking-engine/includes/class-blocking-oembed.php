<?php
/**
 * Blocking for oEmbeds.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces third-party embeds with a placeholder until consent is given.
 *
 * A YouTube or Vimeo embed is an iframe to somebody else's server, and the
 * browser loads it the moment the page renders. The markup is therefore not
 * printed at all: it is parked inside a `<template>`, which the parser reads
 * but does not fetch anything from, next to a short notice. The bootstrap
 * script moves the template's content into the page once the visitor has
 * allowed the category.
 *
 * Embeds served from this site — a post from the same install, an audio file
 * in the media library — are left alone.
 *
 * Wrapping happens on `embed_oembed_html`, which runs every time an embed is
 * printed, and deliberately not on `oembed_result`, which runs once when the
 * provider is queried and whose return value WordPress stores in post meta.
 * Wrapping there would bake a placeholder into that cache: the stored markup
 * would keep the category and the wording it had on the day it was fetched,
 * survive every later change, and get wrapped a second time on output.
 *
 * Known gap: a theme calling `wp_oembed_get()` directly bypasses
 * `embed_oembed_html` and is not covered.
 */
final class Blocking_Oembed {

	/**
	 * Registers the filters.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'embed_oembed_html', array( __CLASS__, 'filter_html' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Wraps an embed in a placeholder as it is printed.
	 *
	 * @param string $html The embed markup.
	 * @param string $url  The URL that was embedded.
	 * @return string Filtered markup.
	 */
	public static function filter_html( $html, $url ): string {
		return self::wrap( (string) $html, (string) $url );
	}

	/**
	 * Builds the placeholder around an embed.
	 *
	 * @param string $html The embed markup.
	 * @param string $url  The URL that was embedded.
	 * @return string Placeholder markup, or the original.
	 */
	private static function wrap( string $html, string $url ): string {
		if ( is_admin() || is_feed() || '' === trim( $html ) ) {
			return $html;
		}

		// Belt and braces against a doubly wrapped placeholder, whatever put
		// the first one there.
		if ( str_contains( $html, 'baukasten-consent-embed' ) ) {
			return $html;
		}

		if ( ! Categories::is_external( $url ) ) {
			return $html;
		}

		$category = (string) Settings::get( 'oembed_category' );

		if ( ! Categories::exists( $category ) || Categories::is_required( $category ) ) {
			return $html;
		}

		$provider = (string) wp_parse_url( $url, PHP_URL_HOST );
		$label    = Categories::all()[ $category ]['label'];

		$notice = sprintf(
			/* translators: 1: provider host name, 2: consent category label. */
			__( 'Content from %1$s is hidden until you allow the category “%2$s”.', 'baukasten-consent-blocking-engine' ),
			$provider,
			$label
		);

		/**
		 * Filters the notice shown in place of a blocked embed.
		 *
		 * @since 1.0.0
		 *
		 * @param string $notice   Notice text.
		 * @param string $provider Provider host name.
		 * @param string $category Category slug.
		 */
		$notice = (string) apply_filters( 'baukasten/consent/embed_notice', $notice, $provider, $category );

		/*
		 * The two buttons are what makes this a two-click solution rather
		 * than a dead end. Without a consent banner — and this plugin
		 * deliberately ships none — the placeholder would otherwise stay a
		 * placeholder forever.
		 *
		 * "Load" affects this one embed and stores nothing at all. "Load and
		 * always allow" writes the category through the REST endpoint, which
		 * is the same decision a banner would record.
		 */
		return sprintf(
			'<div class="baukasten-consent-embed" %1$s="%2$s" data-baukasten-provider="%3$s">' .
			'<p class="baukasten-consent-embed__notice">%4$s</p>' .
			'<p class="baukasten-consent-embed__actions">' .
			'<button type="button" class="baukasten-consent-embed__button" data-baukasten-action="load">%5$s</button>' .
			'<button type="button" class="baukasten-consent-embed__button baukasten-consent-embed__button--link" data-baukasten-action="allow">%6$s</button>' .
			'</p>' .
			'<template class="baukasten-consent-embed__payload">%7$s</template>' .
			'</div>',
			esc_attr( Blocking_Scripts::ATTRIBUTE ),
			esc_attr( $category ),
			esc_attr( $provider ),
			esc_html( $notice ),
			esc_html__( 'Load content', 'baukasten-consent-blocking-engine' ),
			esc_html(
				sprintf(
					/* translators: %s: consent category label. */
					__( 'Load and always allow “%s”', 'baukasten-consent-blocking-engine' ),
					$label
				)
			),
			$html
		);
	}
}
