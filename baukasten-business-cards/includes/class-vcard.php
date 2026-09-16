<?php
/**
 * The vCard download.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * Serves a card as a vCard 3.0 file.
 *
 * RFC 2426 is short but unforgiving in two places, and both are handled here
 * rather than hoped about: text values escape `\`, `;`, `,` and newlines, in
 * that order, while the semicolons that separate the components of `N` and
 * `ADR` are structure and must stay bare; and a content line is folded at 75
 * *octets*, not characters, which means a fold must never land in the middle of
 * a multi-byte sequence.
 */
final class VCard {

	/**
	 * Query string value that asks for the file.
	 */
	const ACTION = 'vcf';

	/**
	 * Octets a folded content line may hold.
	 */
	const FOLD_AT = 75;

	/**
	 * Registers the hooks the download needs.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ), 5 );
	}

	/**
	 * The URL a card's vCard is downloaded from.
	 *
	 * @param int $post_id Card post ID.
	 * @return string Download URL.
	 */
	public static function url( int $post_id ): string {
		return add_query_arg( 'action', self::ACTION, (string) get_permalink( $post_id ) );
	}

	/**
	 * Serves the file when this request asked for it.
	 *
	 * @return void
	 */
	public static function maybe_serve(): void {
		if ( ! is_singular( Post_Type::POST_TYPE ) ) {
			return;
		}

		/*
		 * Read from $_GET directly. `action` is not one of WordPress's public
		 * query vars, so `get_query_var( 'action' )` returns an empty string
		 * here however the URL was built.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public, idempotent GET on a public page.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : '';

		if ( '' === $action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- as above.
			$action = isset( $_GET['bkcard'] ) ? sanitize_key( wp_unslash( (string) $_GET['bkcard'] ) ) : '';
		}

		if ( self::ACTION !== $action ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$card = Fields::load( $post->ID );

		if ( ! isset( $card['enable_vcf'] ) ) {
			return;
		}

		self::serve( $post, $card );
	}

	/**
	 * Sends the file and ends the request.
	 *
	 * @param \WP_Post             $post The card.
	 * @param array<string, mixed> $card Its fields.
	 * @return void
	 */
	private static function serve( \WP_Post $post, array $card ): void {
		$body = self::build( $post, $card );

		/*
		 * Core does not buffer at this point — its own template buffer starts
		 * much later, from the template loader. Any buffer open here belongs to
		 * a plugin whose callback would mangle the file, so it goes.
		 */
		while ( 0 < ob_get_level() ) {
			ob_end_clean();
		}

		if ( headers_sent() ) {
			// Something already printed. A .vcf with a PHP notice glued to the
			// front is worse than no download, so fall through to the card.
			return;
		}

		$filename = $post->post_name . '.vcf';

		nocache_headers();
		status_header( 200 );

		header( 'Content-Type: text/vcard; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
		header( 'Content-Length: ' . strlen( $body ) );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a vCard body, escaped per RFC 2426 in build().
		echo $body;

		/*
		 * `exit`, not `wp_die()`: that would send Content-Type: text/html and
		 * put a whole error page in front of the file. This is a success path.
		 */
		exit;
	}

	/**
	 * Builds the vCard body.
	 *
	 * @param \WP_Post             $post The card.
	 * @param array<string, mixed> $card Its fields.
	 * @return string The file contents, CRLF throughout.
	 */
	private static function build( \WP_Post $post, array $card ): string {
		$lines = array();

		$lines[] = 'BEGIN:VCARD';
		$lines[] = 'VERSION:3.0';
		$lines[] = 'PRODID:-//Baukasten//Business Cards ' . VERSION . '//EN';

		$lines[] = 'N:' . implode(
			';',
			array(
				self::text( (string) ( $card['last_name'] ?? '' ) ),
				self::text( (string) ( $card['first_name'] ?? '' ) ),
				'',
				self::text( (string) ( $card['academic_title'] ?? '' ) ),
				'',
			)
		);

		$lines[] = 'FN:' . self::text( self::display_name( $post, $card ) );

		if ( isset( $card['company'] ) ) {
			$lines[] = 'ORG:' . self::text( (string) $card['company'] );
		}

		if ( isset( $card['position'] ) ) {
			$lines[] = 'TITLE:' . self::text( (string) $card['position'] );
		}

		$telephones = array(
			'phone'  => 'WORK,VOICE',
			'mobile' => 'CELL',
		);

		// Kept past the loop: the assistant's number below is checked against it too.
		$numbers = array();

		foreach ( $telephones as $key => $types ) {
			if ( ! isset( $card[ $key ] ) ) {
				continue;
			}

			$number = (string) $card[ $key ];

			// A contact with the same number twice is nobody's idea of helpful.
			if ( in_array( $number, $numbers, true ) ) {
				continue;
			}

			$numbers[] = $number;
			$lines[]   = 'TEL;TYPE=' . $types . ':' . self::text( $number );
		}

		$emails = array(
			'email'   => 'INTERNET,PREF',
			'email_2' => 'INTERNET',
		);

		$seen = array();

		foreach ( $emails as $key => $types ) {
			if ( ! isset( $card[ $key ] ) ) {
				continue;
			}

			$address = (string) $card[ $key ];

			if ( in_array( $address, $seen, true ) ) {
				continue;
			}

			$seen[]  = $address;
			$lines[] = 'EMAIL;TYPE=' . $types . ':' . self::text( $address );
		}

		/*
		 * vCard 3.0 has no type for an assistant, neither for the person nor for
		 * their number. Apple's grouped labels are the closest thing to a standard:
		 * Contacts on iOS and macOS show "Assistant" for both, and every other
		 * program reads `item1.TEL` as a plain number and skips the extension
		 * properties it does not know — which is the right failure, because a
		 * made-up TYPE value would be filed under the wrong heading instead.
		 */
		$group = 0;

		if ( isset( $card['assistant'] ) ) {
			++$group;

			$lines[] = 'item' . $group . '.X-ABRELATEDNAMES:' . self::text( (string) $card['assistant'] );
			$lines[] = 'item' . $group . '.X-ABLabel:_$!<Assistant>!$_';
		}

		if ( isset( $card['assistant_phone'] ) && ! in_array( (string) $card['assistant_phone'], $numbers, true ) ) {
			++$group;

			$lines[] = 'item' . $group . '.TEL:' . self::text( (string) $card['assistant_phone'] );
			$lines[] = 'item' . $group . '.X-ABLabel:_$!<Assistant>!$_';
		}

		$website = (string) ( $card['contact_website'] ?? '' );

		if ( '' !== $website ) {
			$lines[] = 'URL:' . self::text( (string) $website );
		}

		if ( isset( $card['contact_address'] ) ) {
			/*
			 * ADR has seven components, and a free-text address cannot be split
			 * into them reliably — every heuristic is wrong for some country.
			 * The whole thing goes into the street component with escaped line
			 * breaks, and LABEL carries the presentation form, which is what
			 * RFC 2426 provides it for.
			 */
			$address = self::text( (string) $card['contact_address'] );

			$lines[] = 'ADR;TYPE=WORK:;;' . $address . ';;;;';
			$lines[] = 'LABEL;TYPE=WORK:' . $address;
		}

		if ( isset( $card['bio_text'] ) ) {
			$lines[] = 'NOTE:' . self::text( self::plain_text( (string) $card['bio_text'] ) );
		}

		$lines[] = 'UID:' . self::text( (string) get_permalink( $post ) );
		$lines[] = 'REV:' . gmdate( 'Y-m-d\TH:i:s\Z', (int) get_post_modified_time( 'U', true, $post ) );
		$lines[] = 'END:VCARD';

		$body = '';

		foreach ( $lines as $line ) {
			$body .= self::fold( $line ) . "\r\n";
		}

		return $body;
	}

	/**
	 * The name a contact app will show.
	 *
	 * The salutation is deliberately left out of both `N` and `FN`: contact
	 * apps render it as part of the name, and "Herr Torsten B." reads badly in
	 * a contact list. It stays on the HTML card, where it belongs.
	 *
	 * @param \WP_Post             $post The card.
	 * @param array<string, mixed> $card Its fields.
	 * @return string A non-empty display name.
	 */
	private static function display_name( \WP_Post $post, array $card ): string {
		$parts = array_filter(
			array(
				(string) ( $card['academic_title'] ?? '' ),
				(string) ( $card['first_name'] ?? '' ),
				(string) ( $card['last_name'] ?? '' ),
			)
		);

		$name = trim( implode( ' ', $parts ) );

		if ( '' !== $name ) {
			return $name;
		}

		if ( isset( $card['company'] ) ) {
			return (string) $card['company'];
		}

		return (string) get_the_title( $post );
	}

	/**
	 * Escapes a TEXT value.
	 *
	 * The backslash is doubled first, or the escapes added afterwards would be
	 * doubled along with it.
	 *
	 * @param string $value Raw value.
	 * @return string Escaped value.
	 */
	private static function text( string $value ): string {
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$value = str_replace( '\\', '\\\\', $value );
		$value = str_replace( ';', '\;', $value );
		$value = str_replace( ',', '\,', $value );

		return str_replace( "\n", '\n', $value );
	}

	/**
	 * Turns the HTML biography into something a NOTE can hold.
	 *
	 * @param string $html Biography markup.
	 * @return string Plain text with paragraph breaks kept.
	 */
	private static function plain_text( string $html ): string {
		$text = (string) preg_replace( '#</(p|div|li|h[1-6])>#i', "\n", $html );
		$text = str_replace( array( '<br>', '<br/>', '<br />' ), "\n", $text );
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
		$text = (string) preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( $text );
	}

	/**
	 * Folds one content line at 75 octets.
	 *
	 * Split per UTF-8 character with `preg_split` rather than `mb_str_split`,
	 * so this does not depend on an extension WordPress does not require.
	 *
	 * @param string $line One content line.
	 * @return string The line, folded if it needed it.
	 */
	private static function fold( string $line ): string {
		if ( self::FOLD_AT >= strlen( $line ) ) {
			return $line;
		}

		$characters = preg_split( '//u', $line, -1, PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $characters ) ) {
			return $line;
		}

		$folded = '';
		$buffer = '';
		$octets = 0;

		foreach ( $characters as $character ) {
			$width = strlen( $character );

			if ( self::FOLD_AT < $octets + $width ) {
				$folded .= $buffer . "\r\n ";
				$buffer  = '';

				// The continuation space is part of the next line's budget.
				$octets = 1;
			}

			$buffer .= $character;
			$octets += $width;
		}

		return $folded . $buffer;
	}
}
