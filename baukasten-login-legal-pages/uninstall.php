<?php
/**
 * Cleanup routine, executed when the plugin is deleted from the dashboard.
 *
 * Only the plugin's own options go. The pages they pointed at are ordinary
 * content and are left exactly where they are.
 *
 * @package Baukasten\LoginLegalPages
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes all plugin data for the current site.
 *
 * @return void
 */
function baukasten_login_legal_pages_uninstall_site(): void {
	delete_option( 'baukasten_page_for_terms' );
	delete_option( 'baukasten_page_for_imprint' );
	delete_option( 'baukasten_login_slug' );
}

if ( is_multisite() ) {
	$baukasten_llp_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $baukasten_llp_sites as $baukasten_llp_site_id ) {
		switch_to_blog( (int) $baukasten_llp_site_id );
		baukasten_login_legal_pages_uninstall_site();
		restore_current_blog();
	}
} else {
	baukasten_login_legal_pages_uninstall_site();
}
