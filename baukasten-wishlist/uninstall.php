<?php
/**
 * Removes everything this plugin stored.
 *
 * @package Baukasten\Wishlist
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Deletes the plugin's options, every list and every wish on one site.
 *
 * `wp_delete_post( $id, true )` takes the meta — and with it every reported
 * purchase — along. Product images stay in the media library: they may be in
 * use elsewhere.
 *
 * @return void
 */
function baukasten_wishlist_uninstall_site() {
	delete_option( 'baukasten_wishlist_currency' );
	delete_option( 'baukasten_wishlist_rate_limit' );

	$baukasten_wl_posts = get_posts(
		array(
			'post_type'              => array( 'baukasten_wishlist', 'baukasten_wish_item' ),
			'post_status'            => 'any',
			'numberposts'            => -1,
			'fields'                 => 'ids',
			'suppress_filters'       => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	foreach ( $baukasten_wl_posts as $baukasten_wl_post_id ) {
		wp_delete_post( (int) $baukasten_wl_post_id, true );
	}
}

if ( is_multisite() ) {
	$baukasten_wl_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $baukasten_wl_sites as $baukasten_wl_site_id ) {
		switch_to_blog( (int) $baukasten_wl_site_id );
		baukasten_wishlist_uninstall_site();
		restore_current_blog();
	}
} else {
	baukasten_wishlist_uninstall_site();
}
