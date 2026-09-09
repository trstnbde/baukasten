/**
 * Baukasten Multi-Domain - quick edit.
 *
 * WordPress renders one quick edit form and moves it into whichever row is
 * being edited, so a custom field starts out empty every time. Core fills its
 * own fields from hidden markup in the row; this does the same for the domain,
 * by wrapping inlineEditPost.edit the way core's own scripts do.
 */
( function () {
	'use strict';

	if ( typeof window.inlineEditPost === 'undefined' ) {
		return;
	}

	var original = window.inlineEditPost.edit;

	window.inlineEditPost.edit = function ( id ) {
		var result = original.apply( this, arguments );

		var postId = 0;

		if ( typeof id === 'object' ) {
			postId = parseInt( this.getId( id ), 10 );
		} else {
			postId = parseInt( id, 10 );
		}

		if ( ! postId ) {
			return result;
		}

		var source = document.getElementById( 'baukasten-domain-' + postId );
		var row = document.getElementById( 'edit-' + postId );

		if ( ! source || ! row ) {
			return result;
		}

		var field = row.querySelector( '.baukasten-domain-input' );

		if ( field ) {
			field.value = source.textContent.trim();
		}

		return result;
	};
} )();
