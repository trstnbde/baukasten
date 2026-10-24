<?php
/**
 * Removes everything this plugin stored.
 *
 * The page itself stays — it is the site's content — and so do the images
 * chosen from the media library. Only the colour this plugin noted on them
 * goes.
 *
 * @package Baukasten\NewTab
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Deletes options, transients, the cron event and the icon directory on one site.
 *
 * @return void
 */
function baukasten_new_tab_uninstall_site() {
	global $wpdb;

	foreach ( array( 'baukasten_new_tab_page', 'baukasten_new_tab_settings', 'baukasten_new_tab_unsplash_key', 'baukasten_new_tab_unsplash_pool', 'baukasten_new_tab_icons' ) as $baukasten_nt_option ) {
		delete_option( $baukasten_nt_option );
	}

	// Suggestion cache, rate limit buckets and the pool lock: their names
	// end in hashes, so they are found by prefix.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_baukasten_new_tab_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_baukasten_new_tab_' ) . '%'
		)
	);

	delete_post_meta_by_key( '_baukasten_new_tab_color' );

	wp_clear_scheduled_hook( 'baukasten/new_tab/refresh_icons' );

	$baukasten_nt_uploads = wp_upload_dir( null, false );
	$baukasten_nt_dir     = trailingslashit( (string) $baukasten_nt_uploads['basedir'] ) . 'baukasten-new-tab';

	if ( is_dir( $baukasten_nt_dir ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( WP_Filesystem() ) {
			global $wp_filesystem;

			$wp_filesystem->delete( $baukasten_nt_dir, true );
		}
	}
}

if ( is_multisite() ) {
	$baukasten_nt_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $baukasten_nt_sites as $baukasten_nt_site_id ) {
		switch_to_blog( (int) $baukasten_nt_site_id );
		baukasten_new_tab_uninstall_site();
		restore_current_blog();
	}
} else {
	baukasten_new_tab_uninstall_site();
}
