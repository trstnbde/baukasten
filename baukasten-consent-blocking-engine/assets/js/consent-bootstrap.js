/**
 * Baukasten Consent Blocking Engine - client side unblocking.
 *
 * The server renders every page in the blocked state, the same for every
 * visitor, so a full page cache can store it whole. This script runs in the
 * browser, reads the consent cookie, and puts back what this particular
 * visitor allowed.
 *
 * It is never blocked itself and does nothing at all when no consent has been
 * given. The only request it ever makes is to this site's own REST endpoint,
 * and only when a visitor clicks "load and always allow" on a blocked embed.
 */
( function () {
	'use strict';

	var settings = window.baukastenConsent || {};
	var ATTR = 'data-baukasten-consent';

	/**
	 * Reads the allowed categories out of the consent cookie.
	 *
	 * @return {string[]} Allowed category slugs.
	 */
	function allowedCategories() {
		var name = ( settings.cookie || 'baukasten_consent' ) + '=';
		var parts = document.cookie ? document.cookie.split( ';' ) : [];

		for ( var i = 0; i < parts.length; i++ ) {
			var part = parts[ i ].trim();

			if ( part.indexOf( name ) !== 0 ) {
				continue;
			}

			try {
				var payload = JSON.parse( atob( decodeURIComponent( part.slice( name.length ) ) ) );

				return Array.isArray( payload.categories ) ? payload.categories : [];
			} catch ( error ) {
				return [];
			}
		}

		return [];
	}

	/**
	 * Re-runs a blocked script by replacing it with a live copy.
	 *
	 * A script element that is already in the document does not execute when
	 * its type attribute changes, so a fresh element has to take its place.
	 *
	 * @param {Element} element The blocked script element.
	 */
	function releaseScript( element ) {
		var replacement = document.createElement( 'script' );

		for ( var i = 0; i < element.attributes.length; i++ ) {
			var attribute = element.attributes[ i ];

			if ( attribute.name === 'type' || attribute.name === ATTR || attribute.name === 'data-baukasten-module' ) {
				continue;
			}

			replacement.setAttribute( attribute.name, attribute.value );
		}

		replacement.type = element.getAttribute( 'data-baukasten-module' ) ? 'module' : 'text/javascript';

		if ( ! element.src ) {
			replacement.text = element.text;
		}

		element.parentNode.replaceChild( replacement, element );
	}

	/**
	 * Restores a blocked stylesheet.
	 *
	 * @param {Element} element The blocked link element.
	 */
	function releaseStyle( element ) {
		var href = element.getAttribute( 'data-baukasten-href' );

		if ( ! href ) {
			return;
		}

		element.removeAttribute( 'data-baukasten-href' );
		element.removeAttribute( ATTR );
		element.setAttribute( 'rel', 'stylesheet' );
		element.setAttribute( 'href', href );
	}

	/**
	 * Puts a blocked embed back into the page.
	 *
	 * @param {Element} element The placeholder element.
	 */
	function releaseEmbed( element ) {
		var template = element.querySelector( '.baukasten-consent-embed__payload' );

		if ( ! template || ! template.content ) {
			return;
		}

		element.parentNode.replaceChild( template.content.cloneNode( true ), element );
	}

	/**
	 * Restores a withheld avatar.
	 *
	 * @param {Element} element The image element.
	 */
	function releaseImage( element ) {
		var src = element.getAttribute( 'data-baukasten-src' );

		if ( ! src ) {
			return;
		}

		element.removeAttribute( 'data-baukasten-src' );
		element.removeAttribute( ATTR );
		element.setAttribute( 'src', src );
		element.removeAttribute( 'srcset' );
	}

	/**
	 * Unblocks everything the visitor allowed.
	 */
	function apply() {
		var allowed = allowedCategories();

		if ( ! allowed.length ) {
			return;
		}

		allowed.forEach( function ( category ) {
			var selector = '[' + ATTR + '="' + category.replace( /"/g, '' ) + '"]';
			var elements = document.querySelectorAll( selector );

			Array.prototype.slice.call( elements ).forEach( function ( element ) {
				var name = element.tagName.toLowerCase();

				if ( name === 'script' ) {
					releaseScript( element );
				} else if ( name === 'link' ) {
					releaseStyle( element );
				} else if ( name === 'img' ) {
					releaseImage( element );
				} else if ( element.classList.contains( 'baukasten-consent-embed' ) ) {
					releaseEmbed( element );
				}
			} );
		} );

		document.dispatchEvent(
			new CustomEvent( 'baukastenConsentApplied', { detail: { allowed: allowed } } )
		);
	}

	/**
	 * Grants a category and re-runs the pass over the whole page.
	 *
	 * @param {string}   category The category to allow.
	 * @param {Function} done     Called once the answer is in.
	 */
	function allowCategory( category, done ) {
		var url = ( settings.restUrl || '' ).replace( /\/$/, '' ) + '/consent';
		var allowed = allowedCategories();

		if ( allowed.indexOf( category ) === -1 ) {
			allowed = allowed.concat( [ category ] );
		}

		var request = new XMLHttpRequest();

		request.open( 'POST', url, true );
		request.setRequestHeader( 'Content-Type', 'application/json' );

		if ( settings.nonce ) {
			request.setRequestHeader( 'X-WP-Nonce', settings.nonce );
		}

		request.onload = function () {
			// The server sets the cookie; apply() reads it back.
			apply();
			done( request.status >= 200 && request.status < 300 );
		};

		request.onerror = function () {
			done( false );
		};

		request.send( JSON.stringify( { categories: allowed } ) );
	}

	/**
	 * Handles the two buttons on a blocked embed.
	 *
	 * Delegated from the document, so embeds that arrive later — a lazily
	 * rendered block, a page built by a script — are covered without
	 * rebinding anything.
	 *
	 * @param {Event} event The click event.
	 */
	function onClick( event ) {
		if ( ! event.target || ! event.target.closest ) {
			return;
		}

		var button = event.target.closest( '[data-baukasten-action]' );

		if ( ! button ) {
			return;
		}

		var placeholder = button.closest( '.baukasten-consent-embed' );

		if ( ! placeholder ) {
			return;
		}

		event.preventDefault();

		if ( button.getAttribute( 'data-baukasten-action' ) === 'load' ) {
			releaseEmbed( placeholder );

			return;
		}

		var category = placeholder.getAttribute( ATTR );

		button.disabled = true;

		allowCategory( category, function ( ok ) {
			button.disabled = false;

			// Whether or not the decision could be stored, the visitor asked
			// for this embed and gets it.
			if ( ! ok && placeholder.parentNode ) {
				releaseEmbed( placeholder );
			}
		} );
	}

	document.addEventListener( 'click', onClick );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', apply );
	} else {
		apply();
	}

	// A banner, a preferences dialog or any other UI announces a change with
	// this event; the same pass then runs again over whatever is now allowed.
	document.addEventListener( 'consentUpdated', apply );

	window.baukastenConsentApply = apply;
} )();
