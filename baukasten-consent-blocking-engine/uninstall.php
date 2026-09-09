<?php
/**
 * Cleanup routine, executed when the plugin is deleted from the dashboard.
 *
 * This drops the consent log. The settings screen says so in as many words,
 * because that table is the record a controller needs to demonstrate that
 * consent was given, and there is no way to get it back afterwards.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-consent-log.php';

/**
 * Removes all plugin data for the current site.
 *
 * @return void
 */
function baukasten_consent_uninstall_site(): void {
	Baukasten\ConsentBlockingEngine\Consent_Log::drop();

	delete_option( 'baukasten_consent_settings' );
	delete_option( 'baukasten_consent_categories' );
	delete_option( 'baukasten_consent_category_definitions' );
	delete_option( 'baukasten_consent_handles' );
}

if ( is_multisite() ) {
	$baukasten_cbe_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $baukasten_cbe_sites as $baukasten_cbe_site_id ) {
		switch_to_blog( (int) $baukasten_cbe_site_id );
		baukasten_consent_uninstall_site();
		restore_current_blog();
	}
} else {
	baukasten_consent_uninstall_site();
}
