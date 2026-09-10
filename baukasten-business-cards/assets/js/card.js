/**
 * A card's two interactive parts: the light and dark switch, and the QR modal.
 *
 * No framework and no build step. The QR code is drawn from the bundled
 * encoder, in the browser, so that showing a card never tells a third party
 * that someone looked at it.
 */
( function () {
	'use strict';

	var STORAGE_KEY = 'baukasten-card-theme';

	/**
	 * Reads the visitor's stored preference.
	 *
	 * Storage throws outright in some privacy modes, so every access is
	 * wrapped and an unreadable store simply means "no preference".
	 *
	 * @return {string} 'light', 'dark' or an empty string.
	 */
	function stored() {
		try {
			return window.localStorage.getItem( STORAGE_KEY ) || '';
		} catch ( error ) {
			return '';
		}
	}

	/**
	 * Stores the visitor's preference.
	 *
	 * @param {string} theme 'light' or 'dark'.
	 */
	function remember( theme ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, theme );
		} catch ( error ) {
			// A card that cannot remember the choice still honours it for
			// this page view, which is the part that matters.
		}
	}

	/**
	 * Applies a theme to the document.
	 *
	 * @param {string} theme 'light' or 'dark'.
	 */
	function apply( theme ) {
		document.documentElement.setAttribute( 'data-theme', theme );

		var buttons = document.querySelectorAll( '[data-bkbc-theme-toggle]' );
		var index;

		for ( index = 0; index < buttons.length; index++ ) {
			buttons[ index ].setAttribute( 'aria-pressed', 'dark' === theme ? 'true' : 'false' );
		}
	}

	/**
	 * The theme in effect right now.
	 *
	 * @return {string} 'light' or 'dark'.
	 */
	function current() {
		var attribute = document.documentElement.getAttribute( 'data-theme' );

		if ( 'light' === attribute || 'dark' === attribute ) {
			return attribute;
		}

		return window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches
			? 'dark'
			: 'light';
	}

	/**
	 * Draws the QR code for this page into its container, once.
	 *
	 * @param {HTMLElement} container Where the SVG goes.
	 */
	function draw( container ) {
		if ( container.getAttribute( 'data-drawn' ) || ! window.qrcode ) {
			return;
		}

		// The card URL is plain ASCII, but a filtered permalink need not be.
		if ( window.qrcode.stringToBytesFuncs && window.qrcode.stringToBytesFuncs[ 'UTF-8' ] ) {
			window.qrcode.stringToBytes = window.qrcode.stringToBytesFuncs[ 'UTF-8' ];
		}

		var url = container.getAttribute( 'data-url' ) || window.location.href;
		var code = window.qrcode( 0, 'M' );

		code.addData( url );
		code.make();

		container.innerHTML = code.createSvgTag( {
			cellSize: 6,
			margin: 12,
			scalable: true
		} );
		container.setAttribute( 'data-drawn', '1' );
	}

	/**
	 * Opens the QR dialog.
	 *
	 * @param {HTMLElement} dialog The dialog element.
	 */
	function open( dialog ) {
		var container = dialog.querySelector( '[data-bkbc-qr-canvas]' );

		if ( container ) {
			draw( container );
		}

		if ( 'function' === typeof dialog.showModal ) {
			dialog.showModal();

			return;
		}

		dialog.setAttribute( 'open', 'open' );
	}

	/**
	 * Closes the QR dialog.
	 *
	 * @param {HTMLElement} dialog The dialog element.
	 */
	function close( dialog ) {
		if ( 'function' === typeof dialog.close ) {
			dialog.close();

			return;
		}

		dialog.removeAttribute( 'open' );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var preference = stored();

		if ( 'light' === preference || 'dark' === preference ) {
			apply( preference );
		} else {
			apply( current() );
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;

		if ( ! target || ! target.closest ) {
			return;
		}

		var toggle = target.closest( '[data-bkbc-theme-toggle]' );

		if ( toggle ) {
			event.preventDefault();

			var next = 'dark' === current() ? 'light' : 'dark';

			apply( next );
			remember( next );

			return;
		}

		var opener = target.closest( '[data-bkbc-qr-open]' );

		if ( opener ) {
			event.preventDefault();

			var dialog = document.getElementById( opener.getAttribute( 'data-bkbc-qr-open' ) );

			if ( dialog ) {
				open( dialog );
			}

			return;
		}

		var closer = target.closest( '[data-bkbc-qr-close]' );

		if ( closer ) {
			event.preventDefault();

			var owner = closer.closest( 'dialog' );

			if ( owner ) {
				close( owner );
			}
		}
	} );
}() );
