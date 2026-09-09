<?php
/**
 * Plugin Name:       Baukasten Addon: Content Visibility
 * Plugin URI:        https://github.com/trstnbde/baukasten
 * Description:       Adds a public/private switch to every post, page and public custom post type. Private content is readable by logged-in users only, regardless of their role.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      8.0
 * Requires Plugins:  baukasten
 * Author:            Torsten B.
 * Author URI:        https://github.com/trstnbde
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       baukasten-content-visibility
 * Domain Path:       /languages
 *
 * @package Baukasten\ContentVisibility
 */

namespace Baukasten\ContentVisibility;

defined( 'ABSPATH' ) || exit;

const VERSION    = '1.0.0';
const TEXTDOMAIN = 'baukasten-content-visibility';

/**
 * Absolute path to the plugin directory, with trailing slash.
 */
define( 'Baukasten\ContentVisibility\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of the plugin directory, with trailing slash.
 */
define( 'Baukasten\ContentVisibility\PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Absolute path to the main plugin file.
 */
define( 'Baukasten\ContentVisibility\PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-visibility.php';
require_once __DIR__ . '/includes/class-admin-column.php';
require_once __DIR__ . '/includes/class-bulk-actions.php';
require_once __DIR__ . '/includes/class-meta-box.php';
require_once __DIR__ . '/includes/class-ajax.php';
require_once __DIR__ . '/includes/class-settings-tab.php';
require_once __DIR__ . '/includes/class-frontend-guard.php';
require_once __DIR__ . '/includes/functions.php';

/**
 * Registers all hooks.
 *
 * @return void
 */
function boot(): void {
	add_action( 'init', __NAMESPACE__ . '\\load_textdomain', 1 );

	Settings::register();
	Visibility::register();
	Frontend_Guard::register();

	if ( is_admin() ) {
		Admin_Column::register();
		Bulk_Actions::register();
		Meta_Box::register();
		Ajax::register();
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
 * Existing content is explicitly marked public so that switching the plugin
 * on never takes a live site's public content offline. Only content created
 * from now on defaults to private.
 *
 * @return void
 */
function activate(): void {
	add_option( Settings::OPTION, Settings::defaults(), '', false );

	$migrated = Visibility::migrate_existing_to_public();

	update_option(
		Visibility::OPTION_MIGRATION,
		array(
			'time'  => time(),
			'count' => $migrated,
		),
		false
	);
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot' );

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
