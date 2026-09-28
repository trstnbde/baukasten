<?php
/**
 * Consent Blocking Engine, built into Baukasten - Privacy Toolkit.
 *
 * Loaded by `baukasten/baukasten.php`. Up to the merge this was the separate
 * plugin `baukasten-consent-blocking-engine`; its namespace, options, table,
 * REST route and hooks are unchanged, so existing data carries straight over.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

const VERSION = \Baukasten\VERSION;

/**
 * Absolute path to this feature's directory, with trailing slash.
 */
define( 'Baukasten\ConsentBlockingEngine\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of this feature's directory, with trailing slash.
 */
define( 'Baukasten\ConsentBlockingEngine\PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Absolute path to the main plugin file, `baukasten/baukasten.php`.
 */
define( 'Baukasten\ConsentBlockingEngine\PLUGIN_FILE', \Baukasten\PLUGIN_FILE );

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
	REST_Controller::register();
	Privacy_Audit::register();
	Consent_Log::register();
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
 * Creates this feature's options and table. Idempotent.
 *
 * Called by `Baukasten\Installer` on activation and whenever the structure
 * version is behind.
 *
 * @return void
 */
function install(): void {
	add_option( Settings::OPTION, Settings::defaults(), '', true );

	Inventory::install();
	Consent_Log::install();
	Consent_Log::schedule();
}

/**
 * Undoes what only makes sense while the plugin runs.
 *
 * @return void
 */
function deactivate(): void {
	Consent_Log::unschedule();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot' );
