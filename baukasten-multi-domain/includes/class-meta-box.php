<?php
/**
 * The editor side.
 *
 * @package Baukasten\MultiDomain
 */

namespace Baukasten\MultiDomain;

defined( 'ABSPATH' ) || exit;

/**
 * The same one field, in the editor.
 *
 * Two paths reach it. The classic editor posts a form, and the meta box below
 * handles that. The block editor saves through the REST API and never touches
 * a meta box at all, so the meta is registered with `show_in_rest` — which is
 * also what makes the field reachable to anything else talking to the API.
 *
 * Both end up writing the same post meta, and `Domain_Map` picks it up from
 * there, so uniqueness and the lookup table hold whichever way an editor got
 * to the field.
 */
final class Meta_Box {

	/**
	 * Meta box id.
	 */
	const ID = 'baukasten-multi-domain';

	/**
	 * Nonce action and field.
	 */
	const NONCE = 'baukasten_multi_domain_save';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'add_meta_boxes_page', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_page', array( __CLASS__, 'save' ) );
	}

	/**
	 * Registers the meta, so the block editor and the REST API can write it.
	 *
	 * @return void
	 */
	public static function register_meta(): void {
		register_post_meta(
			'page',
			Domain_Map::META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => array( Domain::class, 'sanitize' ),
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ): bool {
					unset( $allowed, $meta_key );

					return current_user_can( 'edit_post', (int) $post_id );
				},
			)
		);
	}

	/**
	 * Adds the meta box to the page editor.
	 *
	 * @return void
	 */
	public static function add_meta_box(): void {
		add_meta_box(
			self::ID,
			__( 'Front page for domain', 'baukasten-multi-domain' ),
			array( __CLASS__, 'render' ),
			'page',
			'side',
			'default'
		);
	}

	/**
	 * Renders the meta box.
	 *
	 * @param \WP_Post $post The page being edited.
	 * @return void
	 */
	public static function render( $post ): void {
		$domain = Domain_Map::domain_of( (int) $post->ID );

		wp_nonce_field( self::NONCE, self::NONCE );

		?>
		<p>
			<label class="screen-reader-text" for="baukasten-multi-domain-field">
				<?php esc_html_e( 'Domain', 'baukasten-multi-domain' ); ?>
			</label>
			<input
				type="text"
				class="widefat"
				id="baukasten-multi-domain-field"
				name="<?php echo esc_attr( Page_List::COLUMN ); ?>"
				value="<?php echo esc_attr( $domain ); ?>"
				autocomplete="off"
				placeholder="<?php esc_attr_e( 'kunde.example', 'baukasten-multi-domain' ); ?>"
			/>
		</p>
		<p class="description">
			<?php
			esc_html_e(
				'Visitors arriving on this domain see this page as the front page. The scheme, a port and any path are stripped; www is covered automatically.',
				'baukasten-multi-domain'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Saves the meta box field.
	 *
	 * @param int $post_id Page ID.
	 * @return void
	 */
	public static function save( $post_id ): void {
		$post_id = (int) $post_id;

		if ( ! isset( $_POST[ self::NONCE ] ) ) {
			return;
		}

		check_admin_referer( self::NONCE, self::NONCE );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = isset( $_POST[ Page_List::COLUMN ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ Page_List::COLUMN ] ) )
			: '';

		Domain_Map::assign( $post_id, Domain::sanitize( $raw ) );
	}
}
