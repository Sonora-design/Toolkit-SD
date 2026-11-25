<?php
/**
 * Module: e-mailadressen verbergen voor spambots.
 *
 * - Shortcode [obfuscate] (vervangt die van ASE, met de Pro-opties:
 *   klikbare mailto-link, onderwerp, eigen linktekst, klasse).
 * - Optioneel: alle e-mailadressen in de inhoud automatisch verbergen.
 *
 * Bezoekers zien en gebruiken de adressen gewoon; eenvoudige spambots lezen ze niet.
 */

namespace SDE\EmailObfuscate;

defined( 'ABSPATH' ) || exit;

/**
 * Moet er voor deze bezoeker verborgen worden?
 */
function active() {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}
	if ( ! empty( \SDE\settings()['email_visitors_only'] ) && is_user_logged_in() ) {
		return false;
	}
	return true;
}

/**
 * Eén e-mailadres omzetten volgens de gekozen methode.
 *
 * @param string $email   Geldig e-mailadres.
 * @param array  $o       text, display (inline|newline), link (bool), subject, class.
 */
function render( $email, array $o ) {
	$s       = \SDE\settings();
	$reverse = ( 'reverse' === ( $s['email_mode'] ?? 'entities' ) ) && '' === $o['text'];
	$inline  = 'newline' !== $o['display'];

	if ( '' !== $o['text'] ) {
		$label = esc_html( $o['text'] );
	} elseif ( $reverse ) {
		// Zoals ASE: tekens omgekeerd, met CSS terug in de juiste volgorde getoond.
		$parts = explode( '@', strrev( $email ), 2 );
		$label = esc_html( $parts[0] ) . '<span style="display:none">sde-' . esc_html( dechex( wp_rand( 1000000, 9999999 ) ) ) . '</span>&#64;' . esc_html( $parts[1] );
	} else {
		$label = antispambot( $email );
	}

	if ( $reverse ) {
		$style = ( $inline ? 'display:inline;' : 'display:flex;justify-content:flex-end;' ) . 'unicode-bidi:bidi-override;direction:rtl;';
	} else {
		$style = $inline ? '' : 'display:block;';
	}

	$attr = ( '' !== $style ? ' style="' . esc_attr( $style ) . '"' : '' )
		. ( '' !== $o['class'] ? ' class="' . esc_attr( $o['class'] ) . '"' : '' );

	if ( ! $o['link'] ) {
		return '<span' . $attr . '>' . $label . '</span>';
	}

	$href = 'mailto:' . antispambot( $email, 1 );
	if ( '' !== $o['subject'] ) {
		$href .= '?subject=' . rawurlencode( $o['subject'] );
	}
	if ( $reverse ) {
		// De link zelf blijft gewoon links-naar-rechts; enkel de tekst erin is omgekeerd.
		return '<a href="' . $href . '"' . ( '' !== $o['class'] ? ' class="' . esc_attr( $o['class'] ) . '"' : '' ) . ( $inline ? '' : ' style="display:block"' ) . '><span style="' . esc_attr( $style ) . '">' . $label . '</span></a>';
	}
	return '<a href="' . $href . '"' . $attr . '>' . $label . '</a>';
}

/**
 * Shortcode [obfuscate email="" display="inline|newline" link="yes|no" subject="" text="" class=""].
 * Zelfde attributen als ASE (Free en Pro).
 */
function shortcode( $atts ) {
	$s    = \SDE\settings();
	$atts = shortcode_atts(
		array(
			'email'   => '',
			'subject' => '',
			'text'    => '',
			'display' => $s['email_default_display'] ?? 'newline',
			'link'    => $s['email_default_link'] ?? 'no',
			'class'   => '',
		),
		$atts,
		'obfuscate'
	);

	$email = sanitize_email( str_replace( '\\', '', (string) $atts['email'] ) );
	if ( ! is_email( $email ) ) {
		return '';
	}

	$o = array(
		'text'    => sanitize_text_field( $atts['text'] ),
		'display' => 'inline' === $atts['display'] ? 'inline' : 'newline',
		'link'    => in_array( strtolower( (string) $atts['link'] ), array( 'yes', 'mailto', '1', 'true' ), true ),
		'subject' => sanitize_text_field( $atts['subject'] ),
		'class'   => implode( ' ', array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $atts['class'] ) ) ) ),
	);

	if ( ! active() ) {
		// Ingelogd en "enkel voor bezoekers": gewoon tonen.
		$label = '' !== $o['text'] ? esc_html( $o['text'] ) : esc_html( $email );
		$style = 'newline' === $o['display'] ? ' style="display:block"' : '';
		$class = '' !== $o['class'] ? ' class="' . esc_attr( $o['class'] ) . '"' : '';
		if ( $o['link'] ) {
			$href = 'mailto:' . $email . ( '' !== $o['subject'] ? '?subject=' . rawurlencode( $o['subject'] ) : '' );
			return '<a href="' . esc_attr( $href ) . '"' . $style . $class . '>' . $label . '</a>';
		}
		return '<span' . $style . $class . '>' . $label . '</span>';
	}

	return render( $email, $o );
}

/**
 * Alle e-mailadressen in de inhoud automatisch verbergen.
 * Bestaande mailto-links krijgen een gecodeerde href; losse adressen worden gecodeerd.
 */
function content( $content ) {
	if ( ! active() || false === strpos( (string) $content, '@' ) ) {
		return $content;
	}
	// Mailto-links.
	$content = preg_replace_callback(
		'/href=(["\'])mailto:([^"\'?]+)([^"\']*)\1/i',
		function ( $m ) {
			return 'href=' . $m[1] . 'mailto:' . antispambot( $m[2], 1 ) . $m[3] . $m[1];
		},
		$content
	);
	// Losse adressen (ook de tekst van links), niet binnen attributen.
	return preg_replace_callback(
		'/(?<![\w.%+\-=\/:])[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}(?![\w-])/i',
		function ( $m ) {
			return antispambot( $m[0] );
		},
		$content
	);
}

/**
 * Stijlen voor de omgekeerde methode toelaten in gefilterde HTML.
 */
function safe_css( $attrs ) {
	$attrs[] = 'display';
	$attrs[] = 'unicode-bidi';
	$attrs[] = 'direction';
	return $attrs;
}

// Na ASE registreren, zodat deze shortcode die van ASE vervangt.
add_action(
	'init',
	function () {
		if ( ! empty( \SDE\settings()['email_shortcode'] ) ) {
			remove_shortcode( 'obfuscate' );
			add_shortcode( 'obfuscate', __NAMESPACE__ . '\shortcode' );
			add_filter( 'safe_style_css', __NAMESPACE__ . '\safe_css' );
		}
	},
	PHP_INT_MAX
);

if ( ! empty( \SDE\settings()['email_auto'] ) ) {
	add_filter( 'the_content', __NAMESPACE__ . '\content', 99 );
	add_filter( 'widget_text', __NAMESPACE__ . '\content', 99 );
	add_filter( 'widget_block_content', __NAMESPACE__ . '\content', 99 );
	add_filter( 'elementor/frontend/the_content', __NAMESPACE__ . '\content', 99 );
}
