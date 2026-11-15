<?php
/**
 * The block.
 *
 * @package Baukasten\Wishlist
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * `baukasten/wishlist`: a dynamic block, rendered on the server like the
 * shortcode, with the list and its display options in the sidebar.
 *
 * The editor script needs no build step: it is written against the `wp.*`
 * globals. Its strings come from PHP with the list choices, so the plugin's
 * one `.po` file covers them and no JSON translation files are needed.
 */
final class Block {

	/**
	 * Block name.
	 */
	const NAME = 'baukasten/wishlist';

	/**
	 * Editor script handle.
	 */
	const EDITOR_HANDLE = 'baukasten-wishlist-editor';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_block' ), 25 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_data' ) );
	}

	/**
	 * Registers the block and its editor script.
	 *
	 * @return void
	 */
	public static function register_block(): void {
		wp_register_script(
			self::EDITOR_HANDLE,
			PLUGIN_URL . 'assets/js/block-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ),
			Assets::version( 'assets/js/block-editor.js' ),
			true
		);

		register_block_type(
			self::NAME,
			array(
				'api_version'           => 3,
				'title'                 => __( 'Wishlist', 'baukasten-wishlist' ),
				'description'           => __( 'Shows one wishlist, where guests can mark what they bought.', 'baukasten-wishlist' ),
				'category'              => 'widgets',
				'icon'                  => 'heart',
				'keywords'              => array( 'wish', 'gift', 'birthday' ),
				'supports'              => array(
					'html'     => false,
					'align'    => array( 'wide', 'full' ),
					'multiple' => true,
				),
				'attributes'            => array(
					'listId'       => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'sort'         => array(
						'type'    => 'string',
						'default' => 'priority',
					),
					'status'       => array(
						'type'    => 'string',
						'default' => Renderer::DEFAULT_STATUS,
					),
					'showControls' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					// Wide by default: a grid of cards needs more than a text column.
					'align'        => array(
						'type'    => 'string',
						'default' => 'wide',
					),
				),
				'editor_script_handles' => array( self::EDITOR_HANDLE ),
				'style_handles'         => array( Assets::HANDLE ),
				'render_callback'       => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param mixed $attributes Block attributes.
	 * @return string Markup.
	 */
	public static function render( $attributes ): string {
		$attributes = is_array( $attributes ) ? $attributes : array();

		$markup = Renderer::render(
			array(
				'list'     => $attributes['listId'] ?? 0,
				'sort'     => $attributes['sort'] ?? 'priority',
				'status'   => $attributes['status'] ?? Renderer::DEFAULT_STATUS,
				'controls' => $attributes['showControls'] ?? true,
			)
		);

		if ( '' === $markup ) {
			return '';
		}

		$align = in_array( $attributes['align'] ?? 'wide', array( 'wide', 'full' ), true )
			? ' align' . ( $attributes['align'] ?? 'wide' )
			: '';

		return '<div class="wp-block-baukasten-wishlist' . esc_attr( $align ) . '">' . $markup . '</div>';
	}

	/**
	 * Hands the editor script its lists and strings.
	 *
	 * @return void
	 */
	public static function editor_data(): void {
		$lists = array();

		foreach ( Items::lists() as $id => $title ) {
			$lists[] = array(
				'value' => $id,
				'label' => $title,
			);
		}

		$data = array(
			'lists'    => $lists,
			'newUrl'   => admin_url( 'post-new.php?post_type=' . Post_Types::LIST_TYPE ),
			'sorts'    => self::choices( Renderer::sort_options() ),
			'statuses' => self::choices( Renderer::status_options() ),
			'i18n'     => array(
				'title'     => __( 'Wishlist', 'baukasten-wishlist' ),
				'list'      => __( 'List', 'baukasten-wishlist' ),
				'choose'    => __( 'Choose a list', 'baukasten-wishlist' ),
				'none'      => __( 'There is no wishlist yet.', 'baukasten-wishlist' ),
				'create'    => __( 'Create a list', 'baukasten-wishlist' ),
				'sort'      => __( 'Sorted by', 'baukasten-wishlist' ),
				'status'    => __( 'Shows at first', 'baukasten-wishlist' ),
				'controls'  => __( 'Let visitors sort and filter', 'baukasten-wishlist' ),
				'settings'  => __( 'Wishlist settings', 'baukasten-wishlist' ),
				'editLists' => __( 'Edit lists and wishes', 'baukasten-wishlist' ),
			),
			'editUrl'  => admin_url( 'edit.php?post_type=' . Post_Types::LIST_TYPE ),
		);

		wp_add_inline_script( self::EDITOR_HANDLE, 'window.baukastenWishlistEditor = ' . wp_json_encode( $data ) . ';', 'before' );
	}

	/**
	 * Key-label pairs as select options.
	 *
	 * @param array<string, string> $options Key to label.
	 * @return array<int, array<string, string>> Options.
	 */
	private static function choices( array $options ): array {
		$out = array();

		foreach ( $options as $value => $label ) {
			$out[] = array(
				'value' => (string) $value,
				'label' => $label,
			);
		}

		return $out;
	}
}
