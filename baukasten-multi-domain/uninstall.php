<?php
/**
 * Cleanup routine, executed when the plugin is deleted from the dashboard.
 *
 * The pages themselves are ordinary content and are left alone; only the
 * domain assignment on them and the lookup table go.
 *
 * @package Baukasten\MultiDomain
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes all plugin data for the current site.
 *
 * @return void
 */
function baukasten_multi_domain_uninstall_site(): void {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_baukasten_domain' ), array( '%s' ) );

	delete_option( 'baukasten_domain_map' );

	wp_cache_flush();
}

if ( is_multisite() ) {
	$baukasten_md_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $baukasten_md_sites as $baukasten_md_site_id ) {
		switch_to_blog( (int) $baukasten_md_site_id );
		baukasten_multi_domain_uninstall_site();
		restore_current_blog();
	}
} else {
	baukasten_multi_domain_uninstall_site();
}
