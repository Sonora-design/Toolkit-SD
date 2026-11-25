<?php
/**
 * Module: loginpagina in Sonora-stijl.
 *
 * - Logo + domeinnaam links, witte kaart rechts (op gsm onder elkaar).
 * - Inloggen en wachtwoord vergeten: kaart met vaste hoogte, knop op dezelfde plaats.
 * - Kleuren, teksten, logo en zichtbare onderdelen in te stellen.
 * - Geen externe lettertypes of bestanden.
 */

namespace SDE\Login;

defined( 'ABSPATH' ) || exit;

function s( $key ) {
	return \SDE\settings()[ $key ];
}

// Titel en uitleg bovenaan de kaart, per scherm.
add_filter(
	'login_message',
	function ( $message ) {
		if ( ! empty( $_REQUEST['interim-login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return $message;
		}
		$action  = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification
		$nieuw   = array( 'Nieuw wachtwoord', 'Kies een nieuw wachtwoord voor je account.' );
		$teksten = array(
			'login'        => array( s( 'login_title' ), s( 'login_text' ) ),
			'lostpassword' => array( s( 'lost_title' ), s( 'lost_text' ) ),
			'rp'           => $nieuw,
			'resetpass'    => $nieuw,
		);
		if ( ! isset( $teksten[ $action ] ) ) {
			return $message;
		}
		list( $titel, $uitleg ) = $teksten[ $action ];
		$html  = '<div class="sonora-login-intro">';
		$html .= '' !== trim( $titel ) ? '<h2>' . esc_html( $titel ) . '</h2>' : '';
		$html .= '' !== trim( $uitleg ) ? '<p>' . esc_html( $uitleg ) . '</p>' : '';
		$html .= '</div>';
		return $html . $message;
	}
);

// Link onder de kaart.
add_filter(
	'lost_password_html_link',
	function () {
		return '<a class="wp-login-lost-password" href="' . esc_url( wp_lostpassword_url() ) . '">Wachtwoord vergeten?</a>';
	}
);

// Toegankelijke naam voor het logo.
add_filter(
	'login_headertext',
	function () {
		return '' === trim( (string) s( 'login_logo' ) ) ? 'Sonora design' : get_bloginfo( 'name' );
	}
);

// Placeholders en knoptekst (de labels zijn enkel zichtbaar voor schermlezers).
add_action(
	'login_footer',
	function () {
		$data = array(
			'user'   => (string) s( 'login_placeholder_user' ),
			'pass'   => (string) s( 'login_placeholder_pass' ),
			'button' => (string) s( 'login_button_text' ),
		);
		?>
		<script>
		( function ( t ) {
			function apply() {
				var user = document.getElementById( 'user_login' );
				var pass = document.getElementById( 'user_pass' );
				var btn  = document.querySelector( '#loginform #wp-submit' );
				if ( user && t.user ) { user.placeholder = t.user; }
				if ( pass && t.pass ) { pass.placeholder = t.pass; }
				if ( btn && t.button ) { btn.value = t.button; }
			}
			apply();
			// Na eventuele oudere snippets die dezelfde velden instellen.
			window.addEventListener( 'load', apply );
		} )( <?php echo wp_json_encode( $data ); ?> );
		</script>
		<?php
	}
);

/**
 * Hex-kleur naar [r, g, b].
 */
function rgb( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
		return array( 0, 0, 0 );
	}
	return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

function rgba( $hex, $alpha ) {
	list( $r, $g, $b ) = rgb( $hex );
	return "rgba($r, $g, $b, $alpha)";
}

/**
 * Twee kleuren mengen (t = aandeel van de tweede kleur).
 */
function mix( $a, $b, $t ) {
	$x = rgb( $a );
	$y = rgb( $b );
	$o = '#';
	for ( $i = 0; $i < 3; $i++ ) {
		$o .= sprintf( '%02x', (int) round( $x[ $i ] + ( $y[ $i ] - $x[ $i ] ) * $t ) );
	}
	return $o;
}

/**
 * De CSS-variabelen op basis van de instellingen.
 */
function variables() {
	$accent = s( 'login_color_accent' );
	$ink    = s( 'login_color_ink' );
	$top    = s( 'login_bg_top' );
	$bottom = s( 'login_bg_bottom' );
	$w      = max( 60, (int) s( 'login_logo_width' ) );
	$h      = max( 20, (int) s( 'login_logo_height' ) );

	$vars = array(
		'--sde-accent'       => $accent,
		'--sde-accent-hover' => s( 'login_color_hover' ),
		'--sde-accent-18'    => rgba( $accent, '.18' ),
		'--sde-accent-35'    => rgba( $accent, '.35' ),
		'--sde-ink'          => $ink,
		'--sde-ink-60'       => rgba( $ink, '.6' ),
		'--sde-ink-22'       => rgba( $ink, '.22' ),
		'--sde-bg-top'       => $top,
		'--sde-bg-top-60'    => rgba( $top, '.6' ),
		'--sde-bg-top-0'     => rgba( $top, '0' ),
		'--sde-bg-mid'       => mix( $top, $bottom, 0.465 ),
		'--sde-bg-bottom'    => $bottom,
		'--sde-radius'       => max( 0, (int) s( 'login_radius' ) ) . 'px',
		'--sde-logo-w'       => $w . 'px',
		'--sde-logo-h'       => $h . 'px',
		'--sde-logo-w-md'    => round( $w * 320 / 420 ) . 'px',
		'--sde-logo-h-md'    => round( $h * 85 / 112 ) . 'px',
		'--sde-logo-w-sm'    => round( $w * 260 / 420 ) . 'px',
		'--sde-logo-h-sm'    => round( $h * 70 / 112 ) . 'px',
	);

	$css = 'body.login{';
	foreach ( $vars as $k => $v ) {
		$css .= $k . ':' . $v . ';';
	}
	return $css . '}';
}

/**
 * Onderdelen tonen of verbergen volgens de instellingen.
 */
function toggles() {
	$css = '';
	$nl  = 'body.login:not(.interim-login)';
	if ( ! s( 'login_show_lost' ) ) {
		$css .= "$nl #nav{display:none}$nl #login>h1{margin-bottom:0}";
	}
	if ( s( 'login_show_remember' ) ) {
		$css .= "$nl .forgetmenot{display:block;margin:0 0 4px}$nl .forgetmenot label{position:static;width:auto;height:auto;clip:auto;color:var(--sde-ink)}";
	}
	if ( s( 'login_show_back' ) ) {
		$css .= "$nl #backtoblog{display:block;margin:12px 0 0;text-align:center}$nl #backtoblog a{color:var(--sde-ink)}";
	}
	if ( s( 'login_show_privacy' ) ) {
		$css .= "$nl .privacy-policy-page-link{display:block;margin:12px 0 0;text-align:center}$nl .privacy-policy-page-link a{color:var(--sde-ink)}";
	}
	if ( s( 'login_show_language' ) ) {
		$css .= "$nl .language-switcher{display:block;margin-top:16px}";
	}
	if ( ! s( 'login_show_domain' ) ) {
		$css .= "$nl #login h1::after{display:none}";
	}
	return $css;
}

add_action(
	'login_head',
	function () {
		$logo = trim( (string) s( 'login_logo' ) );
		if ( '' === $logo ) {
			$logo = SDE_URL . 'assets/sonora-logo.svg';
		}

		$domein = trim( (string) s( 'login_domain_text' ) );
		if ( '' === $domein ) {
			$domein = preg_replace( '/^www\./i', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		}
		// Veilig in een CSS-string.
		$domein = str_replace( array( '\\', '"', '<', '>' ), '', wp_strip_all_tags( $domein ) );

		$css = file_get_contents( SDE_DIR . 'assets/login.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $css ) {
			return;
		}

		$css = variables() . str_replace(
			array( '%%LOGO%%', '%%DOMEIN%%' ),
			array( esc_url( $logo ), $domein ),
			$css
		) . toggles();

		$extra = trim( (string) s( 'login_custom_css' ) );
		if ( '' !== $extra ) {
			$css .= "\n/* Eigen CSS */\n" . $extra;
		}

		echo '<style id="sonora-login-style">' . str_replace( '</', '<\/', $css ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	PHP_INT_MAX
);
