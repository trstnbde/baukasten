<?php
/**
 * Where the background comes from.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * The three background sources, and what the page needs of each.
 *
 * - `media`, the default: images from the media library, served by this
 *   site. Meant for photos downloaded from unsplash.com under the Unsplash
 *   License, which allows exactly that and asks for no credit (credit is
 *   welcome, and the image caption is where it goes). No API is involved, so
 *   the hotlinking rule does not apply, and no address leaves the site.
 * - `unsplash`: the API pool, hotlinked, behind consent. See `Unsplash`.
 * - `off`: a gradient in the page's own colours.
 *
 * In every mode the page paints a colour first — the photo's dominant colour
 * — and the photo over it once it has decoded.
 */
final class Backgrounds {

	/**
	 * Attachment meta holding the dominant colour.
	 */
	const META_COLOR = '_baukasten_new_tab_color';

	/**
	 * Colour used when nothing better is known.
	 */
	const FALLBACK_COLOR = '#2b2a33';

	/**
	 * Registers the hooks the media list needs.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'delete_attachment', array( __CLASS__, 'forget_attachment' ) );
	}

	/**
	 * Drops a deleted image from the list.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function forget_attachment( $attachment_id ): void {
		$settings = Settings::all();
		$ids      = array_map( 'absint', (array) $settings['media_ids'] );

		if ( ! in_array( (int) $attachment_id, $ids, true ) ) {
			return;
		}

		$settings['media_ids'] = array_values( array_diff( $ids, array( (int) $attachment_id ) ) );

		Settings::save( $settings );
	}

	/**
	 * The mode in force.
	 *
	 * `unsplash` without a key behaves like `off`: there is nothing to show.
	 *
	 * @return string `media`, `unsplash` or `off`.
	 */
	public static function mode(): string {
		$mode = (string) Settings::get( 'background' );

		if ( 'unsplash' === $mode && '' === Settings::unsplash_key() ) {
			return 'off';
		}

		return in_array( $mode, Settings::BACKGROUNDS, true ) ? $mode : 'media';
	}

	/**
	 * The library images, ready for an `<img>`.
	 *
	 * @return array<int, array<string, mixed>> Images.
	 */
	public static function media_items(): array {
		$items = array();

		foreach ( array_map( 'absint', (array) Settings::get( 'media_ids' ) ) as $id ) {
			$item = self::media_item( $id );

			if ( null !== $item ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * One library image, ready for an `<img>`.
	 *
	 * `sizes` is what makes the browser pick a file that covers the screen.
	 * The image is stretched with `object-fit: cover`, so on a screen that is
	 * narrower in proportion than the image, the height decides: there the
	 * image has to be `100vh × width / height` wide.
	 *
	 * @param int $id Attachment ID.
	 * @return array<string, mixed>|null Image, or null when it is not an image.
	 */
	public static function media_item( int $id ): ?array {
		if ( 0 >= $id || ! wp_attachment_is_image( $id ) ) {
			return null;
		}

		$full = wp_get_attachment_image_src( $id, 'full' );

		if ( ! is_array( $full ) || empty( $full[0] ) ) {
			return null;
		}

		$width  = max( 1, (int) $full[1] );
		$height = max( 1, (int) $full[2] );
		$srcset = wp_get_attachment_image_srcset( $id, 'full' );
		$ratio  = $width / $height;
		$large  = wp_get_attachment_image_src( $id, 'large' );

		return array(
			'id'      => $id,
			'src'     => is_array( $large ) && ! empty( $large[0] ) ? (string) $large[0] : (string) $full[0],
			'srcset'  => is_string( $srcset ) ? $srcset : '',
			'sizes'   => sprintf( '(max-aspect-ratio: %1$d/%2$d) %3$dvh, 100vw', $width, $height, (int) ceil( 100 * $ratio ) ),
			'width'   => $width,
			'height'  => $height,
			'color'   => self::color_of( $id ),
			'caption' => trim( wp_strip_all_tags( (string) wp_get_attachment_caption( $id ) ) ),
			'alt'     => trim( wp_strip_all_tags( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ),
		);
	}

	/**
	 * The stored dominant colour of an image, computed on first use.
	 *
	 * @param int $id Attachment ID.
	 * @return string Hex colour.
	 */
	public static function color_of( int $id ): string {
		$color = sanitize_hex_color( (string) get_post_meta( $id, self::META_COLOR, true ) );

		if ( null === $color || '' === $color ) {
			$color = self::compute_color( $id );

			update_post_meta( $id, self::META_COLOR, $color );
		}

		return $color;
	}

	/**
	 * Works out the dominant colour of an image from its smallest size.
	 *
	 * The average of the whole picture, scaled down to one pixel: not the
	 * most frequent colour, but the one that makes the jump from the
	 * placeholder to the photo least noticeable, which is what it is for.
	 * GD when it is there, Imagick otherwise.
	 *
	 * @param int $id Attachment ID.
	 * @return string Hex colour.
	 */
	public static function compute_color( int $id ): string {
		$file = self::smallest_file( $id );

		if ( '' === $file ) {
			return self::FALLBACK_COLOR;
		}

		if ( function_exists( 'imagecreatefromstring' ) ) {
			$data  = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$image = '' !== $data ? @imagecreatefromstring( $data ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if ( false !== $image ) {
				$pixel = imagecreatetruecolor( 1, 1 );

				imagecopyresampled( $pixel, $image, 0, 0, 0, 0, 1, 1, imagesx( $image ), imagesy( $image ) );

				$rgb = imagecolorat( $pixel, 0, 0 );

				return sprintf( '#%02x%02x%02x', ( $rgb >> 16 ) & 0xFF, ( $rgb >> 8 ) & 0xFF, $rgb & 0xFF );
			}
		}

		if ( class_exists( '\Imagick' ) ) {
			try {
				$image = new \Imagick( $file );
				$image->resizeImage( 1, 1, \Imagick::FILTER_BOX, 1 );
				$color = $image->getImagePixelColor( 0, 0 )->getColor();

				return sprintf( '#%02x%02x%02x', (int) $color['r'], (int) $color['g'], (int) $color['b'] );
			} catch ( \Exception $exception ) {
				return self::FALLBACK_COLOR;
			}
		}

		return self::FALLBACK_COLOR;
	}

	/**
	 * The path of the smallest generated size of an image.
	 *
	 * @param int $id Attachment ID.
	 * @return string Absolute path, or an empty string.
	 */
	private static function smallest_file( int $id ): string {
		$original = get_attached_file( $id );

		if ( ! is_string( $original ) || '' === $original ) {
			return '';
		}

		$meta     = wp_get_attachment_metadata( $id );
		$smallest = $original;
		$area     = PHP_INT_MAX;

		if ( is_array( $meta ) && ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				$candidate = path_join( dirname( $original ), (string) ( $size['file'] ?? '' ) );
				$pixels    = (int) ( $size['width'] ?? 0 ) * (int) ( $size['height'] ?? 0 );

				if ( 0 < $pixels && $pixels < $area && is_readable( $candidate ) ) {
					$smallest = $candidate;
					$area     = $pixels;
				}
			}
		}

		return is_readable( $smallest ) ? $smallest : '';
	}

	/**
	 * The background of this request, chosen on the server.
	 *
	 * For the library this is the image itself, so the HTML can carry a
	 * complete `<img srcset>`. For Unsplash it is a photo from the pool the
	 * script sizes for the screen — only when consent is already given, and
	 * only from a pool that is there: a stale pool is refreshed by the
	 * script's next call, not while the page waits.
	 *
	 * @return array<string, mixed> `mode`, `color`, and `media` or `photo`.
	 */
	public static function initial(): array {
		$mode = self::mode();

		if ( 'media' === $mode ) {
			$items = self::media_items();

			if ( array() === $items ) {
				return array(
					'mode'  => 'off',
					'color' => self::FALLBACK_COLOR,
				);
			}

			$item = $items[ wp_rand( 0, count( $items ) - 1 ) ];

			return array(
				'mode'  => 'media',
				'color' => (string) $item['color'],
				'media' => $item,
				'count' => count( $items ),
			);
		}

		if ( 'unsplash' === $mode ) {
			$allowed = Consent::allowed();
			$photos  = $allowed ? Unsplash::pool( false ) : array();
			$photo   = array() !== $photos ? self::for_unsplash( $photos[ wp_rand( 0, count( $photos ) - 1 ) ] ) : null;

			return array(
				'mode'    => 'unsplash',
				'allowed' => $allowed,
				'color'   => null !== $photo && '' !== $photo['color'] ? (string) $photo['color'] : self::FALLBACK_COLOR,
				'photo'   => $photo,
			);
		}

		return array(
			'mode'  => 'off',
			'color' => self::FALLBACK_COLOR,
		);
	}

	/**
	 * What the REST route hands the script for the next change.
	 *
	 * @return array<string, mixed> `mode` and `items`.
	 */
	public static function for_rest(): array {
		$mode = self::mode();

		if ( 'media' === $mode ) {
			return array(
				'mode'  => 'media',
				'items' => self::media_items(),
			);
		}

		if ( 'unsplash' === $mode && Consent::allowed() ) {
			return array(
				'mode'  => 'unsplash',
				'items' => array_map( array( __CLASS__, 'for_unsplash' ), Unsplash::pool() ),
			);
		}

		return array(
			'mode'  => $mode,
			'items' => array(),
		);
	}

	/**
	 * One pool photo with the links the API guidelines ask for.
	 *
	 * @param array<string, mixed> $photo Photo from the pool.
	 * @return array<string, mixed> Photo for the script.
	 */
	public static function for_unsplash( array $photo ): array {
		$utm = array(
			'utm_source' => Unsplash::app_name(),
			'utm_medium' => 'referral',
		);

		return array(
			'raw'     => (string) ( $photo['raw'] ?? '' ),
			'width'   => (int) ( $photo['width'] ?? 0 ),
			'height'  => (int) ( $photo['height'] ?? 0 ),
			'color'   => (string) ( $photo['color'] ?? '' ),
			'alt'     => (string) ( $photo['alt'] ?? '' ),
			'name'    => (string) ( $photo['name'] ?? '' ),
			'profile' => '' !== (string) ( $photo['profile'] ?? '' ) ? add_query_arg( $utm, (string) $photo['profile'] ) : '',
			'site'    => add_query_arg( $utm, 'https://unsplash.com/' ),
		);
	}
}
