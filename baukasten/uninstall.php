<?php
/**
 * Cleanup routine, executed when the plugin is deleted from the dashboard.
 *
 * Addons are separate plugins and clean up after themselves, so there is
 * nothing here but the core's own options and capability.
 *
 * @package Baukasten
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-autoloader.php';

Baukasten\Autoloader::register();

/**
 * Removes all plugin data for the current site.
 *
 * @return void
 */
function baukasten_uninstall_site(): void {
	delete_option( Baukasten\Installer::OPTION_SETTINGS );
	delete_option( Baukasten\Installer::OPTION_VERSION );

	Baukasten\Installer::remove_capabilities();
}

if ( is_multisite() ) {
	$baukasten_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $baukasten_sites as $baukasten_site_id ) {
		switch_to_blog( (int) $baukasten_site_id );
		baukasten_uninstall_site();
		restore_current_blog();
	}
} else {
	baukasten_uninstall_site();
}
