<?php
/**
 * Login Legal Pages, built into Baukasten - Privacy Toolkit.
 *
 * Loaded by `baukasten/baukasten.php`. Up to the merge this was the separate
 * plugin `baukasten-login-legal-pages`; its namespace, options and hooks are
 * unchanged, so existing settings carry straight over.
 *
 * @package Baukasten\LoginLegalPages
 */

namespace Baukasten\LoginLegalPages;

defined( 'ABSPATH' ) || exit;

const VERSION = \Baukasten\VERSION;

/**
 * Absolute path to this feature's directory, with trailing slash.
 */
define( 'Baukasten\LoginLegalPages\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of this feature's directory, with trailing slash.
 */
define( 'Baukasten\LoginLegalPages\PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Absolute path to the main plugin file, `baukasten/baukasten.php`.
 */
define( 'Baukasten\LoginLegalPages\PLUGIN_FILE', \Baukasten\PLUGIN_FILE );

require_once __DIR__ . '/includes/class-legal-pages.php';
require_once __DIR__ . '/includes/class-login-url.php';
require_once __DIR__ . '/includes/class-login-screen.php';
require_once __DIR__ . '/includes/class-login-footer.php';
require_once __DIR__ . '/includes/class-rate-limit.php';
require_once __DIR__ . '/includes/class-settings-tab.php';

/**
 * Registers all hooks.
 *
 * @return void
 */
function boot(): void {
	// The login screen is not an admin screen, so these register
	// unconditionally and hook into login-only actions.
	Login_Screen::register();
	Login_Footer::register();

	if ( is_admin() ) {
		Settings_Tab::register();
	}
}

/**
 * Creates this feature's options. Idempotent.
 *
 * Called by `Baukasten\Installer` on activation and whenever the structure
 * version is behind.
 *
 * @return void
 */
function install(): void {
	add_option( Legal_Pages::OPTION_TERMS, 0, '', false );
	add_option( Legal_Pages::OPTION_IMPRINT, 0, '', false );
	add_option( Login_URL::OPTION_SLUG, Login_URL::DEFAULT_SLUG, '', true );
	add_option( Rate_Limit::OPTION, Rate_Limit::defaults(), '', true );
}

/*
 * The login URL takes over the request before WordPress decides what it is
 * looking at, so it registers directly rather than from boot(). Everything it
 * does still runs on hooks; only the hook is earlier than plugins_loaded.
 *
 * The rate limit sits on `authenticate`, which XML-RPC and application
 * passwords reach as well, so it registers just as early.
 */
Login_URL::register();
Rate_Limit::register();

add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot' );
