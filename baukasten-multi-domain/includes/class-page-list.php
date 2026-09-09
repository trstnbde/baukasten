<?php
/**
 * The page list column and quick edit.
 *
 * @package Baukasten\MultiDomain
 */

namespace Baukasten\MultiDomain;

defined( 'ABSPATH' ) || exit;

/**
 * Puts the domain where the pages already are.
 *
 * Assigning a domain is a one-field decision about a page. Sending somebody to
 * a separate settings screen to make it, away from the list of pages they are
 * looking at, is how that field gets forgotten. So it lives in the list: a
 * column, a post state next to the title, and the quick edit.
 */
final class Page_List {

	/**
	 * Column id, also the form field name.
	 */
	const COLUMN = 'baukasten_domain';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'manage_page_posts_columns', array( __CLASS__, 'add_column' ) );
		add_action( 'manage_page_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_filter( 'display_post_states', array( __CLASS__, 'add_post_state' ), 10, 2 );
		add_action( 'quick_edit_custom_box', array( __CLASS__, 'render_quick_edit' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'save_post_page', array( __CLASS__, 'save_quick_edit' ) );
	}

	/**
	 * Adds the column, before the date.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Filtered columns.
	 */
	public static function add_column( array $columns ): array {
		$position = array_search( 'date', array_keys( $columns ), true );

		if ( false === $position ) {
			$columns[ self::COLUMN ] = __( 'Front page for domain', 'baukasten-multi-domain' );

			return $columns;
		}

		return array_merge(
			array_slice( $columns, 0, (int) $position, true ),
			array( self::COLUMN => __( 'Front page for domain', 'baukasten-multi-domain' ) ),
			array_slice( $columns, (int) $position, null, true )
		);
	}

	/**
	 * Prints one cell.
	 *
	 * The hidden copy is what the quick edit reads its starting value from;
	 * core does the same for its own fields.
	 *
	 * @param string $column  Column id.
	 * @param int    $post_id Page ID.
	 * @return void
	 */
	public static function render_column( $column, $post_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$domain = Domain_Map::domain_of( (int) $post_id );

		if ( '' === $domain ) {
			echo '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">';
			esc_html_e( 'No domain assigned', 'baukasten-multi-domain' );
			echo '</span>';
		} else {
			echo '<code>' . esc_html( $domain ) . '</code>';
		}

		printf(
			'<div class="hidden" id="baukasten-domain-%1$d">%2$s</div>',
			(int) $post_id,
			esc_html( $domain )
		);
	}

	/**
	 * Marks the page in the title column, next to "Front Page" and "Draft".
	 *
	 * @param string[] $states Existing post states.
	 * @param \WP_Post $post   The page.
	 * @return string[] Filtered post states.
	 */
	public static function add_post_state( $states, $post ): array {
		$states = (array) $states;

		if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type ) {
			return $states;
		}

		$domain = Domain_Map::domain_of( (int) $post->ID );

		if ( '' === $domain ) {
			return $states;
		}

		$states[ self::COLUMN ] = sprintf(
			/* translators: %s: domain name. */
			__( 'Front page (%s)', 'baukasten-multi-domain' ),
			$domain
		);

		return $states;
	}

	/**
	 * Prints the quick edit field.
	 *
	 * @param string $column    Column id.
	 * @param string $post_type Post type.
	 * @return void
	 */
	public static function render_quick_edit( $column, $post_type ): void {
		if ( self::COLUMN !== $column || 'page' !== $post_type ) {
			return;
		}

		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<label class="inline-edit-group">
					<span class="title"><?php esc_html_e( 'Domain', 'baukasten-multi-domain' ); ?></span>
					<input
						type="text"
						name="<?php echo esc_attr( self::COLUMN ); ?>"
						class="ptitle baukasten-domain-input"
						value=""
						autocomplete="off"
						placeholder="<?php esc_attr_e( 'kunde.example', 'baukasten-multi-domain' ); ?>"
					/>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Loads the quick edit script on the page list only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public static function enqueue( string $hook_suffix ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['post_type'] ) ) : 'post';

		if ( 'edit.php' !== $hook_suffix || 'page' !== $post_type ) {
			return;
		}

		wp_enqueue_script(
			'baukasten-multi-domain-quick-edit',
			PLUGIN_URL . 'assets/js/admin-quick-edit.js',
			array( 'inline-edit-post' ),
			VERSION,
			true
		);
	}

	/**
	 * Saves the quick edit field.
	 *
	 * Quick edit posts through `inline-save`, which fires `save_post_page` like
	 * any other save, so this handler has to tell the two apart: the meta box
	 * has its own nonce, and everything else — the REST API behind the block
	 * editor — writes the meta itself and is not this method's business.
	 *
	 * @param int $post_id Page ID.
	 * @return void
	 */
	public static function save_quick_edit( $post_id ): void {
		$post_id = (int) $post_id;

		if ( ! isset( $_POST['_inline_edit'] ) ) {
			return;
		}

		check_ajax_referer( 'inlineeditnonce', '_inline_edit' );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::COLUMN ] ) ) {
			return;
		}

		$raw = sanitize_text_field( wp_unslash( (string) $_POST[ self::COLUMN ] ) );

		Domain_Map::assign( $post_id, Domain::sanitize( $raw ) );
	}
}
