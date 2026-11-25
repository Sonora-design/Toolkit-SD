/* Sonora design enhancements — beheerpagina */
( function ( $ ) {
	'use strict';

	var cfg  = window.SDE || {};
	var form = document.getElementById( 'sde-app' );
	if ( ! form ) {
		return;
	}

	var $form   = $( form );
	var status  = form.querySelector( '[data-status]' );
	var saveBtn = form.querySelector( '[data-save]' );
	var toastEl = document.querySelector( '[data-toast]' );
	var dirty   = false;

	/* ---------- melding en status ---------- */

	var toastTimer;
	function toast( msg, isError ) {
		if ( ! toastEl ) {
			return;
		}
		toastEl.textContent = msg;
		toastEl.classList.toggle( 'is-error', !! isError );
		toastEl.hidden = false;
		clearTimeout( toastTimer );
		toastTimer = setTimeout( function () {
			toastEl.hidden = true;
		}, 4000 );
	}

	function setDirty( on ) {
		dirty = on;
		status.classList.remove( 'is-error' );
		status.classList.toggle( 'is-dirty', on );
		status.textContent = on ? 'Niet opgeslagen wijzigingen' : 'Alles opgeslagen';
	}

	form.addEventListener( 'input', function ( e ) {
		if ( ! e.target.closest( '[data-ignore]' ) && ! e.target.matches( '[data-menu-search], [data-only-rules]' ) ) {
			setDirty( true );
		}
	} );
	form.addEventListener( 'change', function ( e ) {
		if ( ! e.target.closest( '[data-ignore]' ) && ! e.target.matches( '[data-menu-search], [data-only-rules]' ) ) {
			setDirty( true );
		}
	} );
	window.addEventListener( 'beforeunload', function ( e ) {
		if ( dirty ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );

	/* ---------- tabbladen ---------- */

	var tabs  = form.querySelectorAll( '[data-tab]' );
	var panes = form.querySelectorAll( '[data-pane]' );

	function showTab( key ) {
		var found = false;
		tabs.forEach( function ( t ) {
			var on = t.getAttribute( 'data-tab' ) === key;
			t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			found = found || on;
		} );
		if ( ! found ) {
			return showTab( 'overzicht' );
		}
		panes.forEach( function ( p ) {
			p.hidden = p.getAttribute( 'data-pane' ) !== key;
		} );
		if ( history.replaceState ) {
			history.replaceState( null, '', '#' + key );
		}
		if ( 'login' === key ) {
			loadPreview();
		}
	}

	tabs.forEach( function ( t ) {
		t.addEventListener( 'click', function () {
			showTab( t.getAttribute( 'data-tab' ) );
		} );
	} );
	$form.on( 'click', '[data-goto]', function () {
		showTab( this.getAttribute( 'data-goto' ) );
		window.scrollTo( 0, 0 );
	} );

	/* ---------- moduleschakelaars (overzicht <-> tabblad) ---------- */

	function syncModule( key, on ) {
		form.querySelectorAll( '[data-module="' + key + '"], [data-mirror="' + key + '"]' ).forEach( function ( el ) {
			el.checked = on;
		} );
		var card = form.querySelector( '[data-card="' + key + '"]' );
		if ( card ) {
			card.classList.toggle( 'is-on', on );
		}
	}
	form.querySelectorAll( '[data-module]' ).forEach( function ( el ) {
		syncModule( el.getAttribute( 'data-module' ), el.checked );
	} );
	$form.on( 'change', '[data-module], [data-mirror]', function () {
		syncModule( this.getAttribute( 'data-module' ) || this.getAttribute( 'data-mirror' ), this.checked );
	} );

	/* ---------- velden tonen als ... ---------- */

	function updateShowIf() {
		form.querySelectorAll( '[data-show-if]' ).forEach( function ( el ) {
			var parts = el.getAttribute( 'data-show-if' ).split( '=' );
			var input = form.querySelector( '[name="sde[' + parts[ 0 ] + ']"]:checked' );
			el.hidden = ! input || input.value !== parts[ 1 ];
		} );
	}
	form.addEventListener( 'change', updateShowIf );
	updateShowIf();

	/* ---------- kleuren ---------- */

	$form.on( 'input', 'input[type="color"][data-color-for]', function () {
		var t = document.getElementById( this.getAttribute( 'data-color-for' ) );
		if ( t ) {
			t.value = this.value;
			$( t ).trigger( 'input' );
		}
	} );
	$form.on( 'input', '.sde-color input[type="text"]', function () {
		if ( /^#[0-9a-f]{6}$/i.test( this.value ) ) {
			var c = form.querySelector( '[data-color-for="' + this.id + '"]' );
			if ( c ) {
				c.value = this.value;
			}
		}
	} );
	$form.on( 'click', '[data-colors-reset]', function () {
		[ 'login_color_accent', 'login_color_hover', 'login_color_ink', 'login_bg_top', 'login_bg_bottom', 'login_radius' ].forEach( function ( k ) {
			var el = document.getElementById( 'sde-' + k );
			if ( el && cfg.defaults ) {
				el.value = cfg.defaults[ k ];
				$( el ).trigger( 'input' );
			}
		} );
		setDirty( true );
	} );

	/* ---------- logo ---------- */

	var logoInput   = form.querySelector( '[data-logo-input]' );
	var logoPreview = form.querySelector( '[data-logo-preview]' );
	var mediaFrame;

	function setLogo( url ) {
		logoInput.value = url;
		logoPreview.style.backgroundImage = 'url("' + ( url || cfg.logo ) + '")';
		setDirty( true );
		livePreview();
	}
	$form.on( 'click', '[data-logo-pick]', function () {
		if ( ! window.wp || ! wp.media ) {
			return;
		}
		if ( ! mediaFrame ) {
			mediaFrame = wp.media( {
				title: 'Logo voor de loginpagina',
				button: { text: 'Dit logo gebruiken' },
				library: { type: 'image' },
				multiple: false
			} );
			mediaFrame.on( 'select', function () {
				var a = mediaFrame.state().get( 'selection' ).first().toJSON();
				setLogo( a.url );
			} );
		}
		mediaFrame.open();
	} );
	$form.on( 'click', '[data-logo-reset]', function () {
		setLogo( '' );
	} );

	/* ---------- beheermenu ---------- */

	$form.on( 'change', '[data-menu-mode]', function () {
		var row  = this.closest( 'tr' );
		var cell = row.querySelector( '.sde-roles-cell' );
		var need = 'except' === this.value || 'only' === this.value;
		if ( need ) {
			cell.removeAttribute( 'data-off' );
		} else {
			cell.setAttribute( 'data-off', '' );
		}
		row.classList.toggle( 'is-hidden-rule', 'visible' !== this.value );
	} );

	function filterMenu() {
		var q    = ( form.querySelector( '[data-menu-search]' ).value || '' ).toLowerCase();
		var only = form.querySelector( '[data-only-rules]' ).checked;
		form.querySelectorAll( '[data-menu-row]' ).forEach( function ( row ) {
			var text = row.querySelector( 'th' ).textContent.toLowerCase();
			var show = ( ! q || -1 !== text.indexOf( q ) ) && ( ! only || row.classList.contains( 'is-hidden-rule' ) );
			row.hidden = ! show;
		} );
	}
	$form.on( 'input', '[data-menu-search]', filterMenu );
	$form.on( 'change', '[data-only-rules]', filterMenu );

	/* ---------- opslaan ---------- */

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		var data = new FormData( form );
		data.append( 'action', 'sde_save' );
		data.append( 'nonce', cfg.nonce );
		saveBtn.disabled = true;
		status.textContent = 'Opslaan…';
		fetch( cfg.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( r ) {
				if ( ! r || ! r.success ) {
					throw new Error( r && r.data && r.data.message ? r.data.message : 'Opslaan mislukt.' );
				}
				setDirty( false );
				toast( 'Instellingen opgeslagen.' );
				reloadPreview();
			} )
			.catch( function ( err ) {
				status.textContent = err.message;
				status.classList.add( 'is-error' );
				toast( err.message, true );
			} )
			.then( function () {
				saveBtn.disabled = false;
			} );
	} );

	function post( action, extra ) {
		var data = new FormData();
		data.append( 'action', action );
		data.append( 'nonce', cfg.nonce );
		Object.keys( extra || {} ).forEach( function ( k ) {
			data.append( k, extra[ k ] );
		} );
		return fetch( cfg.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' } ).then( function ( r ) {
			return r.json();
		} );
	}

	function esc( s ) {
		return String( s ).replace( /[&<>"]/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ];
		} );
	}

	/* ---------- REST testen ---------- */

	$form.on( 'click', '[data-rest-test]', function () {
		var btn = this;
		var out = form.querySelector( '[data-rest-result]' );
		if ( dirty ) {
			toast( 'Let op: de test gebruikt de opgeslagen instellingen.' );
		}
		btn.disabled = true;
		out.textContent = 'Bezig…';
		post( 'sde_rest_test' ).then( function ( r ) {
			var rows = ( r && r.data ) || [];
			var html = '<table class="sde-table sde-result"><thead><tr><th>Route</th><th>Resultaat voor een bezoeker</th></tr></thead><tbody>';
			rows.forEach( function ( row ) {
				var label = 200 === row.code ? 'open (200)' : ( 401 === row.code || 403 === row.code ? 'afgeschermd (' + row.code + ')' : ( row.code ? 'code ' + row.code : 'geen antwoord' ) );
				var cls   = 200 === row.code ? 'is-ok' : ( 401 === row.code || 403 === row.code ? 'is-closed' : 'is-err' );
				html += '<tr><th>' + esc( row.route ) + '</th><td class="' + cls + '">' + label + '</td></tr>';
			} );
			out.innerHTML = html + '</tbody></table>';
		} ).catch( function () {
			out.textContent = 'De test is mislukt.';
		} ).then( function () {
			btn.disabled = false;
		} );
	} );

	/* ---------- overstap ASE ---------- */

	$form.on( 'click', '[data-ase]', function () {
		var btn = this;
		var act = btn.getAttribute( 'data-ase' );
		var out = form.querySelector( '[data-ase-result]' );
		var msg = btn.getAttribute( 'data-confirm' );
		if ( msg && ! window.confirm( msg ) ) {
			return;
		}
		btn.disabled = true;
		post( 'sde_ase', { do: act } ).then( function ( r ) {
			if ( ! r || ! r.success ) {
				throw new Error( r && r.data && r.data.message ? r.data.message : 'Mislukt.' );
			}
			if ( 'preview' === act ) {
				var d = r.data;
				var html = '<div class="sde-report"><h4>Wordt overgenomen</h4><ul>';
				( d.report.length ? d.report : [ 'Niets gevonden in ASE.' ] ).forEach( function ( x ) {
					html += '<li>' + esc( x ) + '</li>';
				} );
				html += '</ul>';
				if ( d.not_covered && d.not_covered.length ) {
					html += '<h4>Niet vervangen door deze plugin</h4><ul>';
					d.not_covered.forEach( function ( x ) {
						html += '<li>' + esc( x ) + '</li>';
					} );
					html += '</ul>';
				}
				out.innerHTML = html + '</div>';
				return;
			}
			toast( r.data.message );
			out.innerHTML = '<div class="sde-report">' + esc( r.data.message ) + '</div>';
			if ( 'import' === act ) {
				dirty = false;
				setTimeout( function () {
					window.location.reload();
				}, 1200 );
			}
		} ).catch( function ( err ) {
			toast( err.message, true );
		} ).then( function () {
			btn.disabled = false;
		} );
	} );

	/* ---------- voorbeeld loginpagina ---------- */

	var frame   = form.querySelector( '[data-preview-frame]' );
	var stage   = form.querySelector( '[data-stage]' );
	var box     = form.querySelector( '[data-screenbox]' );
	var device  = 'desktop';
	var screen  = 'login';
	var loaded  = false;
	var sizes   = { desktop: [ 1280, 800 ], phone: [ 375, 812 ] };

	function val( k ) {
		var el = form.querySelector( '[name="sde[' + k + ']"]:not([type="hidden"])' ) || form.querySelector( '[name="sde[' + k + ']"]' );
		if ( ! el ) {
			return '';
		}
		if ( 'checkbox' === el.type ) {
			return el.checked;
		}
		return el.value;
	}

	function rgb( hex ) {
		hex = String( hex || '' ).replace( '#', '' );
		if ( 3 === hex.length ) {
			hex = hex[ 0 ] + hex[ 0 ] + hex[ 1 ] + hex[ 1 ] + hex[ 2 ] + hex[ 2 ];
		}
		if ( ! /^[0-9a-f]{6}$/i.test( hex ) ) {
			return [ 0, 0, 0 ];
		}
		return [ parseInt( hex.substr( 0, 2 ), 16 ), parseInt( hex.substr( 2, 2 ), 16 ), parseInt( hex.substr( 4, 2 ), 16 ) ];
	}
	function rgba( hex, a ) {
		var c = rgb( hex );
		return 'rgba(' + c[ 0 ] + ', ' + c[ 1 ] + ', ' + c[ 2 ] + ', ' + a + ')';
	}
	function mix( a, b, t ) {
		var x = rgb( a );
		var y = rgb( b );
		return '#' + [ 0, 1, 2 ].map( function ( i ) {
			var v = Math.round( x[ i ] + ( y[ i ] - x[ i ] ) * t );
			return ( v < 16 ? '0' : '' ) + v.toString( 16 );
		} ).join( '' );
	}

	function previewCss() {
		var w = Math.max( 60, parseInt( val( 'login_logo_width' ), 10 ) || 420 );
		var h = Math.max( 20, parseInt( val( 'login_logo_height' ), 10 ) || 112 );
		var accent = val( 'login_color_accent' );
		var ink    = val( 'login_color_ink' );
		var top    = val( 'login_bg_top' );
		var bottom = val( 'login_bg_bottom' );
		var vars = {
			'--sde-accent': accent,
			'--sde-accent-hover': val( 'login_color_hover' ),
			'--sde-accent-18': rgba( accent, '.18' ),
			'--sde-accent-35': rgba( accent, '.35' ),
			'--sde-ink': ink,
			'--sde-ink-60': rgba( ink, '.6' ),
			'--sde-ink-22': rgba( ink, '.22' ),
			'--sde-bg-top': top,
			'--sde-bg-top-60': rgba( top, '.6' ),
			'--sde-bg-top-0': rgba( top, '0' ),
			'--sde-bg-mid': mix( top, bottom, 0.465 ),
			'--sde-bg-bottom': bottom,
			'--sde-radius': Math.max( 0, parseInt( val( 'login_radius' ), 10 ) || 0 ) + 'px',
			'--sde-logo-w': w + 'px',
			'--sde-logo-h': h + 'px',
			'--sde-logo-w-md': Math.round( w * 320 / 420 ) + 'px',
			'--sde-logo-h-md': Math.round( h * 85 / 112 ) + 'px',
			'--sde-logo-w-sm': Math.round( w * 260 / 420 ) + 'px',
			'--sde-logo-h-sm': Math.round( h * 70 / 112 ) + 'px'
		};
		var nl  = 'body.login:not(.interim-login)';
		var css = 'body.login{';
		Object.keys( vars ).forEach( function ( k ) {
			css += k + ':' + vars[ k ] + ';';
		} );
		css += '}';

		var logo   = val( 'login_logo' ) || cfg.logo;
		var domain = ( val( 'login_domain_text' ) || ( document.getElementById( 'sde-login_domain_text' ) || {} ).placeholder || '' ).replace( /^www\./, '' ).replace( /["\\<>]/g, '' );
		css += nl + ' #login h1 a{background-image:url("' + logo.replace( /"/g, '' ) + '")!important}';
		css += nl + ' #login h1::after{content:"' + domain + '";display:' + ( val( 'login_show_domain' ) ? 'block' : 'none' ) + '}';
		css += nl + ' #nav{display:' + ( val( 'login_show_lost' ) ? 'block' : 'none' ) + '}' + nl + ' #login>h1{margin-bottom:' + ( val( 'login_show_lost' ) ? '40px' : '0' ) + '}';
		css += nl + ' .forgetmenot{display:' + ( val( 'login_show_remember' ) ? 'block' : 'none' ) + ';margin:0 0 4px}' + nl + ' .forgetmenot label{position:static;width:auto;height:auto;clip:auto;color:var(--sde-ink)}';
		css += nl + ' #backtoblog{display:' + ( val( 'login_show_back' ) ? 'block' : 'none' ) + ';margin:12px 0 0;text-align:center}';
		css += nl + ' .privacy-policy-page-link{display:' + ( val( 'login_show_privacy' ) ? 'block' : 'none' ) + ';margin:12px 0 0;text-align:center}';
		css += nl + ' .language-switcher{display:' + ( val( 'login_show_language' ) ? 'block' : 'none' ) + '}';
		css += '@media (max-width:900px){' + nl + ' #login>h1{margin-bottom:36px}}';
		css += '\n' + ( val( 'login_custom_css' ) || '' );
		return css;
	}

	function livePreview() {
		if ( ! loaded || ! frame ) {
			return;
		}
		var doc;
		try {
			doc = frame.contentDocument;
		} catch ( e ) {
			return;
		}
		if ( ! doc || ! doc.body ) {
			return;
		}
		var st = doc.getElementById( 'sde-live' );
		if ( ! st ) {
			st = doc.createElement( 'style' );
			st.id = 'sde-live';
			doc.head.appendChild( st );
		}
		st.textContent = previewCss();

		var lost  = /action=lostpassword/.test( doc.location.search );
		var intro = doc.querySelector( '.sonora-login-intro' );
		if ( intro ) {
			var h2 = intro.querySelector( 'h2' );
			var p  = intro.querySelector( 'p' );
			if ( h2 ) {
				h2.textContent = val( lost ? 'lost_title' : 'login_title' );
			}
			if ( p ) {
				p.textContent = val( lost ? 'lost_text' : 'login_text' );
			}
		}
		var u = doc.getElementById( 'user_login' );
		var pw = doc.getElementById( 'user_pass' );
		var b = doc.querySelector( '#loginform #wp-submit' );
		if ( u ) {
			u.placeholder = val( 'login_placeholder_user' );
		}
		if ( pw ) {
			pw.placeholder = val( 'login_placeholder_pass' );
		}
		if ( b ) {
			b.value = val( 'login_button_text' );
		}
	}

	function fit() {
		if ( ! frame || ! stage ) {
			return;
		}
		var s  = sizes[ device ];
		var aw = stage.clientWidth - 48;
		var ah = stage.clientHeight - 48 - ( 'phone' === device ? 20 : 0 );
		var k  = Math.min( 1, aw / s[ 0 ], ah / s[ 1 ] );
		frame.style.width  = s[ 0 ] + 'px';
		frame.style.height = s[ 1 ] + 'px';
		frame.style.transform = 'scale(' + k + ')';
		box.style.width  = Math.round( s[ 0 ] * k ) + 'px';
		box.style.height = Math.round( s[ 1 ] * k ) + 'px';
		box.classList.toggle( 'is-phone', 'phone' === device );
	}

	function previewUrl() {
		return cfg.loginUrl + ( cfg.loginUrl.indexOf( '?' ) > -1 ? '&' : '?' ) + 'sde_preview=' + Date.now();
	}

	function loadPreview() {
		if ( ! frame ) {
			return;
		}
		fit();
		if ( ! frame.src ) {
			frame.src = previewUrl();
		}
	}

	function reloadPreview() {
		if ( frame && frame.src ) {
			loaded = false;
			frame.src = previewUrl();
		}
	}

	if ( frame ) {
		frame.addEventListener( 'load', function () {
			var doc;
			try {
				doc = frame.contentDocument;
			} catch ( e ) {
				return;
			}
			// Naar het gekozen scherm via de link op de pagina zelf (houdt de eigen login-URL intact).
			if ( 'lostpassword' === screen && doc && ! /action=lostpassword/.test( doc.location.search ) ) {
				var a = doc.querySelector( 'a[href*="action=lostpassword"]' );
				if ( a ) {
					frame.src = a.href;
					return;
				}
			}
			if ( 'login' === screen && doc && /action=lostpassword/.test( doc.location.search ) ) {
				frame.src = previewUrl();
				return;
			}
			loaded = true;
			livePreview();
		} );
		window.addEventListener( 'resize', fit );
	}

	$form.on( 'click', '[data-device]', function () {
		device = this.getAttribute( 'data-device' );
		form.querySelectorAll( '[data-device]' ).forEach( function ( b ) {
			b.setAttribute( 'aria-pressed', b.getAttribute( 'data-device' ) === device ? 'true' : 'false' );
		} );
		fit();
	} );
	$form.on( 'click', '[data-screen]', function () {
		screen = this.getAttribute( 'data-screen' );
		form.querySelectorAll( '[data-screen]' ).forEach( function ( b ) {
			b.setAttribute( 'aria-pressed', b.getAttribute( 'data-screen' ) === screen ? 'true' : 'false' );
		} );
		reloadPreview();
	} );
	$form.on( 'input change', '[data-pane="login"] input, [data-pane="login"] textarea', livePreview );

	/* ---------- shortcode-maker (e-mail) ---------- */

	var scBox = form.querySelector( '[data-sc-builder]' );
	var scOut = form.querySelector( '[data-sc-out]' );
	function scVal( key ) {
		var el = scBox.querySelector( '[data-sc="' + key + '"]:checked' ) || scBox.querySelector( '[data-sc="' + key + '"]' );
		return el ? el.value.trim().replace( /"/g, '' ) : '';
	}
	function scBuild() {
		var code = '[obfuscate email="' + scVal( 'email' ) + '" display="' + scVal( 'display' ) + '" link="' + scVal( 'link' ) + '"';
		[ 'subject', 'text', 'class' ].forEach( function ( k ) {
			var v = scVal( k );
			if ( v ) {
				code += ' ' + k + '="' + v + '"';
			}
		} );
		scOut.textContent = code + ']';
	}
	if ( scBox && scOut ) {
		scBox.addEventListener( 'input', scBuild );
		scBox.addEventListener( 'change', scBuild );
		scBuild();
		$form.on( 'click', '[data-sc-copy]', function () {
			var btn = this;
			var done = function () {
				btn.textContent = 'Gekopieerd';
				setTimeout( function () { btn.textContent = 'Kopiëren'; }, 1500 );
			};
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( scOut.textContent ).then( done );
			} else {
				var r = document.createRange();
				r.selectNodeContents( scOut );
				var sel = window.getSelection();
				sel.removeAllRanges();
				sel.addRange( r );
				document.execCommand( 'copy' );
				done();
			}
		} );
	}

	/* ---------- start ---------- */

	showTab( ( window.location.hash || '#overzicht' ).replace( '#', '' ) );
	setDirty( false );
} )( jQuery );
