/**
 * Wishlist on the page: sorting, filtering and reporting a purchase.
 *
 * The server prints every wish with its state. This script shows the
 * controls, keeps the state fresh (a page cache may hold an old one), and
 * talks to the plugin's REST routes. The token that takes a report back is
 * kept in this browser's localStorage and nowhere else.
 */
( function () {
	'use strict';

	var config = window.baukastenWishlist || {};
	var strings = config.i18n || {};
	var STORE_KEY = 'baukastenWishlist';

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

	function request( method, path ) {
		return window.fetch( config.rest + path, {
			method: method,
			credentials: 'omit',
			cache: 'no-store',
			headers: { Accept: 'application/json' }
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
		this.open = ! root.classList.contains( 'is-closed' );
		this.filters = {
			sort: root.getAttribute( 'data-sort' ) || 'priority',
			status: root.getAttribute( 'data-status' ) || 'all',
			priority: '',
			price: ''
		};

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
			var defaults = {
				sort: this.root.getAttribute( 'data-sort' ) || 'priority',
				status: this.root.getAttribute( 'data-status' ) || 'all',
				priority: '',
				price: ''
			};

			Object.keys( this.filters ).forEach( function ( name ) {
				if ( this.filters[ name ] === defaults[ name ] ) {
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

	Wishlist.prototype.bind = function () {
		var self = this;

		if ( this.controls ) {
			this.controls.hidden = false;

			Array.prototype.forEach.call( this.controls.querySelectorAll( 'select[data-control]' ), function ( select ) {
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

			if ( item ) {
				self.act( button.getAttribute( 'data-action' ), item, button );
			}
		} );
	};

	Wishlist.prototype.act = function ( action, item, button ) {
		var confirm = item.querySelector( '.baukasten-wl-item__confirm' );
		var ask = item.querySelector( '[data-action="ask"]' );

		if ( 'ask' === action && confirm ) {
			confirm.hidden = false;
			ask.hidden = true;

			var yes = confirm.querySelector( '[data-action="claim"]' );

			if ( yes ) {
				yes.focus();
			}

			return;
		}

		if ( 'cancel' === action && confirm ) {
			confirm.hidden = true;
			this.paint( item );

			if ( ask && ! ask.hidden ) {
				ask.focus();
			}

			return;
		}

		if ( 'claim' === action ) {
			this.claim( item, button );

			return;
		}

		if ( 'undo' === action ) {
			this.undo( item, button );
		}
	};

	Wishlist.prototype.claim = function ( item, button ) {
		var self = this;
		var id = item.getAttribute( 'data-id' );

		button.disabled = true;

		request( 'POST', 'items/' + encodeURIComponent( id ) + '/claim' )
			.then( function ( body ) {
				if ( body.token ) {
					setTokens( id, tokensFor( id ).concat( [ body.token ] ) );
				}

				self.update( item, body );
				self.say( strings.thanks );
			} )
			.catch( function ( error ) {
				self.fail( error );

				if ( 409 === error.status ) {
					self.refresh();
				}
			} )
			.then( function () {
				button.disabled = false;

				var confirm = item.querySelector( '.baukasten-wl-item__confirm' );

				if ( confirm ) {
					confirm.hidden = true;
				}

				self.paint( item );
				self.apply();

				var undo = item.querySelector( '[data-action="undo"]' );

				if ( undo && ! undo.hidden ) {
					undo.focus();
				}
			} );
	};

	Wishlist.prototype.undo = function ( item, button ) {
		var self = this;
		var id = item.getAttribute( 'data-id' );
		var tokens = tokensFor( id );
		var token = tokens[ tokens.length - 1 ];

		if ( ! token ) {
			this.paint( item );

			return;
		}

		button.disabled = true;

		request( 'DELETE', 'items/' + encodeURIComponent( id ) + '/claim?token=' + encodeURIComponent( token ) )
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

				self.fail( error );
			} )
			.then( function () {
				button.disabled = false;
				self.paint( item );
				self.apply();

				var ask = item.querySelector( '[data-action="ask"]' );

				if ( ask && ! ask.hidden ) {
					ask.focus();
				}
			} );
	};

	Wishlist.prototype.fail = function ( error ) {
		var message = strings.error;

		if ( 409 === error.status ) {
			message = strings.soldOut;
		} else if ( 429 === error.status ) {
			message = strings.tooMany;
		} else if ( 403 === error.status ) {
			message = strings.closed;
		}

		this.say( message );
	};

	Wishlist.prototype.say = function ( message ) {
		if ( ! this.live || ! message ) {
			return;
		}

		this.live.textContent = '';

		// A new node, a moment later, so screen readers announce a repeat.
		window.setTimeout( function () {
			this.live.textContent = message;
		}.bind( this ), 50 );

		var toast = this.root.querySelector( '.baukasten-wl__toast' );

		if ( ! toast ) {
			toast = document.createElement( 'p' );
			toast.className = 'baukasten-wl__toast';
			toast.setAttribute( 'aria-hidden', 'true' );
			this.root.appendChild( toast );
		}

		toast.textContent = message;
		toast.classList.add( 'is-visible' );

		window.clearTimeout( this.toastTimer );
		this.toastTimer = window.setTimeout( function () {
			toast.classList.remove( 'is-visible' );
		}, 4000 );
	};

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
				status.textContent = strings.oneLeft;
			} else {
				status.textContent = format( strings.remaining, number( remaining ), number( quantity ) );
			}
		}

		if ( ask ) {
			ask.hidden = ! this.open || done || asking;
		}

		if ( undo ) {
			undo.hidden = ! this.open || 0 === tokensFor( item.getAttribute( 'data-id' ) ).length;
		}
	};

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

		var doneA = num( a, 'data-remaining' ) <= 0;
		var doneB = num( b, 'data-remaining' ) <= 0;

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
		var done = ( parseInt( item.getAttribute( 'data-remaining' ), 10 ) || 0 ) <= 0;
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

		var sorted = this.items.slice().sort( this.compare.bind( this ) );
		var shown = 0;

		sorted.forEach( function ( item ) {
			var visible = this.matches( item );

			item.hidden = ! visible;
			shown += visible ? 1 : 0;
			this.container.appendChild( item );
		}, this );

		if ( this.nothing ) {
			this.nothing.hidden = 0 !== shown;
		}

		if ( this.count ) {
			this.count.textContent = format( strings.shown, number( shown ), number( this.items.length ) );
		}
	};

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
