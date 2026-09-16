/**
 * Watches an open challenge from the login screen.
 *
 * Submits the form only when the challenge was approved. Never on a denial and
 * never on expiry: every submitted-and-rejected attempt counts towards Two
 * Factor's failure counter, which locks the account with a longer and longer
 * delay and, after thirty of them, resets the user's password outright. A
 * forgotten tab retrying every couple of minutes would get there on its own.
 *
 * @package Baukasten\TwoFactor
 */

( function () {
	'use strict';

	var config = window.baukasten2faLogin;

	if ( ! config || ! config.endpoint ) {
		return;
	}

	var status = document.getElementById( 'baukasten-2fa-status' );
	var form = document.getElementById( 'loginform' );

	if ( ! status || ! form ) {
		return;
	}

	var timer = null;
	var submitting = false;

	function say( message, settled ) {
		status.textContent = message;
		// Drop the spinner once there is nothing left to wait for: an animation
		// that keeps going after the answer arrived says the opposite of what
		// the text says.
		status.classList.toggle( 'baukasten-2fa-settled', true === settled );
	}

	function stop() {
		if ( null !== timer ) {
			window.clearTimeout( timer );
			timer = null;
		}
	}

	function finish() {
		// Guard before submitting, not after: an in-flight response landing
		// during navigation would otherwise submit the form a second time.
		if ( submitting ) {
			return;
		}

		submitting = true;
		stop();
		say( config.i18n.approved );
		form.submit();
	}

	function schedule() {
		stop();

		if ( Date.now() >= config.expiresAt ) {
			say( config.i18n.expired, true );
			return;
		}

		timer = window.setTimeout( poll, config.interval );
	}

	function poll() {
		if ( submitting ) {
			return;
		}

		if ( Date.now() >= config.expiresAt ) {
			say( config.i18n.expired, true );
			return;
		}

		window.fetch( config.endpoint, {
			credentials: 'omit',
			cache: 'no-store',
			headers: { Accept: 'application/json' }
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'unavailable' );
			}

			return response.json();
		} ).then( function ( body ) {
			switch ( body.status ) {
				case 'approved':
					finish();
					break;

				case 'denied':
					stop();
					say( config.i18n.denied, true );
					break;

				case 'expired':
				case 'superseded':
				case 'consumed':
					stop();
					say( config.i18n.expired, true );
					break;

				default:
					say( config.i18n.waiting );
					schedule();
					break;
			}
		} ).catch( function () {
			// A blip, a proxy, a rate limit. Keep waiting — the explicit
			// button below the form is the way out if this never recovers.
			say( config.i18n.offline );
			schedule();
		} );
	}

	// Pause while the tab is hidden. The user cannot act on it there, and this
	// is the browser that is about to be signed in, so it is usually in front.
	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden ) {
			stop();
			return;
		}

		if ( ! submitting ) {
			poll();
		}
	} );

	poll();
}() );
