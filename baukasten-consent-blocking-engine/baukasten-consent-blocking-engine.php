<?php
/**
 * Plugin Name:       Baukasten Addon: Consent Blocking Engine
 * Plugin URI:        https://github.com/trstnbde/baukasten
 * Description:       Blocks non-essential scripts, styles, embeds, resource hints, Gravatar and emoji until the visitor has consented, and keeps an auditable consent log.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * Requires Plugins:  baukasten
 * Author:            Torsten B.
 * Author URI:        https://github.com/trstnbde
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       baukasten-consent-blocking-engine
 * Domain Path:       /languages
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

const VERSION    = '1.0.0';
const TEXTDOMAIN = 'baukasten-consent-blocking-engine';

/**
 * Absolute path to the plugin directory, with trailing slash.
 */
define( 'Baukasten\ConsentBlockingEngine\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of the plugin directory, with trailing slash.
 */
define( 'Baukasten\ConsentBlockingEngine\PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Absolute path to the main plugin file.
 */
define( 'Baukasten\ConsentBlockingEngine\PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-categories.php';
require_once __DIR__ . '/includes/class-consent-state.php';
require_once __DIR__ . '/includes/class-consent-log.php';
require_once __DIR__ . '/includes/class-inventory.php';
require_once __DIR__ . '/includes/class-frontend.php';
require_once __DIR__ . '/includes/class-rest-controller.php';
require_once __DIR__ . '/includes/class-blocking-scripts.php';
require_once __DIR__ . '/includes/class-blocking-script-modules.php';
require_once __DIR__ . '/includes/class-blocking-resource-hints.php';
require_once __DIR__ . '/includes/class-blocking-oembed.php';
require_once __DIR__ . '/includes/class-blocking-gravatar.php';
require_once __DIR__ . '/includes/class-blocking-emoji.php';
require_once __DIR__ . '/includes/class-privacy-audit.php';
require_once __DIR__ . '/includes/class-hardening.php';
require_once __DIR__ . '/includes/class-cli.php';
require_once __DIR__ . '/includes/class-settings-tab.php';

/**
 * Registers all hooks.
 *
 * @return void
 */
function boot(): void {
	add_action( 'init', __NAMESPACE__ . '\\load_textdomain', 1 );

	REST_Controller::register();
	Privacy_Audit::register();
	CLI::register();

	if ( is_admin() ) {
		add_action( 'admin_init', array( Consent_Log::class, 'maybe_install' ) );
		add_action( 'admin_enqueue_scripts', array( Settings_Tab::class, 'enqueue' ) );

		Settings_Tab::register();

		return;
	}

	// Everything below only makes sense while a page is being rendered for a
	// visitor. The REST endpoints above are registered either way.
	Frontend::register();
	Inventory::register();

	Blocking_Scripts::register();

	if ( Settings::enabled( 'block_script_modules' ) ) {
		Blocking_Script_Modules::register();
	}

	if ( Settings::enabled( 'block_resource_hints' ) ) {
		Blocking_Resource_Hints::register();
	}

	if ( Settings::enabled( 'block_oembed' ) ) {
		Blocking_Oembed::register();
	}

	if ( Settings::enabled( 'block_gravatar' ) ) {
		Blocking_Gravatar::register();
	}

	if ( Settings::enabled( 'block_emoji' ) ) {
		Blocking_Emoji::register();
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
	add_option( Settings::OPTION, Settings::defaults(), '', true );

	Consent_Log::install();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot' );

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
