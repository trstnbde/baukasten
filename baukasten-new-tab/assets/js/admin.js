/**
 * Baukasten New Tab Page - the settings tab.
 *
 * The media dialog for the background images and the order buttons of the
 * favourites editor. Without it the tab still saves: the favourites table
 * comes with three empty rows, only the images cannot be chosen.
 */
( function () {
	'use strict';

	var strings = window.baukastenNewTab && window.baukastenNewTab.i18n ? window.baukastenNewTab.i18n : {};

	/* Background images. */

	var media = document.querySelector( '[data-baukasten-new-tab-media]' );

	if ( media && window.wp && window.wp.media ) {
		var field = media.querySelector( 'input[name="media_ids"]' );
		var list = media.querySelector( '.baukasten-new-tab-media__list' );
		var frame = null;

		var ids = function () {
			return field.value.split( ',' ).filter( Boolean );
		};

		var render = function ( attachments ) {
			list.textContent = '';

			attachments.forEach( function ( attachment ) {
				var item = document.createElement( 'li' );
				var image = document.createElement( 'img' );
				var remove = document.createElement( 'button' );
				var sizes = attachment.sizes || {};
				var thumb = sizes.thumbnail || sizes.medium || sizes.full || {};

				item.setAttribute( 'data-id', attachment.id );
				image.src = thumb.url || attachment.url;
				image.alt = '';
				image.width = 75;
				image.height = 75;
				remove.type = 'button';
				remove.className = 'button-link baukasten-new-tab-media__remove';
				remove.setAttribute( 'aria-label', strings.removed || '' );
				remove.textContent = '×';

				item.appendChild( image );
				item.appendChild( remove );
				list.appendChild( item );
			} );

			field.value = attachments.map( function ( attachment ) {
				return attachment.id;
			} ).join( ',' );
		};

		// Remove buttons for the images printed by the server.
		Array.prototype.forEach.call( list.children, function ( item ) {
			var remove = document.createElement( 'button' );

			remove.type = 'button';
			remove.className = 'button-link baukasten-new-tab-media__remove';
			remove.setAttribute( 'aria-label', strings.removed || '' );
			remove.textContent = '×';
			item.appendChild( remove );
		} );

		list.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.baukasten-new-tab-media__remove' );

			if ( ! button ) {
				return;
			}

			var item = button.closest( 'li' );
			var id = item.getAttribute( 'data-id' );

			item.parentNode.removeChild( item );
			field.value = ids().filter( function ( value ) {
				return value !== id;
			} ).join( ',' );
		} );

		media.querySelector( '[data-baukasten-new-tab-choose]' ).addEventListener( 'click', function () {
			if ( ! frame ) {
				frame = window.wp.media( {
					title: strings.choose,
					button: { text: strings.use },
					library: { type: 'image' },
					multiple: 'add',
				} );

				frame.on( 'open', function () {
					var selection = frame.state().get( 'selection' );

					selection.reset();

					ids().forEach( function ( id ) {
						var attachment = window.wp.media.attachment( id );

						attachment.fetch();
						selection.add( attachment );
					} );
				} );

				frame.on( 'select', function () {
					render( frame.state().get( 'selection' ).toJSON() );
				} );
			}

			frame.open();
		} );
	}

	/* Favourites: reorder, remove, add. */

	var table = document.querySelector( '[data-baukasten-new-tab-favorites]' );

	if ( ! table ) {
		return;
	}

	var body = table.tBodies[ 0 ];
	var add = document.querySelector( '[data-baukasten-new-tab-add]' );

	var button = function ( action, label, text ) {
		var element = document.createElement( 'button' );

		element.type = 'button';
		element.className = 'button button-small';
		element.setAttribute( 'data-action', action );
		element.setAttribute( 'aria-label', label || action );
		element.textContent = text;

		return element;
	};

	var renumber = function () {
		Array.prototype.forEach.call( body.rows, function ( row, index ) {
			Array.prototype.forEach.call( row.querySelectorAll( 'input' ), function ( input ) {
				input.name = input.name.replace( /^favorites\[\d+\]/, 'favorites[' + index + ']' );
			} );
		} );
	};

	var decorate = function ( row ) {
		var cell = row.querySelector( '.baukasten-new-tab-favorites__order' );

		cell.textContent = '';
		cell.appendChild( button( 'up', strings.up, '↑' ) );
		cell.appendChild( button( 'down', strings.down, '↓' ) );
		cell.appendChild( button( 'remove', strings.remove, '×' ) );
	};

	Array.prototype.forEach.call( body.rows, decorate );

	body.addEventListener( 'click', function ( event ) {
		var control = event.target.closest( 'button[data-action]' );

		if ( ! control ) {
			return;
		}

		var row = control.closest( 'tr' );
		var action = control.getAttribute( 'data-action' );

		if ( action === 'up' && row.previousElementSibling ) {
			body.insertBefore( row, row.previousElementSibling );
		} else if ( action === 'down' && row.nextElementSibling ) {
			body.insertBefore( row.nextElementSibling, row );
		} else if ( action === 'remove' ) {
			if ( body.rows.length > 1 ) {
				body.removeChild( row );
			} else {
				Array.prototype.forEach.call( row.querySelectorAll( 'input' ), function ( input ) {
					input.value = '';
				} );
			}
		}

		renumber();
		control.focus();
	} );

	if ( add ) {
		add.hidden = false;

		add.addEventListener( 'click', function () {
			var row = body.rows[ body.rows.length - 1 ].cloneNode( true );

			Array.prototype.forEach.call( row.querySelectorAll( 'input' ), function ( input ) {
				input.value = '';
			} );

			body.appendChild( row );
			renumber();
			row.querySelector( 'input' ).focus();
		} );
	}
}() );
