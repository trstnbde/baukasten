<?php
/**
 * Plugin Name:       Baukasten Addon: Wishlist
 * Plugin URI:        https://github.com/trstnbde/baukasten
 * Description:       Wishlists for birthdays and other occasions: wishes with image, quantity, price, priority and shop link, shown in a page or post, where guests mark what they bought.
 * Version:           1.0.2
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * Requires Plugins:  baukasten
 * Author:            Torsten B.
 * Author URI:        https://github.com/trstnbde
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       baukasten-wishlist
 * Domain Path:       /languages
 * Update URI:        https://github.com/trstnbde/baukasten
 *
 * @package Baukasten\Wishlist
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

const VERSION    = '1.0.2';
const TEXTDOMAIN = 'baukasten-wishlist';

/**
 * Absolute path to the plugin directory, with trailing slash.
 */
define( 'Baukasten\Wishlist\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of the plugin directory, with trailing slash.
 */
define( 'Baukasten\Wishlist\PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Absolute path to the main plugin file.
 */
define( 'Baukasten\Wishlist\PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-post-types.php';
require_once __DIR__ . '/includes/class-fields.php';
require_once __DIR__ . '/includes/class-claims.php';
require_once __DIR__ . '/includes/class-items.php';
require_once __DIR__ . '/includes/class-assets.php';
require_once __DIR__ . '/includes/class-renderer.php';
require_once __DIR__ . '/includes/class-shortcode.php';
require_once __DIR__ . '/includes/class-block.php';
require_once __DIR__ . '/includes/class-rest-controller.php';
require_once __DIR__ . '/includes/class-meta-boxes.php';
require_once __DIR__ . '/includes/class-list-tables.php';
require_once __DIR__ . '/includes/class-settings-tab.php';

/**
 * Registers all hooks.
 *
 * @return void
 */
function boot(): void {
	add_action( 'init', __NAMESPACE__ . '\load_textdomain', 1 );

	Post_Types::register();
	Assets::register();
	Shortcode::register();
	Block::register();
	REST_Controller::register();

	if ( is_admin() ) {
		Meta_Boxes::register();
		List_Tables::register();
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
 * @return void
 */
function activate(): void {
	add_option( Settings::OPTION_CURRENCY, Settings::DEFAULT_CURRENCY, '', false );
	add_option( Settings::OPTION_RATE_LIMIT, Settings::DEFAULT_RATE_LIMIT, '', false );
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\boot' );

register_activation_hook( __FILE__, __NAMESPACE__ . '\activate' );
