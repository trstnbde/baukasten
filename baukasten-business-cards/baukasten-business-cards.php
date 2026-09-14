<?php
/**
 * Plugin Name:       Baukasten Addon: Business Cards
 * Plugin URI:        https://github.com/trstnbde/baukasten
 * Description:       Digital business cards as a custom post type, served on their own short URL and detached from the active theme.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * Requires Plugins:  baukasten, contact-form-7
 * Author:            Torsten B.
 * Author URI:        https://github.com/trstnbde
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       baukasten-business-cards
 * Domain Path:       /languages
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

const VERSION    = '1.0.0';
const TEXTDOMAIN = 'baukasten-business-cards';

/**
 * Absolute path to the plugin directory, with trailing slash.
 */
define( 'Baukasten\BusinessCards\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of the plugin directory, with trailing slash.
 */
define( 'Baukasten\BusinessCards\PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Absolute path to the main plugin file.
 */
define( 'Baukasten\BusinessCards\PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-fields.php';
require_once __DIR__ . '/includes/class-icons.php';
require_once __DIR__ . '/includes/class-obfuscate.php';
require_once __DIR__ . '/includes/class-skins.php';
require_once __DIR__ . '/includes/class-legal-links.php';
require_once __DIR__ . '/includes/class-post-type.php';
require_once __DIR__ . '/includes/class-slug.php';
require_once __DIR__ . '/includes/class-permalink-field.php';
require_once __DIR__ . '/includes/class-meta-boxes.php';
require_once __DIR__ . '/includes/class-renderer.php';
require_once __DIR__ . '/includes/class-vcard.php';
require_once __DIR__ . '/includes/class-pass.php';
require_once __DIR__ . '/includes/class-forms.php';
require_once __DIR__ . '/includes/class-settings-tab.php';

/**
 * Registers all hooks.
 *
 * @return void
 */
function boot(): void {
	add_action( 'init', __NAMESPACE__ . '\load_textdomain', 1 );

	Post_Type::register();
	Slug::register();
	Renderer::register();
	VCard::register();
	Pass::register();
	Forms::register();

	if ( is_admin() ) {
		Meta_Boxes::register();
		Permalink_Field::register();
		Settings_Tab::register();
	}
}

/**
 * Loads the plugin translations.
 *
 * On `init` rather than on `plugins_loaded`: since WordPress 6.7 loading a
 * text domain before `init` is flagged as doing it wrong.
 *
 * @return void
 */
function load_textdomain(): void {
	load_plugin_textdomain( TEXTDOMAIN, false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );
}

/**
 * Runs once on activation.
 *
 * `activate_plugin()` includes this file mid-request, long after
 * `plugins_loaded` has fired, so `boot()` never ran and the post type is not
 * registered. Registering it here first is what makes the flush write rules
 * for the card route rather than rules without it.
 *
 * @return void
 */
function activate(): void {
	add_option( Settings::OPTION_BASE, Settings::DEFAULT_BASE, '', true );

	Post_Type::register_post_type();

	flush_rewrite_rules();
}

/**
 * Runs once on deactivation.
 *
 * The post type is likewise not registered in this request, so a plain flush
 * regenerates the rules without the card route — which is exactly right.
 *
 * @return void
 */
function deactivate(): void {
	delete_option( Post_Type::OPTION_STAMP );

	flush_rewrite_rules();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\boot' );

register_activation_hook( __FILE__, __NAMESPACE__ . '\activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\deactivate' );
