<?php
/**
 * Removes everything this plugin stored.
 *
 * @package Baukasten\TwoFactor
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-challenges.php';

/**
 * Deletes the options and user meta for one site.
 *
 * @return void
 */
function baukasten_2fa_uninstall_site() {
	delete_option( Baukasten\TwoFactor\Settings::OPTION );
	delete_option( Baukasten\TwoFactor\Challenges::OPTION_DB_VERSION );
}

if ( is_multisite() ) {
	$baukasten_2fa_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $baukasten_2fa_sites as $baukasten_2fa_site_id ) {
		switch_to_blog( (int) $baukasten_2fa_site_id );
		baukasten_2fa_uninstall_site();
		restore_current_blog();
	}
} else {
	baukasten_2fa_uninstall_site();
}

/*
 * The challenge table is dropped once, not once per site, and the loop above
 * deliberately does not touch it. It is named with `base_prefix` because users
 * are network-wide and a confirmation has to reach whichever site the second
 * browser happens to be on. Dropping it inside the loop would try the same
 * table on every iteration.
 */
Baukasten\TwoFactor\Challenges::drop();

// Literals rather than the class constants: pulling in the notice class and the
// main plugin file just to read two strings would run their side effects during
// an uninstall, which is the one request where nothing should be booting.
delete_metadata( 'user', 0, 'baukasten_2fa_notice_dismissed', '', true );

wp_clear_scheduled_hook( 'baukasten/2fa/purge' );
