<?php
/**
 * Cleanup routine, executed when the plugin is deleted from the dashboard.
 *
 * Removes the core's options and capability and everything the three built-in
 * features keep: the consent log table and the consent settings, the
 * visibility flag on every post, and the legal page, login and rate limit
 * settings.
 *
 * The consent log is the record a controller needs to demonstrate that consent
 * was given, and there is no way to get it back afterwards. The Consent tab
 * says so in as many words. The pages the legal settings pointed at are
 * ordinary content and are left exactly where they are.
 *
 * @package Baukasten
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-autoloader.php';
require_once __DIR__ . '/features/consent-blocking-engine/includes/class-consent-log.php';
require_once __DIR__ . '/features/content-visibility/includes/class-visibility.php';

Baukasten\Autoloader::register();

/**
 * Removes all plugin data for the current site.
 *
 * @return void
 */
function baukasten_uninstall_site(): void {
	global $wpdb;

	delete_option( Baukasten\Installer::OPTION_SETTINGS );
	delete_option( Baukasten\Installer::OPTION_VERSION );
	delete_option( Baukasten\Installer::OPTION_STRUCTURE );

	Baukasten\Installer::remove_capabilities();

	// Consent Blocking Engine.
	Baukasten\ConsentBlockingEngine\Consent_Log::drop();
	wp_clear_scheduled_hook( Baukasten\ConsentBlockingEngine\Consent_Log::CRON_HOOK );

	delete_option( 'baukasten_consent_settings' );
	delete_option( 'baukasten_consent_categories' );
	delete_option( 'baukasten_consent_category_definitions' );
	delete_option( 'baukasten_consent_handles' );

	// Content Visibility: the flag on every post that carries one.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => Baukasten\ContentVisibility\Visibility::META_KEY ), array( '%s' ) );

	delete_option( Baukasten\ContentVisibility\Visibility::OPTION_MIGRATION );
	delete_option( 'baukasten_content_visibility_settings' );

	// Login Legal Pages.
	delete_option( 'baukasten_page_for_terms' );
	delete_option( 'baukasten_page_for_imprint' );
	delete_option( 'baukasten_login_slug' );
	delete_option( 'baukasten_login_rate_limit' );

	// The bulk delete above bypassed the object cache, so drop it wholesale
	// rather than leaving stale post meta behind.
	wp_cache_flush();
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
