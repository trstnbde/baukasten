<?php
/**
 * The shortcode.
 *
 * @package Baukasten\Wishlist
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * `[baukasten_wishlist id="12" sort="priority" filter="all" controls="no"]`
 *
 * `filter` defaults to `open`, so bought wishes leave the list.
 *
 * For the classic editor, widgets and anywhere a block cannot go.
 */
final class Shortcode {

	/**
	 * Shortcode tag.
	 */
	const TAG = 'baukasten_wishlist';

	/**
	 * Registers the shortcode.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Renders the shortcode.
	 *
	 * @param mixed $atts Shortcode attributes.
	 * @return string Markup.
	 */
	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'       => '0',
				'sort'     => 'priority',
				'filter'   => Renderer::DEFAULT_STATUS,
				'controls' => 'yes',
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		return Renderer::render(
			array(
				'list'     => $atts['id'],
				'sort'     => $atts['sort'],
				'status'   => $atts['filter'],
				'controls' => (string) $atts['controls'],
			)
		);
	}

	/**
	 * The shortcode for one list, as the admin offers it to copy.
	 *
	 * @param int $list_id List post ID.
	 * @return string Shortcode.
	 */
	public static function for_list( int $list_id ): string {
		return sprintf( '[%s id="%d"]', self::TAG, $list_id );
	}
}
