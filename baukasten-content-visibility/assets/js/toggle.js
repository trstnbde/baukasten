/**
 * Content Visibility - list table toggle.
 *
 * Sends the change over admin-ajax and reflects the result in place. The
 * checkbox is reverted when the request fails, so the UI never shows a state
 * the server did not accept.
 */
( function ( settings ) {
	'use strict';

	if ( ! settings ) {
		return;
	}

	var i18n = settings.i18n || {};

	/**
	 * Announces a message to assistive technology.
	 *
	 * @param {string}  message  Message text.
	 * @param {boolean} isError  Whether the message reports a failure.
	 */
	function announce( message, isError ) {
		if ( window.wp && window.wp.a11y && window.wp.a11y.speak ) {
			window.wp.a11y.speak( message, isError ? 'assertive' : 'polite' );
		}
	}

	/**
	 * Writes the current state into one toggle.
	 *
	 * @param {Element} wrapper    The toggle wrapper.
	 * @param {string}  visibility Either 'public' or 'private'.
	 * @param {string}  label      Label to display.
	 */
	function paint( wrapper, visibility, label ) {
		var isPublic = 'public' === visibility;

		wrapper.classList.toggle( 'is-public', isPublic );
		wrapper.classList.toggle( 'is-private', ! isPublic );
		wrapper.classList.remove( 'has-error' );

		var state = wrapper.querySelector( '.baukasten-visibility__state' );

		if ( state ) {
			state.textContent = label || ( isPublic ? i18n.public : i18n.private );
		}
	}

	/**
	 * Marks a toggle as busy or idle.
	 *
	 * @param {Element} wrapper The toggle wrapper.
	 * @param {boolean} busy    Whether a request is in flight.
	 */
	function setBusy( wrapper, busy ) {
		var input = wrapper.querySelector( '.baukasten-visibility__input' );
		var spinner = wrapper.querySelector( '.spinner' );

		wrapper.classList.toggle( 'is-busy', busy );

		if ( input ) {
			input.disabled = busy;
		}

		if ( spinner ) {
			spinner.classList.toggle( 'is-active', busy );
		}

		if ( busy ) {
			var state = wrapper.querySelector( '.baukasten-visibility__state' );

			if ( state && i18n.saving ) {
				state.textContent = i18n.saving;
			}
		}
	}

	/**
	 * Sends the change to the server.
	 *
	 * @param {Element} wrapper The toggle wrapper.
	 * @param {Element} input   The checkbox.
	 */
	function save( wrapper, input ) {
		var postId = wrapper.getAttribute( 'data-post' );
		var target = input.checked ? 'public' : 'private';

		if ( ! postId ) {
			return;
		}

		var body = new window.URLSearchParams();

		body.append( 'action', settings.action );
		body.append( 'nonce', settings.nonce );
		body.append( 'post', postId );
		body.append( 'visibility', target );

		setBusy( wrapper, true );

		window.fetch( settings.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json().catch( function () {
				return null;
			} );
		} ).then( function ( payload ) {
			if ( payload && payload.success ) {
				paint( wrapper, payload.data.visibility, payload.data.label );
				announce( payload.data.message || i18n.saved, false );
				return;
			}

			throw new Error(
				( payload && payload.data && payload.data.message ) || i18n.error
			);
		} ).catch( function ( error ) {
			// Put the checkbox back where the server still has it.
			input.checked = ! input.checked;
			paint( wrapper, input.checked ? 'public' : 'private' );

			wrapper.classList.add( 'has-error' );

			var state = wrapper.querySelector( '.baukasten-visibility__state' );

			if ( state ) {
				state.textContent = error.message || i18n.error;
			}

			announce( error.message || i18n.error, true );
		} ).then( function () {
			setBusy( wrapper, false );
		} );
	}

	document.addEventListener( 'change', function ( event ) {
		var input = event.target;

		if ( ! input || ! input.classList || ! input.classList.contains( 'baukasten-visibility__input' ) ) {
			return;
		}

		var wrapper = input.closest( '.baukasten-visibility' );

		if ( wrapper ) {
			save( wrapper, input );
		}
	} );
}( window.baukastenContentVisibility ) );
