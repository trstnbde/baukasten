<?php
/**
 * The three designs a card can wear.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * One description of every skin, used by everything that renders one.
 *
 * The three designs share a single layout skeleton. They differ in typeface,
 * palette, spacing, radii, how a photograph is treated, whether a section is
 * drawn as a framed plate, and whether the design starts light or dark. None of
 * that is structure, so there is one template and this table rather than three
 * templates that would drift apart.
 */
final class Skins {

	/**
	 * The skin a card falls back to.
	 */
	const DEFAULT_SKIN = 'modernist';

	/**
	 * Every skin, as id to description.
	 *
	 * `scheme` is the design's own starting point, written onto the document so
	 * the one assignment layer in `card.css` can branch on it. `frame` turns
	 * each section into a plate with register marks. `fonts` are the two faces
	 * worth preloading — the ones that paint above the fold.
	 *
	 * @return array<string, array<string, mixed>> Skin id to description.
	 */
	public static function all(): array {
		$skins = array(
			'modernist' => array(
				'label'  => __( 'Modernist', 'baukasten-business-cards' ),
				'scheme' => 'light',
				'frame'  => false,
				'fonts'  => array( 'archivo-latin-400.woff2', 'archivo-latin-800.woff2' ),
			),
			'industry'  => array(
				'label'  => __( 'Industry', 'baukasten-business-cards' ),
				'scheme' => 'light',
				'frame'  => true,
				'fonts'  => array( 'barlow-latin-400.woff2', 'barlow-condensed-latin-600.woff2' ),
			),
			'nocturne'  => array(
				'label'  => __( 'Nocturne', 'baukasten-business-cards' ),
				'scheme' => 'dark',
				'frame'  => false,
				'fonts'  => array( 'inter-latin-400.woff2', 'inter-latin-600.woff2' ),
			),
		);

		/**
		 * Filters the designs a card can be rendered in.
		 *
		 * A skin added here needs a stylesheet at `assets/css/skins/<id>.css`,
		 * or a `stylesheet` key holding its URL.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string, array<string, mixed>> $skins Skin id to description.
		 */
		return (array) apply_filters( 'baukasten/business_cards/skins', $skins );
	}

	/**
	 * The id of every skin.
	 *
	 * @return string[] Skin ids.
	 */
	public static function ids(): array {
		return array_map( 'strval', array_keys( self::all() ) );
	}

	/**
	 * One skin's description, filled in and never null.
	 *
	 * A card must always render, so an unknown id falls back rather than
	 * failing.
	 *
	 * @param string $id Skin id.
	 * @return array<string, mixed> Skin description, carrying its own id.
	 */
	public static function get( string $id ): array {
		$skins = self::all();

		if ( ! isset( $skins[ $id ] ) || ! is_array( $skins[ $id ] ) ) {
			$id = isset( $skins[ self::DEFAULT_SKIN ] ) ? self::DEFAULT_SKIN : (string) array_key_first( $skins );
		}

		$skin = array_merge(
			array(
				'label'      => $id,
				'scheme'     => 'light',
				'frame'      => false,
				'fonts'      => array(),
				'stylesheet' => PLUGIN_URL . 'assets/css/skins/' . $id . '.css',
			),
			(array) $skins[ $id ]
		);

		$skin['id'] = $id;

		return $skin;
	}

	/**
	 * Whether a skin draws its sections as framed plates.
	 *
	 * @param array<string, mixed> $skin Skin description.
	 * @return bool True to print the register marks.
	 */
	public static function frames( array $skin ): bool {
		return ! empty( $skin['frame'] );
	}

	/**
	 * Opens a card section, with its heading and its register marks.
	 *
	 * The frame is one decision in one place, so no partial has to know which
	 * design it is being rendered in. The four marks are elements rather than a
	 * pseudo-element trick because each is an L of two hairlines sitting six
	 * pixels *outside* the border, and a background cannot paint past the border
	 * box. They are printed only for a design that asks for them.
	 *
	 * @param array<string, mixed> $skin     The design being rendered.
	 * @param string               $modifier Section modifier, such as `contact`.
	 * @param string               $title    Heading, or an empty string for none.
	 * @return void
	 */
	public static function open_section( array $skin, string $modifier, string $title = '' ): void {
		printf(
			'<section class="bkbc-section bkbc-%s">',
			esc_attr( $modifier )
		);

		if ( self::frames( $skin ) ) {
			foreach ( array( 'tl', 'tr', 'bl', 'br' ) as $corner ) {
				printf(
					'<i class="bkbc-corner bkbc-corner--%s" aria-hidden="true"></i>',
					esc_attr( $corner )
				);
			}
		}

		if ( '' !== $title ) {
			printf( '<h2 class="bkbc-section__title">%s</h2>', esc_html( $title ) );
		}
	}

	/**
	 * Closes a card section.
	 *
	 * A function rather than a literal `</section>` in ten partials, so the
	 * wrapper can change in one place.
	 *
	 * @return void
	 */
	public static function close_section(): void {
		echo '</section>';
	}

	/**
	 * The labels, for the design picker.
	 *
	 * @return array<string, string> Skin id to label.
	 */
	public static function choices(): array {
		$choices = array();

		foreach ( self::all() as $id => $skin ) {
			$choices[ (string) $id ] = isset( $skin['label'] ) ? (string) $skin['label'] : (string) $id;
		}

		return $choices;
	}
}
