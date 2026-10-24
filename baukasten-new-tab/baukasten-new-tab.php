<?php
/**
 * Plugin Name:       Baukasten Addon: New Tab Page
 * Plugin URI:        https://github.com/trstnbde/baukasten
 * Description:       Turns one page into a browser start page: search with a choice of engines, a favourites bar, a greeting and a background, detached from the theme and without third-party requests.
 * Version:           1.0.1
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * Requires Plugins:  baukasten
 * Author:            Torsten B.
 * Author URI:        https://github.com/trstnbde
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       baukasten-new-tab
 * Domain Path:       /languages
 * Update URI:        https://github.com/trstnbde/baukasten
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

const VERSION    = '1.0.1';
const TEXTDOMAIN = 'baukasten-new-tab';

/**
 * Absolute path to the plugin directory, with trailing slash.
 */
define( 'Baukasten\NewTab\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Absolute path to the main plugin file.
 */
define( 'Baukasten\NewTab\PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-clock.php';
require_once __DIR__ . '/includes/class-holidays.php';
require_once __DIR__ . '/includes/class-greetings.php';
require_once __DIR__ . '/includes/class-search.php';
require_once __DIR__ . '/includes/class-suggest.php';
require_once __DIR__ . '/includes/class-unsplash.php';
require_once __DIR__ . '/includes/class-backgrounds.php';
require_once __DIR__ . '/includes/class-favicons.php';
require_once __DIR__ . '/includes/class-legal-links.php';
require_once __DIR__ . '/includes/class-page-setting.php';
require_once __DIR__ . '/includes/class-admin-bar.php';
require_once __DIR__ . '/includes/class-rest-controller.php';
require_once __DIR__ . '/includes/class-renderer.php';
require_once __DIR__ . '/includes/class-settings-tab.php';

/**
 * Registers all hooks.
 *
 * @return void
 */
function boot(): void {
	add_action( 'init', __NAMESPACE__ . '\load_textdomain', 1 );

	Page_Setting::register();
	Admin_Bar::register();
	Renderer::register();
	REST_Controller::register();
	Favicons::register();
	Backgrounds::register();

	if ( is_admin() ) {
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
 * The URL of a file in this plugin.
 *
 * Through `plugins_url()` on every call rather than a constant taken at
 * include time, so the Multi-Domain addon can point it at the host the page
 * was requested on.
 *
 * @param string $path Path relative to the plugin directory.
 * @return string URL.
 */
function plugin_url( string $path ): string {
	return plugins_url( $path, PLUGIN_FILE );
}

/**
 * Runs once on activation.
 *
 * @return void
 */
function activate(): void {
	add_option( Settings::OPTION, Settings::defaults(), '', true );

	Favicons::schedule();
}

/**
 * Runs once on deactivation.
 *
 * Settings and the selected page stay; only the weekly icon refresh stops.
 *
 * @return void
 */
function deactivate(): void {
	wp_clear_scheduled_hook( Favicons::CRON_HOOK );
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\boot' );

register_activation_hook( __FILE__, __NAMESPACE__ . '\activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\deactivate' );
