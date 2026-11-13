/**
 * ChatterMate Chat: the plugin's front-end helper.
 *
 * - With "Load the chat" on first interaction, adds the hosted widget script on the
 *   visitor's first scroll, tap, mouse move, click or key press. Nothing is fetched
 *   from ChatterMate before that.
 * - Opens the chat from the Chat button block, the [chattermate_button] shortcode and
 *   any link to #chattermate on the same page (for example a menu item), prefilling an
 *   optional message.
 * - Hands the widget the page details the plugin printed, plus the visitor's cart when
 *   they open the chat.
 *
 * The plugin sets window.chattermateWp = { src, lazy, context?, cartUrl? } and
 * window.chattermateId first.
 */
( function () {
	'use strict';

	var settings = window.chattermateWp;
	if ( ! settings || ! settings.src ) {
		return;
	}

	var LOAD_EVENTS = [ 'scroll', 'mousemove', 'pointerdown', 'touchstart', 'keydown', 'wheel' ];
	var WAIT_MS = 30000;
	var injected = false;
	var failed = false;
	var waiters = [];
	var polling = false;

	/* Loading ------------------------------------------------------------- */

	function stopListening() {
		LOAD_EVENTS.forEach( function ( name ) {
			window.removeEventListener( name, loadWidget, { passive: true } );
		} );
	}

	function loadWidget() {
		if ( injected || ! settings.lazy ) {
			return;
		}
		injected = true;
		stopListening();

		var script = document.createElement( 'script' );
		script.src = settings.src;
		script.async = true;
		script.onerror = function () {
			// Blocked (an ad blocker, a consent tool) or offline: drop what was waiting.
			failed = true;
			waiters = [];
		};
		document.body.appendChild( script );
	}

	function widgetReady() {
		return window.ChatterMate && 'function' === typeof window.ChatterMate.open;
	}

	// Runs fn( ChatterMate ) once the widget script has defined its API. One shared
	// poll serves every caller, so repeated clicks never stack up.
	function whenWidget( fn ) {
		if ( widgetReady() ) {
			fn( window.ChatterMate );
			return;
		}
		if ( failed ) {
			return;
		}
		waiters.push( fn );
		if ( polling ) {
			return;
		}
		polling = true;

		var started = Date.now();
		( function check() {
			if ( widgetReady() ) {
				polling = false;
				var queued = waiters;
				waiters = [];
				queued.forEach( function ( waiting ) {
					waiting( window.ChatterMate );
				} );
				return;
			}
			if ( failed || Date.now() - started > WAIT_MS ) {
				polling = false;
				waiters = [];
				return;
			}
			window.setTimeout( check, 150 );
		}() );
	}

	/* Opening ------------------------------------------------------------- */

	var pendingMessage = null;

	// The latest click wins: several clicks while the widget loads open it once.
	function openChat( message ) {
		var first = null === pendingMessage;
		pendingMessage = message;
		loadWidget();
		if ( ! first ) {
			return;
		}

		whenWidget( function ( widget ) {
			var text = pendingMessage;
			pendingMessage = null;
			widget.open( text ? { message: text } : undefined );
		} );
	}

	function isChatLink( link ) {
		return '#chattermate' === link.hash &&
			link.origin === window.location.origin &&
			link.pathname === window.location.pathname;
	}

	function opener( target ) {
		if ( ! target || 'function' !== typeof target.closest ) {
			return null;
		}

		var button = target.closest( '[data-chattermate-open]' );
		if ( button ) {
			return button;
		}

		var link = target.closest( 'a[href*="#chattermate"]' );
		return link && isChatLink( link ) ? link : null;
	}

	document.addEventListener( 'click', function ( event ) {
		var trigger = opener( event.target );
		if ( ! trigger ) {
			return;
		}

		event.preventDefault();
		openChat( trigger.getAttribute( 'data-chattermate-message' ) || '' );
	} );

	/* Page details -------------------------------------------------------- */

	// The visitor's cart, read in their own browser from the WooCommerce Store API
	// (never printed into the page, which a cache may serve to someone else).
	function readCart() {
		if ( ! settings.cartUrl || ! window.fetch ) {
			return Promise.resolve( {} );
		}

		return window.fetch( settings.cartUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } } )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( cart ) {
				if ( ! cart || ! cart.totals ) {
					return {};
				}
				var minor = parseInt( cart.totals.currency_minor_unit, 10 ) || 0;
				var items = ( cart.items || [] ).map( function ( item ) {
					return item.quantity + ' x ' + item.name;
				} ).join( '; ' );

				return {
					cart_item_count: cart.items_count || 0,
					cart_total: ( parseInt( cart.totals.total_price, 10 ) / Math.pow( 10, minor ) ).toFixed( minor ) + ' ' + cart.totals.currency_code,
					cart_items: items.slice( 0, 500 ),
				};
			} )
			.catch( function () {
				return {};
			} );
	}

	function merge( a, b ) {
		var out = {};
		[ a, b ].forEach( function ( source ) {
			Object.keys( source ).forEach( function ( key ) {
				out[ key ] = source[ key ];
			} );
		} );
		return out;
	}

	function shareContext( widget ) {
		var page = settings.context || {};
		if ( 'function' !== typeof widget.setContext ) {
			return;
		}
		widget.setContext( page );

		if ( ! settings.cartUrl || 'function' !== typeof widget.on ) {
			return;
		}
		// Read when the visitor opens the chat, so the cart is current.
		widget.on( 'open', function () {
			readCart().then( function ( cart ) {
				widget.setContext( merge( page, cart ) );
			} );
		} );
	}

	if ( settings.context ) {
		whenWidget( shareContext );
	}

	if ( settings.lazy ) {
		LOAD_EVENTS.forEach( function ( name ) {
			window.addEventListener( name, loadWidget, { passive: true } );
		} );
	}
}() );
