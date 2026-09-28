<?php
/**
 * Content Visibility, built into Baukasten - Privacy Toolkit.
 *
 * Loaded by `baukasten/baukasten.php`. Up to the merge this was the separate
 * plugin `baukasten-content-visibility`; its namespace, post meta, options and
 * hooks are unchanged, so existing data carries straight over.
 *
 * @package Baukasten\ContentVisibility
 */

namespace Baukasten\ContentVisibility;

defined( 'ABSPATH' ) || exit;

const VERSION = \Baukasten\VERSION;

/**
 * Absolute path to this feature's directory, with trailing slash.
 */
define( 'Baukasten\ContentVisibility\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of this feature's directory, with trailing slash.
 */
define( 'Baukasten\ContentVisibility\PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Absolute path to the main plugin file, `baukasten/baukasten.php`.
 */
define( 'Baukasten\ContentVisibility\PLUGIN_FILE', \Baukasten\PLUGIN_FILE );

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
 * Creates this feature's options and, on a site that never ran it, marks
 * existing content public. Idempotent.
 *
 * Called by `Baukasten\Installer` whenever the structure version is behind.
 * A site that used the separate Content Visibility plugin already ran the
 * migration, and running it again there would publish anything that has
 * gone without a flag since — so it only runs where it never has.
 *
 * @return void
 */
function install(): void {
	add_option( Settings::OPTION, Settings::defaults(), '', false );

	if ( false === get_option( Visibility::OPTION_MIGRATION ) ) {
		activate();
	}
}

/**
 * Marks existing content public, on every activation of the plugin.
 *
 * Existing content is explicitly marked public so that switching the plugin
 * on never takes a live site's public content offline. Only content created
 * from now on defaults to private. The migration only touches entries that
 * carry no flag yet, so it never overrides a choice made by hand.
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
