<?php
/**
 * Uploading and serving an Apple Wallet pass.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * Serves a card's Apple Wallet pass, with the one header that makes it work.
 *
 * A `.pkpass` is a ZIP with a fixed manifest and a detached signature, and
 * everything about handing one to a phone is the media type: iOS decides whether
 * to open Wallet from that alone. A web server that has never heard of the
 * extension sends `application/octet-stream`, and the visitor is offered a
 * download they can do nothing with. So the file is served from here rather than
 * from its uploads URL — which is also why the button never points at that URL.
 */
final class Pass {

	/**
	 * The pass media type. Apple's, exactly; nothing else opens Wallet.
	 */
	const MIME = 'application/vnd.apple.pkpass';

	/**
	 * Query string value that asks for the file.
	 */
	const ACTION = 'pkpass';

	/**
	 * Registers the hooks the pass needs.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'upload_mimes', array( __CLASS__, 'allow_upload' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ), 5 );
	}

	/**
	 * Lets a `.pkpass` through the uploader.
	 *
	 * Priority 20 rather than the default: on multisite `check_upload_mimes()`
	 * filters at 10 and intersects the list with the network's allowed
	 * extensions, which will not contain this one. Running after it puts the
	 * type back.
	 *
	 * No `wp_check_filetype_and_ext` filter is needed with it, and adding one
	 * would only widen what gets past core's check. Core sniffs the upload as
	 * `application/zip`, which is in its own `$nonspecific_types` list, and the
	 * branch that takes only requires the declared type's major part to be
	 * `application` — which `application/vnd.apple.pkpass` is.
	 *
	 * @param mixed $mimes Allowed extension pattern to media type.
	 * @return array<string, string> Allowed types.
	 */
	public static function allow_upload( $mimes ): array {
		$mimes = array_map( 'strval', (array) $mimes );

		$mimes['pkpass'] = self::MIME;

		return $mimes;
	}

	/**
	 * The URL a card's pass is downloaded from.
	 *
	 * @param int $post_id Card post ID.
	 * @return string Download URL.
	 */
	public static function url( int $post_id ): string {
		return add_query_arg( 'action', self::ACTION, (string) get_permalink( $post_id ) );
	}

	/**
	 * Whether a card really has a pass to offer.
	 *
	 * Called from the template so a card whose attachment was deleted shows no
	 * button, rather than a button that leads nowhere.
	 *
	 * @param array<string, mixed> $card Loaded card fields.
	 * @return bool True when the file is there and is a pass.
	 */
	public static function available( array $card ): bool {
		return isset( $card['wallet_apple_pass_id'] )
			&& '' !== self::path( (int) $card['wallet_apple_pass_id'] );
	}

	/**
	 * Serves the pass when this request asked for it.
	 *
	 * @return void
	 */
	public static function maybe_serve(): void {
		if ( ! is_singular( Post_Type::POST_TYPE ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public, idempotent GET on a public page.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : '';

		if ( self::ACTION !== $action ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		/*
		 * The authorisation rule, and the reason this is not an
		 * `?attachment_id=` route: the id is read from the card being viewed and
		 * from nowhere else. A caller cannot name a file, only a card, and the
		 * card names its own file.
		 */
		$card = Fields::load( $post->ID );

		if ( ! isset( $card['wallet_apple_pass_id'] ) ) {
			return;
		}

		$path = self::path( (int) $card['wallet_apple_pass_id'] );

		if ( '' === $path ) {
			return;
		}

		self::serve( $post, $path );
	}

	/**
	 * The readable file behind an attachment, if it really is a pass.
	 *
	 * Three checks, none of them redundant. The media type is what stops this
	 * route serving anything else at all. The containment check is against an id
	 * that was valid when it was stored and has since been repointed at a file
	 * outside the uploads tree.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Absolute path, or an empty string.
	 */
	private static function path( int $attachment_id ): string {
		if ( 0 >= $attachment_id || self::MIME !== get_post_mime_type( $attachment_id ) ) {
			return '';
		}

		$file = get_attached_file( $attachment_id );

		if ( ! is_string( $file ) || '' === $file || ! is_readable( $file ) ) {
			return '';
		}

		$uploads = wp_get_upload_dir();
		$base    = realpath( (string) ( $uploads['basedir'] ?? '' ) );
		$real    = realpath( $file );

		if ( false === $base || false === $real ) {
			return '';
		}

		return str_starts_with( wp_normalize_path( $real ), trailingslashit( wp_normalize_path( $base ) ) )
			? $real
			: '';
	}

	/**
	 * Sends the file and ends the request.
	 *
	 * @param \WP_Post $post The card.
	 * @param string   $path Absolute path to the pass.
	 * @return void
	 */
	private static function serve( \WP_Post $post, string $path ): void {
		while ( 0 < ob_get_level() ) {
			ob_end_clean();
		}

		if ( headers_sent() ) {
			// A pass with a PHP notice glued to the front fails its signature
			// check on the phone and says nothing about why. Fall back to the
			// card rather than send a broken file.
			return;
		}

		$filename = $post->post_name . '.pkpass';

		nocache_headers();
		status_header( 200 );

		header( 'Content-Type: ' . self::MIME );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'Content-Transfer-Encoding: binary' );
		// No intermediary may re-sniff this back to application/zip: the type is
		// the only thing that opens Wallet.
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Accept-Ranges: none' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a binary file to the browser; WP_Filesystem would read the whole pass into memory first.
		readfile( $path );

		exit;
	}
}
