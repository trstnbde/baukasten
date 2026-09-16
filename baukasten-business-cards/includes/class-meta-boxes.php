<?php
/**
 * The card editing screen.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves every card field.
 *
 * Classic meta boxes, not a block-editor panel: the card has no content, both
 * editors are switched off for the post type, and fifty fields in a sidebar
 * panel would be unusable. Each box is one group from the schema, rendered by
 * one shared view — so adding a field is two lines in `Fields` and a label
 * here, and nothing else.
 */
final class Meta_Boxes {

	/**
	 * Nonce action.
	 */
	const NONCE_ACTION = 'baukasten_business_cards_save';

	/**
	 * Nonce field name.
	 */
	const NONCE_FIELD = 'baukasten_business_cards_nonce';

	/**
	 * Script and style handle.
	 */
	const HANDLE = 'baukasten-business-cards-admin';

	/**
	 * Registers the hooks the editing screen needs.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_' . Post_Type::POST_TYPE, array( __CLASS__, 'add' ) );
		add_action( 'edit_form_after_title', array( __CLASS__, 'print_nonce' ) );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'manage_' . Post_Type::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . Post_Type::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	/**
	 * Adds one box per field group.
	 *
	 * @return void
	 */
	public static function add(): void {
		foreach ( self::boxes() as $group => $box ) {
			if ( 'legal' === $group && Legal_Links::has_site_source() ) {
				// The site has one answer for all cards. A box that saves values
				// nothing reads is a trap, so it is not offered at all.
				add_meta_box(
					'baukasten-card-legal',
					$box['title'],
					array( __CLASS__, 'render_inherited_legal' ),
					Post_Type::POST_TYPE,
					$box['context'],
					$box['priority']
				);

				continue;
			}

			add_meta_box(
				'baukasten-card-' . $group,
				$box['title'],
				array( __CLASS__, 'render' ),
				Post_Type::POST_TYPE,
				$box['context'],
				$box['priority'],
				array( 'group' => $group )
			);
		}
	}

	/**
	 * Prints the one nonce every box shares.
	 *
	 * On `edit_form_after_title` rather than inside a box: a box can be hidden
	 * through Screen Options, and a nonce that can be switched off is a save
	 * that stops working for reasons nobody can see.
	 *
	 * @param mixed $post The post being edited.
	 * @return void
	 */
	public static function print_nonce( $post ): void {
		if ( ! $post instanceof \WP_Post || Post_Type::POST_TYPE !== $post->post_type ) {
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
	}

	/**
	 * Prints one box.
	 *
	 * @param mixed                $post The post being edited.
	 * @param array<string, mixed> $box  Box arguments, carrying the group id.
	 * @return void
	 */
	public static function render( $post, $box ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$group  = isset( $box['args']['group'] ) ? (string) $box['args']['group'] : '';
		$keys   = Fields::group( $group );
		$labels = self::labels();
		$notes  = self::notes();
		$types  = Fields::types();
		$values = self::stored( $post->ID );
		$boxes  = self::boxes();
		$intro  = isset( $boxes[ $group ]['note'] ) ? (string) $boxes[ $group ]['note'] : '';

		require PLUGIN_DIR . 'admin/views/meta-box.php';
	}

	/**
	 * Stores every submitted field.
	 *
	 * @param mixed $post_id The post being saved.
	 * @param mixed $post    The post object.
	 * @return void
	 */
	public static function save( $post_id, $post ): void {
		$post_id = (int) $post_id;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! $post instanceof \WP_Post || Post_Type::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( Fields::types() as $key => $type ) {
			/*
			 * A field whose box was never rendered posts nothing, and "posted
			 * nothing" is indistinguishable from "was cleared". For an unticked
			 * checkbox those mean the same thing; for a whole box that is not on
			 * the screen they do not, and the stored page would be thrown away
			 * on the next save of some unrelated field.
			 */
			if ( 'page' === $type && Legal_Links::has_site_source() ) {
				continue;
			}

			$meta_key = Fields::meta_key( $key );

			// An unticked checkbox posts nothing at all, which is the only way
			// to tell it apart from one that was never rendered — and both
			// mean the same thing here.
			$raw = isset( $_POST[ $meta_key ] )
				? wp_unslash( $_POST[ $meta_key ] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by type on the next line.
				: '';

			$value = Fields::sanitize( $type, $raw );

			if ( '' === $value ) {
				delete_post_meta( $post_id, $meta_key );

				continue;
			}

			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	/**
	 * Loads the editing screen's own assets.
	 *
	 * @param string $hook_suffix Current admin screen.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ): void {
		if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style( self::HANDLE, PLUGIN_URL . 'assets/css/admin.css', array(), VERSION );

		wp_enqueue_script( self::HANDLE, PLUGIN_URL . 'assets/js/admin-media.js', array(), VERSION, true );

		wp_localize_script(
			self::HANDLE,
			'baukastenBusinessCards',
			array(
				'i18n' => array(
					'chooseImage' => __( 'Choose an image', 'baukasten-business-cards' ),
					'chooseFile'  => __( 'Choose a file', 'baukasten-business-cards' ),
					'use'         => __( 'Use this', 'baukasten-business-cards' ),
				),
			)
		);
	}

	/**
	 * Adds a card address column to the list table.
	 *
	 * The title is an internal label, so the list would otherwise give no way
	 * to see which card is which address.
	 *
	 * @param mixed $columns Existing columns.
	 * @return array<string, string> Columns.
	 */
	public static function columns( $columns ): array {
		$columns = array_map( 'strval', (array) $columns );
		$out     = array();

		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;

			if ( 'title' === $key ) {
				$out['baukasten_card_address'] = __( 'Address', 'baukasten-business-cards' );
			}
		}

		return $out;
	}

	/**
	 * Prints the card address column.
	 *
	 * @param mixed $column  Column key.
	 * @param mixed $post_id Post ID.
	 * @return void
	 */
	public static function column( $column, $post_id ): void {
		if ( 'baukasten_card_address' !== $column ) {
			return;
		}

		$permalink = get_permalink( (int) $post_id );

		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return;
		}

		printf(
			'<a href="%s"><code>%s</code></a>',
			esc_url( $permalink ),
			esc_html( (string) wp_parse_url( $permalink, PHP_URL_PATH ) )
		);
	}

	/**
	 * Prints the legal box when the links come from elsewhere.
	 *
	 * Showing what a card will actually display, rather than three disabled
	 * dropdowns: the question "where does this link go" is answered on the
	 * screen where it is asked.
	 *
	 * @param mixed $post The post being edited.
	 * @return void
	 */
	public static function render_inherited_legal( $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$links = Legal_Links::all( Fields::load( $post->ID ) );

		echo '<p class="description">';
		esc_html_e( 'These come from the Login Legal Pages addon and are the same on every card.', 'baukasten-business-cards' );
		echo '</p>';

		if ( array() === $links ) {
			echo '<p>';
			esc_html_e( 'No legal pages are set yet, so the footer stays empty.', 'baukasten-business-cards' );
			echo '</p>';
		} else {
			echo '<ul>';

			foreach ( $links as $link ) {
				printf(
					'<li><a href="%1$s" target="_blank" rel="noopener">%2$s</a></li>',
					esc_url( (string) $link['url'] ),
					esc_html( (string) $link['label'] )
				);
			}

			echo '</ul>';
		}

		if ( class_exists( '\Baukasten\Admin' ) ) {
			printf(
				'<p><a href="%1$s">%2$s</a></p>',
				esc_url( \Baukasten\Admin::page_url( 'login-legal-pages' ) ),
				esc_html__( 'Change them', 'baukasten-business-cards' )
			);
		}
	}

	/**
	 * Every stored value, raw, keyed by unprefixed field key.
	 *
	 * The editing screen needs what is stored, not what `Fields::load()` would
	 * render — an invalid value has to stay visible so it can be corrected.
	 *
	 * @param int $post_id Card post ID.
	 * @return array<string, string> Stored values.
	 */
	private static function stored( int $post_id ): array {
		$raw = get_post_meta( $post_id );
		$raw = is_array( $raw ) ? $raw : array();

		$values = array();

		foreach ( array_keys( Fields::types() ) as $key ) {
			$meta_key       = Fields::meta_key( $key );
			$values[ $key ] = isset( $raw[ $meta_key ][0] ) ? (string) $raw[ $meta_key ][0] : '';
		}

		return $values;
	}

	/**
	 * The boxes, in the order they are added.
	 *
	 * @return array<string, array<string, string>> Group id to title, context and priority.
	 */
	private static function boxes(): array {
		return array(
			'design'    => array(
				'title'    => __( 'Design and mode', 'baukasten-business-cards' ),
				'context'  => 'side',
				'priority' => 'high',
			),
			'wallet'    => array(
				'title'    => __( 'Wallet and vCard', 'baukasten-business-cards' ),
				'context'  => 'side',
				'priority' => 'default',
			),
			'form'      => array(
				'title'    => __( 'Contact form', 'baukasten-business-cards' ),
				'context'  => 'side',
				'priority' => 'default',
			),
			'basics'    => array(
				'title'    => __( 'Name and biography', 'baukasten-business-cards' ),
				'context'  => 'normal',
				'priority' => 'high',
			),
			'images'    => array(
				'title'    => __( 'Images', 'baukasten-business-cards' ),
				'context'  => 'normal',
				'priority' => 'high',
			),
			'contact'   => array(
				'title'    => __( 'My details', 'baukasten-business-cards' ),
				'context'  => 'normal',
				'priority' => 'default',
			),
			'social'    => array(
				'title'    => __( 'My networks', 'baukasten-business-cards' ),
				'context'  => 'normal',
				'priority' => 'default',
			),
			'links'     => array(
				'title'    => __( 'My links', 'baukasten-business-cards' ),
				'context'  => 'normal',
				'priority' => 'default',
			),
			'downloads' => array(
				'title'    => __( 'My downloads', 'baukasten-business-cards' ),
				'context'  => 'normal',
				'priority' => 'default',
			),
			'legal'     => array(
				'title'    => __( 'Footer links', 'baukasten-business-cards' ),
				'context'  => 'normal',
				'priority' => 'low',
			),
		);
	}

	/**
	 * A label for every field.
	 *
	 * @return array<string, string> Field key to label.
	 */
	private static function labels(): array {
		return array(
			'card_layout'          => __( 'Design', 'baukasten-business-cards' ),
			'card_color_scheme'    => __( 'Light or dark', 'baukasten-business-cards' ),
			'show_qr_modal'        => __( 'Show a QR code button', 'baukasten-business-cards' ),
			'show_theme_toggle'    => __( 'Show a light and dark switch', 'baukasten-business-cards' ),

			'banner_image_id'      => __( 'Banner', 'baukasten-business-cards' ),
			'avatar_image_id'      => __( 'Profile picture', 'baukasten-business-cards' ),

			'salutation'           => __( 'Salutation', 'baukasten-business-cards' ),
			'academic_title'       => __( 'Academic title', 'baukasten-business-cards' ),
			'first_name'           => __( 'First name', 'baukasten-business-cards' ),
			'last_name'            => __( 'Last name', 'baukasten-business-cards' ),
			'position'             => __( 'Position', 'baukasten-business-cards' ),
			'company'              => __( 'Company', 'baukasten-business-cards' ),
			'bio_text'             => __( 'Biography', 'baukasten-business-cards' ),

			'email'                => __( 'Email', 'baukasten-business-cards' ),
			'email_2'              => __( 'Email 2', 'baukasten-business-cards' ),
			'phone'                => __( 'Phone', 'baukasten-business-cards' ),
			'mobile'               => __( 'Mobile', 'baukasten-business-cards' ),
			'assistant'            => __( 'Assistant', 'baukasten-business-cards' ),
			'assistant_phone'      => __( 'Assistant\'s phone', 'baukasten-business-cards' ),
			'contact_website'      => __( 'Website', 'baukasten-business-cards' ),
			'contact_address'      => __( 'Address', 'baukasten-business-cards' ),
			'what3words_link'      => __( 'what3words link', 'baukasten-business-cards' ),

			'network_linkedin'     => __( 'LinkedIn', 'baukasten-business-cards' ),
			'network_xing'         => __( 'Xing', 'baukasten-business-cards' ),
			'network_github'       => __( 'GitHub', 'baukasten-business-cards' ),
			'network_mastodon'     => __( 'Mastodon', 'baukasten-business-cards' ),
			'network_facebook'     => __( 'Facebook', 'baukasten-business-cards' ),
			'network_instagram'    => __( 'Instagram', 'baukasten-business-cards' ),
			'network_threads'      => __( 'Threads', 'baukasten-business-cards' ),
			'network_discord'      => __( 'Discord', 'baukasten-business-cards' ),
			'network_signal'       => __( 'Signal', 'baukasten-business-cards' ),

			'custom_link_1_label'  => __( 'Link 1 label', 'baukasten-business-cards' ),
			'custom_link_1_url'    => __( 'Link 1 address', 'baukasten-business-cards' ),
			'custom_link_2_label'  => __( 'Link 2 label', 'baukasten-business-cards' ),
			'custom_link_2_url'    => __( 'Link 2 address', 'baukasten-business-cards' ),
			'custom_link_3_label'  => __( 'Link 3 label', 'baukasten-business-cards' ),
			'custom_link_3_url'    => __( 'Link 3 address', 'baukasten-business-cards' ),

			'download_1_label'     => __( 'Download 1 label', 'baukasten-business-cards' ),
			'download_1_file_id'   => __( 'Download 1 file', 'baukasten-business-cards' ),
			'download_2_label'     => __( 'Download 2 label', 'baukasten-business-cards' ),
			'download_2_file_id'   => __( 'Download 2 file', 'baukasten-business-cards' ),
			'download_3_label'     => __( 'Download 3 label', 'baukasten-business-cards' ),
			'download_3_file_id'   => __( 'Download 3 file', 'baukasten-business-cards' ),

			'enable_vcf'           => __( 'Offer a vCard download', 'baukasten-business-cards' ),
			'wallet_apple_pass_id' => __( 'Apple Wallet pass', 'baukasten-business-cards' ),
			'wallet_google_jwt'    => __( 'Google Wallet token', 'baukasten-business-cards' ),

			'cf7_form_id'          => __( 'Form', 'baukasten-business-cards' ),

			'footer_privacy_page'  => __( 'Privacy policy', 'baukasten-business-cards' ),
			'footer_terms_page'    => __( 'Terms', 'baukasten-business-cards' ),
			'footer_imprint_page'  => __( 'Imprint', 'baukasten-business-cards' ),
		);
	}

	/**
	 * The handful of fields that need a word of explanation.
	 *
	 * @return array<string, string> Field key to description.
	 */
	private static function notes(): array {
		return array(
			'bio_text'             => __( 'Basic formatting is kept. Used as the note on the vCard as well.', 'baukasten-business-cards' ),
			'contact_address'      => __( 'Written to the vCard as one block. Line breaks are kept.', 'baukasten-business-cards' ),
			'phone'                => __( 'Becomes the Call button at the top of the card. Without it, the mobile number does.', 'baukasten-business-cards' ),
			'mobile'               => __( 'Becomes the WhatsApp button — WhatsApp needs a mobile number.', 'baukasten-business-cards' ),
			'email'                => __( 'Becomes the Email button. Without it, Email 2 does.', 'baukasten-business-cards' ),
			'assistant'            => __( 'Name of the person who handles your calls and appointments.', 'baukasten-business-cards' ),
			'contact_website'      => __( 'Becomes the Website button.', 'baukasten-business-cards' ),
			'wallet_apple_pass_id' => __( 'The .pkpass file itself, uploaded here and served by this site with the media type that opens Wallet. Signing a pass needs an Apple certificate, so it has to be built elsewhere.', 'baukasten-business-cards' ),
			'wallet_google_jwt'    => __( 'The signed token for the pass, or the whole pay.google.com/gp/v/save/… address — the token is taken out of it. Only its shape is checked: the signature was made with a key this site does not hold.', 'baukasten-business-cards' ),
			'footer_privacy_page'  => __( 'Leave empty to use the page set under Settings, Privacy.', 'baukasten-business-cards' ),
			'card_layout'          => __( 'One layout in three looks. Modernist is sharp-edged and typographic, Industry frames each section like a technical drawing, Nocturne is dark with soft corners.', 'baukasten-business-cards' ),
			'card_color_scheme'    => __( 'Each design has a light and a dark palette. Follow the reader\'s own setting, or pin one of the two.', 'baukasten-business-cards' ),
		);
	}
}
