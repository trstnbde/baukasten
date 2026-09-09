<?php
/**
 * Plugin Name:       Baukasten Addon: Multi-Domain Landingpage
 * Plugin URI:        https://github.com/trstnbde/baukasten
 * Description:       Assigns domains to pages, so one WordPress install serves a different front page under each domain.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      8.0
 * Requires Plugins:  baukasten
 * Author:            Torsten B.
 * Author URI:        https://github.com/trstnbde
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       baukasten-multi-domain
 * Domain Path:       /languages
 *
 * @package Baukasten\MultiDomain
 */

namespace Baukasten\MultiDomain;

defined( 'ABSPATH' ) || exit;

const VERSION    = '1.0.0';
const TEXTDOMAIN = 'baukasten-multi-domain';

/**
 * Absolute path to the plugin directory, with trailing slash.
 */
define( 'Baukasten\MultiDomain\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of the plugin directory, with trailing slash.
 */
define( 'Baukasten\MultiDomain\PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Absolute path to the main plugin file.
 */
define( 'Baukasten\MultiDomain\PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-domain.php';
require_once __DIR__ . '/includes/class-domain-map.php';
require_once __DIR__ . '/includes/class-domain-router.php';
require_once __DIR__ . '/includes/class-page-list.php';
require_once __DIR__ . '/includes/class-meta-box.php';
require_once __DIR__ . '/includes/class-settings-tab.php';

/**
 * Registers all hooks.
 *
 * @return void
 */
function boot(): void {
	add_action( 'init', __NAMESPACE__ . '\\load_textdomain', 1 );

	Domain_Map::register();
	Meta_Box::register();
	Domain_Router::register();

	if ( is_admin() ) {
		Page_List::register();
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
 * Rebuilds rather than starting empty: a site that had this plugin before
 * still has the meta on its pages, and reactivating should pick it back up
 * instead of quietly serving the wrong front page.
 *
 * @return void
 */
function activate(): void {
	Domain_Map::rebuild();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot' );

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
