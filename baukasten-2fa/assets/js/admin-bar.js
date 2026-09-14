/**
 * The confirmation prompt in the admin bar.
 *
 * Heartbeat is the baseline: it already runs for every signed-in user, so
 * asking it "is anything waiting?" costs one field on a request that was
 * happening anyway. Only once it says yes does the fast poll start, and it
 * stops again the moment the challenge is answered or runs out — so the
 * few-seconds polling exists only while somebody is actually waiting on it.
 *
 * @package Baukasten\TwoFactor
 */

( function () {
	'use strict';

	var config = window.baukasten2faBar;

	if ( ! config || ! config.pending || ! window.jQuery || ! window.wp || ! window.wp.heartbeat ) {
		return;
	}

	var $ = window.jQuery;

	var node = document.getElementById( 'wp-admin-bar-baukasten-2fa' );

	if ( ! node ) {
		return;
	}

	var contextEl = node.querySelector( '[data-baukasten-2fa="context"]' );
	var resultEl = node.querySelector( '[data-baukasten-2fa="result"]' );
	var buttons = node.querySelector( '.baukasten-2fa-buttons' );
	var approveEl = node.querySelector( '[data-baukasten-2fa="approve"]' );
	var denyEl = node.querySelector( '[data-baukasten-2fa="deny"]' );

	var timer = null;
	var handle = '';
	var busy = false;

	function show() {
		node.classList.add( 'baukasten-2fa-visible' );
		node.setAttribute( 'aria-hidden', 'false' );
	}

	function hide() {
		node.classList.remove( 'baukasten-2fa-visible' );
		node.setAttribute( 'aria-hidden', 'true' );
		handle = '';
	}

	function stop() {
		if ( null !== timer ) {
			window.clearTimeout( timer );
			timer = null;
		}
	}

	function settle( message ) {
		stop();

		if ( buttons ) {
			buttons.hidden = true;
		}

		if ( resultEl ) {
			resultEl.textContent = message;
		}

		// Leave the answer on screen briefly so it is not a flicker, then let
		// the node go back to being invisible.
		window.setTimeout( hide, 4000 );
	}

	function describe( body ) {
		if ( ! contextEl ) {
			return;
		}

		var parts = [];

		if ( body.agentLabel ) {
			parts.push( body.agentLabel );
		}

		if ( body.ipLabel ) {
			parts.push( body.ipLabel );
		}

		contextEl.textContent = parts.length
			? config.i18n.from.replace( '%s', parts.join( ' · ' ) )
			: '';
	}

	function poll() {
		stop();

		window.fetch( config.pending, {
			credentials: 'same-origin',
			cache: 'no-store',
			headers: {
				Accept: 'application/json',
				'X-WP-Nonce': config.nonce
			}
		} ).then( function ( response ) {
			return response.ok ? response.json() : null;
		} ).then( function ( body ) {
			if ( ! body || ! body.pending ) {
				hide();
				return;
			}

			handle = body.handle;

			if ( body.nonce ) {
				config.nonce = body.nonce;
			}

			describe( body );

			if ( buttons ) {
				buttons.hidden = false;
			}

			if ( resultEl ) {
				resultEl.textContent = '';
			}

			show();

			timer = window.setTimeout( poll, config.interval );
		} ).catch( function () {
			// Only keep retrying if something was actually being watched.
			// Otherwise this was a one-off check and a failed request must not
			// turn into a standing poll.
			if ( handle ) {
				timer = window.setTimeout( poll, config.interval );
			}
		} );
	}

	function decide( approve ) {
		if ( busy || ! handle ) {
			return;
		}

		busy = true;
		stop();

		window.fetch( config.decide, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			},
			body: JSON.stringify( {
				handle: handle,
				decision: approve ? 'approve' : 'deny'
			} )
		} ).then( function ( response ) {
			return response.json().then( function ( body ) {
				return { ok: response.ok, body: body };
			} );
		} ).then( function ( result ) {
			busy = false;

			if ( ! result.ok ) {
				if ( 'baukasten_2fa_stale' === result.body.code ) {
					settle( config.i18n.stale );
					return;
				}

				// Somebody else answered it first, from another device. Not an
				// error worth alarming anyone about.
				settle( config.i18n.already );
				return;
			}

			if ( 'already-decided' === result.body.status ) {
				settle( config.i18n.already );
				return;
			}

			settle( approve ? config.i18n.approved : config.i18n.denied );
		} ).catch( function () {
			busy = false;
			stop();

			if ( resultEl ) {
				resultEl.textContent = config.i18n.failed;
			}
		} );
	}

	if ( approveEl ) {
		approveEl.addEventListener( 'click', function () {
			decide( true );
		} );
	}

	if ( denyEl ) {
		denyEl.addEventListener( 'click', function () {
			decide( false );
		} );
	}

	// Ask to be told about challenges. Without this field the server does not
	// run the query at all.
	$( document ).on( 'heartbeat-send', function ( event, data ) {
		data.baukasten_2fa = 1;
	} );

	// Heartbeat alone is not enough to notice a request in time. It ticks once
	// a minute at best, and slows down further in a window without focus —
	// which is precisely what the browser showing this prompt is. Two cheaper
	// triggers cover the cases that matter, and neither adds a standing poll:
	//
	// 1. The page already knew. The server looked while building the admin bar,
	//    so a page opened while something is waiting is right immediately.
	if ( config.waiting ) {
		poll();
	}

	// 2. The user came back to this tab — the moment they would expect to see
	//    it. One request per actual tab switch, and only while nothing is
	//    already being polled.
	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden || busy || null !== timer ) {
			return;
		}

		poll();
	} );

	$( document ).on( 'heartbeat-tick', function ( event, data ) {
		if ( ! data.baukasten_2fa ) {
			return;
		}

		if ( data.baukasten_2fa.nonce ) {
			config.nonce = data.baukasten_2fa.nonce;
		}

		if ( data.baukasten_2fa.pending ) {
			if ( null === timer && ! busy ) {
				poll();
			}
			return;
		}

		if ( ! busy ) {
			stop();
			hide();
		}
	} );
}() );
