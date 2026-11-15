/**
 * Quick Edit for wishes: fills the wish's fields from the row.
 *
 * WordPress clones one hidden edit row for every Quick Edit; this wraps its
 * edit() and copies the values the list column printed into the clone.
 */
( function ( $ ) {
	'use strict';

	if ( ! $ || ! window.inlineEditPost ) {
		return;
	}

	var edit = window.inlineEditPost.edit;

	window.inlineEditPost.edit = function ( id ) {
		edit.apply( this, arguments );

		var postId = 'object' === typeof id ? parseInt( this.getId( id ), 10 ) : parseInt( id, 10 );

		if ( ! postId ) {
			return;
		}

		var data = $( '#post-' + postId ).find( '.baukasten-wl-inline' );
		var row = $( '#edit-' + postId );

		if ( ! data.length || ! row.length ) {
			return;
		}

		[ 'list', 'quantity', 'price', 'priority' ].forEach( function ( key ) {
			row.find( '[name="_baukasten_wishlist_' + key + '"]' ).val( String( data.attr( 'data-' + key ) || '' ) );
		} );
	};
}( window.jQuery ) );
