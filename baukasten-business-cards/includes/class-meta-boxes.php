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
			'quick'     => array(
				'title'    => __( 'Quick actions', 'baukasten-business-cards' ),
				'context'  => 'normal',
				'priority' => 'default',
			),
			'contact'   => array(
				'title'    => __( 'Contact details', 'baukasten-business-cards' ),
				'context'  => 'normal',
				'priority' => 'default',
			),
			'social'    => array(
				'title'    => __( 'Networks', 'baukasten-business-cards' ),
				'context'  => 'normal',
				'priority' => 'default',
			),
			'links'     => array(
				'title'    => __( 'Custom links', 'baukasten-business-cards' ),
				'context'  => 'normal',
				'priority' => 'default',
			),
			'downloads' => array(
				'title'    => __( 'Downloads', 'baukasten-business-cards' ),
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
			'card_layout'         => __( 'Layout', 'baukasten-business-cards' ),
			'show_qr_modal'       => __( 'Show a QR code button', 'baukasten-business-cards' ),
			'show_theme_toggle'   => __( 'Show a light and dark switch', 'baukasten-business-cards' ),

			'banner_image_id'     => __( 'Banner', 'baukasten-business-cards' ),
			'avatar_image_id'     => __( 'Profile picture', 'baukasten-business-cards' ),

			'salutation'          => __( 'Salutation', 'baukasten-business-cards' ),
			'academic_title'      => __( 'Academic title', 'baukasten-business-cards' ),
			'first_name'          => __( 'First name', 'baukasten-business-cards' ),
			'last_name'           => __( 'Last name', 'baukasten-business-cards' ),
			'position'            => __( 'Position', 'baukasten-business-cards' ),
			'company'             => __( 'Company', 'baukasten-business-cards' ),
			'bio_text'            => __( 'Biography', 'baukasten-business-cards' ),

			'quick_tel'           => __( 'Call', 'baukasten-business-cards' ),
			'quick_email'         => __( 'Email', 'baukasten-business-cards' ),
			'quick_whatsapp'      => __( 'WhatsApp', 'baukasten-business-cards' ),
			'quick_website'       => __( 'Website', 'baukasten-business-cards' ),

			'email_work'          => __( 'Email, work', 'baukasten-business-cards' ),
			'email_priv'          => __( 'Email, private', 'baukasten-business-cards' ),
			'phone_work'          => __( 'Phone, work', 'baukasten-business-cards' ),
			'phone_priv'          => __( 'Phone, private', 'baukasten-business-cards' ),
			'mobile_work'         => __( 'Mobile, work', 'baukasten-business-cards' ),
			'mobile_priv'         => __( 'Mobile, private', 'baukasten-business-cards' ),
			'contact_website'     => __( 'Website', 'baukasten-business-cards' ),
			'contact_address'     => __( 'Address', 'baukasten-business-cards' ),
			'what3words_link'     => __( 'what3words link', 'baukasten-business-cards' ),

			'network_linkedin'    => __( 'LinkedIn', 'baukasten-business-cards' ),
			'network_xing'        => __( 'Xing', 'baukasten-business-cards' ),
			'network_github'      => __( 'GitHub', 'baukasten-business-cards' ),
			'network_mastodon'    => __( 'Mastodon', 'baukasten-business-cards' ),
			'network_facebook'    => __( 'Facebook', 'baukasten-business-cards' ),
			'network_instagram'   => __( 'Instagram', 'baukasten-business-cards' ),
			'network_threads'     => __( 'Threads', 'baukasten-business-cards' ),
			'network_discord'     => __( 'Discord', 'baukasten-business-cards' ),
			'network_signal'      => __( 'Signal', 'baukasten-business-cards' ),

			'custom_link_1_label' => __( 'Link 1 label', 'baukasten-business-cards' ),
			'custom_link_1_url'   => __( 'Link 1 address', 'baukasten-business-cards' ),
			'custom_link_2_label' => __( 'Link 2 label', 'baukasten-business-cards' ),
			'custom_link_2_url'   => __( 'Link 2 address', 'baukasten-business-cards' ),
			'custom_link_3_label' => __( 'Link 3 label', 'baukasten-business-cards' ),
			'custom_link_3_url'   => __( 'Link 3 address', 'baukasten-business-cards' ),

			'download_1_label'    => __( 'Download 1 label', 'baukasten-business-cards' ),
			'download_1_file_id'  => __( 'Download 1 file', 'baukasten-business-cards' ),
			'download_2_label'    => __( 'Download 2 label', 'baukasten-business-cards' ),
			'download_2_file_id'  => __( 'Download 2 file', 'baukasten-business-cards' ),
			'download_3_label'    => __( 'Download 3 label', 'baukasten-business-cards' ),
			'download_3_file_id'  => __( 'Download 3 file', 'baukasten-business-cards' ),

			'enable_vcf'          => __( 'Offer a vCard download', 'baukasten-business-cards' ),
			'wallet_apple_url'    => __( 'Apple Wallet address', 'baukasten-business-cards' ),
			'wallet_google_url'   => __( 'Google Wallet address', 'baukasten-business-cards' ),

			'cf7_form_id'         => __( 'Form', 'baukasten-business-cards' ),

			'footer_imprint_url'  => __( 'Imprint', 'baukasten-business-cards' ),
			'footer_privacy_url'  => __( 'Privacy policy', 'baukasten-business-cards' ),
			'footer_terms_url'    => __( 'Terms', 'baukasten-business-cards' ),
		);
	}

	/**
	 * The handful of fields that need a word of explanation.
	 *
	 * @return array<string, string> Field key to description.
	 */
	private static function notes(): array {
		return array(
			'quick_whatsapp'     => __( 'Digits and a leading plus only. Everything else is stripped when saved.', 'baukasten-business-cards' ),
			'bio_text'           => __( 'Basic formatting is kept. Used as the note on the vCard as well.', 'baukasten-business-cards' ),
			'contact_address'    => __( 'Written to the vCard as one block. Line breaks are kept.', 'baukasten-business-cards' ),
			'wallet_apple_url'   => __( 'A signed pass needs an Apple certificate, so this is a link to wherever your pass is hosted. Leave it empty to hide the badge.', 'baukasten-business-cards' ),
			'footer_privacy_url' => __( 'Leave empty to use the privacy policy page set under Settings, Privacy.', 'baukasten-business-cards' ),
			'card_layout'        => __( 'Classic has a banner, Modern is borderless, Bio is a stack of link buttons.', 'baukasten-business-cards' ),
		);
	}
}
