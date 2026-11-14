/**
 * ChatterMate Chat admin screen.
 *
 * - Shows the page picker only for "only on" and "except" rules.
 * - Filters the page list as you type.
 * - Loads the real widget on this screen when "Show live preview" is clicked.
 */
( function () {
	'use strict';

	function initRules() {
		var rules = document.querySelector( '[data-chattermate-rules]' );
		var modes = document.querySelectorAll( '[data-chattermate-mode]' );
		if ( ! rules || ! modes.length ) {
			return;
		}

		function sync() {
			var checked = document.querySelector( '[data-chattermate-mode]:checked' );
			rules.hidden = ! checked || 'all' === checked.value;
		}

		modes.forEach( function ( input ) {
			input.addEventListener( 'change', sync );
		} );
		sync();
	}

	function initPageFilter() {
		var filter = document.querySelector( '[data-chattermate-filter]' );
		var list = document.querySelector( '[data-chattermate-pages]' );
		var empty = document.querySelector( '[data-chattermate-empty]' );
		if ( ! filter || ! list ) {
			return;
		}

		filter.addEventListener( 'input', function () {
			var query = filter.value.trim().toLowerCase();
			var shown = 0;

			list.querySelectorAll( 'li' ).forEach( function ( item ) {
				var match = '' === query || -1 !== item.textContent.toLowerCase().indexOf( query );
				item.hidden = ! match;
				if ( match ) {
					shown++;
				}
			} );

			if ( empty ) {
				empty.hidden = 0 !== shown;
			}
		} );

		// Enter in the filter must not submit the settings form.
		filter.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
			}
		} );
	}

	// The signed-in admin's own token, when "Recognise signed-in customers" is on.
	function fetchToken( identity ) {
		if ( ! identity || ! identity.url || ! window.fetch ) {
			return Promise.resolve( '' );
		}
		var body = new window.FormData();
		body.append( 'nonce', identity.nonce );

		return window.fetch( identity.url, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( answer ) {
				return answer && answer.success && answer.data && answer.data.token ? answer.data.token : '';
			} )
			.catch( function () {
				return '';
			} );
	}

	function initPreview() {
		var button = document.querySelector( '[data-chattermate-preview]' );
		if ( ! button ) {
			return;
		}

		var identity = null;
		try {
			identity = JSON.parse( button.getAttribute( 'data-identity' ) || 'null' );
		} catch ( e ) {
			identity = null;
		}

		button.addEventListener( 'click', function () {
			if ( window.chattermateId ) {
				if ( window.ChatterMate && 'function' === typeof window.ChatterMate.open ) {
					window.ChatterMate.open();
				}
				return;
			}

			window.chattermateId = button.getAttribute( 'data-widget-id' );
			// Keep Cmd/Ctrl+K for the WordPress command palette on this screen.
			window.chattermateConfig = { hotkey: false };
			if ( button.getAttribute( 'data-api-url' ) ) {
				window.chattermateBaseUrl = button.getAttribute( 'data-api-url' );
			}
			button.disabled = true;

			// With signed-in customers on, the preview chats as you.
			fetchToken( identity ).then( function ( token ) {
				if ( token ) {
					window.chattermateToken = token;
				}

				var script = document.createElement( 'script' );
				script.src = button.getAttribute( 'data-script-url' );
				script.async = true;
				document.body.appendChild( script );

				var text = document.querySelector( '[data-chattermate-preview-text]' );
				if ( text ) {
					text.textContent = button.getAttribute( 'data-loaded-text' );
				}
				button.textContent = button.getAttribute( 'data-open-text' ) || button.textContent;
				button.disabled = false;
			} );
		} );
	}

	function initIdentity() {
		var toggle = document.querySelector( '[data-chattermate-identity]' );
		var fields = document.querySelector( '[data-chattermate-identity-fields]' );
		if ( ! toggle || ! fields ) {
			return;
		}

		toggle.addEventListener( 'change', function () {
			fields.hidden = ! toggle.checked;
		} );
	}

	function init() {
		initRules();
		initIdentity();
		initPageFilter();
		initPreview();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
