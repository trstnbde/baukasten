<?php
/**
 * Removes everything this plugin stored.
 *
 * @package Baukasten\BusinessCards
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Deletes the plugin's options and every card on one site.
 *
 * `wp_delete_post( $id, true )` rather than a pair of DELETE queries: it takes
 * the post meta, the term relationships and the attachment links with it, and
 * gives other plugins the hooks they expect.
 *
 * @return void
 */
function baukasten_business_cards_uninstall_site() {
	delete_option( 'baukasten_business_cards_base' );
	delete_option( 'baukasten_business_cards_rewrite_stamp' );

	$baukasten_bc_cards = get_posts(
		array(
			'post_type'              => 'baukasten_card',
			'post_status'            => 'any',
			'numberposts'            => -1,
			'fields'                 => 'ids',
			'suppress_filters'       => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	foreach ( $baukasten_bc_cards as $baukasten_bc_card_id ) {
		wp_delete_post( (int) $baukasten_bc_card_id, true );
	}
}

if ( is_multisite() ) {
	$baukasten_bc_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $baukasten_bc_sites as $baukasten_bc_site_id ) {
		switch_to_blog( (int) $baukasten_bc_site_id );
		baukasten_business_cards_uninstall_site();
		restore_current_blog();
	}
} else {
	baukasten_business_cards_uninstall_site();
}
