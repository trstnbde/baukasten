<?php
/**
 * Plugin Name:       Baukasten Addon: Form Privacy
 * Plugin URI:        https://github.com/trstnbde/baukasten
 * Description:       Contact Form 7 and Flamingo without the leaks: form assets only where a form is, no IP address, no address book, a retention period, an exporter, and spam protection without a third party.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * Requires Plugins:  baukasten, contact-form-7
 * Author:            Torsten B.
 * Author URI:        https://github.com/trstnbde
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       baukasten-form-privacy
 * Domain Path:       /languages
 * Update URI:        https://github.com/trstnbde/baukasten
 *
 * @package Baukasten\FormPrivacy
 */

namespace Baukasten\FormPrivacy;

defined( 'ABSPATH' ) || exit;

const VERSION    = '1.0.0';
const TEXTDOMAIN = 'baukasten-form-privacy';

/**
 * Absolute path to the plugin directory, with trailing slash.
 */
define( 'Baukasten\FormPrivacy\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of the plugin directory, with trailing slash.
 */
define( 'Baukasten\FormPrivacy\PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Absolute path to the main plugin file.
 */
define( 'Baukasten\FormPrivacy\PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-assets.php';
require_once __DIR__ . '/includes/class-remote-ip.php';
require_once __DIR__ . '/includes/class-address-book.php';
require_once __DIR__ . '/includes/class-submissions.php';
require_once __DIR__ . '/includes/class-retention.php';
require_once __DIR__ . '/includes/class-cleanup.php';
require_once __DIR__ . '/includes/class-exporter.php';
require_once __DIR__ . '/includes/class-policy-text.php';
require_once __DIR__ . '/includes/class-honeypot.php';
require_once __DIR__ . '/includes/class-cli.php';
require_once __DIR__ . '/includes/class-settings-tab.php';

/**
 * Registers all hooks.
 *
 * Filters are registered whatever is installed. They only ever run when
 * Contact Form 7 or Flamingo fires them, so a missing plugin simply means they
 * never do. Only calls into those plugins are guarded.
 *
 * @return void
 */
function boot(): void {
	add_action( 'init', __NAMESPACE__ . '\\load_textdomain', 1 );

	Assets::register();
	Remote_Ip::register();
	Address_Book::register();
	Submissions::register();
	Retention::register();
	Exporter::register();
	Policy_Text::register();
	Honeypot::register();
	CLI::register();

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
 * The cleanup of existing data is not part of it and never will be: it deletes
 * things, and that is a decision somebody makes on the settings tab.
 *
 * @return void
 */
function activate(): void {
	add_option( Settings::OPTION, Settings::defaults(), '', true );

	Retention::schedule();
}

/**
 * Runs on deactivation.
 *
 * @return void
 */
function deactivate(): void {
	Retention::unschedule();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot' );

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );
