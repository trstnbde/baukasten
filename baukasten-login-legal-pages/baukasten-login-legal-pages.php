<?php
/**
 * Plugin Name:       Baukasten Addon: Login Legal Pages
 * Plugin URI:        https://github.com/trstnbde/baukasten
 * Description:       Puts the privacy policy, terms and imprint under the login form, strips the WordPress header off the login screen, and moves it to a tidy /login/ URL.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      8.0
 * Requires Plugins:  baukasten
 * Author:            Torsten B.
 * Author URI:        https://github.com/trstnbde
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       baukasten-login-legal-pages
 * Domain Path:       /languages
 *
 * @package Baukasten\LoginLegalPages
 */

namespace Baukasten\LoginLegalPages;

defined( 'ABSPATH' ) || exit;

const VERSION    = '1.0.0';
const TEXTDOMAIN = 'baukasten-login-legal-pages';

/**
 * Absolute path to the plugin directory, with trailing slash.
 */
define( 'Baukasten\LoginLegalPages\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of the plugin directory, with trailing slash.
 */
define( 'Baukasten\LoginLegalPages\PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Absolute path to the main plugin file.
 */
define( 'Baukasten\LoginLegalPages\PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-legal-pages.php';
require_once __DIR__ . '/includes/class-login-url.php';
require_once __DIR__ . '/includes/class-login-screen.php';
require_once __DIR__ . '/includes/class-login-footer.php';
require_once __DIR__ . '/includes/class-settings-tab.php';

/**
 * Registers all hooks.
 *
 * @return void
 */
function boot(): void {
	add_action( 'init', __NAMESPACE__ . '\\load_textdomain', 1 );

	// The login screen is not an admin screen, so these register
	// unconditionally and hook into login-only actions.
	Login_Screen::register();
	Login_Footer::register();

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
 * Runs once on activation.
 *
 * @return void
 */
function activate(): void {
	add_option( Legal_Pages::OPTION_TERMS, 0, '', false );
	add_option( Legal_Pages::OPTION_IMPRINT, 0, '', false );
	add_option( Login_URL::OPTION_SLUG, Login_URL::DEFAULT_SLUG, '', true );
}

/*
 * The login URL takes over the request before WordPress decides what it is
 * looking at, so it registers directly rather than from boot(). Everything it
 * does still runs on hooks; only the hook is earlier than plugins_loaded.
 */
Login_URL::register();

add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot' );

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
