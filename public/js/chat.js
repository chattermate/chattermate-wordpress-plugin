/**
 * ChatterMate Chat: the plugin's front-end helper.
 *
 * - With "Load the chat" on first interaction, adds the hosted widget script on the
 *   visitor's first scroll, tap, mouse move, click or key press. Nothing is fetched
 *   from ChatterMate before that.
 * - Opens the chat from the Chat button block, the [chattermate_button] shortcode and
 *   any link to #chattermate (for example a menu item), prefilling an optional message.
 *
 * The plugin sets window.chattermateWp = { src, lazy } and window.chattermateId first.
 */
( function () {
	'use strict';

	var settings = window.chattermateWp;
	if ( ! settings || ! settings.src ) {
		return;
	}

	var LOAD_EVENTS = [ 'scroll', 'mousemove', 'pointerdown', 'touchstart', 'keydown', 'wheel' ];
	var OPEN_WAIT_MS = 15000;
	var injected = false;

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
		document.body.appendChild( script );
	}

	// Calls ChatterMate.open() once the widget script has run; its open() queues
	// itself until the widget is ready.
	function openChat( message ) {
		loadWidget();

		var waited = 0;
		( function tryOpen() {
			if ( window.ChatterMate && 'function' === typeof window.ChatterMate.open ) {
				window.ChatterMate.open( message ? { message: message } : undefined );
				return;
			}
			waited += 100;
			if ( waited < OPEN_WAIT_MS ) {
				window.setTimeout( tryOpen, 100 );
			}
		}() );
	}

	function opener( target ) {
		if ( ! target || 'function' !== typeof target.closest ) {
			return null;
		}

		return target.closest( '[data-chattermate-open], a[href="#chattermate"]' );
	}

	document.addEventListener( 'click', function ( event ) {
		var trigger = opener( event.target );
		if ( ! trigger ) {
			return;
		}

		event.preventDefault();
		openChat( trigger.getAttribute( 'data-chattermate-message' ) || '' );
	} );

	if ( settings.lazy ) {
		LOAD_EVENTS.forEach( function ( name ) {
			window.addEventListener( name, loadWidget, { passive: true } );
		} );
	}
}() );
