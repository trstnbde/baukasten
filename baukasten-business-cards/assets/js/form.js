/**
 * Loads Cloudflare Turnstile for a card's contact form, once consent is given.
 *
 * Contact Form 7 would load Turnstile with the page, which sends every visitor's
 * address to Cloudflare whether or not they ever write a message. On a card that
 * is most visitors. So the server takes the script out of the page (see
 * `Forms::defer_turnstile()`) and this file puts it back the moment the visitor
 * ticks the form's consent box, `[acceptance email-consent]` — the sentence that
 * box stands for is the consent to having the data processed, and Cloudflare
 * seeing the visitor's address is part of that processing. Typing into the form
 * without ticking it sends nothing anywhere.
 *
 * Unticking the box again cannot take a loaded script back, so the promise is
 * only ever "not before you tick it".
 *
 * Four details make that work rather than merely look like it:
 *
 * - The script is loaded in explicit mode and each widget rendered from here,
 *   so there is no race between Turnstile's own scan of the page and this file.
 * - A submit that arrives before the token does is held and sent again once the
 *   token is there. Ticking the box and tapping send can follow each other
 *   closely, and without this the submission would go out without a token and
 *   be filed as spam.
 * - A submit with the box unticked is let through untouched. Contact Form 7
 *   validates before it checks for spam, so the visitor is told that consent
 *   is missing, and Cloudflare is never asked.
 * - Contact Form 7 resets Turnstile after each submission through an inline
 *   script attached to the handle the server removed, so that is done here too.
 *
 * @param {Window}   window   The window.
 * @param {Document} document The document.
 */
( function ( window, document ) {
	'use strict';

	var config = window.baukastenBusinessCardsForm || {};
	var READY = 'baukastenBusinessCardsTurnstileReady';
	var WIDGET = '.cf-turnstile';
	var CONSENT = 'input[type="checkbox"][name="email-consent"]';
	var ANY_CONSENT = '.wpcf7-acceptance input[type="checkbox"]';

	/** @type {'idle'|'loading'|'ready'|'failed'} */
	var state = 'idle';

	/**
	 * Forms waiting for a token, with the button that submitted them.
	 *
	 * @type {Array<{form: HTMLFormElement, submitter: (HTMLElement|null)}>}
	 */
	var waiting = [];

	if ( ! config.turnstileSrc ) {
		return;
	}

	/**
	 * The form a node belongs to, if that form carries a Turnstile widget.
	 *
	 * @param {EventTarget|null} node Any node.
	 * @return {HTMLFormElement|null} The form, or null.
	 */
	function protectedForm( node ) {
		var form = node && node.closest ? node.closest( 'form.wpcf7-form' ) : null;

		return form && form.querySelector( WIDGET ) ? form : null;
	}

	/**
	 * The consent box of a form.
	 *
	 * The one named `email-consent`, which is what the form this plugin creates
	 * uses; failing that, the first acceptance box, the same rule the server
	 * applies in `Forms::require_consent()`.
	 *
	 * @param {HTMLFormElement} form A form.
	 * @return {HTMLInputElement|null} The checkbox, or null.
	 */
	function consentBox( form ) {
		return form.querySelector( CONSENT ) || form.querySelector( ANY_CONSENT );
	}

	/**
	 * Whether the visitor has ticked the form's consent box.
	 *
	 * @param {HTMLFormElement} form A form.
	 * @return {boolean} True once ticked.
	 */
	function consented( form ) {
		var box = consentBox( form );

		return !! ( box && box.checked );
	}

	/**
	 * The light or dark the card is showing right now.
	 *
	 * The card's own switch writes `data-theme` on the root, so the widget
	 * follows that rather than the operating system, which may disagree.
	 *
	 * @return {string} 'light' or 'dark'.
	 */
	function theme() {
		return 'dark' === document.documentElement.getAttribute( 'data-theme' ) ? 'dark' : 'light';
	}

	/**
	 * Whether a form's widget has produced a token.
	 *
	 * @param {HTMLFormElement} form The form.
	 * @return {boolean} True when the form can be sent.
	 */
	function hasToken( form ) {
		var widget = form.querySelector( WIDGET );
		var name = widget ? widget.getAttribute( 'data-response-field-name' ) || 'cf-turnstile-response' : '';
		var field = name ? form.querySelector( 'input[name="' + name + '"]' ) : null;

		return !! ( field && field.value );
	}

	/**
	 * Sends every form that was held back waiting for a token.
	 *
	 * Also called when Turnstile fails, so a form is never stuck: Contact Form 7
	 * then answers with its own message, which is better than a button that does
	 * nothing.
	 */
	function release() {
		var queue = waiting;
		var index;

		waiting = [];

		for ( index = 0; index < queue.length; index++ ) {
			if ( 'failed' === state || hasToken( queue[ index ].form ) ) {
				resubmit( queue[ index ] );
			} else {
				waiting.push( queue[ index ] );
			}
		}
	}

	/**
	 * Submits a held form the way the visitor did.
	 *
	 * @param {{form: HTMLFormElement, submitter: (HTMLElement|null)}} entry Held form.
	 */
	function resubmit( entry ) {
		if ( 'function' === typeof entry.form.requestSubmit ) {
			entry.form.requestSubmit( entry.submitter || undefined );
		} else if ( entry.submitter && 'function' === typeof entry.submitter.click ) {
			entry.submitter.click();
		}
	}

	/**
	 * Renders every widget on the page that has not been rendered yet.
	 */
	function renderAll() {
		var widgets = document.querySelectorAll( WIDGET );
		var index;
		var widget;
		var options;

		for ( index = 0; index < widgets.length; index++ ) {
			widget = widgets[ index ];

			if ( widget.hasAttribute( 'data-bkbc-widget' ) ) {
				continue;
			}

			options = {
				sitekey: widget.getAttribute( 'data-sitekey' ),
				theme: widget.getAttribute( 'data-theme' ) || theme(),
				size: widget.getAttribute( 'data-size' ) || 'flexible',
				callback: release,
				'error-callback': function () {
					state = 'failed';
					release();
				},
			};

			copyOption( widget, options, 'data-response-field-name', 'response-field-name' );
			copyOption( widget, options, 'data-action', 'action' );
			copyOption( widget, options, 'data-appearance', 'appearance' );
			copyOption( widget, options, 'data-language', 'language' );

			widget.setAttribute( 'data-bkbc-widget', window.turnstile.render( widget, options ) );
		}
	}

	/**
	 * Copies one of Contact Form 7's data attributes into the render options.
	 *
	 * @param {Element} widget    The widget element.
	 * @param {Object}  options   Render options.
	 * @param {string}  attribute Attribute name.
	 * @param {string}  option    Option name.
	 */
	function copyOption( widget, options, attribute, option ) {
		var value = widget.getAttribute( attribute );

		if ( value ) {
			options[ option ] = value;
		}
	}

	/**
	 * Adds Turnstile's script to the page, once.
	 */
	function load() {
		var script;

		if ( 'idle' !== state ) {
			return;
		}

		state = 'loading';

		window[ READY ] = function () {
			state = 'ready';
			renderAll();
		};

		script = document.createElement( 'script' );
		script.src = config.turnstileSrc;
		script.async = true;
		script.onerror = function () {
			state = 'failed';
			release();
		};

		document.head.appendChild( script );
	}

	/**
	 * Starts loading when the consent box of a protected form is ticked.
	 *
	 * @param {Event} event A change event.
	 */
	function onConsent( event ) {
		var form = protectedForm( event.target );

		if ( form && event.target === consentBox( form ) && consented( form ) ) {
			load();
		}
	}

	document.addEventListener( 'change', onConsent, true );

	/**
	 * Loads right away when a box is already ticked, as it is when the browser
	 * restores a form on going back.
	 */
	function loadIfConsented() {
		var forms = document.querySelectorAll( 'form.wpcf7-form' );
		var index;

		for ( index = 0; index < forms.length; index++ ) {
			if ( protectedForm( forms[ index ] ) && consented( forms[ index ] ) ) {
				load();

				return;
			}
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', loadIfConsented );
	} else {
		loadIfConsented();
	}

	window.addEventListener( 'pageshow', loadIfConsented );

	/*
	 * On the document, in the capture phase, so this runs before Contact Form 7's
	 * own listener on the form and can stop it from sending without a token.
	 */
	document.addEventListener(
		'submit',
		function ( event ) {
			var form = protectedForm( event.target );

			// Without consent the submission goes through as it is: Contact
			// Form 7 rejects it for the missing tick before it would ever ask
			// Cloudflare, so there is nothing to wait for.
			if ( ! form || ! consented( form ) || 'failed' === state || hasToken( form ) ) {
				return;
			}

			event.preventDefault();
			event.stopImmediatePropagation();

			waiting.push( { form: form, submitter: event.submitter || null } );

			load();

			if ( 'ready' === state ) {
				release();
			}
		},
		true
	);

	/*
	 * A token is good for one submission. Contact Form 7 fires `wpcf7submit`
	 * after every attempt, successful or not, so the widget is reset there and
	 * the next attempt gets a fresh token.
	 */
	document.addEventListener( 'wpcf7submit', function ( event ) {
		var widgets;
		var index;

		if ( 'ready' !== state || ! window.turnstile || ! event.target.querySelectorAll ) {
			return;
		}

		widgets = event.target.querySelectorAll( WIDGET + '[data-bkbc-widget]' );

		for ( index = 0; index < widgets.length; index++ ) {
			window.turnstile.reset( widgets[ index ].getAttribute( 'data-bkbc-widget' ) );
		}
	} );
}( window, document ) );
