/**
 * The media pickers on the card editing screen.
 *
 * One delegated listener for every image and file field, rather than one
 * wp.media frame per field created up front: a card has eight of them and most
 * are never opened.
 */
( function () {
	'use strict';

	var strings = window.baukastenBusinessCards && window.baukastenBusinessCards.i18n
		? window.baukastenBusinessCards.i18n
		: {};

	/**
	 * Opens the media library for one field.
	 *
	 * @param {HTMLElement} wrapper The .baukasten-bc-media element.
	 */
	function choose( wrapper ) {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		var isImage = 'image' === wrapper.getAttribute( 'data-kind' );

		var frame = window.wp.media( {
			title: isImage ? strings.chooseImage : strings.chooseFile,
			button: { text: strings.use },
			library: isImage ? { type: 'image' } : {},
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();

			set( wrapper, attachment );
		} );

		frame.open();
	}

	/**
	 * Writes a chosen attachment into the field.
	 *
	 * @param {HTMLElement} wrapper    The .baukasten-bc-media element.
	 * @param {Object}      attachment The selected attachment.
	 */
	function set( wrapper, attachment ) {
		var input = wrapper.querySelector( '.baukasten-bc-media__value' );
		var view = wrapper.querySelector( '.baukasten-bc-media__preview' );
		var clear = wrapper.querySelector( '.baukasten-bc-media__clear' );

		if ( ! input || ! view ) {
			return;
		}

		input.value = attachment.id;

		var thumbnail = attachment.sizes && attachment.sizes.medium
			? attachment.sizes.medium.url
			: attachment.url;

		if ( 'image' === attachment.type ) {
			view.innerHTML = '';

			var image = document.createElement( 'img' );

			image.src = thumbnail;
			image.alt = '';
			view.appendChild( image );
		} else {
			view.textContent = attachment.filename || attachment.title || '';
		}

		if ( clear ) {
			clear.hidden = false;
		}
	}

	/**
	 * Empties a field.
	 *
	 * @param {HTMLElement} wrapper The .baukasten-bc-media element.
	 */
	function reset( wrapper ) {
		var input = wrapper.querySelector( '.baukasten-bc-media__value' );
		var view = wrapper.querySelector( '.baukasten-bc-media__preview' );
		var clear = wrapper.querySelector( '.baukasten-bc-media__clear' );

		if ( input ) {
			input.value = '0';
		}

		if ( view ) {
			view.textContent = '';
		}

		if ( clear ) {
			clear.hidden = true;
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;

		if ( ! target || ! target.closest ) {
			return;
		}

		var wrapper = target.closest( '.baukasten-bc-media' );

		if ( ! wrapper ) {
			return;
		}

		if ( target.classList.contains( 'baukasten-bc-media__select' ) ) {
			event.preventDefault();
			choose( wrapper );

			return;
		}

		if ( target.classList.contains( 'baukasten-bc-media__clear' ) ) {
			event.preventDefault();
			reset( wrapper );
		}
	} );
}() );
