<?php
/**
 * Plugin Name:       Baukasten Addon: Two-Factor Approval
 * Plugin URI:        https://github.com/trstnbde/baukasten
 * Description:       Adds a two-factor method that asks a second, already signed-in browser session to approve the login from the WordPress admin bar.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * Requires Plugins:  baukasten, two-factor
 * Author:            Torsten B.
 * Author URI:        https://github.com/trstnbde
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       baukasten-2fa
 * Domain Path:       /languages
 *
 * @package Baukasten\TwoFactor
 */

namespace Baukasten\TwoFactor;

defined( 'ABSPATH' ) || exit;

const VERSION    = '1.0.0';
const TEXTDOMAIN = 'baukasten-2fa';

/**
 * The key this provider is registered under in Two Factor.
 *
 * Two Factor stores this string in user meta, in session meta and in the
 * site-wide allowlist option, so it is effectively a data format: renaming the
 * class later is a migration, not a rename.
 */
const PROVIDER_KEY = 'Baukasten_Two_Factor_Approve';

/**
 * Cron hook that deletes decided and expired challenges.
 */
const PURGE_HOOK = 'baukasten/2fa/purge';

/**
 * Absolute path to the plugin directory, with trailing slash.
 */
define( 'Baukasten\TwoFactor\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of the plugin directory, with trailing slash.
 */
define( 'Baukasten\TwoFactor\PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Absolute path to the main plugin file.
 */
define( 'Baukasten\TwoFactor\PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-challenges.php';
require_once __DIR__ . '/includes/class-context.php';
require_once __DIR__ . '/includes/class-rest-controller.php';
require_once __DIR__ . '/includes/class-login-screen.php';
require_once __DIR__ . '/includes/class-admin-bar.php';
require_once __DIR__ . '/includes/class-notices.php';
require_once __DIR__ . '/includes/class-settings-tab.php';

/*
 * Deliberately not required here: includes/class-baukasten-two-factor-approve.php.
 *
 * Two Factor includes it itself, lazily, from `get_providers_classes()` using
 * the path handed to the `two_factor_providers` filter below. At that moment
 * the abstract `Two_Factor_Provider` is guaranteed to exist, because
 * two-factor.php requires it at include time. Requiring it here instead would
 * fatal on any site where this plugin loads and Two Factor does not.
 */

/**
 * Registers all hooks.
 *
 * @return void
 */
function boot(): void {
	add_action( 'init', __NAMESPACE__ . '\load_textdomain', 1 );

	Notices::register();

	// Without Two Factor there is no provider to register and nothing to poll
	// for. The notice above explains the situation; everything else stays off.
	if ( ! class_exists( 'Two_Factor_Core' ) ) {
		return;
	}

	add_filter( 'two_factor_providers', __NAMESPACE__ . '\register_provider' );
	add_filter( 'two_factor_user_rate_limit', __NAMESPACE__ . '\clamp_rate_limit', 10, 2 );

	Login_Screen::register();
	REST_Controller::register();
	Admin_Bar::register();

	add_action( PURGE_HOOK, array( Challenges::class, 'purge' ) );

	if ( is_admin() ) {
		Settings_Tab::register();
		add_action( 'admin_init', array( Challenges::class, 'maybe_install' ) );
	}
}

/**
 * Adds this plugin's provider to the Two Factor provider list.
 *
 * The array key has to be the class name verbatim: Two Factor resolves it with
 * `class_exists()` and drops anything it cannot find, silently.
 *
 * Registering on `plugins_loaded` puts this filter in place well before
 * `init` priority 10, where Two Factor first calls `get_providers()`.
 *
 * @param array<string, string> $providers Class name to file path.
 * @return array<string, string> Filtered providers.
 */
function register_provider( array $providers ): array {
	$providers[ PROVIDER_KEY ] = PLUGIN_DIR . 'includes/class-baukasten-two-factor-approve.php';

	return $providers;
}

/**
 * Keeps Two Factor's exponential backoff shorter than a challenge lives.
 *
 * Two Factor doubles the delay between attempts with every failure, capped at
 * fifteen minutes, and checks it *before* the provider gets to validate. That
 * backoff exists because a six or eight digit code can be guessed. A 256-bit
 * challenge id that a human approves by pressing a button cannot, and past
 * failures of some other method should not be able to outlive every challenge
 * this one can issue — which is what happens from roughly nine failures on: the
 * approved challenge expires before the delay is over, and the user is locked
 * out of a second factor that is working perfectly.
 *
 * So the delay is clamped, but only for users whose current provider is this
 * one. Every other provider keeps the full backoff.
 *
 * @param int      $rate_limit Delay in seconds.
 * @param \WP_User $user       User being rate limited.
 * @return int Delay in seconds.
 */
function clamp_rate_limit( $rate_limit, $user ): int {
	$rate_limit = (int) $rate_limit;

	if ( ! $user instanceof \WP_User ) {
		return $rate_limit;
	}

	$provider = \Two_Factor_Core::get_primary_provider_for_user( $user );

	if ( ! $provider instanceof \Two_Factor_Provider || PROVIDER_KEY !== $provider->get_key() ) {
		return $rate_limit;
	}

	return (int) min( $rate_limit, max( 1, (int) floor( Settings::expiry() / 3 ) ) );
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
	add_option( Settings::OPTION, Settings::defaults(), '', false );

	Challenges::install();

	if ( ! wp_next_scheduled( PURGE_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', PURGE_HOOK );
	}
}

/**
 * Runs on deactivation.
 *
 * The table and the settings stay. Only deleting the plugin cleans up, which
 * is what `uninstall.php` is for.
 *
 * @return void
 */
function deactivate(): void {
	wp_clear_scheduled_hook( PURGE_HOOK );
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\boot' );

register_activation_hook( __FILE__, __NAMESPACE__ . '\activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\deactivate' );
