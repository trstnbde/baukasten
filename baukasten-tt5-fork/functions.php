<?php
/**
 * Baukasten Theme: TT5 Fork functions and definitions.
 *
 * A fork of Twenty Twenty-Five 1.5. Functions keep the parent's structure
 * under the baukasten_tt5_fork_ prefix; the design lives in theme.json and
 * styles/*.json.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package baukasten-tt5-fork
 * @since Twenty Twenty-Five 1.0
 */

if ( ! function_exists( 'baukasten_tt5_fork_load_textdomain' ) ) :
	/**
	 * Loads the bundled translations.
	 *
	 * The theme is not in the WordPress.org directory, so no language pack
	 * arrives for it; the translations ship in languages/.
	 *
	 * @since 1.5
	 *
	 * @return void
	 */
	function baukasten_tt5_fork_load_textdomain() {
		load_theme_textdomain( 'baukasten-tt5-fork', get_template_directory() . '/languages' );
	}
endif;
add_action( 'after_setup_theme', 'baukasten_tt5_fork_load_textdomain' );

if ( ! function_exists( 'baukasten_tt5_fork_post_format_setup' ) ) :
	/**
	 * Adds theme support for post formats.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function baukasten_tt5_fork_post_format_setup() {
		add_theme_support( 'post-formats', array( 'aside', 'audio', 'chat', 'gallery', 'image', 'link', 'quote', 'status', 'video' ) );
	}
endif;
add_action( 'after_setup_theme', 'baukasten_tt5_fork_post_format_setup' );

if ( ! function_exists( 'baukasten_tt5_fork_editor_style' ) ) :
	/**
	 * Enqueues editor-style.css and style.css in the editors.
	 *
	 * style.css comes second so its rules win over the editor stylesheet's
	 * at equal specificity.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function baukasten_tt5_fork_editor_style() {
		add_editor_style( array( 'assets/css/editor-style.css', 'style.css' ) );
	}
endif;
add_action( 'after_setup_theme', 'baukasten_tt5_fork_editor_style' );

if ( ! function_exists( 'baukasten_tt5_fork_enqueue_styles' ) ) :
	/**
	 * Enqueues the theme stylesheet on the front.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function baukasten_tt5_fork_enqueue_styles() {
		wp_enqueue_style(
			'baukasten-tt5-fork-style',
			get_parent_theme_file_uri( 'style.css' ),
			array(),
			wp_get_theme()->get( 'Version' )
		);
		wp_style_add_data(
			'baukasten-tt5-fork-style',
			'path',
			get_parent_theme_file_path( 'style.css' )
		);
	}
endif;
add_action( 'wp_enqueue_scripts', 'baukasten_tt5_fork_enqueue_styles' );

if ( ! function_exists( 'baukasten_tt5_fork_block_styles' ) ) :
	/**
	 * Registers custom block styles.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function baukasten_tt5_fork_block_styles() {
		register_block_style(
			'core/list',
			array(
				'name'         => 'checkmark-list',
				'label'        => __( 'Checkmark', 'baukasten-tt5-fork' ),
				'inline_style' => '
				ul.is-style-checkmark-list {
					list-style-type: "\2713";
				}

				ul.is-style-checkmark-list li {
					padding-inline-start: 1ch;
				}',
			)
		);
	}
endif;
add_action( 'init', 'baukasten_tt5_fork_block_styles' );

if ( ! function_exists( 'baukasten_tt5_fork_pattern_categories' ) ) :
	/**
	 * Registers pattern categories.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function baukasten_tt5_fork_pattern_categories() {

		register_block_pattern_category(
			'baukasten_tt5_fork_page',
			array(
				'label'       => __( 'Pages', 'baukasten-tt5-fork' ),
				'description' => __( 'A collection of full page layouts.', 'baukasten-tt5-fork' ),
			)
		);

		register_block_pattern_category(
			'baukasten_tt5_fork_post-format',
			array(
				'label'       => __( 'Post formats', 'baukasten-tt5-fork' ),
				'description' => __( 'A collection of post format patterns.', 'baukasten-tt5-fork' ),
			)
		);
	}
endif;
add_action( 'init', 'baukasten_tt5_fork_pattern_categories' );

if ( ! function_exists( 'baukasten_tt5_fork_register_block_bindings' ) ) :
	/**
	 * Registers the post format block binding source.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function baukasten_tt5_fork_register_block_bindings() {
		register_block_bindings_source(
			'baukasten-tt5-fork/format',
			array(
				'label'              => _x( 'Post format name', 'Label for the block binding placeholder in the editor', 'baukasten-tt5-fork' ),
				'get_value_callback' => 'baukasten_tt5_fork_format_binding',
			)
		);
	}
endif;
add_action( 'init', 'baukasten_tt5_fork_register_block_bindings' );

if ( ! function_exists( 'baukasten_tt5_fork_format_binding' ) ) :
	/**
	 * Callback function for the post format name block binding source.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return string|void Post format name, or nothing if the format is 'standard'.
	 */
	function baukasten_tt5_fork_format_binding() {
		$post_format_slug = get_post_format();

		if ( $post_format_slug && 'standard' !== $post_format_slug ) {
			return get_post_format_string( $post_format_slug );
		}
	}
endif;
