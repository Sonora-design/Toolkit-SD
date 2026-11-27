/**
 * Publieke preview: paneel in de editor en knop in de admin bar.
 * De link komt enkel terug van de server op het moment dat hij gemaakt wordt.
 */
( function () {
	'use strict';

	var cfg = window.SDE_PREVIEW;
	if ( ! cfg ) {
		return;
	}
	var text = cfg.text;

	function call( action, post, nonce, done ) {
		var body = new URLSearchParams();
		body.append( 'action', 'sde_preview' );
		body.append( 'do', action );
		body.append( 'post', post );
		body.append( 'nonce', nonce );
		fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				done( !! ( res && res.success ), ( res && res.data ) || {} );
			} )
			.catch( function () {
				done( false, {} );
			} );
	}

	function copy( value, ok, fail ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( value ).then( ok, fail );
		} else {
			fail();
		}
	}

	/* ---------- Paneel in de editor ---------- */

	function part( box, name ) {
		return box.querySelector( '[data-pp-' + name + ']' );
	}

	// url is enkel gevuld net nadat de link gemaakt is.
	function render( box, url, message ) {
		var active = '1' === box.getAttribute( 'data-active' );
		var expires = box.getAttribute( 'data-expires' ) || '';
		var line;
		if ( message ) {
			line = message;
		} else if ( ! active ) {
			line = text.off;
		} else if ( url ) {
			line = text.fresh.replace( '%s', expires );
		} else {
			line = text.on.replace( '%s', expires ) + ' ' + text.lost;
		}
		part( box, 'toggle' ).checked = active;
		part( box, 'state' ).textContent = line;
		part( box, 'link' ).hidden = ! ( active && url );
		part( box, 'url' ).value = url || '';
		part( box, 'copy' ).textContent = text.copy;
		part( box, 'actions' ).hidden = ! active;
	}

	function run( box, action ) {
		var controls = box.querySelectorAll( 'input[type="checkbox"], button' );
		controls.forEach( function ( el ) {
			el.disabled = true;
		} );
		part( box, 'state' ).textContent = text.busy;
		call( action, box.getAttribute( 'data-post' ), box.getAttribute( 'data-nonce' ), function ( ok, data ) {
			controls.forEach( function ( el ) {
				el.disabled = false;
			} );
			if ( 'active' in data ) {
				box.setAttribute( 'data-active', data.active ? '1' : '0' );
				box.setAttribute( 'data-expires', data.expires || '' );
			}
			render( box, ok ? data.url : '', ok ? '' : data.message || text.error );
		} );
	}

	document.querySelectorAll( '[data-sde-preview]' ).forEach( function ( box ) {
		render( box, '', '' );
	} );

	document.addEventListener( 'change', function ( e ) {
		if ( ! e.target.matches || ! e.target.matches( '[data-pp-toggle]' ) ) {
			return;
		}
		run( e.target.closest( '[data-sde-preview]' ), e.target.checked ? 'create' : 'disable' );
	} );

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '[data-pp-new], [data-pp-off], [data-pp-copy]' ) : null;
		if ( ! btn ) {
			return;
		}
		var box = btn.closest( '[data-sde-preview]' );
		if ( btn.hasAttribute( 'data-pp-copy' ) ) {
			var field = part( box, 'url' );
			copy(
				field.value,
				function () {
					btn.textContent = text.copied;
				},
				function () {
					field.focus();
					field.select();
				}
			);
		} else if ( btn.hasAttribute( 'data-pp-off' ) ) {
			run( box, 'disable' );
		} else if ( window.confirm( text.replace ) ) {
			run( box, 'create' );
		}
	} );

	/* ---------- Knop in de admin bar (previewpagina, ook van Elementor) ---------- */

	var bar = cfg.bar;
	var link = bar ? document.querySelector( '#wp-admin-bar-sde-preview > a' ) : null;
	if ( link ) {
		link.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( bar.active && ! window.confirm( text.replace ) ) {
				return;
			}
			link.textContent = text.busy;
			call( 'create', bar.post, bar.nonce, function ( ok, data ) {
				if ( ! ok || ! data.url ) {
					link.textContent = text.bar;
					window.alert( data.message || text.error );
					return;
				}
				bar.active = true;
				copy(
					data.url,
					function () {
						link.textContent = text.copied;
						window.setTimeout( function () {
							link.textContent = text.bar;
						}, 3000 );
					},
					function () {
						link.textContent = text.bar;
						window.prompt( text.manual, data.url );
					}
				);
			} );
		} );
	}
} )();
