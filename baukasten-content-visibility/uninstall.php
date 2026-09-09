<?php
/**
 * Cleanup routine, executed when the plugin is deleted from the dashboard.
 *
 * @package Baukasten\ContentVisibility
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-visibility.php';

/**
 * Removes all plugin data for the current site.
 *
 * @return void
 */
function baukasten_content_visibility_uninstall_site(): void {
	global $wpdb;

	// Remove the visibility flag from every post that carries one.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => Baukasten\ContentVisibility\Visibility::META_KEY ), array( '%s' ) );

	delete_option( Baukasten\ContentVisibility\Visibility::OPTION_MIGRATION );
	delete_option( 'baukasten_content_visibility_settings' );

	// The bulk delete above bypassed the object cache, so drop the post meta
	// cache wholesale rather than leaving stale values behind.
	wp_cache_flush();
}

if ( is_multisite() ) {
	$baukasten_cv_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $baukasten_cv_sites as $baukasten_cv_site_id ) {
		switch_to_blog( (int) $baukasten_cv_site_id );
		baukasten_content_visibility_uninstall_site();
		restore_current_blog();
	}
} else {
	baukasten_content_visibility_uninstall_site();
}
