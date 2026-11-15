/**
 * Wishlist on the page: sorting, filtering and reporting a purchase.
 *
 * The server prints every wish with its state. This script shows the
 * controls, keeps the state fresh (a page cache may hold an old one), and
 * talks to the plugin's REST routes. The token that takes a report back is
 * kept in this browser's localStorage and nowhere else, and is sent back in a
 * request header, never in the address.
 *
 * Focus rule: nothing ever focuses an element inside a hidden card. When the
 * card that held the focus disappears, the focus moves to the toast's Undo
 * button or, failing that, to the status filter or the list's title.
 */
( function () {
	'use strict';

	var config = window.baukastenWishlist || {};
	var strings = config.i18n || {};
	var STORE_KEY = 'baukastenWishlist';
	var TOKEN_HEADER = 'X-Baukasten-Wishlist-Token';
	var LEAVE_MS = 250;
	var TOAST_MS = 4000;
	var TOAST_ACTION_MS = 10000;

	/* ---------- Helpers ---------- */

	function format( template ) {
		var args = Array.prototype.slice.call( arguments, 1 );

		return String( template || '' ).replace( /%(\d+)\$s|%s/g, function ( match, position ) {
			var index = position ? parseInt( position, 10 ) - 1 : 0;

			return undefined === args[ index ] ? match : String( args[ index ] );
		} );
	}

	function number( value ) {
		try {
			return new Intl.NumberFormat( document.documentElement.lang || undefined ).format( value );
		} catch ( error ) {
			return String( value );
		}
	}

	function reducedMotion() {
		return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	}

	function readStore() {
		try {
			var raw = window.localStorage.getItem( STORE_KEY );
			var parsed = raw ? JSON.parse( raw ) : {};

			return parsed && 'object' === typeof parsed ? parsed : {};
		} catch ( error ) {
			return {};
		}
	}

	function writeStore( store ) {
		try {
			window.localStorage.setItem( STORE_KEY, JSON.stringify( store ) );
		} catch ( error ) {
			// Private mode or blocked storage: the report stands, it just
			// cannot be taken back from this browser.
		}
	}

	function tokensFor( id ) {
		var store = readStore();
		var list = store[ id ];

		return Array.isArray( list ) ? list.filter( function ( token ) {
			return 'string' === typeof token && /^[a-f0-9]{32}$/.test( token );
		} ) : [];
	}

	function setTokens( id, tokens ) {
		var store = readStore();

		if ( tokens.length ) {
			store[ id ] = tokens;
		} else {
			delete store[ id ];
		}

		writeStore( store );
	}

	function request( method, path, extraHeaders ) {
		var headers = { Accept: 'application/json' };

		Object.keys( extraHeaders || {} ).forEach( function ( name ) {
			headers[ name ] = extraHeaders[ name ];
		} );

		return window.fetch( config.rest + path, {
			method: method,
			credentials: 'omit',
			cache: 'no-store',
			headers: headers
		} ).then( function ( response ) {
			return response.json().catch( function () {
				return {};
			} ).then( function ( body ) {
				if ( ! response.ok ) {
					var error = new Error( body && body.message ? body.message : 'HTTP ' + response.status );

					error.status = response.status;
					throw error;
				}

				return body;
			} );
		} );
	}

	/** Whether the keyboard, not a pointer, put the focus on an element. */
	function keyboardFocus( element ) {
		try {
			return !! element && element.matches( ':focus-visible' );
		} catch ( error ) {
			return !! element;
		}
	}

	/**
	 * Marks a button as working on a request, or done with it.
	 *
	 * `aria-disabled` rather than `disabled`: disabling the focused button
	 * would throw the focus back to the page's start.
	 */
	function setBusy( button, busy ) {
		if ( ! button ) {
			return;
		}

		if ( busy ) {
			button.setAttribute( 'aria-disabled', 'true' );
		} else {
			button.removeAttribute( 'aria-disabled' );
		}
	}

	function isBusy( button ) {
		return !! button && 'true' === button.getAttribute( 'aria-disabled' );
	}

	function isDone( item ) {
		return ( parseInt( item.getAttribute( 'data-remaining' ), 10 ) || 0 ) <= 0;
	}

	/**
	 * Whether an element can take the focus: present, and neither it nor a
	 * parent hidden or on its way out.
	 */
	function isShown( element ) {
		return !! element &&
			document.contains( element ) &&
			! element.disabled &&
			! element.closest( '[hidden]' ) &&
			! element.closest( '.is-leaving' );
	}

	/* ---------- One list ---------- */

	function Wishlist( root ) {
		this.root = root;
		this.listId = root.getAttribute( 'data-list' );
		this.items = Array.prototype.slice.call( root.querySelectorAll( '.baukasten-wl-item' ) );
		this.container = root.querySelector( '.baukasten-wl__items' );
		this.live = root.querySelector( '.baukasten-wl__live' );
		this.count = root.querySelector( '.baukasten-wl__count' );
		this.nothing = root.querySelector( '.baukasten-wl__nothing' );
		this.controls = root.querySelector( '.baukasten-wl__controls' );
		this.title = root.querySelector( '.baukasten-wl__title' );
		this.open = ! root.classList.contains( 'is-closed' );
		this.defaults = {
			sort: root.getAttribute( 'data-sort' ) || 'priority',
			status: root.getAttribute( 'data-status' ) || 'open',
			priority: '',
			price: ''
		};
		this.filters = {
			sort: this.defaults.sort,
			status: this.defaults.status,
			priority: '',
			price: ''
		};
		this.toast = null;
		this.toastItem = null;
		this.toastTimer = 0;
		this.toastHover = false;
		this.notice = null;

		this.readUrl();
		this.bind();
		this.items.forEach( this.paint.bind( this ) );
		this.apply();
		this.countdown();
		this.refresh();
	}

	Wishlist.prototype.param = function ( name ) {
		return 'wl' + this.listId + '_' + name;
	};

	Wishlist.prototype.readUrl = function () {
		var params;

		try {
			params = new URLSearchParams( window.location.search );
		} catch ( error ) {
			return;
		}

		[ 'sort', 'status', 'priority', 'price' ].forEach( function ( name ) {
			var value = params.get( this.param( name ) );

			if ( null !== value ) {
				this.filters[ name ] = value;
			}
		}, this );
	};

	Wishlist.prototype.writeUrl = function () {
		if ( ! window.history || ! window.history.replaceState ) {
			return;
		}

		try {
			var url = new URL( window.location.href );

			Object.keys( this.filters ).forEach( function ( name ) {
				if ( this.filters[ name ] === this.defaults[ name ] ) {
					url.searchParams.delete( this.param( name ) );
				} else {
					url.searchParams.set( this.param( name ), this.filters[ name ] );
				}
			}, this );

			window.history.replaceState( window.history.state, '', url.toString() );
		} catch ( error ) {
			// Nothing depends on the address.
		}
	};

	Wishlist.prototype.selects = function () {
		return this.controls ? Array.prototype.slice.call( this.controls.querySelectorAll( 'select[data-control]' ) ) : [];
	};

	/** Shows the filters in the selects. */
	Wishlist.prototype.syncSelects = function () {
		this.selects().forEach( function ( select ) {
			var value = this.filters[ select.getAttribute( 'data-control' ) ];

			if ( undefined !== value ) {
				select.value = value;
			}
		}, this );
	};

	Wishlist.prototype.bind = function () {
		var self = this;

		if ( this.controls ) {
			this.controls.hidden = false;

			if ( this.count ) {
				this.count.hidden = false;
			}

			this.selects().forEach( function ( select ) {
				var name = select.getAttribute( 'data-control' );
				var wanted = self.filters[ name ];

				// Only take a value from the address that the select offers.
				if ( Array.prototype.some.call( select.options, function ( option ) {
					return option.value === wanted;
				} ) ) {
					select.value = wanted;
				} else {
					self.filters[ name ] = select.value;
				}

				select.addEventListener( 'change', function () {
					self.filters[ name ] = select.value;
					self.apply();
					self.writeUrl();
				} );
			} );
		}

		this.root.addEventListener( 'click', function ( event ) {
			var button = event.target.closest ? event.target.closest( '[data-action]' ) : null;

			if ( ! button || ! self.root.contains( button ) ) {
				return;
			}

			var item = button.closest( '.baukasten-wl-item' );

			if ( item && ! isBusy( button ) ) {
				self.act( button.getAttribute( 'data-action' ), item, button );
			}
		} );
	};

	Wishlist.prototype.act = function ( action, item, button ) {
		var confirm = item.querySelector( '.baukasten-wl-item__confirm' );
		var ask = item.querySelector( '[data-action="ask"]' );

		if ( 'ask' === action && confirm ) {
			var yes = confirm.querySelector( '[data-action="claim"]' );

			this.showError( item, '' );
			confirm.hidden = false;
			ask.hidden = true;

			// The question opens where the button was. On a phone it can
			// still reach below the screen or under the toast; bring all of
			// it into view (its scroll margin leaves room for the toast).
			if ( confirm.scrollIntoView ) {
				confirm.scrollIntoView( { block: 'nearest', behavior: reducedMotion() ? 'auto' : 'smooth' } );
			}

			if ( isShown( yes ) ) {
				yes.focus( { preventScroll: true } );
			}

			return;
		}

		if ( 'cancel' === action && confirm ) {
			confirm.hidden = true;
			this.paint( item );
			this.focus( ask );

			return;
		}

		if ( 'claim' === action ) {
			this.showError( item, '' );
			this.claim( item, button );

			return;
		}

		if ( 'undo' === action ) {
			this.undo( item, button );
		}
	};

	/* ---------- Focus ---------- */

	/** Focuses an element if it can be focused, or the list's fallback. */
	Wishlist.prototype.focus = function ( element ) {
		if ( isShown( element ) ) {
			element.focus();

			return;
		}

		this.focusFallback();
	};

	/**
	 * Somewhere sensible outside every card: the status filter, else the title.
	 *
	 * @param {Object} options Passed to focus(); `{ preventScroll: true }`
	 *                         keeps the page where the visitor is reading.
	 */
	Wishlist.prototype.focusFallback = function ( options ) {
		var status = this.controls ? this.controls.querySelector( 'select[data-control="status"]' ) : null;

		if ( isShown( status ) ) {
			status.focus( options );

			return;
		}

		if ( this.title ) {
			this.title.setAttribute( 'tabindex', '-1' );
			this.title.focus( options );
		}
	};

	/* ---------- Reporting ---------- */

	/** Closes the question and repaints a card after a request. */
	Wishlist.prototype.settle = function ( item, button ) {
		var confirm = item.querySelector( '.baukasten-wl-item__confirm' );

		setBusy( button, false );

		if ( confirm ) {
			confirm.hidden = true;
		}

		this.paint( item );
	};

	/**
	 * Fades a card out, then applies the filters, which hide it.
	 *
	 * @param {HTMLElement} item  The card.
	 * @param {Function}    after Called once it is hidden.
	 */
	Wishlist.prototype.leave = function ( item, after ) {
		var self = this;

		item.classList.add( 'is-leaving' );

		window.setTimeout( function () {
			item.classList.remove( 'is-leaving' );
			self.apply();

			if ( after ) {
				after();
			}
		}, reducedMotion() ? 0 : LEAVE_MS );
	};

	Wishlist.prototype.claim = function ( item, button ) {
		var self = this;
		var id = item.getAttribute( 'data-id' );

		setBusy( button, true );

		request( 'POST', 'items/' + encodeURIComponent( id ) + '/claim' )
			.then( function ( body ) {
				if ( body.token ) {
					setTokens( id, tokensFor( id ).concat( [ body.token ] ) );
				}

				self.update( item, body );
				self.settle( item, button );

				if ( self.matches( item ) ) {
					self.apply();
					self.say( strings.thanks );
					self.focus( item.querySelector( '[data-action="undo"]' ) );

					return;
				}

				// The filter no longer shows it: the way back is the toast.
				self.say( strings.thanks, item );
				self.focus( self.toastAction() );
				self.leave( item );
			} )
			.catch( function ( error ) {
				// Sold out means nothing is left, whatever this page thought.
				if ( 409 === error.status ) {
					item.setAttribute( 'data-remaining', '0' );
				}

				self.settle( item, button );
				self.apply();
				self.fail( error, item );
				self.focus( item.querySelector( '[data-action="ask"]' ) );

				if ( 409 === error.status ) {
					self.refresh();
				}
			} );
	};

	Wishlist.prototype.undo = function ( item, button ) {
		var self = this;
		var id = item.getAttribute( 'data-id' );
		var tokens = tokensFor( id );
		var token = tokens[ tokens.length - 1 ];
		var headers = {};

		if ( ! token ) {
			this.paint( item );
			this.apply();

			return;
		}

		headers[ TOKEN_HEADER ] = token;
		setBusy( button, true );

		request( 'DELETE', 'items/' + encodeURIComponent( id ) + '/claim', headers )
			.then( function ( body ) {
				setTokens( id, tokens.slice( 0, -1 ) );
				self.update( item, body );
				self.say( strings.undone );
			} )
			.catch( function ( error ) {
				// Already gone, for instance reset by the list's owner: forget it.
				if ( 404 === error.status ) {
					setTokens( id, tokens.slice( 0, -1 ) );
					self.refresh();
				}

				self.fail( error, item );
			} )
			.then( function () {
				var focused = document.activeElement;

				setBusy( button, false );
				self.paint( item );

				if ( self.matches( item ) ) {
					self.apply();
					self.focus( item.querySelector( '[data-action="ask"]' ) || item.querySelector( '[data-action="undo"]' ) );

					return;
				}

				// Taken back under "Already bought": the card leaves this view,
				// and the focus must not leave with it. Only a keyboard user is
				// taken up to the filters; a mouse user stays where they are.
				if ( ! focused || focused === document.body || item.contains( focused ) ) {
					self.focusFallback( { preventScroll: ! keyboardFocus( focused ) } );
				}

				self.leave( item );
			} );
	};

	/**
	 * Shows or clears the error line of a card.
	 *
	 * @param {HTMLElement} item    The card.
	 * @param {string}      message Text; empty clears it.
	 */
	Wishlist.prototype.showError = function ( item, message ) {
		var line = item.querySelector( '.baukasten-wl-item__error' );

		if ( line ) {
			line.textContent = message || '';
			line.hidden = ! message;
		}
	};

	Wishlist.prototype.fail = function ( error, item ) {
		var message = strings.error;

		if ( 409 === error.status ) {
			message = strings.soldOut;
		} else if ( 429 === error.status ) {
			message = strings.tooMany;
		} else if ( 403 === error.status ) {
			message = strings.closed;
		}

		// In the card as well as in the toast: on a phone the toast can be
		// out of sight, and nothing else would say why nothing happened.
		if ( item ) {
			this.showError( item, message );
		}

		this.say( message );
	};

	/* ---------- Announcements and the toast ---------- */

	/**
	 * Announces a message, and shows it in the toast.
	 *
	 * @param {string}      message  Text.
	 * @param {HTMLElement} undoItem Card whose report the toast can take back.
	 */
	Wishlist.prototype.say = function ( message, undoItem ) {
		if ( ! message ) {
			return;
		}

		if ( this.live ) {
			this.live.textContent = '';

			// A new node, a moment later, so screen readers announce a repeat.
			window.setTimeout( function () {
				this.live.textContent = message;
			}.bind( this ), 50 );
		}

		this.showToast( message, undoItem || null );
	};

	/** Builds the toast once. */
	Wishlist.prototype.buildToast = function () {
		var self = this;
		var toast = document.createElement( 'div' );
		var text = document.createElement( 'p' );
		var action = document.createElement( 'button' );

		toast.className = 'baukasten-wl__toast';
		toast.hidden = true;

		// The live region already speaks the text; the button must stay
		// reachable, so only the text is hidden from assistive technology.
		text.className = 'baukasten-wl__toast-text';
		text.setAttribute( 'aria-hidden', 'true' );

		action.type = 'button';
		action.className = 'baukasten-wl__toast-action';
		action.textContent = strings.undo || 'Undo';
		action.hidden = true;

		toast.appendChild( text );
		toast.appendChild( action );
		this.root.appendChild( toast );

		action.addEventListener( 'click', function () {
			var item = self.toastItem;

			if ( isBusy( action ) ) {
				return;
			}

			// undo() moves the focus to the card once it is back.
			self.hideToast( true );

			if ( item ) {
				self.undo( item, action );
			}
		} );

		// Pause while the pointer or the keyboard focus is on the toast. A
		// focus that followed a mouse click does not count, or the toast
		// would never leave for a mouse user.
		toast.addEventListener( 'pointerenter', function ( event ) {
			if ( 'mouse' !== event.pointerType ) {
				return;
			}

			self.toastHover = true;
			window.clearTimeout( self.toastTimer );
		} );
		toast.addEventListener( 'pointerleave', function ( event ) {
			if ( 'mouse' !== event.pointerType ) {
				return;
			}

			self.toastHover = false;
			self.scheduleToast();
		} );
		toast.addEventListener( 'focusin', function () {
			if ( keyboardFocus( document.activeElement ) ) {
				window.clearTimeout( self.toastTimer );
			}
		} );
		toast.addEventListener( 'focusout', function () {
			window.setTimeout( function () {
				self.scheduleToast();
			}, 0 );
		} );

		this.toast = toast;
		this.toastText = text;
		this.toastButton = action;
	};

	Wishlist.prototype.toastAction = function () {
		return this.toast && ! this.toastButton.hidden ? this.toastButton : null;
	};

	Wishlist.prototype.showToast = function ( message, undoItem ) {
		if ( ! this.toast ) {
			this.buildToast();
		}

		var toast = this.toast;

		this.toastText.textContent = message;
		this.toastItem = undoItem;
		this.toastButton.hidden = ! undoItem;
		toast.classList.toggle( 'has-action', !! undoItem );
		toast.hidden = false;

		// Let the browser lay it out before the transition starts.
		void toast.offsetWidth;
		toast.classList.add( 'is-visible' );

		this.scheduleToast();
	};

	/** (Re)starts the countdown to hiding, unless the toast is in use. */
	Wishlist.prototype.scheduleToast = function () {
		var self = this;

		window.clearTimeout( this.toastTimer );

		if ( ! this.toast || this.toast.hidden || this.toastHover || ( this.toast.contains( document.activeElement ) && keyboardFocus( document.activeElement ) ) ) {
			return;
		}

		this.toastTimer = window.setTimeout( function () {
			self.hideToast();
		}, this.toastItem ? TOAST_ACTION_MS : TOAST_MS );
	};

	/**
	 * Hides the toast.
	 *
	 * @param {boolean} keepFocus True when the caller moves the focus itself.
	 */
	Wishlist.prototype.hideToast = function ( keepFocus ) {
		var toast = this.toast;

		if ( ! toast ) {
			return;
		}

		// The focus must not stay on a button that is about to vanish. A
		// mouse user, whose focus the script put there, simply loses it: the
		// page stays where it is. A keyboard user gets a visible focus back,
		// without a scroll, since the toast sat fixed over the reading position.
		if ( ! keepFocus && toast.contains( document.activeElement ) ) {
			if ( keyboardFocus( document.activeElement ) ) {
				this.focusFallback( { preventScroll: true } );
			} else {
				document.activeElement.blur();
			}
		}

		window.clearTimeout( this.toastTimer );
		toast.classList.remove( 'is-visible' );
		this.toastItem = null;

		window.setTimeout( function () {
			if ( ! toast.classList.contains( 'is-visible' ) ) {
				toast.hidden = true;
			}
		}, reducedMotion() ? 0 : 200 );
	};

	/* ---------- State ---------- */

	Wishlist.prototype.update = function ( item, state ) {
		if ( ! state || 'number' !== typeof state.remaining ) {
			return;
		}

		item.setAttribute( 'data-remaining', String( state.remaining ) );

		if ( 'number' === typeof state.quantity ) {
			item.setAttribute( 'data-quantity', String( state.quantity ) );
		}
	};

	Wishlist.prototype.paint = function ( item ) {
		var remaining = parseInt( item.getAttribute( 'data-remaining' ), 10 ) || 0;
		var quantity = parseInt( item.getAttribute( 'data-quantity' ), 10 ) || 1;
		var done = remaining <= 0;
		var status = item.querySelector( '.baukasten-wl-item__status' );
		var ask = item.querySelector( '[data-action="ask"]' );
		var undo = item.querySelector( '[data-action="undo"]' );
		var confirm = item.querySelector( '.baukasten-wl-item__confirm' );
		var asking = confirm && ! confirm.hidden;

		item.classList.toggle( 'is-done', done );

		if ( status ) {
			if ( done ) {
				status.textContent = strings.done;
			} else if ( 1 === quantity ) {
				status.textContent = '';
			} else {
				status.textContent = format( strings.remaining, number( remaining ), number( quantity ) );
			}

			status.hidden = '' === status.textContent;
		}

		if ( ask ) {
			ask.hidden = ! this.open || done || asking;
		}

		if ( undo ) {
			undo.hidden = ! this.open || 0 === tokensFor( item.getAttribute( 'data-id' ) ).length;
		}
	};

	/* ---------- Sorting and filtering ---------- */

	Wishlist.prototype.compare = function ( a, b ) {
		var sort = this.filters.sort;
		var result = 0;

		function attr( node, name ) {
			return node.getAttribute( name ) || '';
		}

		function num( node, name ) {
			var value = parseFloat( attr( node, name ) );

			return isNaN( value ) ? null : value;
		}

		var doneA = isDone( a );
		var doneB = isDone( b );

		// Showing everything, the bought ones go last in every order.
		if ( 'all' === this.filters.status && doneA !== doneB ) {
			return doneA ? 1 : -1;
		}

		if ( 'priority' === sort ) {
			result = num( a, 'data-priority' ) - num( b, 'data-priority' );
		} else if ( 'open' === sort ) {
			result = ( doneA ? 1 : 0 ) - ( doneB ? 1 : 0 );
			result = result || num( a, 'data-priority' ) - num( b, 'data-priority' );
		} else if ( 'price-asc' === sort || 'price-desc' === sort ) {
			var priceA = num( a, 'data-price' );
			var priceB = num( b, 'data-price' );

			if ( null === priceA || null === priceB ) {
				result = ( null === priceA ? 1 : 0 ) - ( null === priceB ? 1 : 0 );
			} else {
				result = 'price-asc' === sort ? priceA - priceB : priceB - priceA;
			}
		} else if ( 'title' === sort ) {
			result = attr( a, 'data-title' ).localeCompare( attr( b, 'data-title' ), document.documentElement.lang || undefined, { sensitivity: 'base' } );
		}

		return result || ( num( a, 'data-order' ) - num( b, 'data-order' ) ) || attr( a, 'data-title' ).localeCompare( attr( b, 'data-title' ) );
	};

	Wishlist.prototype.matches = function ( item ) {
		var done = isDone( item );
		var price = parseFloat( item.getAttribute( 'data-price' ) );

		if ( 'open' === this.filters.status && done ) {
			return false;
		}

		if ( 'done' === this.filters.status && ! done ) {
			return false;
		}

		if ( this.filters.priority && item.getAttribute( 'data-priority' ) !== this.filters.priority ) {
			return false;
		}

		if ( this.filters.price && ( isNaN( price ) || price > parseFloat( this.filters.price ) ) ) {
			return false;
		}

		return true;
	};

	Wishlist.prototype.apply = function () {
		if ( ! this.container ) {
			return;
		}

		// Re-appending a focused card drops its focus; note it to restore it.
		var active = document.activeElement;
		var activeItem = active && active.closest ? active.closest( '.baukasten-wl-item' ) : null;
		// Measured now: re-appending drops the focus, and with it :focus-visible.
		var activeByKeyboard = keyboardFocus( active );
		var sorted = this.items.slice().sort( this.compare.bind( this ) );
		var shown = 0;

		if ( activeItem && ! this.root.contains( activeItem ) ) {
			activeItem = null;
		}

		sorted.forEach( function ( item ) {
			var visible = this.matches( item );

			item.hidden = ! visible;
			shown += visible ? 1 : 0;
			this.container.appendChild( item );
		}, this );

		if ( activeItem ) {
			if ( activeItem.hidden ) {
				this.focusFallback( { preventScroll: ! activeByKeyboard } );
			} else if ( document.activeElement !== active && isShown( active ) ) {
				active.focus( { preventScroll: true } );
			}
		}

		if ( this.nothing ) {
			var everythingBought = 'open' === this.filters.status &&
				! this.filters.priority &&
				! this.filters.price &&
				this.items.length > 0 &&
				this.items.every( isDone );

			this.nothing.hidden = 0 !== shown;
			this.nothing.textContent = everythingBought && strings.allDone ? strings.allDone : strings.nothing;
		}

		if ( this.count ) {
			this.count.textContent = format( strings.shown, number( shown ), number( this.items.length ) );
		}

		this.updateNotice();
	};

	/* ---------- Returning guests ---------- */

	/**
	 * Points a returning guest to the wishes they reported, when the filter
	 * hides them.
	 */
	Wishlist.prototype.updateNotice = function () {
		var mine = this.items.filter( function ( item ) {
			return item.hidden && isDone( item ) && tokensFor( item.getAttribute( 'data-id' ) ).length > 0;
		} );

		if ( 0 === mine.length || ! this.container ) {
			if ( this.notice ) {
				if ( this.notice.contains( document.activeElement ) ) {
					this.focusFallback( { preventScroll: ! keyboardFocus( document.activeElement ) } );
				}

				this.notice.hidden = true;
			}

			return;
		}

		if ( ! this.notice ) {
			this.buildNotice();
		}

		this.noticeText.textContent = format( 1 === mine.length ? strings.mineOne : strings.mineOther, number( mine.length ) );
		this.notice.hidden = false;
	};

	Wishlist.prototype.buildNotice = function () {
		var self = this;
		var notice = document.createElement( 'p' );
		var text = document.createElement( 'span' );
		var show = document.createElement( 'button' );

		notice.className = 'baukasten-wl__mine';
		notice.hidden = true;
		show.type = 'button';
		show.className = 'baukasten-wl-link';
		show.textContent = strings.mineShow || '';

		notice.appendChild( text );
		notice.appendChild( document.createTextNode( ' ' ) );
		notice.appendChild( show );
		this.container.parentNode.insertBefore( notice, this.container );

		show.addEventListener( 'click', function () {
			// Every other filter is cleared too, so the reported wishes
			// really appear.
			self.filters.status = 'done';
			self.filters.priority = '';
			self.filters.price = '';
			self.syncSelects();
			self.apply();
			self.writeUrl();

			var first = self.items.filter( function ( item ) {
				return ! item.hidden && tokensFor( item.getAttribute( 'data-id' ) ).length > 0;
			} )[ 0 ];

			self.focus( first ? first.querySelector( '[data-action="undo"]' ) : null );
		} );

		this.notice = notice;
		this.noticeText = text;
	};

	/* ---------- Countdown and refresh ---------- */

	Wishlist.prototype.countdown = function () {
		var element = this.root.querySelector( '.baukasten-wl__countdown' );
		var date = this.root.getAttribute( 'data-date' );

		if ( ! element || ! /^\d{4}-\d{2}-\d{2}$/.test( date || '' ) ) {
			return;
		}

		// A cached page may be days old; count from the visitor's today.
		var parts = date.split( '-' );
		var target = new Date( +parts[ 0 ], +parts[ 1 ] - 1, +parts[ 2 ] );
		var now = new Date();
		var today = new Date( now.getFullYear(), now.getMonth(), now.getDate() );
		var days = Math.round( ( target - today ) / 86400000 );

		if ( days < 0 ) {
			element.hidden = true;
		} else if ( 0 === days ) {
			element.textContent = strings.today;
		} else if ( 1 === days ) {
			element.textContent = strings.tomorrow;
		} else {
			element.textContent = format( strings.daysLeft, number( days ) );
		}
	};

	Wishlist.prototype.refresh = function () {
		var self = this;

		if ( ! config.rest || ! this.listId ) {
			return;
		}

		request( 'GET', 'lists/' + encodeURIComponent( this.listId ) )
			.then( function ( body ) {
				var states = body && body.items ? body.items : {};

				self.open = !! body.open;
				self.root.classList.toggle( 'is-closed', ! self.open );

				self.items.forEach( function ( item ) {
					var state = states[ item.getAttribute( 'data-id' ) ];

					if ( state ) {
						self.update( item, state );
					}

					self.paint( item );
				} );

				self.apply();
			} )
			.catch( function () {
				// The printed state stays; reporting still works.
			} );
	};

	function start() {
		if ( ! window.fetch ) {
			return;
		}

		Array.prototype.forEach.call( document.querySelectorAll( '.baukasten-wl[data-list]' ), function ( root ) {
			if ( ! root.baukastenWishlist ) {
				root.baukastenWishlist = new Wishlist( root );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
