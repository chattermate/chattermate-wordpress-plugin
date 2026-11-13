/**
 * ChatterMate Chat: the plugin's front-end helper.
 *
 * - Loads the hosted widget when the plugin leaves that to it: on the visitor's
 *   first scroll, tap, mouse move, click or key press ("Load the chat" on first
 *   interaction), and, for a signed-in customer, once their token is in.
 * - Signed-in customers: fetches the visitor's own token from admin-ajax.php (never
 *   printed into the page) and hands the widget a way to renew it.
 * - Opens the chat from the Chat button block, the [chattermate_button] shortcode and
 *   links to #chattermate on the same page, prefilling an optional message.
 * - Hands the widget the page details the plugin printed, plus the visitor's cart
 *   (read in their browser) when they open the chat.
 *
 * The plugin sets window.chattermateWp = { src, lazy, identity?, context?, cartUrl? }
 * and, when it leaves loading to this script, window.chattermateId first.
 */
( function () {
	'use strict';

	var settings = window.chattermateWp;
	if ( ! settings || ! settings.src ) {
		return;
	}

	var LOAD_EVENTS = [ 'scroll', 'mousemove', 'pointerdown', 'touchstart', 'keydown', 'wheel' ];
	var IDENTIFIED_KEY = 'chattermate_wp_identified';
	var WAIT_MS = 30000;
	// Whether this script adds the widget: on interaction, or after the token.
	var loadsWidget = !! ( settings.lazy || settings.identity );
	var injected = false;
	var failed = false;
	var waiters = [];
	var polling = false;

	/* Identity ------------------------------------------------------------ */

	function remember( key, value ) {
		try {
			if ( null === value ) {
				window.localStorage.removeItem( key );
			} else {
				window.localStorage.setItem( key, value );
			}
		} catch ( e ) {
			// No storage: nothing to remember or forget.
		}
	}

	var SESSION_KEY = 'chattermate_wp_token';
	// A kept token is reused between pages while it has this long left.
	var KEEP_MS = 60 * 60 * 1000;

	// The token kept for this user in this tab's session, so not every page view
	// asks the server again. Dropped as soon as the user or widget differs.
	function keptToken() {
		try {
			var kept = JSON.parse( window.sessionStorage.getItem( SESSION_KEY ) || 'null' );
			if ( kept && kept.user === settings.identity.user && kept.expires * 1000 - Date.now() > KEEP_MS ) {
				return kept.token;
			}
		} catch ( e ) {
			// No storage or a damaged entry: ask the server.
		}
		return '';
	}

	function keepToken( token, expires ) {
		try {
			if ( token && expires ) {
				window.sessionStorage.setItem( SESSION_KEY, JSON.stringify( { user: settings.identity.user, token: token, expires: expires } ) );
			} else {
				window.sessionStorage.removeItem( SESSION_KEY );
			}
		} catch ( e ) {
			// No storage: the server is asked on each page.
		}
	}

	// The signed-in visitor's own token, or '' (signed out, key refused, offline).
	// renew asks the server for a new one rather than the cached one, for when
	// ChatterMate refused the token the widget had.
	function fetchToken( renew ) {
		if ( ! settings.identity || ! window.fetch ) {
			return Promise.resolve( '' );
		}
		if ( ! renew ) {
			var kept = keptToken();
			if ( kept ) {
				return Promise.resolve( kept );
			}
		}
		var body = new window.FormData();
		body.append( 'nonce', settings.identity.nonce );
		if ( renew ) {
			body.append( 'renew', '1' );
		}

		return window.fetch( settings.identity.url, { method: 'POST', credentials: 'same-origin', cache: 'no-store', body: body } )
			.then( function ( response ) {
				// A refusal still carries the next nonce, so read it either way.
				return response.json().catch( function () {
					return null;
				} );
			} )
			.then( function ( answer ) {
				// Every answer brings the nonce for the next request (renewals).
				if ( answer && answer.data && 'string' === typeof answer.data.nonce ) {
					settings.identity.nonce = answer.data.nonce;
				}
				var token = answer && answer.success && answer.data && 'string' === typeof answer.data.token ? answer.data.token : '';
				keepToken( token, token && answer.data.expires );
				return token;
			} )
			.catch( function () {
				return '';
			} );
	}

	// Ready the widget's identity before it loads. Without a token, a token left
	// by an earlier signed-in visitor in this browser is forgotten, so nobody
	// chats as someone else.
	function prepareIdentity() {
		if ( ! settings.identity ) {
			return Promise.resolve();
		}

		return fetchToken().then( function ( token ) {
			if ( token ) {
				window.chattermateToken = token;
				remember( IDENTIFIED_KEY, '1' );
				// The widget asks again when the token runs out mid-chat.
				window.chattermateConfig = window.chattermateConfig || {};
				window.chattermateConfig.tokenProvider = function () {
					return fetchToken( true );
				};
				return;
			}

			try {
				if ( window.localStorage.getItem( IDENTIFIED_KEY ) ) {
					remember( 'ctid', null );
					remember( IDENTIFIED_KEY, null );
				}
			} catch ( e ) {
				// No storage: no stored token either.
			}
		} );
	}

	/* Loading ------------------------------------------------------------- */

	function stopListening() {
		LOAD_EVENTS.forEach( function ( name ) {
			window.removeEventListener( name, onFirstInteraction, { passive: true } );
		} );
	}

	function injectScript() {
		var script = document.createElement( 'script' );
		script.src = settings.src;
		script.async = true;
		script.onerror = function () {
			// Blocked (an ad blocker, a consent tool) or offline: drop what was waiting.
			failed = true;
			giveUp();
		};
		document.body.appendChild( script );

		// Whatever waited for the widget can start waiting for it now.
		if ( waiters.length && ! polling ) {
			startPolling();
		}
	}

	function loadWidget() {
		if ( injected || ! loadsWidget ) {
			return;
		}
		injected = true;
		stopListening();
		prepareIdentity().then( injectScript );
	}

	function onFirstInteraction() {
		loadWidget();
	}

	function widgetReady() {
		return window.ChatterMate && 'function' === typeof window.ChatterMate.open;
	}

	// Runs fn( ChatterMate ) once the widget script has defined its API. One shared
	// poll serves every caller, and it only runs once the widget is on its way: at
	// once when the plugin enqueued it, else from injectScript().
	function whenWidget( fn ) {
		if ( widgetReady() ) {
			fn( window.ChatterMate );
			return;
		}
		if ( failed ) {
			return;
		}
		waiters.push( fn );
		if ( polling || ( loadsWidget && ! injected ) ) {
			return;
		}
		startPolling();
	}

	function startPolling() {
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
				giveUp();
				return;
			}
			window.setTimeout( check, 150 );
		}() );
	}

	/* Opening ------------------------------------------------------------- */

	var pendingMessage = null;

	// The widget never came: forget what waited, so a later click starts afresh.
	function giveUp() {
		waiters = [];
		pendingMessage = null;
	}

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

	// Store API names are HTML-encoded (&amp;, &#8211;): read them as text.
	function decode( html ) {
		var area = document.createElement( 'textarea' );
		area.innerHTML = String( html );
		return area.value;
	}

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
				if ( ! cart || ! cart.totals || ! cart.items_count ) {
					return { cart_item_count: 0 };
				}
				var minor = parseInt( cart.totals.currency_minor_unit, 10 ) || 0;
				var items = ( cart.items || [] ).map( function ( item ) {
					return item.quantity + ' x ' + decode( item.name );
				} ).join( '; ' );

				return {
					cart_item_count: cart.items_count,
					cart_total: ( parseInt( cart.totals.total_price, 10 ) / Math.pow( 10, minor ) ).toFixed( minor ) + ' ' + cart.totals.currency_code,
					cart_items: items.slice( 0, 300 ),
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
		var latest = 0;
		if ( 'function' !== typeof widget.setContext ) {
			return;
		}
		widget.setContext( page );

		if ( ! settings.cartUrl || 'function' !== typeof widget.on ) {
			return;
		}
		// Read when the visitor opens the chat, so the cart is current. Only the
		// newest read is applied, whichever answers first.
		widget.on( 'open', function () {
			var ask = ++latest;
			readCart().then( function ( cart ) {
				if ( ask === latest ) {
					widget.setContext( merge( page, cart ) );
				}
			} );
		} );
	}

	/* Start --------------------------------------------------------------- */

	if ( settings.context ) {
		whenWidget( shareContext );
	}

	if ( settings.lazy ) {
		LOAD_EVENTS.forEach( function ( name ) {
			window.addEventListener( name, onFirstInteraction, { passive: true } );
		} );
	} else if ( settings.identity ) {
		loadWidget();
	}
}() );
