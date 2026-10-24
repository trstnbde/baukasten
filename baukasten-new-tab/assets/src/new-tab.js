/**
 * Baukasten New Tab Page.
 *
 * Everything the page does in the browser: the engine menu, the search with
 * suggestions, the favourites overflow, the background, the consent switch
 * and the greeting's memory. The page works without it — the form is a GET
 * the server answers — so every part here only improves on what is there.
 *
 * No inline script is allowed by the page's policy; the configuration comes
 * from the JSON block `#bk-config`.
 */
( function () {
	'use strict';

	var doc = document;
	var root = doc.documentElement;
	var configElement = doc.getElementById( 'bk-config' );
	var config = {};

	try {
		config = JSON.parse( configElement ? configElement.textContent : '{}' ) || {};
	} catch ( error ) {
		config = {};
	}

	root.classList.add( 'bk-js' );

	var i18n = config.i18n || {};
	var reducedMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/**
	 * localStorage that never throws.
	 */
	var store = {
		get: function ( key ) {
			try {
				return window.localStorage.getItem( key );
			} catch ( error ) {
				return null;
			}
		},
		set: function ( key, value ) {
			try {
				window.localStorage.setItem( key, value );
			} catch ( error ) {}
		},
	};

	function format( template, values ) {
		var index = 0;

		return String( template || '' ).replace( /%(\d+\$)?s/g, function ( match, position ) {
			var value = position ? values[ parseInt( position, 10 ) - 1 ] : values[ index++ ];

			return value === undefined ? '' : value;
		} );
	}

	function restUrl( base, params ) {
		var query = Object.keys( params || {} ).map( function ( key ) {
			return encodeURIComponent( key ) + '=' + encodeURIComponent( params[ key ] );
		} ).join( '&' );

		return query ? base + ( base.indexOf( '?' ) === -1 ? '?' : '&' ) + query : base;
	}

	function getJson( url, signal ) {
		var rest = config.rest || {};

		return window.fetch( url, {
			credentials: 'same-origin',
			headers: { Accept: 'application/json', 'X-WP-Nonce': rest.nonce || '' },
			signal: signal,
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'HTTP ' + response.status );
			}

			return response.json();
		} );
	}

	/* ------------------------------------------------------------------
	 * Greeting: the server picks at random; this avoids a repeat of the
	 * last three. A holiday greeting is left alone.
	 * ------------------------------------------------------------------ */

	( function greeting() {
		var data = config.greeting || {};
		var text = doc.querySelector( '.bk-greeting__text' );

		if ( ! text || data.holiday || ! data.pool || data.pool.length < 5 ) {
			return;
		}

		var key = 'bk-new-tab-greetings';
		var recent = [];

		try {
			recent = JSON.parse( store.get( key ) || '[]' );
		} catch ( error ) {
			recent = [];
		}

		recent = Array.isArray( recent ) ? recent.slice( -3 ) : [];

		var current = text.textContent;

		if ( recent.indexOf( current ) !== -1 ) {
			var fresh = data.pool.filter( function ( line ) {
				return recent.indexOf( line ) === -1;
			} );

			if ( fresh.length ) {
				current = fresh[ Math.floor( Math.random() * fresh.length ) ];
				text.textContent = current;
			}
		}

		recent.push( current );
		store.set( key, JSON.stringify( recent.slice( -3 ) ) );
	}() );

	/* ------------------------------------------------------------------
	 * Addresses and searches. Mirrors Search::address() and Search::parse().
	 * ------------------------------------------------------------------ */

	var engines = config.engines || [];
	var fileEndings = config.fileEndings || [];

	function engineById( id ) {
		for ( var i = 0; i < engines.length; i++ ) {
			if ( engines[ i ].id === id ) {
				return engines[ i ];
			}
		}

		return null;
	}

	function isHost( host, hasSuffix ) {
		if ( host === 'localhost' || /\.localhost$/.test( host ) ) {
			return true;
		}

		if ( /^(25[0-5]|2[0-4]\d|1?\d?\d)(\.(25[0-5]|2[0-4]\d|1?\d?\d)){3}$/.test( host ) ) {
			return true;
		}

		if ( /^\d+(\.\d+)*$/.test( host ) ) {
			return false;
		}

		var labels = host.split( '.' );

		if ( labels.length < 2 ) {
			return false;
		}

		for ( var i = 0; i < labels.length; i++ ) {
			if ( ! /^(?!-)[\p{L}\p{N}-]{1,63}$/u.test( labels[ i ] ) || /-$/.test( labels[ i ] ) ) {
				return false;
			}
		}

		var tld = labels[ labels.length - 1 ];

		if ( ! /^(\p{L}{2,63}|xn--[a-z0-9-]+)$/u.test( tld ) ) {
			return false;
		}

		return ! ( labels.length === 2 && ! hasSuffix && fileEndings.indexOf( tld ) !== -1 );
	}

	function addressOf( entry ) {
		entry = entry.trim();

		if ( ! entry || /\s/.test( entry ) ) {
			return '';
		}

		if ( /^https?:\/\/[^/?#]+/i.test( entry ) ) {
			return entry;
		}

		if ( /^[a-z][a-z0-9+.-]*:\/\//i.test( entry ) ) {
			return '';
		}

		var authority = entry.replace( /[/?#][\s\S]*$/, '' );
		var rest = entry.slice( authority.length );
		var host = authority;
		var port = '';
		var match = authority.match( /^(\[[0-9a-f:.]+\])(?::(\d{1,5}))?$/i );

		if ( match ) {
			host = match[ 1 ];
			port = match[ 2 ] || '';
		} else {
			match = authority.match( /^(.+):(\d{1,5})$/ );

			if ( match ) {
				host = match[ 1 ];
				port = match[ 2 ];
			}

			host = host.toLowerCase();

			if ( ! isHost( host, port !== '' || rest !== '' ) ) {
				return '';
			}
		}

		if ( port && ( +port < 1 || +port > 65535 ) ) {
			return '';
		}

		var plain = host === 'localhost' || /\.localhost$/.test( host ) || host.charAt( 0 ) === '[' || /^[\d.]+$/.test( host );

		return ( plain ? 'http' : 'https' ) + '://' + host + ( port ? ':' + port : '' ) + rest;
	}

	function parseEntry( entry, engineId ) {
		var match = entry.trim().match( /^@(\S+)\s+([\s\S]+)$/ );

		if ( match ) {
			for ( var i = 0; i < engines.length; i++ ) {
				if ( engines[ i ].shortcut && engines[ i ].shortcut.toLowerCase() === match[ 1 ].toLowerCase() ) {
					return { engine: engines[ i ], terms: match[ 2 ].trim(), shortcut: true };
				}
			}
		}

		return { engine: engineById( engineId ) || engines[ 0 ], terms: entry.trim(), shortcut: false };
	}

	function go( url, newTab ) {
		if ( newTab ) {
			window.open( url, '_blank', 'noopener,noreferrer' );
		} else {
			window.location.assign( url );
		}
	}

	/* ------------------------------------------------------------------
	 * Search box, engine menu and suggestions.
	 * ------------------------------------------------------------------ */

	( function search() {
		var form = doc.querySelector( '.bk-search' );
		var input = doc.getElementById( 'bk-q' );
		var hiddenEngine = form ? form.querySelector( 'input[name="e"]' ) : null;
		var button = doc.querySelector( '.bk-engine' );
		var badge = button ? button.querySelector( '.bk-badge' ) : null;
		var menu = doc.getElementById( 'bk-engines' );
		var list = doc.getElementById( 'bk-suggestions' );

		if ( ! form || ! input || ! engines.length ) {
			return;
		}

		var engineKey = 'bk-new-tab-engine';
		var current = engineById( store.get( engineKey ) ) ? store.get( engineKey ) : ( config.defaultEngine || engines[ 0 ].id );
		var temp = null; // engine chosen for this one search with an @ shortcut
		var options = menu ? Array.prototype.slice.call( menu.querySelectorAll( '[role="option"]' ) ) : [];
		var narrow = window.matchMedia( '(max-width: 600px)' );
		var activeOption = -1;
		var cache = {};

		function used() {
			return engineById( temp || current ) || engines[ 0 ];
		}

		function paint() {
			var engine = used();

			if ( hiddenEngine ) {
				hiddenEngine.value = engine.id;
			}

			if ( badge ) {
				badge.textContent = engine.badge;
				badge.className = 'bk-badge bk-badge--' + engine.id;
			}

			if ( button ) {
				button.setAttribute( 'aria-label', format( i18n.engine, [ engine.label ] ) );
				button.classList.toggle( 'is-temp', !! temp );
			}

			input.placeholder = format( narrow.matches ? i18n.placeholderShort : i18n.placeholder, [ engine.label ] );

			options.forEach( function ( option ) {
				option.setAttribute( 'aria-checked', option.getAttribute( 'data-engine' ) === current ? 'true' : 'false' );
			} );
		}

		function setEngine( id ) {
			if ( ! engineById( id ) ) {
				return;
			}

			current = id;
			temp = null;
			store.set( engineKey, id );
			paint();
		}

		function highlightOption( index ) {
			activeOption = ( index + options.length ) % options.length;

			options.forEach( function ( option, i ) {
				option.setAttribute( 'aria-selected', i === activeOption ? 'true' : 'false' );
			} );

			menu.setAttribute( 'aria-activedescendant', options[ activeOption ].id );
		}

		function openMenu() {
			closeSuggestions();
			menu.hidden = false;
			button.setAttribute( 'aria-expanded', 'true' );

			var selected = options.findIndex( function ( option ) {
				return option.getAttribute( 'data-engine' ) === ( temp || current );
			} );

			highlightOption( selected < 0 ? 0 : selected );
			menu.focus( { preventScroll: true } );
		}

		function closeMenu( focusInput ) {
			if ( ! menu || menu.hidden ) {
				return;
			}

			menu.hidden = true;
			button.setAttribute( 'aria-expanded', 'false' );

			if ( focusInput ) {
				input.focus();
			}
		}

		function chooseEngine( id ) {
			setEngine( id );
			closeMenu( true );

			if ( input.value.trim() ) {
				requestSuggestions();
			}
		}

		if ( button && menu ) {
			button.addEventListener( 'click', function () {
				if ( menu.hidden ) {
					openMenu();
				} else {
					closeMenu( true );
				}
			} );

			button.addEventListener( 'keydown', function ( event ) {
				if ( event.key === 'ArrowDown' ) {
					event.preventDefault();
					openMenu();
				}
			} );

			menu.addEventListener( 'keydown', function ( event ) {
				if ( event.key === 'ArrowDown' || event.key === 'ArrowUp' ) {
					event.preventDefault();
					highlightOption( activeOption + ( event.key === 'ArrowDown' ? 1 : -1 ) );
				} else if ( event.key === 'Home' || event.key === 'End' ) {
					event.preventDefault();
					highlightOption( event.key === 'Home' ? 0 : options.length - 1 );
				} else if ( event.key === 'Enter' || event.key === ' ' ) {
					event.preventDefault();
					chooseEngine( options[ activeOption ].getAttribute( 'data-engine' ) );
				} else if ( event.key === 'Escape' || event.key === 'Tab' ) {
					event.preventDefault();
					closeMenu( true );
				}
			} );

			menu.addEventListener( 'click', function ( event ) {
				var option = event.target.closest( '[role="option"]' );

				if ( option ) {
					chooseEngine( option.getAttribute( 'data-engine' ) );
				}
			} );
		}

		if ( narrow.addEventListener ) {
			narrow.addEventListener( 'change', paint );
		}

		/* An @ shortcut and a space picks an engine for one search, like the address bar. */

		function checkShortcut() {
			var match = /^@(\S+)\s/.exec( input.value );

			if ( ! match ) {
				return;
			}

			for ( var i = 0; i < engines.length; i++ ) {
				if ( engines[ i ].shortcut && engines[ i ].shortcut.toLowerCase() === match[ 1 ].toLowerCase() ) {
					temp = engines[ i ].id;
					input.value = input.value.slice( match[ 0 ].length );
					paint();

					return;
				}
			}
		}

		/* Suggestions. */

		var suggestions = [];
		var activeSuggestion = -1;
		var typed = '';
		var timer = 0;
		var controller = null;

		function closeSuggestions() {
			if ( ! list ) {
				return;
			}

			list.hidden = true;
			list.textContent = '';
			suggestions = [];
			activeSuggestion = -1;
			form.classList.remove( 'is-open' );
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
		}

		function showSuggestions( items ) {
			list.textContent = '';
			suggestions = items;
			activeSuggestion = -1;

			if ( ! items.length || doc.activeElement !== input ) {
				closeSuggestions();

				return;
			}

			var typedLower = typed.trim().toLowerCase();

			items.forEach( function ( item, index ) {
				var li = doc.createElement( 'li' );

				li.id = 'bk-suggestion-' + index;
				li.setAttribute( 'role', 'option' );
				li.setAttribute( 'aria-selected', 'false' );

				// The part that was typed stays plain, the completion is bold.
				if ( typedLower && item.toLowerCase().indexOf( typedLower ) === 0 ) {
					var bold = doc.createElement( 'b' );

					bold.textContent = item.slice( typedLower.length );
					li.appendChild( doc.createTextNode( item.slice( 0, typedLower.length ) ) );
					li.appendChild( bold );
				} else {
					li.textContent = item;
				}

				list.appendChild( li );
			} );

			list.hidden = false;
			form.classList.add( 'is-open' );
			input.setAttribute( 'aria-expanded', 'true' );
		}

		function highlightSuggestion( index ) {
			var items = list.children;

			activeSuggestion = index;

			for ( var i = 0; i < items.length; i++ ) {
				items[ i ].classList.toggle( 'is-active', i === index );
				items[ i ].setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
			}

			if ( index === -1 ) {
				input.value = typed;
				input.removeAttribute( 'aria-activedescendant' );
			} else {
				input.value = suggestions[ index ];
				input.setAttribute( 'aria-activedescendant', items[ index ].id );
				items[ index ].scrollIntoView( { block: 'nearest' } );
			}
		}

		function requestSuggestions() {
			var value = input.value.trim();

			typed = input.value;
			window.clearTimeout( timer );

			if ( ! config.suggest || ! list || value.length < 2 || addressOf( value ) ) {
				closeSuggestions();

				return;
			}

			if ( cache[ value ] ) {
				showSuggestions( cache[ value ] );

				return;
			}

			timer = window.setTimeout( function () {
				if ( controller ) {
					controller.abort();
				}

				controller = window.AbortController ? new window.AbortController() : null;

				getJson( restUrl( config.rest.suggest, { q: value } ), controller ? controller.signal : undefined )
					.then( function ( data ) {
						var items = Array.isArray( data ) && Array.isArray( data[ 1 ] ) ? data[ 1 ] : [];

						cache[ value ] = items;

						if ( input.value.trim() === value ) {
							showSuggestions( items );
						}
					} )
					.catch( function ( error ) {
						if ( ! error || error.name !== 'AbortError' ) {
							closeSuggestions();
						}
					} );
			}, 110 );
		}

		input.addEventListener( 'input', function () {
			checkShortcut();
			requestSuggestions();
		} );

		if ( list ) {
			// pointerdown, not click: the input keeps its focus until the choice is made.
			list.addEventListener( 'pointerdown', function ( event ) {
				var item = event.target.closest( 'li' );

				if ( item ) {
					event.preventDefault();
					input.value = suggestions[ Array.prototype.indexOf.call( list.children, item ) ];
					submit( event.ctrlKey || event.metaKey || event.button === 1 );
				}
			} );
		}

		input.addEventListener( 'blur', function () {
			window.setTimeout( function () {
				if ( ! form.contains( doc.activeElement ) ) {
					closeSuggestions();
				}
			}, 120 );
		} );

		input.addEventListener( 'focus', function () {
			if ( suggestions.length && input.value.trim() ) {
				showSuggestions( suggestions );
			}
		} );

		input.addEventListener( 'keydown', function ( event ) {
			var open = ! list.hidden && suggestions.length;

			if ( event.key === 'ArrowDown' ) {
				if ( open ) {
					event.preventDefault();
					highlightSuggestion( activeSuggestion + 1 >= suggestions.length ? -1 : activeSuggestion + 1 );
				} else if ( input.value.trim() ) {
					requestSuggestions();
				}
			} else if ( event.key === 'ArrowUp' && open ) {
				event.preventDefault();
				highlightSuggestion( activeSuggestion - 1 < -1 ? suggestions.length - 1 : activeSuggestion - 1 );
			} else if ( event.key === 'Escape' ) {
				if ( open ) {
					event.preventDefault();
					highlightSuggestion( -1 );
					closeSuggestions();
				} else if ( temp ) {
					temp = null;
					paint();
				} else if ( input.value ) {
					event.preventDefault();
					input.value = '';
				}
			} else if ( event.key === 'Backspace' && ! input.value && temp ) {
				temp = null;
				paint();
			} else if ( event.key === 'Enter' && ( event.altKey || event.ctrlKey || event.metaKey ) ) {
				event.preventDefault();
				submit( true );
			} else if ( event.key === 'Tab' ) {
				closeSuggestions();
			}
		} );

		function submit( newTab ) {
			var value = input.value.trim();

			if ( ! value ) {
				input.focus();

				return;
			}

			var engine = used();
			var address = temp ? '' : addressOf( value );

			temp = null;
			closeSuggestions();
			paint();

			go( address || engine.template.split( '{q}' ).join( encodeURIComponent( value ) ), newTab );
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			submit( false );
		} );

		doc.addEventListener( 'keydown', function ( event ) {
			var target = event.target;
			var typing = target && ( target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test( target.tagName ) );

			if ( event.key === '/' && ! typing && ! event.ctrlKey && ! event.metaKey && ! event.altKey ) {
				event.preventDefault();
				input.focus();
				input.select();
			}
		} );

		doc.addEventListener( 'pointerdown', function ( event ) {
			if ( menu && ! menu.hidden && ! event.target.closest( '.bk-engine, #bk-engines' ) ) {
				closeMenu( false );
			}
		} );

		// Back navigation from the cache: no stale popups, no leftover shortcut.
		window.addEventListener( 'pageshow', function ( event ) {
			if ( event.persisted ) {
				closeSuggestions();
				closeMenu( false );
				temp = null;
				paint();
			}
		} );

		paint();
	}() );

	/* ------------------------------------------------------------------
	 * Favourites: what does not fit moves into the » menu.
	 * ------------------------------------------------------------------ */

	( function favourites() {
		var bar = doc.querySelector( '.bk-bar' );

		if ( ! bar ) {
			return;
		}

		var listElement = bar.querySelector( '.bk-bar__list' );
		var items = Array.prototype.slice.call( listElement.children );
		var more = bar.querySelector( '.bk-bar__more' );
		var moreButton = bar.querySelector( '.bk-bar__more-button' );
		var menu = bar.querySelector( '.bk-bar__menu' );

		function layout() {
			items.forEach( function ( item ) {
				item.hidden = false;
			} );

			more.hidden = true;

			if ( listElement.scrollWidth <= listElement.clientWidth + 1 ) {
				menu.textContent = '';
				closeMenu( false );

				return;
			}

			more.hidden = false;

			var limit = listElement.getBoundingClientRect().right;
			var overflow = [];

			items.forEach( function ( item ) {
				if ( overflow.length || item.getBoundingClientRect().right > limit + 1 ) {
					item.hidden = true;
					overflow.push( item );
				}
			} );

			menu.textContent = '';

			overflow.forEach( function ( item ) {
				var li = doc.createElement( 'li' );
				var link = item.querySelector( 'a' ).cloneNode( true );

				li.setAttribute( 'role', 'none' );
				link.className = '';
				link.setAttribute( 'role', 'menuitem' );
				link.setAttribute( 'tabindex', '-1' );
				li.appendChild( link );
				menu.appendChild( li );
			} );
		}

		function menuLinks() {
			return Array.prototype.slice.call( menu.querySelectorAll( 'a' ) );
		}

		function openMenu() {
			menu.hidden = false;
			moreButton.setAttribute( 'aria-expanded', 'true' );

			var links = menuLinks();

			if ( links.length ) {
				links[ 0 ].focus();
			}
		}

		function closeMenu( focusButton ) {
			if ( menu.hidden ) {
				return;
			}

			menu.hidden = true;
			moreButton.setAttribute( 'aria-expanded', 'false' );

			if ( focusButton ) {
				moreButton.focus();
			}
		}

		menu.setAttribute( 'role', 'menu' );

		moreButton.addEventListener( 'click', function () {
			if ( menu.hidden ) {
				openMenu();
			} else {
				closeMenu( false );
			}
		} );

		moreButton.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'ArrowDown' ) {
				event.preventDefault();
				openMenu();
			}
		} );

		menu.addEventListener( 'keydown', function ( event ) {
			var links = menuLinks();
			var index = links.indexOf( doc.activeElement );

			if ( event.key === 'ArrowDown' || event.key === 'ArrowUp' ) {
				event.preventDefault();
				index = ( index + ( event.key === 'ArrowDown' ? 1 : -1 ) + links.length ) % links.length;
				links[ index ].focus();
			} else if ( event.key === 'Home' || event.key === 'End' ) {
				event.preventDefault();
				links[ event.key === 'Home' ? 0 : links.length - 1 ].focus();
			} else if ( event.key === 'Escape' ) {
				event.preventDefault();
				closeMenu( true );
			} else if ( event.key === 'Tab' ) {
				closeMenu( false );
			}
		} );

		doc.addEventListener( 'click', function ( event ) {
			if ( ! event.target.closest( '.bk-bar__more' ) ) {
				closeMenu( false );
			}
		} );

		// Left and right move along the bar, like a toolbar.
		listElement.addEventListener( 'keydown', function ( event ) {
			if ( event.key !== 'ArrowLeft' && event.key !== 'ArrowRight' ) {
				return;
			}

			var links = items.filter( function ( item ) {
				return ! item.hidden;
			} ).map( function ( item ) {
				return item.querySelector( 'a' );
			} );

			if ( ! more.hidden ) {
				links.push( moreButton );
			}

			var index = links.indexOf( doc.activeElement );

			if ( index !== -1 ) {
				event.preventDefault();
				links[ ( index + ( event.key === 'ArrowRight' ? 1 : -1 ) + links.length ) % links.length ].focus();
			}
		} );

		layout();

		if ( window.ResizeObserver ) {
			var frame = 0;

			new window.ResizeObserver( function () {
				window.cancelAnimationFrame( frame );
				frame = window.requestAnimationFrame( layout );
			} ).observe( bar );
		} else {
			window.addEventListener( 'resize', layout );
		}

		if ( doc.fonts && doc.fonts.ready ) {
			doc.fonts.ready.then( layout );
		}
	}() );

	/* ------------------------------------------------------------------
	 * Background: reveal, rotate, Unsplash sizing and consent.
	 * ------------------------------------------------------------------ */

	( function background() {
		var settings = config.background || {};
		var container = doc.querySelector( '.bk-bg' );
		var image = container ? container.querySelector( '.bk-bg__img' ) : null;
		var credit = doc.querySelector( '.bk-footer__credit' );
		var consentButton = doc.querySelector( '.bk-consent' );
		var items = null;
		var current = settings.photo || null;
		var position = -1;
		var timer = 0;
		var lastChange = Date.now();

		if ( ! container || ! image ) {
			return;
		}

		function reveal( element ) {
			var show = function () {
				if ( reducedMotion ) {
					element.style.transition = 'none';
				}

				element.classList.add( 'is-in' );
			};

			if ( element.decode ) {
				element.decode().then( show, function () {
					if ( element.complete && element.naturalWidth ) {
						show();
					}
				} );
			} else if ( element.complete ) {
				show();
			} else {
				element.addEventListener( 'load', show );
			}
		}

		function unsplashUrl( raw ) {
			var ratio = Math.min( window.devicePixelRatio || 1, 1.5 );
			var round = function ( value ) {
				return Math.min( 2560, Math.max( 256, Math.ceil( value * ratio / 256 ) * 256 ) );
			};
			var url;

			try {
				url = new URL( raw );
			} catch ( error ) {
				return '';
			}

			url.searchParams.set( 'w', round( window.innerWidth ) );
			url.searchParams.set( 'h', round( window.innerHeight ) );
			url.searchParams.set( 'fit', 'crop' );
			url.searchParams.set( 'crop', 'entropy' );
			url.searchParams.set( 'q', navigator.connection && navigator.connection.saveData ? '35' : '50' );
			url.searchParams.set( 'auto', 'format' );
			url.searchParams.set( 'cs', 'srgb' );

			return url.toString();
		}

		function setCredit( item ) {
			if ( ! credit ) {
				return;
			}

			var span = doc.createElement( 'span' );

			span.className = 'bk-credit';

			if ( settings.mode === 'unsplash' && item && item.name ) {
				var person = doc.createElement( 'a' );
				var site = doc.createElement( 'a' );
				var parts = String( i18n.photoBy || '%1$s / %2$s' ).split( /(%\d\$s)/ );

				person.href = item.profile;
				person.textContent = item.name;
				site.href = item.site;
				site.textContent = 'Unsplash';

				parts.forEach( function ( part ) {
					if ( part === '%1$s' ) {
						span.appendChild( person );
					} else if ( part === '%2$s' ) {
						span.appendChild( site );
					} else if ( part ) {
						span.appendChild( doc.createTextNode( part ) );
					}
				} );
			} else if ( item && item.caption ) {
				span.textContent = item.caption;
			}

			credit.textContent = '';
			credit.appendChild( span );
		}

		function show( item ) {
			var next = doc.createElement( 'img' );

			next.className = 'bk-bg__img';
			next.alt = '';
			next.decoding = 'async';

			if ( settings.mode === 'unsplash' ) {
				next.src = unsplashUrl( item.raw );
			} else {
				if ( item.srcset ) {
					next.srcset = item.srcset;
					next.sizes = item.sizes;
				}

				next.src = item.src;
			}

			if ( ! next.src ) {
				return;
			}

			var previous = image;
			var done = function () {
				if ( item.color ) {
					root.style.setProperty( '--bk-dominant', item.color );
				}

				next.classList.add( 'is-in' );
				setCredit( item );

				window.setTimeout( function () {
					if ( previous && previous.parentNode && previous !== next ) {
						previous.parentNode.removeChild( previous );
					}
				}, reducedMotion ? 0 : 900 );

				image = next;
				current = item;
			};

			if ( reducedMotion ) {
				next.style.transition = 'none';
			}

			container.appendChild( next );

			( next.decode ? next.decode() : Promise.resolve() ).then( done, function () {
				if ( next.parentNode ) {
					next.parentNode.removeChild( next );
				}
			} );
		}

		function load() {
			if ( items ) {
				return Promise.resolve( items );
			}

			return getJson( config.rest.backgrounds ).then( function ( data ) {
				items = data && Array.isArray( data.items ) ? data.items : [];

				return items;
			} );
		}

		function change() {
			lastChange = Date.now();

			load().then( function ( list ) {
				if ( ! list.length ) {
					return;
				}

				var index = Math.floor( Math.random() * list.length );

				if ( list.length > 1 && index === position ) {
					index = ( index + 1 ) % list.length;
				}

				position = index;
				show( list[ index ] );
			} ).catch( function () {} );
		}

		function schedule() {
			window.clearTimeout( timer );

			var minutes = parseInt( settings.interval, 10 ) || 0;

			if ( minutes <= 0 || doc.hidden ) {
				return;
			}

			var wait = Math.max( 1000, minutes * 60000 - ( Date.now() - lastChange ) );

			timer = window.setTimeout( function () {
				change();
				schedule();
			}, wait );
		}

		function canRotate() {
			return settings.mode === 'media' ? settings.count > 1 : settings.mode === 'unsplash' && settings.allowed;
		}

		// The first picture.
		if ( settings.mode === 'media' && image.getAttribute( 'src' ) ) {
			reveal( image );
		} else if ( settings.mode === 'unsplash' && settings.allowed ) {
			if ( settings.photo && settings.photo.raw ) {
				image.src = unsplashUrl( settings.photo.raw );
				image.hidden = false;
				reveal( image );
			} else {
				change();
			}
		}

		// A resized window gets a photo cut for its new size.
		if ( settings.mode === 'unsplash' && settings.allowed ) {
			var resizeTimer = 0;

			window.addEventListener( 'resize', function () {
				window.clearTimeout( resizeTimer );
				resizeTimer = window.setTimeout( function () {
					if ( current && current.raw ) {
						show( current );
					}
				}, 600 );
			} );
		}

		if ( canRotate() ) {
			schedule();

			doc.addEventListener( 'visibilitychange', function () {
				if ( doc.hidden ) {
					window.clearTimeout( timer );
				} else {
					schedule();
				}
			} );
		}

		/* Consent: add the category to whatever was allowed before. */

		if ( ! consentButton || settings.mode !== 'unsplash' ) {
			return;
		}

		var consent = config.consent || {};

		consentButton.hidden = false;

		function granted() {
			settings.allowed = true;
			consentButton.hidden = true;
			change();
			schedule();
		}

		consentButton.addEventListener( 'click', function () {
			consentButton.disabled = true;

			if ( ! consent.engine ) {
				doc.cookie = ( consent.cookie || 'baukasten_new_tab_unsplash' ) + '=1; max-age=31536000; path=/; SameSite=Lax' + ( window.location.protocol === 'https:' ? '; Secure' : '' );
				granted();

				return;
			}

			getJson( consent.state ).then( function ( state ) {
				var allowed = state && Array.isArray( state.allowed ) ? state.allowed.slice() : [];

				if ( allowed.indexOf( consent.category ) === -1 ) {
					allowed.push( consent.category );
				}

				return window.fetch( consent.save, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
					body: JSON.stringify( { categories: allowed } ),
				} );
			} ).then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}

				granted();
			} ).catch( function () {
				consentButton.disabled = false;
				consentButton.title = i18n.consentFail || '';
			} );
		} );
	}() );
}() );
