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
	 * The layouts a card can be rendered in.
	 */
	const LAYOUTS = array( 'classic', 'modern', 'bio' );

	/**
	 * The layout a card falls back to.
	 */
	const DEFAULT_LAYOUT = 'classic';

	/**
	 * Every field, as unprefixed key to type.
	 *
	 * @return array<string, string> Field key to type.
	 */
	public static function types(): array {
		return array(
			'card_layout'         => 'layout',
			'show_qr_modal'       => 'bool',
			'show_theme_toggle'   => 'bool',

			'banner_image_id'     => 'image',
			'avatar_image_id'     => 'image',

			'salutation'          => 'text',
			'academic_title'      => 'text',
			'first_name'          => 'text',
			'last_name'           => 'text',
			'position'            => 'text',
			'company'             => 'text',
			'bio_text'            => 'html',

			'quick_tel'           => 'tel',
			'quick_email'         => 'email',
			'quick_whatsapp'      => 'tel_digits',
			'quick_website'       => 'url',

			'email_work'          => 'email',
			'email_priv'          => 'email',
			'phone_work'          => 'tel',
			'phone_priv'          => 'tel',
			'mobile_work'         => 'tel',
			'mobile_priv'         => 'tel',
			'contact_website'     => 'url',
			'contact_address'     => 'textarea',
			'what3words_link'     => 'url',

			'network_linkedin'    => 'url',
			'network_xing'        => 'url',
			'network_github'      => 'url',
			'network_mastodon'    => 'url',
			'network_facebook'    => 'url',
			'network_instagram'   => 'url',
			'network_threads'     => 'url',
			'network_discord'     => 'url',
			'network_signal'      => 'url',

			'custom_link_1_label' => 'text',
			'custom_link_1_url'   => 'url',
			'custom_link_2_label' => 'text',
			'custom_link_2_url'   => 'url',
			'custom_link_3_label' => 'text',
			'custom_link_3_url'   => 'url',

			'download_1_label'    => 'text',
			'download_1_file_id'  => 'file',
			'download_2_label'    => 'text',
			'download_2_file_id'  => 'file',
			'download_3_label'    => 'text',
			'download_3_file_id'  => 'file',

			'enable_vcf'          => 'bool',
			'wallet_apple_url'    => 'url',
			'wallet_google_url'   => 'url',

			'cf7_form_id'         => 'form',

			'footer_imprint_url'  => 'url',
			'footer_privacy_url'  => 'url',
			'footer_terms_url'    => 'url',
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
			'design'    => array( 'card_layout', 'show_qr_modal', 'show_theme_toggle' ),
			'images'    => array( 'banner_image_id', 'avatar_image_id' ),
			'basics'    => array( 'salutation', 'academic_title', 'first_name', 'last_name', 'position', 'company', 'bio_text' ),
			'quick'     => array( 'quick_tel', 'quick_email', 'quick_whatsapp', 'quick_website' ),
			'contact'   => array( 'email_work', 'email_priv', 'phone_work', 'phone_priv', 'mobile_work', 'mobile_priv', 'contact_website', 'contact_address', 'what3words_link' ),
			'social'    => array( 'network_linkedin', 'network_xing', 'network_github', 'network_mastodon', 'network_facebook', 'network_instagram', 'network_threads', 'network_discord', 'network_signal' ),
			'links'     => array( 'custom_link_1_label', 'custom_link_1_url', 'custom_link_2_label', 'custom_link_2_url', 'custom_link_3_label', 'custom_link_3_url' ),
			'downloads' => array( 'download_1_label', 'download_1_file_id', 'download_2_label', 'download_2_file_id', 'download_3_label', 'download_3_file_id' ),
			'wallet'    => array( 'enable_vcf', 'wallet_apple_url', 'wallet_google_url' ),
			'form'      => array( 'cf7_form_id' ),
			'legal'     => array( 'footer_imprint_url', 'footer_privacy_url', 'footer_terms_url' ),
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
			$card['card_layout'] = self::DEFAULT_LAYOUT;
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

				// Never dropped: the template must always have a layout.
				return in_array( $layout, self::LAYOUTS, true ) ? $layout : self::DEFAULT_LAYOUT;

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

			case 'tel_digits':
				$digits = (string) preg_replace( '/[^0-9+]/', '', (string) $value );

				return '' !== $digits ? $digits : null;

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

				return in_array( $layout, self::LAYOUTS, true ) ? $layout : self::DEFAULT_LAYOUT;

			case 'email':
				return sanitize_email( (string) $value );

			case 'url':
				return esc_url_raw( (string) $value );

			case 'html':
				return trim( wp_kses_post( (string) $value ) );

			case 'textarea':
				return trim( sanitize_textarea_field( (string) $value ) );

			case 'tel_digits':
				return (string) preg_replace( '/[^0-9+]/', '', (string) $value );

			case 'tel':
			case 'text':
			default:
				return trim( sanitize_text_field( (string) $value ) );
		}
	}
}
