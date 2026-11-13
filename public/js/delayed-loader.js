/**
 * ChatterMate Chat: load the widget on the visitor's first interaction.
 *
 * The plugin sets window.chattermateWpLoader.src (the hosted widget loader) and
 * window.chattermateId before this runs. Nothing is fetched from ChatterMate until
 * the visitor scrolls, taps, moves the mouse, clicks or presses a key.
 */
( function () {
	'use strict';

	var settings = window.chattermateWpLoader;
	if ( ! settings || ! settings.src ) {
		return;
	}

	var events = [ 'scroll', 'mousemove', 'pointerdown', 'touchstart', 'keydown', 'wheel' ];
	var loaded = false;

	function load() {
		if ( loaded ) {
			return;
		}
		loaded = true;

		events.forEach( function ( name ) {
			window.removeEventListener( name, load, { passive: true } );
		} );

		var script = document.createElement( 'script' );
		script.src = settings.src;
		script.async = true;
		document.body.appendChild( script );
	}

	events.forEach( function ( name ) {
		window.addEventListener( name, load, { passive: true } );
	} );
}() );
