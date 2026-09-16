<?php
/**
 * The card's field schema.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * One description of every card field, used by everything that touches one.
 *
 * Fifty-odd fields sanitised in one file, rendered in another and read in a
 * third is three lists that drift apart. Here there is one: `types()` says what
 * a field is and `groups()` says where it goes, and the meta boxes, the save
 * handler, the template and the vCard all ask rather than knowing on their own.
 *
 * Labels are deliberately not part of it. They are translated strings, the
 * front end never needs them, and keeping them apart means rendering a card
 * does not run fifty `__()` calls.
 */
final class Fields {

	/**
	 * Prefix every card meta key carries.
	 *
	 * Leading underscore: these are not free-form custom fields and have no
	 * business in the Custom Fields box.
	 */
	const PREFIX = '_baukasten_card_';

	/**
	 * How a card decides between its light and its dark palette.
	 */
	const SCHEMES = array( 'system', 'light', 'dark' );

	/**
	 * The colour scheme a card falls back to.
	 */
	const DEFAULT_SCHEME = 'system';

	/**
	 * Every field, as unprefixed key to type.
	 *
	 * @return array<string, string> Field key to type.
	 */
	public static function types(): array {
		return array(
			'card_layout'          => 'layout',
			'card_color_scheme'    => 'scheme',
			'show_qr_modal'        => 'bool',
			'show_theme_toggle'    => 'bool',

			'banner_image_id'      => 'image',
			'avatar_image_id'      => 'image',

			'salutation'           => 'text',
			'academic_title'       => 'text',
			'first_name'           => 'text',
			'last_name'            => 'text',
			'position'             => 'text',
			'company'              => 'text',
			'bio_text'             => 'html',

			'email'                => 'email',
			'email_2'              => 'email',
			'phone'                => 'tel',
			'mobile'               => 'tel',
			'assistant'            => 'text',
			'assistant_phone'      => 'tel',
			'contact_website'      => 'url',
			'contact_address'      => 'textarea',
			'what3words_link'      => 'url',

			'network_linkedin'     => 'url',
			'network_xing'         => 'url',
			'network_github'       => 'url',
			'network_mastodon'     => 'url',
			'network_facebook'     => 'url',
			'network_instagram'    => 'url',
			'network_threads'      => 'url',
			'network_discord'      => 'url',
			'network_signal'       => 'url',

			'custom_link_1_label'  => 'text',
			'custom_link_1_url'    => 'url',
			'custom_link_2_label'  => 'text',
			'custom_link_2_url'    => 'url',
			'custom_link_3_label'  => 'text',
			'custom_link_3_url'    => 'url',

			'download_1_label'     => 'text',
			'download_1_file_id'   => 'file',
			'download_2_label'     => 'text',
			'download_2_file_id'   => 'file',
			'download_3_label'     => 'text',
			'download_3_file_id'   => 'file',

			'enable_vcf'           => 'bool',
			'wallet_apple_pass_id' => 'file',
			'wallet_google_jwt'    => 'jwt',

			'cf7_form_id'          => 'form',

			'footer_privacy_page'  => 'page',
			'footer_terms_page'    => 'page',
			'footer_imprint_page'  => 'page',
		);
	}

	/**
	 * Which fields belong to which group, in the order they are rendered.
	 *
	 * Kept apart from the types rather than nested inside them: this way the
	 * order of a group's fields is written down where it is read, and neither
	 * table needs a two-level array to say one thing.
	 *
	 * @return array<string, string[]> Group id to field keys.
	 */
	public static function groups(): array {
		return array(
			'design'    => array( 'card_layout', 'card_color_scheme', 'show_qr_modal', 'show_theme_toggle' ),
			'images'    => array( 'banner_image_id', 'avatar_image_id' ),
			'basics'    => array( 'salutation', 'academic_title', 'first_name', 'last_name', 'position', 'company', 'bio_text' ),
			'contact'   => array( 'email', 'email_2', 'phone', 'mobile', 'assistant', 'assistant_phone', 'contact_website', 'contact_address', 'what3words_link' ),
			'social'    => array( 'network_linkedin', 'network_xing', 'network_github', 'network_mastodon', 'network_facebook', 'network_instagram', 'network_threads', 'network_discord', 'network_signal' ),
			'links'     => array( 'custom_link_1_label', 'custom_link_1_url', 'custom_link_2_label', 'custom_link_2_url', 'custom_link_3_label', 'custom_link_3_url' ),
			'downloads' => array( 'download_1_label', 'download_1_file_id', 'download_2_label', 'download_2_file_id', 'download_3_label', 'download_3_file_id' ),
			'wallet'    => array( 'enable_vcf', 'wallet_apple_pass_id', 'wallet_google_jwt' ),
			'form'      => array( 'cf7_form_id' ),
			'legal'     => array( 'footer_privacy_page', 'footer_terms_page', 'footer_imprint_page' ),
		);
	}

	/**
	 * The type of one field.
	 *
	 * @param string $key Unprefixed field key.
	 * @return string Schema type, or `text` for a key that has none.
	 */
	public static function type( string $key ): string {
		$types = self::types();

		return isset( $types[ $key ] ) ? $types[ $key ] : 'text';
	}

	/**
	 * The keys belonging to one group.
	 *
	 * @param string $group Group id.
	 * @return string[] Unprefixed field keys.
	 */
	public static function group( string $group ): array {
		$groups = self::groups();

		return isset( $groups[ $group ] ) ? $groups[ $group ] : array();
	}

	/**
	 * The full meta key for a field.
	 *
	 * @param string $key Unprefixed field key.
	 * @return string Meta key.
	 */
	public static function meta_key( string $key ): string {
		return self::PREFIX . $key;
	}

	/**
	 * Reads every field of a card in one pass.
	 *
	 * `get_post_meta( $id )` without a key has two behaviours worth knowing: it
	 * returns values raw, without `maybe_unserialize()`, and it does not apply
	 * registered defaults, because the default filter matches on an exact key
	 * and there is none. Both are compensated here.
	 *
	 * Anything empty is dropped, so a template can ask `isset()` and never
	 * think about it again. `card_layout` is the one key guaranteed to exist.
	 *
	 * @param int $post_id Card post ID.
	 * @return array<string, mixed> Present, normalised fields.
	 */
	public static function load( int $post_id ): array {
		$raw = get_post_meta( $post_id );
		$raw = is_array( $raw ) ? $raw : array();

		$card = array();

		foreach ( self::types() as $key => $type ) {
			$meta_key = self::PREFIX . $key;
			$value    = isset( $raw[ $meta_key ][0] ) ? maybe_unserialize( $raw[ $meta_key ][0] ) : null;
			$value    = self::normalise( $type, $value );

			if ( null === $value ) {
				continue;
			}

			$card[ $key ] = $value;
		}

		if ( ! isset( $card['card_layout'] ) ) {
			$card['card_layout'] = Skins::DEFAULT_SKIN;
		}

		if ( ! isset( $card['card_color_scheme'] ) ) {
			$card['card_color_scheme'] = self::DEFAULT_SCHEME;
		}

		/**
		 * Filters a card's fields just before they are rendered.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $card    Present, normalised fields.
		 * @param int                  $post_id Card post ID.
		 */
		return (array) apply_filters( 'baukasten/business_cards/card', $card, $post_id );
	}

	/**
	 * Whether a card has at least one of the given fields.
	 *
	 * Lets a template drop a whole section — heading, wrapper, separator —
	 * rather than render an empty box around nothing.
	 *
	 * @param array<string, mixed> $card Loaded card.
	 * @param string[]             $keys Keys the section needs.
	 * @return bool True when at least one is present.
	 */
	public static function has_any( array $card, array $keys ): bool {
		return array() !== array_intersect_key( $card, array_flip( $keys ) );
	}

	/**
	 * Pulls the token out of whatever was pasted into the field.
	 *
	 * What people actually have in the clipboard is the whole
	 * `https://pay.google.com/gp/v/save/<token>` address, and refusing that
	 * teaches nobody anything. A token copied out of a terminal arrives with
	 * line breaks in it, which is the other common shape.
	 *
	 * @param string $value Raw field value.
	 * @return string The token, as far as it can be recovered.
	 */
	private static function extract_jwt( string $value ): string {
		$value = (string) preg_replace( '/\s+/', '', trim( $value ) );

		if ( str_starts_with( $value, 'http' ) ) {
			$path  = (string) wp_parse_url( $value, PHP_URL_PATH );
			$value = (string) substr( (string) strrchr( '/' . $path, '/' ), 1 );
		}

		return $value;
	}

	/**
	 * Whether a string has the shape of a JSON Web Token.
	 *
	 * Shape only. The signature is deliberately not checked: verifying it needs
	 * the issuer's public key, which this plugin has no way to hold, and a check
	 * that cannot fail is worse than no check because it reads like one that can.
	 * All this rules out is a pasted save-URL or a stray sentence.
	 *
	 * @param string $jwt Candidate token.
	 * @return bool True when it is three base64url segments with a readable header.
	 */
	private static function looks_like_a_jwt( string $jwt ): bool {
		if ( ! preg_match( '#^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$#', $jwt ) ) {
			return false;
		}

		$segments = explode( '.', $jwt );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- a JWT header is base64url by definition; this reads it, it does not hide anything.
		$header = base64_decode( strtr( $segments[0], '-_', '+/' ), true );

		if ( ! is_string( $header ) ) {
			return false;
		}

		$decoded = json_decode( $header, true );

		return is_array( $decoded ) && isset( $decoded['alg'] );
	}

	/**
	 * Turns one stored value into something renderable, or null to drop it.
	 *
	 * @param string $type  Schema type.
	 * @param mixed  $value Stored value.
	 * @return mixed Normalised value, or null when there is nothing to render.
	 */
	private static function normalise( string $type, $value ) {
		if ( null === $value || is_array( $value ) ) {
			return null;
		}

		switch ( $type ) {
			case 'bool':
				// An unticked box is dropped rather than returned as false, so
				// every key in a loaded card is a value worth rendering.
				return rest_sanitize_boolean( $value ) ? true : null;

			case 'image':
			case 'file':
			case 'form':
				$id = absint( $value );

				return 0 < $id ? $id : null;

			case 'layout':
				$layout = (string) $value;

				// Never dropped: the template must always have a design. The
				// list lives in Skins, which is also what renders one.
				return in_array( $layout, Skins::ids(), true ) ? $layout : Skins::DEFAULT_SKIN;

			case 'scheme':
				$scheme = (string) $value;

				// Never dropped either, for the same reason.
				return in_array( $scheme, self::SCHEMES, true ) ? $scheme : self::DEFAULT_SCHEME;

			case 'page':
				$page_id = absint( $value );

				/*
				 * Only the id is checked here. Whether the page is published is
				 * asked at render time, in Legal_Links: a page put back into
				 * draft for an afternoon should not have the editor's choice
				 * quietly disappear from the card.
				 */
				return 0 < $page_id ? $page_id : null;

			case 'jwt':
				$jwt = trim( (string) $value );

				return self::looks_like_a_jwt( $jwt ) ? $jwt : null;

			case 'email':
				$email = sanitize_email( (string) $value );

				return is_email( $email ) ? $email : null;

			case 'url':
				$url = esc_url_raw( (string) $value );

				return '' !== $url ? $url : null;

			case 'html':
				$html = trim( wp_kses_post( (string) $value ) );

				return '' !== $html ? $html : null;

			case 'textarea':
				$text = trim( sanitize_textarea_field( (string) $value ) );

				return '' !== $text ? $text : null;

			case 'tel':
			case 'text':
			default:
				$text = trim( sanitize_text_field( (string) $value ) );

				return '' !== $text ? $text : null;
		}
	}

	/**
	 * Reduces a submitted value the way `load()` will read it back.
	 *
	 * @param string $type  Schema type.
	 * @param mixed  $value Raw, unslashed submitted value.
	 * @return string The value to store, or an empty string to delete the meta.
	 */
	public static function sanitize( string $type, $value ): string {
		if ( is_array( $value ) ) {
			return '';
		}

		switch ( $type ) {
			case 'bool':
				return rest_sanitize_boolean( $value ) ? '1' : '';

			case 'image':
			case 'file':
			case 'form':
				$id = absint( $value );

				return 0 < $id ? (string) $id : '';

			case 'layout':
				$layout = sanitize_key( (string) $value );

				return in_array( $layout, Skins::ids(), true ) ? $layout : Skins::DEFAULT_SKIN;

			case 'scheme':
				$scheme = sanitize_key( (string) $value );

				return in_array( $scheme, self::SCHEMES, true ) ? $scheme : self::DEFAULT_SCHEME;

			case 'page':
				$page_id = absint( $value );

				// Only a real page may be stored; anything else clears the field.
				// The same rule the Login Legal Pages addon applies to its own
				// two page pickers.
				return 0 < $page_id && 'page' === get_post_type( $page_id ) ? (string) $page_id : '';

			case 'jwt':
				$jwt = self::extract_jwt( (string) $value );

				return self::looks_like_a_jwt( $jwt ) ? $jwt : '';

			case 'email':
				return sanitize_email( (string) $value );

			case 'url':
				return esc_url_raw( (string) $value );

			case 'html':
				return trim( wp_kses_post( (string) $value ) );

			case 'textarea':
				return trim( sanitize_textarea_field( (string) $value ) );

			case 'tel':
			case 'text':
			default:
				return trim( sanitize_text_field( (string) $value ) );
		}
	}
}
