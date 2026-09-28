<?php
/**
 * Cleanup routine, executed when the plugin is deleted from the dashboard.
 *
 * Removes the plugin's own option and its cron event. Flamingo's messages are
 * Flamingo's data and stay where they are.
 *
 * @package Baukasten\FormPrivacy
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes all plugin data for the current site.
 *
 * @return void
 */
function baukasten_form_privacy_uninstall_site(): void {
	delete_option( 'baukasten_form_privacy_settings' );
	wp_clear_scheduled_hook( 'baukasten/form_privacy/purge' );
}

if ( is_multisite() ) {
	$baukasten_fp_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $baukasten_fp_sites as $baukasten_fp_site_id ) {
		switch_to_blog( (int) $baukasten_fp_site_id );
		baukasten_form_privacy_uninstall_site();
		restore_current_blog();
	}
} else {
	baukasten_form_privacy_uninstall_site();
}
