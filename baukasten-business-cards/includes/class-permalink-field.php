<?php
/**
 * The base field on Settings, Permalinks.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * Puts the card base where the other bases already are.
 *
 * `options-permalink.php` does not save registered settings. It posts to
 * itself, verifies one nonce and hand-handles exactly three keys, so
 * `register_setting()` would be ignored without a word. What it does offer is
 * `do_settings_fields( 'permalink', 'optional' )` inside its Optional table —
 * a section does not have to exist for that — and a screen hook that fires
 * before it reads `$_POST`. That is the seam this class uses.
 */
final class Permalink_Field {

	/**
	 * Settings page the field is printed on.
	 */
	const PAGE = 'permalink';

	/**
	 * Section within that page.
	 */
	const SECTION = 'optional';

	/**
	 * Name of the input, and the id of the settings field.
	 */
	const FIELD = 'baukasten_business_cards_base';

	/**
	 * Registers the hooks the field needs.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'load-options-permalink.php', array( __CLASS__, 'boot_screen' ) );
	}

	/**
	 * Adds the field, and saves it when the screen was submitted.
	 *
	 * `load-options-permalink.php` fires from `admin.php`, which
	 * `options-permalink.php` includes at its top — so this runs before that
	 * file inspects `$_POST` and before its unconditional flush.
	 *
	 * @return void
	 */
	public static function boot_screen(): void {
		add_settings_field(
			self::FIELD,
			__( 'Business card base', 'baukasten-business-cards' ),
			array( __CLASS__, 'render' ),
			self::PAGE,
			self::SECTION,
			array( 'label_for' => self::FIELD )
		);

		self::maybe_save();
	}

	/**
	 * Prints the input.
	 *
	 * @return void
	 */
	public static function render(): void {
		$pretty = Settings::pretty_permalinks();

		printf(
			'<input name="%1$s" id="%1$s" type="text" value="%2$s" class="regular-text code"%3$s />',
			esc_attr( self::FIELD ),
			esc_attr( Settings::base() ),
			$pretty ? '' : ' disabled="disabled"'
		);

		if ( ! $pretty ) {
			printf(
				'<p class="description">%s</p>',
				esc_html__( 'With plain permalinks a card has no base. Cards stay reachable at ?baukasten_card=… until a permalink structure is chosen above.', 'baukasten-business-cards' )
			);

			return;
		}

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: an example card URL. */
					__( 'Cards are served from %s. Reserved segments and segments already used by a page or another post type are rejected; a rule added by another plugin cannot be detected, so check the base still works after changing it.', 'baukasten-business-cards' ),
					home_url( '/' . Settings::base() . '/a1b2c3d4' )
				)
			)
		);
	}

	/**
	 * Stores a submitted base.
	 *
	 * @return void
	 */
	private static function maybe_save(): void {
		if ( ! isset( $_POST[ self::FIELD ] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'baukasten-business-cards' ), 403 );
		}

		// The screen's own nonce: the form belongs to core, not to this plugin.
		check_admin_referer( 'update-permalink' );

		$submitted = sanitize_text_field( wp_unslash( (string) $_POST[ self::FIELD ] ) );
		$base      = Settings::sanitize_base( $submitted );

		if ( '' === $base ) {
			add_settings_error(
				'general',
				'baukasten_business_cards_base',
				__( 'That business card base is reserved or already in use. The previous one was kept.', 'baukasten-business-cards' ),
				'error'
			);

			return;
		}

		if ( Settings::base() === $base ) {
			return;
		}

		update_option( Settings::OPTION_BASE, $base, true );

		self::repoint_permastruct( $base );

		// The permastruct registered on init carried the old base, so the
		// stamp is stale by definition; dropping it makes the next request
		// flush even if core's own flush somehow does not happen.
		delete_option( Post_Type::OPTION_STAMP );
	}

	/**
	 * Rewrites the registered permastruct to the new base.
	 *
	 * `init` ran long before this with the old base, so without this the flush
	 * that follows would write the rules that are being replaced.
	 *
	 * @param string $base The new base segment.
	 * @return void
	 */
	private static function repoint_permastruct( string $base ): void {
		global $wp_rewrite;

		if ( ! $wp_rewrite instanceof \WP_Rewrite ) {
			return;
		}

		$wp_rewrite->add_permastruct(
			Post_Type::POST_TYPE,
			$base . '/%' . Post_Type::POST_TYPE . '%',
			array(
				'with_front' => false,
				'feeds'      => false,
				'pages'      => false,
				'ep_mask'    => EP_NONE,
			)
		);
	}
}
