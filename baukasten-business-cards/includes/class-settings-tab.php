<?php
/**
 * The Baukasten tab.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the overview tab on the core plugin's settings screen.
 *
 * There is nothing to configure here that is not better placed elsewhere: the
 * base belongs next to the other bases on the Permalinks screen, and every
 * other setting belongs to one card. So the tab is an overview — what the base
 * is, how many cards there are, and what is and is not available on this site.
 */
final class Settings_Tab {

	/**
	 * Tab slug.
	 */
	const TAB = 'business-cards';

	/**
	 * Capability the tab requires.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * `admin-post` action that creates the card contact form.
	 */
	const ACTION_CREATE_FORM = 'baukasten_business_cards_create_form';

	/**
	 * Nonce action for the tab's own forms.
	 */
	const NONCE = 'baukasten_business_cards_tab';

	/**
	 * Registers the hooks the tab needs.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION_CREATE_FORM, array( __CLASS__, 'create_form' ) );

		add_filter(
			'plugin_action_links_' . plugin_basename( PLUGIN_FILE ),
			array( __CLASS__, 'plugin_action_links' )
		);

		if ( ! class_exists( '\Baukasten\Addons' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_missing_core_notice' ) );

			return;
		}

		add_action( 'baukasten/register_addons', array( __CLASS__, 'register_addon' ) );
	}

	/**
	 * Registers the tab with the core plugin.
	 *
	 * @return void
	 */
	public static function register_addon(): void {
		\Baukasten\Addons::register(
			array(
				'id'          => self::TAB,
				'title'       => __( 'Business Cards', 'baukasten-business-cards' ),
				'plugin_file' => PLUGIN_FILE,
				'capability'  => self::CAPABILITY,
				'position'    => 50,
				'render'      => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * Adds a link to the card list next to Deactivate.
	 *
	 * @param mixed $links Existing action links.
	 * @return string[] Action links.
	 */
	public static function plugin_action_links( $links ): array {
		$links = array_map( 'strval', (array) $links );

		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'edit.php?post_type=' . Post_Type::POST_TYPE ) ),
				esc_html__( 'Cards', 'baukasten-business-cards' )
			)
		);

		return $links;
	}

	/**
	 * Warns that the core plugin is missing.
	 *
	 * Cards keep working without it — only the overview has nowhere to go.
	 *
	 * @return void
	 */
	public static function render_missing_core_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html__(
				'Business Cards is serving cards, but its overview needs the Baukasten - Privacy Toolkit plugin. Cards can still be edited under Business Cards.',
				'baukasten-business-cards'
			)
		);
	}

	/**
	 * Creates the contact form a card can embed.
	 *
	 * Not on activation. A form is content: it appears in the Contact Form 7
	 * list, it carries a mail configuration pointing at the site's admin
	 * address, and it sends email. Making one behind somebody's back the moment
	 * a plugin is switched on is not a favour. This is a button, pressed once,
	 * by somebody who read what it does.
	 *
	 * @return void
	 */
	public static function create_form(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'baukasten-business-cards' ), 403 );
		}

		check_admin_referer( self::NONCE );

		if ( ! Forms::is_available() ) {
			self::go_back(
				'error',
				__( 'Contact Form 7 is not active, so there is nothing to build the form with.', 'baukasten-business-cards' )
			);
		}

		$existing = Forms::plugin_form_id();

		if ( 0 < $existing ) {
			self::go_back(
				'success',
				__( 'The card contact form already exists — it is listed under Contact.', 'baukasten-business-cards' )
			);
		}

		$form_id = Forms::create();

		if ( 0 >= $form_id ) {
			self::go_back(
				'error',
				__( 'The form could not be created. Contact Form 7 refused to save it.', 'baukasten-business-cards' )
			);
		}

		self::go_back(
			'success',
			__( 'Contact form created. Choose it on a card under Contact form.', 'baukasten-business-cards' )
		);
	}

	/**
	 * Returns to the tab with a notice, and ends the request.
	 *
	 * @param string $type    Either `success` or `error`.
	 * @param string $message Notice text.
	 * @return void
	 */
	private static function go_back( string $type, string $message ): void {
		if ( class_exists( '\Baukasten\Admin' ) ) {
			\Baukasten\Admin::redirect_to_tab( self::TAB, $type, $message );
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Post_Type::POST_TYPE ) );

		exit;
	}

	/**
	 * Prints the tab.
	 *
	 * @return void
	 */
	public static function render(): void {
		$base       = Settings::base();
		$pretty     = Settings::pretty_permalinks();
		$counts     = wp_count_posts( Post_Type::POST_TYPE );
		$published  = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$drafts     = isset( $counts->draft ) ? (int) $counts->draft : 0;
		$has_cf7    = Forms::is_available();
		$form_id    = Forms::plugin_form_id();
		$site_legal = Legal_Links::has_site_source();
		$report     = Upgrade::report();
		$skins      = Skins::choices();

		require PLUGIN_DIR . 'admin/views/settings-tab.php';
	}
}
