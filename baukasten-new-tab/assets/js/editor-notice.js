/**
 * Baukasten New Tab Page - notice in the block editor.
 *
 * Loaded only when the page being edited is the new tab page. Says so in the
 * editor's own notice area, the way the editor flags the posts page.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.data || ! wp.domReady || ! window.baukastenNewTabNotice ) {
		return;
	}

	wp.domReady( function () {
		wp.data.dispatch( 'core/notices' ).createWarningNotice( window.baukastenNewTabNotice, {
			id: 'baukasten-new-tab-page',
			isDismissible: false,
		} );
	} );
}( window.wp ) );
