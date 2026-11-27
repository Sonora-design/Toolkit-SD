<?php
/**
 * Beheerpagina: Extra > Toolkit SD (Tools > Toolkit SD).
 * Opbouw zoals Fluidly: bovenbalk, tabbladen, kaarten en schakelaars, opslaan zonder herladen.
 */

namespace SDE\Admin;

defined( 'ABSPATH' ) || exit;

const SLUG = 'toolkit-sd';

/** @var array Volledig beheermenu, vastgelegd vóór de module Beheermenu items weghaalt. */
$GLOBALS['sde_menu_snapshot'] = array(
	'menu'    => array(),
	'submenu' => array(),
);

function modules_info() {
	return array(
		'rest'             => array( 'REST API afschermen', 'Bezoekers krijgen enkel toegang tot de routes die nodig zijn, zoals de cookiebanner.', 'rest' ),
		'login'            => array( 'Loginpagina in Sonora-stijl', 'Logo en domeinnaam links, witte kaart rechts, in je eigen kleuren.', 'login' ),
		'admin_menu'       => array( 'Beheermenu per rol', 'Klanten zien enkel de menu-items die ze nodig hebben.', 'menu' ),
		'editor_menu'      => array( __( 'Menu voor editors afschermen', 'toolkit-sd' ), __( 'Editors zien enkel Pagina\'s, Media en hun profiel. Andere beheerpagina\'s zijn voor hen afgesloten.', 'toolkit-sd' ), 'menu' ),
		'login_redirect'   => array( 'Doorsturen na in- en uitloggen', 'Per rol naar een eigen pagina, bv. het klantendashboard.', 'redirects' ),
		'admin_bar'        => array( 'Admin bar verbergen in de backend', 'Voor gekozen rollen, voor een rustiger beheer.', 'extra' ),
		'media_categories' => array( 'Mediacategorieën', 'Bestanden ordenen in categorieën, zelfde indeling als ASE Pro.', 'extra' ),
		'email_obfuscate'  => array( 'E-mailadressen verbergen voor spambots', 'Shortcode [obfuscate] met klikbare link, en optioneel alle adressen in de inhoud.', 'extra' ),
		'hide_notices'     => array( 'Beheermeldingen verbergen', 'Klanten zien geen update- of reclameberichten van plugins.', 'extra' ),
		'public_preview'   => array( 'Publieke preview van concepten', 'Deel een concept via een geheime link, zonder dat de ontvanger moet inloggen. Links uit ASE worden niet overgenomen: maak na de overstap nieuwe links.', 'extra' ),
	);
}

function tabs() {
	return array(
		'overzicht' => 'Overzicht',
		'rest'      => 'REST API',
		'login'     => 'Loginpagina',
		'menu'      => 'Beheermenu',
		'redirects' => 'Doorsturen',
		'extra'     => 'Extra',
		'ase'       => 'Overstap ASE',
	);
}

function roles() {
	$out = array();
	foreach ( wp_roles()->get_names() as $key => $name ) {
		$out[ $key ] = translate_user_role( $name );
	}
	return $out;
}

/**
 * Berichttypes waarvoor een publieke preview kan: alles met een eigen pagina op de site.
 */
function preview_types() {
	$out = array();
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $key => $type ) {
		if ( 'attachment' !== $key ) {
			$out[ $key ] = $type->labels->name;
		}
	}
	return $out;
}

/**
 * Login-adres, rekening houdend met de eigen login-URL van Security Optimizer.
 */
function login_url() {
	$slug = ( 'custom' === get_option( 'sg_security_login_type' ) ) ? trim( (string) get_option( 'sg_security_login_url' ), '/' ) : '';
	return $slug ? home_url( '/' . $slug . '/' ) : wp_login_url();
}

/* -------------------------------------------------------------------------
 * Menu, assets en opslaan
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_management_page( 'Toolkit SD', 'Toolkit SD', 'manage_options', SLUG, __NAMESPACE__ . '\render' );
	}
);

function page_url() {
	return admin_url( 'tools.php?page=' . SLUG );
}

// Oud adres (Instellingen > Toolkit SD) doorsturen naar Extra > Toolkit SD.
add_action(
	'admin_init',
	function () {
		global $pagenow;
		if ( 'options-general.php' === $pagenow && isset( $_GET['page'] ) && SLUG === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification -- enkel doorsturen.
			wp_safe_redirect( page_url() );
			exit;
		}
	}
);

// Volledig menu vastleggen, net vóór de module Beheermenu items verbergt.
add_action(
	'admin_menu',
	function () {
		$GLOBALS['sde_menu_snapshot'] = array(
			'menu'    => isset( $GLOBALS['menu'] ) ? $GLOBALS['menu'] : array(),
			'submenu' => isset( $GLOBALS['submenu'] ) ? $GLOBALS['submenu'] : array(),
		);
	},
	PHP_INT_MAX - 1
);

/*
 * Menu voor editors: eenmalige overname uit ASE (bij de eerste lading na de update)
 * of via de knop "Importeer uit ASE". Draait na de snapshot, dus met het volledige menu.
 */
add_action(
	'admin_menu',
	function () {
		if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
			return;
		}
		$snap = $GLOBALS['sde_menu_snapshot'];

		// phpcs:ignore WordPress.Security.NonceVerification -- nonce wordt hieronder gecontroleerd.
		if ( isset( $_GET['sde_editor_import'] ) ) {
			check_admin_referer( 'sde_editor_import' );
			\SDE\Ase\import_editor_from_ase( $snap );
			wp_safe_redirect( page_url() . '#menu' );
			exit;
		}

		if ( empty( \SDE\settings()['editor_migrated'] ) && ! empty( $snap['menu'] ) ) {
			\SDE\Ase\import_editor_from_ase( $snap );
		}
	},
	PHP_INT_MAX
);

function editor_import_url() {
	return wp_nonce_url( add_query_arg( 'sde_editor_import', '1', page_url() ), 'sde_editor_import' );
}

// Melding na de overname uit ASE: raadt aan het verbergen per rol in ASE uit te zetten.
add_action(
	'admin_init',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification -- nonce wordt hieronder gecontroleerd.
		if ( isset( $_GET['sde_editor_notice'] ) && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'sde_editor_notice' );
			$s                            = \SDE\settings( true );
			$s['editor_import']['notice'] = false;
			update_option( \SDE\OPTION, $s );
			wp_safe_redirect( remove_query_arg( array( 'sde_editor_notice', '_wpnonce' ) ) );
			exit;
		}
	}
);

add_action(
	'admin_notices',
	function () {
		$imp = \SDE\settings()['editor_import'];
		if ( empty( $imp['notice'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$close = wp_nonce_url( add_query_arg( 'sde_editor_notice', 'dismiss' ), 'sde_editor_notice' );
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Toolkit SD heeft het menu voor editors overgenomen uit ASE.', 'toolkit-sd' ) . '</strong> ';
		echo esc_html__( 'Zet in ASE bij Admin Menu Organizer het verbergen per rol uit, zodat beide plugins elkaar niet tegenwerken. Volgorde en namen van het menu mag je in ASE laten staan.', 'toolkit-sd' ) . '</p>';
		echo '<p><a href="' . esc_url( page_url() . '#menu' ) . '">' . esc_html__( 'Bekijk het menu voor editors', 'toolkit-sd' ) . '</a> · <a href="' . esc_url( $close ) . '">' . esc_html__( 'Melding sluiten', 'toolkit-sd' ) . '</a></p></div>';
	}
);

/**
 * REST: ASE is actief, maar sluit niet zelf af. Toolkit SD doet het dan als vangnet.
 */
function rest_needs_ase() {
	return \SDE\module_on( 'rest' ) && \SDE\Ase\ase_active() && ! \SDE\Ase\ase_rest_on();
}

function rest_ase_text() {
	return __( 'Zet Disable REST API aan in ASE. Toolkit SD beheert enkel de uitzonderingen.', 'toolkit-sd' );
}

add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! rest_needs_ase() ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'tools_page_' . SLUG === $screen->id ) {
			return; // Staat al op de REST-pagina van Toolkit SD zelf.
		}
		echo '<div class="notice notice-warning"><p><strong>Toolkit SD:</strong> ' . esc_html( rest_ase_text() ) . ' <a href="' . esc_url( page_url() . '#rest' ) . '">' . esc_html__( 'Bekijk de REST-instellingen', 'toolkit-sd' ) . '</a></p></div>';
	}
);

/**
 * Melding of tip binnen de beheerpagina.
 *
 * @param string $kind  warn of tip.
 * @param string $attrs Vaste attributen uit de code.
 */
function note( $text, $kind = 'warn', $attrs = '' ) {
	printf(
		'<p class="sde-note sde-note--%1$s" %2$s>%3$s</p>',
		esc_attr( $kind ),
		$attrs, // phpcs:ignore WordPress.Security.EscapeOutput -- vaste attributen uit de code.
		esc_html( $text )
	);
}

/**
 * Hebben alle ingevulde rollen hetzelfde adres (na inloggen en na uitloggen)? Dat kan ASE gratis ook.
 */
function redirects_uniform( array $s ) {
	$in  = array_filter( \SDE\pairs( $s['login_redirects'] ), 'strlen' );
	$out = array_filter( \SDE\pairs( $s['logout_redirects'] ), 'strlen' );
	return ( $in || $out ) && count( array_unique( $in ) ) <= 1 && count( array_unique( $out ) ) <= 1;
}

add_filter(
	'plugin_action_links_' . plugin_basename( SDE_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( page_url() ) . '">Instellingen</a>' );
		return $links;
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'tools_page_' . SLUG !== $hook ) {
			return;
		}
		wp_enqueue_media();
		// Versie + wijzigingsdatum, zodat de browser na een update meteen de nieuwe bestanden laadt.
		$ver = function ( $file ) {
			return \SDE\VERSION . '.' . (int) @filemtime( SDE_DIR . $file );
		};
		wp_enqueue_style( 'sde-admin', SDE_URL . 'assets/admin.css', array(), $ver( 'assets/admin.css' ) );
		wp_enqueue_script( 'sde-admin', SDE_URL . 'assets/admin.js', array( 'jquery' ), $ver( 'assets/admin.js' ), true );
		wp_add_inline_script(
			'sde-admin',
			'window.SDE = ' . wp_json_encode(
				array(
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'sde_admin' ),
					'loginUrl' => login_url(),
					'logo'     => SDE_URL . 'assets/sonora-logo.svg',
					'defaults' => \SDE\defaults(),
				)
			) . ';',
			'before'
		);
	}
);

function guard() {
	check_ajax_referer( 'sde_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Geen toestemming.' ), 403 );
	}
}

add_action(
	'wp_ajax_sde_save',
	function () {
		guard();
		$in    = isset( $_POST['sde'] ) && is_array( $_POST['sde'] ) ? wp_unslash( $_POST['sde'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wordt in sanitize() opgeschoond.
		$clean = sanitize( $in );
		update_option( \SDE\OPTION, $clean );
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}
		wp_send_json_success( array( 'message' => 'Opgeslagen' ) );
	}
);

add_action(
	'wp_ajax_sde_rest_test',
	function () {
		guard();
		$routes = array( '', 'wp/v2/users', 'wp/v2/pages' );
		if ( in_array( 'surecookie/surecookie.php', (array) get_option( 'active_plugins', array() ), true ) ) {
			$routes[] = 'surecookie/v1/frontend/settings';
		}
		foreach ( \SDE\lines( \SDE\settings()['rest_allow'] ) as $p ) {
			$routes[] = trim( $p, '/' );
		}
		$out = array();
		foreach ( array_unique( $routes ) as $route ) {
			$res   = wp_remote_get(
				add_query_arg( 'sde', time(), rest_url( $route ) ),
				array(
					'timeout'   => 10,
					'sslverify' => false,
				)
			);
			$out[] = array(
				'route' => '/wp-json/' . $route,
				'code'  => is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res ),
			);
		}
		wp_send_json_success( $out );
	}
);

add_action(
	'wp_ajax_sde_ase',
	function () {
		guard();
		$do = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
		if ( 'preview' === $do ) {
			$r = \SDE\Ase\build_from_ase();
			// Wat "ASE-functies uitzetten" daarna in ASE verandert, uitgaand van deze overname.
			$plan = \SDE\Ase\plan_switch( $r['settings'], \SDE\Ase\ase_options(), \SDE\Ase\ase_extra() );
			wp_send_json_success(
				array(
					'items'       => $r['items'],
					'not_covered' => $r['not_covered'],
					'ase_changes' => $plan['changes'],
				)
			);
		} elseif ( 'import' === $do ) {
			$r     = \SDE\Ase\import_from_ase();
			$stays = count( $r['items'] ) - count( $r['report'] );
			wp_send_json_success( array( 'message' => 'Overgenomen uit ASE: ' . ( $r['report'] ? implode( '; ', $r['report'] ) : 'niets' ) . '.' . ( $stays ? ' ' . $stays . ( 1 === $stays ? ' onderdeel blijft' : ' onderdelen blijven' ) . ' in ASE (gratis).' : '' ) ) );
		} elseif ( 'switch' === $do ) {
			$c = \SDE\Ase\switch_from_ase();
			wp_send_json_success(
				array(
					'message' => $c ? 'ASE is aangepast. Back-up bewaard.' : 'Er was niets aan te passen in ASE.',
					'list'    => $c,
				)
			);
		} elseif ( 'restore' === $do ) {
			wp_send_json_success( array( 'message' => \SDE\Ase\restore_ase() ? 'ASE-instellingen teruggezet uit de back-up.' : 'Geen back-up gevonden.' ) );
		}
		wp_send_json_error( array( 'message' => 'Onbekende actie.' ) );
	}
);

/* -------------------------------------------------------------------------
 * Opschonen
 * ---------------------------------------------------------------------- */

function bool( $in, $key ) {
	return ! empty( $in[ $key ] );
}

function color( $in, $key, $default ) {
	$c = isset( $in[ $key ] ) ? sanitize_hex_color( trim( (string) $in[ $key ] ) ) : '';
	return $c ? strtolower( $c ) : $default;
}

function int( $in, $key, $default, $min, $max ) {
	if ( ! isset( $in[ $key ] ) || '' === $in[ $key ] ) {
		return $default;
	}
	return max( $min, min( $max, (int) $in[ $key ] ) );
}

function role_list( $value ) {
	$valid = array_keys( roles() );
	return array_values( array_intersect( array_map( 'sanitize_key', (array) $value ), $valid ) );
}

function sanitize( array $in ) {
	$d   = \SDE\defaults();
	$out = $d;

	foreach ( array_keys( $d['modules'] ) as $key ) {
		$out['modules'][ $key ] = ! empty( $in['modules'][ $key ] );
	}

	// REST.
	$out['rest_allow']           = sanitize_textarea_field( (string) ( $in['rest_allow'] ?? '' ) );
	$out['rest_auto_surecookie'] = bool( $in, 'rest_auto_surecookie' );
	$out['rest_auto_iawp']       = bool( $in, 'rest_auto_iawp' );
	$out['rest_auto_novamira']   = bool( $in, 'rest_auto_novamira' );
	$out['rest_index_open']      = bool( $in, 'rest_index_open' );
	$out['rest_roles_mode']      = ( 'some' === ( $in['rest_roles_mode'] ?? '' ) ) ? 'some' : 'all';
	$out['rest_roles']           = role_list( $in['rest_roles'] ?? array() );

	// Loginpagina.
	$out['login_logo']        = esc_url_raw( trim( (string) ( $in['login_logo'] ?? '' ) ) );
	$out['login_logo_width']  = int( $in, 'login_logo_width', $d['login_logo_width'], 60, 800 );
	$out['login_logo_height'] = int( $in, 'login_logo_height', $d['login_logo_height'], 20, 400 );
	$out['login_show_domain'] = bool( $in, 'login_show_domain' );
	foreach ( array( 'login_domain_text', 'login_title', 'login_text', 'lost_title', 'lost_text', 'login_placeholder_user', 'login_placeholder_pass', 'login_button_text' ) as $key ) {
		$out[ $key ] = sanitize_text_field( (string) ( $in[ $key ] ?? '' ) );
	}
	foreach ( array( 'login_color_accent', 'login_color_hover', 'login_color_ink', 'login_bg_top', 'login_bg_bottom' ) as $key ) {
		$out[ $key ] = color( $in, $key, $d[ $key ] );
	}
	$out['login_radius'] = int( $in, 'login_radius', $d['login_radius'], 0, 40 );
	foreach ( array( 'login_show_lost', 'login_show_remember', 'login_show_back', 'login_show_privacy', 'login_show_language' ) as $key ) {
		$out[ $key ] = bool( $in, $key );
	}
	$out['login_custom_css'] = wp_strip_all_tags( (string) ( $in['login_custom_css'] ?? '' ) );

	// Beheermenu: tabel + extra regels.
	$rules = array();
	foreach ( (array) ( $in['menu'] ?? array() ) as $row ) {
		$target = trim( sanitize_text_field( (string) ( $row['target'] ?? '' ) ) );
		$mode   = (string) ( $row['mode'] ?? 'visible' );
		$rroles = role_list( $row['roles'] ?? array() );
		if ( '' === $target || 'visible' === $mode ) {
			continue;
		}
		if ( 'all' === $mode || ( 'except' === $mode && ! $rroles ) ) {
			$rules[] = $target . ' | iedereen';
		} elseif ( 'only' === $mode ) {
			if ( $rroles ) {
				$rules[] = $target . ' | alleen: ' . implode( ', ', $rroles );
			}
		} else {
			$rules[] = $target . ' | behalve: ' . implode( ', ', $rroles );
		}
	}
	foreach ( \SDE\lines( sanitize_textarea_field( (string) ( $in['menu_extra'] ?? '' ) ) ) as $line ) {
		$rules[] = $line;
	}
	$out['admin_menu_rules'] = implode( "\n", array_unique( $rules ) );
	$out['admin_bar_nodes']  = implode( "\n", array_map( 'sanitize_key', \SDE\lines( (string) ( $in['admin_bar_nodes'] ?? '' ) ) ) );

	// Menu voor editors.
	$allow = array();
	foreach ( (array) ( $in['editor_allow'] ?? array() ) as $slug ) {
		$slug = trim( sanitize_text_field( (string) $slug ) );
		if ( '' !== $slug ) {
			$allow[] = $slug;
		}
	}
	$out['editor_allow']     = implode( "\n", array_unique( $allow ) );
	$out['editor_hide']      = implode( "\n", \SDE\lines( sanitize_textarea_field( (string) ( $in['editor_hide'] ?? '' ) ) ) );
	$out['editor_bar_allow'] = implode( "\n", array_map( 'sanitize_key', \SDE\lines( (string) ( $in['editor_bar_allow'] ?? '' ) ) ) );
	// Niet via het formulier: blijven zoals ze opgeslagen zijn.
	$now                    = \SDE\settings( true );
	$out['editor_import']   = $now['editor_import'];
	$out['editor_migrated'] = $now['editor_migrated'];

	// Doorsturen.
	foreach ( array( 'login_redirects' => 'login_redirect', 'logout_redirects' => 'logout_redirect' ) as $key => $field ) {
		$lines = array();
		foreach ( (array) ( $in[ $field ] ?? array() ) as $role => $path ) {
			$role = sanitize_key( $role );
			$path = trim( sanitize_text_field( (string) $path ) );
			if ( '' !== $path && isset( roles()[ $role ] ) ) {
				$lines[] = $role . '=' . $path;
			}
		}
		$out[ $key ] = implode( "\n", $lines );
	}

	// Extra.
	$out['admin_bar_roles']     = implode( "\n", role_list( $in['admin_bar_roles'] ?? array() ) );
	$out['media_grid_filter']   = bool( $in, 'media_grid_filter' );
	$out['email_shortcode']       = bool( $in, 'email_shortcode' );
	$out['email_auto']            = bool( $in, 'email_auto' );
	$out['email_visitors_only']   = bool( $in, 'email_visitors_only' );
	$out['email_mode']            = 'reverse' === ( $in['email_mode'] ?? '' ) ? 'reverse' : 'entities';
	$out['email_default_link']    = 'yes' === ( $in['email_default_link'] ?? '' ) ? 'yes' : 'no';
	$out['email_default_display'] = 'inline' === ( $in['email_default_display'] ?? '' ) ? 'inline' : 'newline';
	$out['notices_roles']       = implode( "\n", role_list( $in['notices_roles'] ?? array() ) );
	if ( '' === $out['notices_roles'] ) {
		$out['notices_roles'] = 'administrator';
	}

	// Publieke preview. Zonder berichttype geldt de standaard; zonder rol enkel administrators.
	$types = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $in['preview_post_types'] ?? array() ) ), array_keys( preview_types() ) ) );
	$out['preview_post_types'] = $types ? implode( "\n", $types ) : $d['preview_post_types'];
	$out['preview_days']       = int( $in, 'preview_days', $d['preview_days'], 1, 30 );
	$out['preview_roles']      = implode( "\n", role_list( $in['preview_roles'] ?? array() ) );

	return $out;
}

/**
 * Pagina's die de shortcode [obfuscate] gebruiken (inhoud of Elementor).
 */
function email_usage() {
	global $wpdb;
	$like = '%' . $wpdb->esc_like( '[obfuscate' ) . '%';
	$ids  = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
			 WHERE p.post_type NOT IN ('revision','nav_menu_item','elementor_snippet')
			 AND p.post_status IN ('publish','draft','private','future')
			 AND ( p.post_content LIKE %s OR m.meta_value LIKE %s )
			 ORDER BY p.post_title",
			$like,
			$like
		)
	);
	return array_map( 'intval', (array) $ids );
}

/**
 * Voorstel voor het e-mailadres in de shortcode-maker: info@ + domein.
 */
function email_guess() {
	$host = preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	return $host ? 'info@' . $host : '';
}

/* -------------------------------------------------------------------------
 * Hulpjes voor de weergave
 * ---------------------------------------------------------------------- */

function name( $key ) {
	return 'sde[' . $key . ']';
}

function toggle( $name, $checked, $label, $help = '', $attrs = '' ) {
	$hidden = '';
	$named  = '';
	if ( '' !== $name ) {
		$hidden = '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
		$named  = ' name="' . esc_attr( $name ) . '"';
	}
	printf(
		'<label class="sde-toggle">%1$s<input type="checkbox" value="1"%2$s %3$s %4$s><span class="sde-toggle__ui"></span>%5$s</label>',
		$hidden, // phpcs:ignore WordPress.Security.EscapeOutput -- hierboven opgebouwd.
		$named, // phpcs:ignore WordPress.Security.EscapeOutput -- hierboven opgebouwd.
		checked( $checked, true, false ),
		$attrs, // phpcs:ignore WordPress.Security.EscapeOutput -- vaste attributen uit de code.
		( '' !== $label || $help ) ? '<span>' . esc_html( $label ) . ( $help ? '<small>' . esc_html( $help ) . '</small>' : '' ) . '</span>' : ''
	);
}

function text( $key, $label, $value, $help = '', $placeholder = '' ) {
	printf(
		'<div class="sde-field"><label for="sde-%1$s">%2$s</label><input type="text" id="sde-%1$s" name="%3$s" value="%4$s" placeholder="%6$s" data-live="%1$s">%5$s</div>',
		esc_attr( $key ),
		esc_html( $label ),
		esc_attr( name( $key ) ),
		esc_attr( $value ),
		$help ? '<p class="sde-hint">' . wp_kses_post( $help ) . '</p>' : '',
		esc_attr( $placeholder )
	);
}

function number( $key, $label, $value, $unit, $min, $max ) {
	printf(
		'<div class="sde-field"><label for="sde-%1$s">%2$s</label><div class="sde-num"><input type="number" id="sde-%1$s" name="%3$s" value="%4$d" min="%6$d" max="%7$d" data-live="%1$s"><span class="sde-unit">%5$s</span></div></div>',
		esc_attr( $key ),
		esc_html( $label ),
		esc_attr( name( $key ) ),
		(int) $value,
		esc_html( $unit ),
		(int) $min,
		(int) $max
	);
}

function color_field( $key, $label, $value ) {
	printf(
		'<div class="sde-field"><label for="sde-%1$s">%2$s</label><div class="sde-color"><input type="color" value="%4$s" data-color-for="sde-%1$s" aria-label="%2$s"><input type="text" id="sde-%1$s" name="%3$s" value="%4$s" data-live="%1$s" maxlength="7"></div></div>',
		esc_attr( $key ),
		esc_html( $label ),
		esc_attr( name( $key ) ),
		esc_attr( $value )
	);
}

function role_checks( $name, array $selected, $exclude = array() ) {
	echo '<div class="sde-chips">';
	foreach ( roles() as $key => $label ) {
		if ( in_array( $key, $exclude, true ) ) {
			continue;
		}
		printf(
			'<label class="sde-chip"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s><span>%4$s</span></label>',
			esc_attr( $name ),
			esc_attr( $key ),
			checked( in_array( $key, $selected, true ), true, false ),
			esc_html( $label )
		);
	}
	echo '</div>';
}

function menu_title( $title ) {
	$title = preg_replace( '#<span[^>]*>.*?</span>#s', '', (string) $title );
	$title = trim( wp_strip_all_tags( $title ) );
	return '' !== $title ? $title : '(zonder titel)';
}

/**
 * Bestaande menuregels per doel.
 */
function rules_by_target() {
	$out = array();
	foreach ( \SDE\lines( \SDE\settings()['admin_menu_rules'] ) as $line ) {
		$parts  = array_map( 'trim', explode( '|', $line, 2 ) );
		$rule   = isset( $parts[1] ) ? strtolower( $parts[1] ) : 'behalve: administrator';
		$mode   = 'except';
		$rroles = array();
		if ( 0 === strpos( $rule, 'iedereen' ) ) {
			$mode = 'all';
		} elseif ( preg_match( '/^(behalve|alleen)\s*:\s*(.*)$/', $rule, $m ) ) {
			$mode   = ( 'alleen' === $m[1] ) ? 'only' : 'except';
			$rroles = array_filter( array_map( 'trim', explode( ',', $m[2] ) ) );
		}
		$out[ html_entity_decode( $parts[0] ) ] = array(
			'mode'  => $mode,
			'roles' => $rroles,
			'line'  => $line,
		);
	}
	return $out;
}

function menu_row( $i, $target, $title, $is_sub, $rule ) {
	$mode  = $rule ? $rule['mode'] : 'visible';
	$sel   = $rule ? $rule['roles'] : array( 'administrator' );
	$modes = array(
		'visible' => 'Zichtbaar',
		'except'  => 'Verbergen, behalve voor',
		'only'    => 'Enkel verbergen voor',
		'all'     => 'Verbergen voor iedereen',
	);
	echo '<tr class="' . ( $is_sub ? 'is-sub' : 'is-top' ) . ( 'visible' !== $mode ? ' is-hidden-rule' : '' ) . '" data-menu-row>';
	echo '<th scope="row">' . ( $is_sub ? '<span class="sde-sub">↳</span> ' : '' ) . esc_html( $title ) . '<small>' . esc_html( $target ) . '</small>';
	echo '<input type="hidden" name="sde[menu][' . (int) $i . '][target]" value="' . esc_attr( $target ) . '"></th>';
	echo '<td class="sde-mode"><select name="sde[menu][' . (int) $i . '][mode]" data-menu-mode>';
	foreach ( $modes as $k => $label ) {
		echo '<option value="' . esc_attr( $k ) . '" ' . selected( $mode, $k, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></td><td class="sde-roles-cell"' . ( in_array( $mode, array( 'except', 'only' ), true ) ? '' : ' data-off' ) . '>';
	role_checks( 'sde[menu][' . (int) $i . '][roles]', $sel );
	echo '</td></tr>';
}

/**
 * Titels van de hoofdmenu-items uit de snapshot, per slug.
 */
function top_menu_titles() {
	$out = array();
	foreach ( (array) $GLOBALS['sde_menu_snapshot']['menu'] as $item ) {
		if ( empty( $item[2] ) || ( isset( $item[4] ) && false !== strpos( (string) $item[4], 'wp-menu-separator' ) ) ) {
			continue;
		}
		$out[ html_entity_decode( (string) $item[2] ) ] = menu_title( $item[0] );
	}
	// Bij een administrator staat Profiel onder Gebruikers; bij een editor is het een eigen item.
	if ( ! isset( $out['profile.php'] ) ) {
		$out['profile.php'] = __( 'Profiel', 'toolkit-sd' );
	}
	return $out;
}

function editor_row( $slug, $title ) {
	printf(
		'<li class="sde-allow__item" data-allow-item><span>%1$s<small>%2$s</small></span><input type="hidden" name="sde[editor_allow][]" value="%2$s"><button type="button" class="sde-btn sde-btn--ghost" data-allow-remove aria-label="%3$s">%4$s</button></li>',
		esc_html( $title ),
		esc_attr( $slug ),
		/* translators: %s: naam van het menu-item */
		esc_attr( sprintf( __( '%s verwijderen', 'toolkit-sd' ), $title ) ),
		esc_html__( 'Verwijderen', 'toolkit-sd' )
	);
}

/**
 * Blok "Menu voor editors" in het tabblad Beheermenu.
 */
function editor_block( array $s ) {
	$titles = top_menu_titles();
	$allow  = array_map( 'html_entity_decode', \SDE\lines( $s['editor_allow'] ) );
	$imp    = (array) $s['editor_import'];
	$roles  = array_map(
		function ( $r ) {
			return roles()[ $r ] ?? $r;
		},
		\SDE\restricted_roles()
	);
	?>
	<div class="sde-panehead">
		<div>
			<h2 class="sde-title"><?php esc_html_e( 'Menu voor editors', 'toolkit-sd' ); ?></h2>
			<p class="sde-intro">
				<?php
				/* translators: %s: rollen, bv. Editor */
				echo esc_html( sprintf( __( 'Voor %s staat enkel wat hieronder staat in het menu. Alles wat plugins later toevoegen, blijft verborgen, en andere beheerpagina\'s zijn voor hen afgesloten. Het Dashboard blijft bereikbaar. Administrators merken niets.', 'toolkit-sd' ), implode( ', ', $roles ) ) );
				?>
			</p>
		</div>
		<?php toggle( '', ! empty( $s['modules']['editor_menu'] ), __( 'Module aan', 'toolkit-sd' ), '', 'data-mirror="editor_menu"' ); ?>
	</div>

	<div class="sde-block">
		<h3><?php esc_html_e( 'Zichtbaar voor editors', 'toolkit-sd' ); ?></h3>
		<p class="sde-hint"><?php esc_html_e( 'Een item toont ook zijn submenu\'s.', 'toolkit-sd' ); ?></p>
		<ul class="sde-allow" data-allow-list>
			<?php
			foreach ( $allow as $slug ) {
				editor_row( $slug, $titles[ $slug ] ?? $slug );
			}
			?>
		</ul>
		<div class="sde-actions">
			<select data-allow-pick data-ignore aria-label="<?php esc_attr_e( 'Menu-item kiezen', 'toolkit-sd' ); ?>">
				<option value=""><?php esc_html_e( 'Item toevoegen…', 'toolkit-sd' ); ?></option>
				<?php foreach ( $titles as $slug => $title ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" data-title="<?php echo esc_attr( $title ); ?>"><?php echo esc_html( $title . ' (' . $slug . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="sde-btn" data-allow-add><?php esc_html_e( 'Toevoegen', 'toolkit-sd' ); ?></button>
			<button type="button" class="sde-btn" data-allow-reset data-confirm="<?php esc_attr_e( 'De lijst terugzetten naar Pagina\'s, Media en Profiel?', 'toolkit-sd' ); ?>"><?php esc_html_e( 'Herstel standaard', 'toolkit-sd' ); ?></button>
		</div>
		<template data-allow-template><?php editor_row( '__slug__', '__title__' ); ?></template>
	</div>

	<details class="sde-details" <?php echo ( $s['editor_hide'] || $s['editor_bar_allow'] ) ? 'open' : ''; ?>>
		<summary><?php esc_html_e( 'Meer instellingen', 'toolkit-sd' ); ?></summary>
		<p class="sde-hint"><?php esc_html_e( 'Submenu\'s die toch verborgen blijven, één per regel, bv.', 'toolkit-sd' ); ?> <code>upload.php &gt; webp-converter</code></p>
		<textarea name="<?php echo esc_attr( name( 'editor_hide' ) ); ?>" rows="3" class="sde-code-input"><?php echo esc_textarea( $s['editor_hide'] ); ?></textarea>
		<p class="sde-hint"><?php esc_html_e( 'Admin bar: editors zien hun profiel, afmelden, de link naar de site en "Bewerken". Extra items (ID, één per regel) blijven met alles eronder zichtbaar, bv.', 'toolkit-sd' ); ?> <code>new-content</code></p>
		<textarea name="<?php echo esc_attr( name( 'editor_bar_allow' ) ); ?>" rows="2" class="sde-code-input"><?php echo esc_textarea( $s['editor_bar_allow'] ); ?></textarea>
	</details>

	<div class="sde-block">
		<h3><?php esc_html_e( 'Overnemen uit ASE', 'toolkit-sd' ); ?></h3>
		<p class="sde-hint"><?php esc_html_e( 'Neemt het menu over zoals editors het nu in ASE zien. ASE zelf wordt niet gewijzigd. Volgorde en namen van het menu blijven in ASE.', 'toolkit-sd' ); ?></p>
		<?php if ( ! empty( $imp['time'] ) ) : ?>
			<div class="sde-report">
				<p>
					<?php
					/* translators: %s: datum en uur */
					echo esc_html( sprintf( __( 'Laatste overname: %s', 'toolkit-sd' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $imp['time'] ) ) );
					?>
				</p>
				<ul>
					<?php foreach ( (array) ( $imp['report'] ?? array() ) as $line ) : ?>
						<li><?php echo esc_html( $line ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<div class="sde-actions sde-spaced">
			<a class="sde-btn" href="<?php echo esc_url( editor_import_url() ); ?>" data-confirm="<?php esc_attr_e( 'De lijst voor editors vervangen door wat in ASE staat? Niet opgeslagen wijzigingen gaan verloren.', 'toolkit-sd' ); ?>"><?php esc_html_e( 'Importeer uit ASE', 'toolkit-sd' ); ?></a>
		</div>
	</div>
	<hr class="sde-sep">
	<?php
}

/* -------------------------------------------------------------------------
 * Weergave
 * ---------------------------------------------------------------------- */

function render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s     = \SDE\settings( true );
	$roles = roles();
	?>
	<div class="wrap sde-wrap">
	<h1 class="screen-reader-text">Toolkit SD</h1>
	<form id="sde-app" autocomplete="off">

		<header class="sde-top">
			<div class="sde-logo">
				<span class="sde-mark">SD</span>
				<strong>Toolkit SD</strong>
				<span class="sde-ver">v<?php echo esc_html( \SDE\VERSION ); ?></span>
			</div>
			<div class="sde-top__actions">
				<span class="sde-status" data-status>Alles opgeslagen</span>
				<button type="submit" class="sde-btn sde-btn--primary" data-save>Opslaan</button>
			</div>
		</header>

		<nav class="sde-tabs" role="tablist">
			<?php foreach ( tabs() as $key => $label ) : ?>
				<button type="button" role="tab" data-tab="<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo 'overzicht' === $key ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
			<?php endforeach; ?>
		</nav>

		<?php /* ---------------- Overzicht ---------------- */ ?>
		<section class="sde-pane" data-pane="overzicht">
			<h2 class="sde-title">Modules</h2>
			<p class="sde-intro">Zet per site aan wat nodig is. Alles staat standaard uit, dus een nieuwe installatie verandert niets tot je ze instelt.</p>
			<div class="sde-cards">
				<?php foreach ( modules_info() as $key => $info ) : ?>
					<div class="sde-card" data-card="<?php echo esc_attr( $key ); ?>">
						<div class="sde-card__head">
							<h3><?php echo esc_html( $info[0] ); ?></h3>
							<?php toggle( 'sde[modules][' . $key . ']', ! empty( $s['modules'][ $key ] ), '', '', 'data-module="' . esc_attr( $key ) . '"' ); ?>
						</div>
						<p><?php echo esc_html( $info[1] ); ?></p>
						<button type="button" class="sde-link" data-goto="<?php echo esc_attr( $info[2] ); ?>">Instellen →</button>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<?php /* ---------------- REST ---------------- */ ?>
		<section class="sde-pane" data-pane="rest" hidden>
			<div class="sde-panehead">
				<div>
					<h2 class="sde-title">REST API afschermen</h2>
					<p class="sde-intro">Bezoekers zonder login krijgen enkel de routes hieronder. Ingelogde gebruikers en de Novamira-koppeling werken gewoon. Draait ASE op de site, dan sluit ASE af met "Disable REST API" en beheert Toolkit SD de uitzonderingen. Zonder ASE sluit Toolkit SD zelf af.</p>
				</div>
				<?php toggle( '', ! empty( $s['modules']['rest'] ), 'Module aan', '', 'data-mirror="rest"' ); ?>
			</div>
			<?php
			if ( rest_needs_ase() ) {
				note( rest_ase_text() . ' ' . __( 'Tot dan sluit Toolkit SD zelf af.', 'toolkit-sd' ) );
			}
			?>

			<div class="sde-block">
				<h3>Automatisch toelaten</h3>
				<?php toggle( name( 'rest_auto_surecookie' ), ! empty( $s['rest_auto_surecookie'] ), 'SureCookie (cookiebanner, toestemmingen, cookiescan)', 'Nodig voor een werkende cookiebanner. Enkel als SureCookie actief is.' ); ?>
				<?php toggle( name( 'rest_auto_iawp' ), ! empty( $s['rest_auto_iawp'] ), 'Independent Analytics', 'Enkel als Independent Analytics actief is.' ); ?>
				<?php toggle( name( 'rest_auto_novamira' ), ! empty( $s['rest_auto_novamira'] ), 'Inlogkoppeling van Novamira', 'Nodig om via Novamira in te loggen in het beheer. Beveiligd met een eenmalige sleutel.' ); ?>
				<?php toggle( name( 'rest_index_open' ), ! empty( $s['rest_index_open'] ), 'Overzicht /wp-json/ bereikbaar', 'Toont welke routes bestaan, zonder inhoud. Zo stond het ook in ASE.' ); ?>
			</div>

			<div class="sde-block">
				<h3>Extra routes voor bezoekers</h3>
				<p class="sde-hint">Begin van de route, één per regel, bv. <code>contact-form-7/v1/</code>.</p>
				<textarea name="<?php echo esc_attr( name( 'rest_allow' ) ); ?>" rows="4" class="sde-code-input"><?php echo esc_textarea( $s['rest_allow'] ); ?></textarea>
			</div>

			<div class="sde-block">
				<h3>Ingelogde gebruikers</h3>
				<div class="sde-seg" data-seg>
					<label><input type="radio" name="<?php echo esc_attr( name( 'rest_roles_mode' ) ); ?>" value="all" <?php checked( 'all', $s['rest_roles_mode'] ); ?>><span>Iedereen die ingelogd is</span></label>
					<label><input type="radio" name="<?php echo esc_attr( name( 'rest_roles_mode' ) ); ?>" value="some" <?php checked( 'some', $s['rest_roles_mode'] ); ?>><span>Enkel deze rollen</span></label>
				</div>
				<div class="sde-spaced" data-show-if="rest_roles_mode=some">
					<?php role_checks( name( 'rest_roles' ), (array) $s['rest_roles'], array( 'administrator' ) ); ?>
					<p class="sde-hint">Administrators houden altijd toegang. Let op: Elementor en de blokeditor gebruiken de REST API. Laat rollen die pagina's bewerken dus toe.</p>
				</div>
			</div>

			<div class="sde-block">
				<h3>Testen als bezoeker</h3>
				<p class="sde-hint">Vraagt de routes op zonder login, met de opgeslagen instellingen.</p>
				<button type="button" class="sde-btn" data-rest-test>Nu testen</button>
				<div data-rest-result class="sde-spaced"></div>
			</div>
		</section>

		<?php /* ---------------- Login ---------------- */ ?>
		<section class="sde-pane sde-pane--preview" data-pane="login" hidden>
			<div class="sde-split">
				<div class="sde-split__form">
					<div class="sde-panehead">
						<div>
							<h2 class="sde-title">Loginpagina</h2>
							<p class="sde-intro">Wijzigingen zie je meteen in het voorbeeld. Opslaan om ze op de site te zetten.</p>
						</div>
						<?php toggle( '', ! empty( $s['modules']['login'] ), 'Module aan', '', 'data-mirror="login"' ); ?>
					</div>

					<div class="sde-block">
						<h3>Logo en domeinnaam</h3>
						<div class="sde-logo-pick">
							<div class="sde-logo-pick__img" data-logo-preview style="background-image:url('<?php echo esc_url( $s['login_logo'] ? $s['login_logo'] : SDE_URL . 'assets/sonora-logo.svg' ); ?>')"></div>
							<div>
								<input type="hidden" name="<?php echo esc_attr( name( 'login_logo' ) ); ?>" value="<?php echo esc_attr( $s['login_logo'] ); ?>" data-logo-input>
								<button type="button" class="sde-btn" data-logo-pick>Ander logo kiezen</button>
								<button type="button" class="sde-btn sde-btn--ghost" data-logo-reset>Sonora-logo</button>
							</div>
						</div>
						<div class="sde-row2">
							<?php number( 'login_logo_width', 'Breedte logo', $s['login_logo_width'], 'px', 60, 800 ); ?>
							<?php number( 'login_logo_height', 'Hoogte logo', $s['login_logo_height'], 'px', 20, 400 ); ?>
						</div>
						<?php toggle( name( 'login_show_domain' ), ! empty( $s['login_show_domain'] ), 'Domeinnaam onder het logo tonen' ); ?>
						<?php text( 'login_domain_text', 'Eigen tekst onder het logo', $s['login_domain_text'], 'Leeg laten voor de domeinnaam van de site.', wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>
					</div>

					<div class="sde-block">
						<h3>Teksten</h3>
						<div class="sde-row2">
							<?php text( 'login_title', 'Titel inloggen', $s['login_title'] ); ?>
							<?php text( 'login_text', 'Uitleg inloggen', $s['login_text'] ); ?>
							<?php text( 'lost_title', 'Titel wachtwoord vergeten', $s['lost_title'] ); ?>
							<?php text( 'lost_text', 'Uitleg wachtwoord vergeten', $s['lost_text'] ); ?>
							<?php text( 'login_placeholder_user', 'Veld gebruikersnaam', $s['login_placeholder_user'] ); ?>
							<?php text( 'login_placeholder_pass', 'Veld wachtwoord', $s['login_placeholder_pass'] ); ?>
							<?php text( 'login_button_text', 'Knop', $s['login_button_text'] ); ?>
						</div>
					</div>

					<div class="sde-block">
						<h3>Kleuren en vorm</h3>
						<div class="sde-row2">
							<?php color_field( 'login_color_accent', 'Knop', $s['login_color_accent'] ); ?>
							<?php color_field( 'login_color_hover', 'Knop bij aanwijzen', $s['login_color_hover'] ); ?>
							<?php color_field( 'login_color_ink', 'Tekst en logo', $s['login_color_ink'] ); ?>
							<?php number( 'login_radius', 'Afronding knop en velden', $s['login_radius'], 'px', 0, 40 ); ?>
							<?php color_field( 'login_bg_top', 'Achtergrond boven', $s['login_bg_top'] ); ?>
							<?php color_field( 'login_bg_bottom', 'Achtergrond onder', $s['login_bg_bottom'] ); ?>
						</div>
						<button type="button" class="sde-link" data-colors-reset>Sonora-kleuren terugzetten</button>
					</div>

					<div class="sde-block">
						<h3>Onderdelen</h3>
						<?php toggle( name( 'login_show_lost' ), ! empty( $s['login_show_lost'] ), 'Link "Wachtwoord vergeten?" onder de kaart' ); ?>
						<?php toggle( name( 'login_show_remember' ), ! empty( $s['login_show_remember'] ), '"Onthoud mij" tonen' ); ?>
						<?php toggle( name( 'login_show_back' ), ! empty( $s['login_show_back'] ), 'Link terug naar de website' ); ?>
						<?php toggle( name( 'login_show_privacy' ), ! empty( $s['login_show_privacy'] ), 'Link naar de privacyverklaring' ); ?>
						<?php toggle( name( 'login_show_language' ), ! empty( $s['login_show_language'] ), 'Taalkeuze' ); ?>
					</div>

					<div class="sde-block">
						<h3>Eigen CSS</h3>
						<p class="sde-hint">Komt na de standaardstijl. Enkel voor uitzonderingen.</p>
						<textarea name="<?php echo esc_attr( name( 'login_custom_css' ) ); ?>" rows="5" class="sde-code-input"><?php echo esc_textarea( $s['login_custom_css'] ); ?></textarea>
					</div>
				</div>

				<aside class="sde-preview">
					<div class="sde-preview__bar">
						<h2>Voorbeeld</h2>
						<div class="sde-iconbar" role="group" aria-label="Toestel">
							<button type="button" data-device="desktop" aria-pressed="true" title="Desktop"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg></button>
							<button type="button" data-device="phone" aria-pressed="false" title="Gsm"><svg viewBox="0 0 24 24"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg></button>
						</div>
						<div class="sde-iconbar" role="group" aria-label="Scherm">
							<button type="button" data-screen="login" aria-pressed="true">Inloggen</button>
							<button type="button" data-screen="lostpassword" aria-pressed="false">Wachtwoord vergeten</button>
						</div>
					</div>
					<div class="sde-stage" data-stage>
						<div class="sde-screen" data-screenbox><iframe title="Voorbeeld loginpagina" data-preview-frame></iframe></div>
					</div>
				</aside>
			</div>
		</section>

		<?php /* ---------------- Beheermenu ---------------- */ ?>
		<section class="sde-pane" data-pane="menu" hidden>
			<?php editor_block( $s ); ?>

			<div class="sde-panehead">
				<div>
					<h2 class="sde-title">Beheermenu per rol</h2>
					<p class="sde-intro">Kies per menu-item wie het ziet. Het gaat om rollen, niet om rechten: een Manager met dezelfde rechten als een administrator ziet dus toch het eenvoudige menu. Volgorde en namen van het menu stel je in ASE in.</p>
				</div>
				<?php toggle( '', ! empty( $s['modules']['admin_menu'] ), 'Module aan', '', 'data-mirror="admin_menu"' ); ?>
			</div>
			<div class="sde-toolbar">
				<label class="sde-check"><input type="checkbox" data-only-rules> Enkel verborgen items tonen</label>
				<input type="search" placeholder="Zoeken in het menu" data-menu-search>
			</div>
			<table class="sde-table sde-menu-table">
				<thead><tr><th>Menu-item</th><th>Zichtbaarheid</th><th>Rollen</th></tr></thead>
				<tbody>
				<?php
				$snap  = $GLOBALS['sde_menu_snapshot'];
				$rules = rules_by_target();
				$used  = array();
				$i     = 0;
				foreach ( (array) $snap['menu'] as $item ) {
					if ( empty( $item[2] ) || ( isset( $item[4] ) && false !== strpos( (string) $item[4], 'wp-menu-separator' ) ) ) {
						continue;
					}
					$slug   = html_entity_decode( (string) $item[2] );
					$used[] = $slug;
					menu_row( $i++, $slug, menu_title( $item[0] ), false, $rules[ $slug ] ?? null );
					foreach ( (array) ( $snap['submenu'][ $item[2] ] ?? array() ) as $sub ) {
						if ( empty( $sub[2] ) ) {
							continue;
						}
						$target = $slug . ' > ' . html_entity_decode( (string) $sub[2] );
						$used[] = $target;
						menu_row( $i++, $target, menu_title( $sub[0] ), true, $rules[ $target ] ?? null );
					}
				}
				$extra = array();
				foreach ( $rules as $target => $rule ) {
					if ( ! in_array( $target, $used, true ) ) {
						$extra[] = $rule['line'];
					}
				}
				?>
				</tbody>
			</table>
			<details class="sde-details" <?php echo $extra ? 'open' : ''; ?>>
				<summary>Extra regels (items die niet in de lijst staan)</summary>
				<p class="sde-hint">Eén per regel: <code>plugins.php | behalve: administrator</code>, <code>upload.php &gt; webp-converter | behalve: administrator</code>, <code>tools.php | alleen: editor</code> of <code>… | iedereen</code>.</p>
				<textarea name="sde[menu_extra]" rows="4" class="sde-code-input"><?php echo esc_textarea( implode( "\n", $extra ) ); ?></textarea>
			</details>

			<div class="sde-block">
				<h3>Admin bar: items verwijderen</h3>
				<p class="sde-hint">Geldt voor iedereen. ID van het item, één per regel, bv. <code>search</code>, <code>wp-logo</code>, <code>comments</code>.</p>
				<textarea name="<?php echo esc_attr( name( 'admin_bar_nodes' ) ); ?>" rows="3" class="sde-code-input"><?php echo esc_textarea( $s['admin_bar_nodes'] ); ?></textarea>
			</div>
		</section>

		<?php /* ---------------- Doorsturen ---------------- */ ?>
		<section class="sde-pane" data-pane="redirects" hidden>
			<div class="sde-panehead">
				<div>
					<h2 class="sde-title">Doorsturen na in- en uitloggen</h2>
					<p class="sde-intro">Per rol een pad op deze site, bv. <code>/dashboard/</code>. Leeg = de standaard van WordPress.</p>
				</div>
				<?php toggle( '', ! empty( $s['modules']['login_redirect'] ), 'Module aan', '', 'data-mirror="login_redirect"' ); ?>
			</div>
			<?php
			$in_map  = \SDE\pairs( $s['login_redirects'] );
			$out_map = \SDE\pairs( $s['logout_redirects'] );

			// Beide aan: ASE stuurt meteen door, waardoor Toolkit SD niet meer aan bod komt.
			$ase_on = \SDE\Ase\ase_redirects_on();
			if ( $ase_on ) {
				$names = array(
					'login'  => 'Redirect after login',
					'logout' => 'Redirect after logout',
				);
				note(
					sprintf(
						/* translators: %s: naam van de functie(s) in ASE */
						__( 'Let op: in ASE staat "%s" ook aan. ASE stuurt meteen door, waardoor wat je hier instelt niet meer aan bod komt. Zet het in ASE uit, of zet deze module uit als alle rollen naar hetzelfde adres gaan.', 'toolkit-sd' ),
						implode( '" en "', array_intersect_key( $names, array_flip( $ase_on ) ) )
					),
					'warn',
					'data-if-module="login_redirect"'
				);
			}
			if ( \SDE\Ase\ase_active() && redirects_uniform( $s ) ) {
				note( __( 'Tip: alle rollen gaan naar hetzelfde adres. Dit kan ook met ASE gratis.', 'toolkit-sd' ), 'tip', 'data-if-module="login_redirect"' );
			}
			?>
			<table class="sde-table">
				<thead><tr><th>Rol</th><th>Na inloggen</th><th>Na uitloggen</th></tr></thead>
				<tbody>
				<?php foreach ( $roles as $key => $label ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?><small><?php echo esc_html( $key ); ?></small></th>
						<td><input type="text" name="sde[login_redirect][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $in_map[ $key ] ?? '' ); ?>" placeholder="standaard"></td>
						<td><input type="text" name="sde[logout_redirect][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $out_map[ $key ] ?? '' ); ?>" placeholder="standaard"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>

		<?php /* ---------------- Extra ---------------- */ ?>
		<section class="sde-pane" data-pane="extra" hidden>
			<h2 class="sde-title">Extra</h2>
			<p class="sde-intro">Kleinere aanpassingen. Elke module zet je aan of uit in het Overzicht of hieronder.</p>

			<div class="sde-block">
				<div class="sde-blockhead"><h3>Admin bar verbergen in de backend</h3><?php toggle( '', ! empty( $s['modules']['admin_bar'] ), 'Aan', '', 'data-mirror="admin_bar"' ); ?></div>
				<p class="sde-hint">Voor deze rollen verdwijnt de zwarte balk bovenaan in het beheer.</p>
				<?php role_checks( name( 'admin_bar_roles' ), \SDE\lines( $s['admin_bar_roles'] ) ); ?>
			</div>

			<div class="sde-block">
				<div class="sde-blockhead"><h3>Mediacategorieën</h3><?php toggle( '', ! empty( $s['modules']['media_categories'] ), 'Aan', '', 'data-mirror="media_categories"' ); ?></div>
				<p class="sde-hint">Categorieën beheer je onder Media &gt; Mediacategorieën. Bestaande categorieën uit ASE Pro blijven behouden.</p>
				<?php toggle( name( 'media_grid_filter' ), ! empty( $s['media_grid_filter'] ), 'Filter ook in de rasterweergave en het mediavenster', 'Ook in Elementor. In de lijstweergave staat de filter er altijd.' ); ?>
			</div>

			<div class="sde-block">
				<div class="sde-blockhead"><h3>E-mailadressen verbergen voor spambots</h3><?php toggle( '', ! empty( $s['modules']['email_obfuscate'] ), 'Aan', '', 'data-mirror="email_obfuscate"' ); ?></div>
				<p class="sde-hint">Bezoekers zien en gebruiken het adres gewoon. Eenvoudige spambots kunnen het niet lezen.</p>

				<?php toggle( name( 'email_shortcode' ), ! empty( $s['email_shortcode'] ), 'Shortcode [obfuscate]', 'Zelfde schrijfwijze als in ASE. Een klikbare link (link="yes") werkt ook, zoals in ASE Pro. Vervangt de shortcode van ASE. Gebruik je geen link, subject of text, dan volstaat de shortcode van ASE gratis.' ); ?>
				<?php toggle( name( 'email_auto' ), ! empty( $s['email_auto'] ), 'Alle e-mailadressen in de inhoud automatisch verbergen', 'Ook gewone mailto-links en adressen die niet via de shortcode staan.' ); ?>
				<?php toggle( name( 'email_visitors_only' ), ! empty( $s['email_visitors_only'] ), 'Enkel voor bezoekers die niet ingelogd zijn', 'Ingelogd zie je het adres zoals het in de inhoud staat.' ); ?>

				<div class="sde-field sde-spaced">
					<label>Methode</label>
					<div class="sde-seg">
						<label><input type="radio" name="<?php echo esc_attr( name( 'email_mode' ) ); ?>" value="entities" <?php checked( 'entities', $s['email_mode'] ); ?>><span>Gecodeerde tekens (aanbevolen)</span></label>
						<label><input type="radio" name="<?php echo esc_attr( name( 'email_mode' ) ); ?>" value="reverse" <?php checked( 'reverse', $s['email_mode'] ); ?>><span>Omgekeerde tekst (zoals ASE)</span></label>
					</div>
					<p class="sde-hint">Gecodeerde tekens werkt in elke browser en page builder, en kopiëren en plakken geeft het juiste adres. Omgekeerde tekst is wat ASE standaard deed en geldt enkel voor de shortcode.</p>
				</div>

				<div class="sde-row2">
					<div class="sde-field">
						<label>Standaardweergave van de shortcode</label>
						<div class="sde-seg">
							<label><input type="radio" name="<?php echo esc_attr( name( 'email_default_display' ) ); ?>" value="newline" <?php checked( 'newline', $s['email_default_display'] ); ?>><span>Op een eigen regel</span></label>
							<label><input type="radio" name="<?php echo esc_attr( name( 'email_default_display' ) ); ?>" value="inline" <?php checked( 'inline', $s['email_default_display'] ); ?>><span>In de tekst</span></label>
						</div>
					</div>
					<div class="sde-field">
						<label>Standaard klikbaar</label>
						<div class="sde-seg">
							<label><input type="radio" name="<?php echo esc_attr( name( 'email_default_link' ) ); ?>" value="no" <?php checked( 'no', $s['email_default_link'] ); ?>><span>Gewone tekst</span></label>
							<label><input type="radio" name="<?php echo esc_attr( name( 'email_default_link' ) ); ?>" value="yes" <?php checked( 'yes', $s['email_default_link'] ); ?>><span>Klikbare link</span></label>
						</div>
					</div>
				</div>
				<p class="sde-hint">Geldt wanneer display="" of link="" ontbreekt in de shortcode. Wat in de shortcode staat, gaat altijd voor.</p>

				<details class="sde-details" data-ignore>
					<summary>Shortcode maken</summary>
					<div class="sde-row2" data-sc-builder>
						<div class="sde-field"><label for="sde-sc-email">E-mailadres</label><input type="text" id="sde-sc-email" data-sc="email" value="<?php echo esc_attr( email_guess() ); ?>"></div>
						<div class="sde-field"><label for="sde-sc-text">Linktekst (optioneel)</label><input type="text" id="sde-sc-text" data-sc="text" placeholder="Standaard het adres zelf"></div>
						<div class="sde-field"><label for="sde-sc-subject">Onderwerp van de mail (optioneel)</label><input type="text" id="sde-sc-subject" data-sc="subject" placeholder="Bijvoorbeeld: Vraag via de website"></div>
						<div class="sde-field"><label for="sde-sc-class">CSS-klasse (optioneel)</label><input type="text" id="sde-sc-class" data-sc="class"></div>
						<div class="sde-field">
							<label>Weergave</label>
							<div class="sde-seg">
								<label><input type="radio" name="sde-sc-display" data-sc="display" value="inline" checked><span>In de tekst</span></label>
								<label><input type="radio" name="sde-sc-display" data-sc="display" value="newline"><span>Op een eigen regel</span></label>
							</div>
						</div>
						<div class="sde-field">
							<label>Klikbaar</label>
							<div class="sde-seg">
								<label><input type="radio" name="sde-sc-link" data-sc="link" value="yes" checked><span>Klikbare link</span></label>
								<label><input type="radio" name="sde-sc-link" data-sc="link" value="no"><span>Gewone tekst</span></label>
							</div>
						</div>
					</div>
					<div class="sde-sc-out">
						<code data-sc-out></code>
						<button type="button" class="sde-btn" data-sc-copy>Kopiëren</button>
					</div>
				</details>

				<?php $used = email_usage(); ?>
				<details class="sde-details"<?php echo $used ? ' open' : ''; ?>>
					<summary>Gebruik op deze site (<?php echo (int) count( $used ); ?>)</summary>
					<?php if ( $used ) : ?>
						<ul class="sde-used">
							<?php foreach ( $used as $pid ) : ?>
								<li><a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $pid ) ?: '(zonder titel)' ); ?></a> <small><?php echo esc_html( get_post_status( $pid ) ); ?></small></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="sde-hint">Geen pagina's met de shortcode gevonden.</p>
					<?php endif; ?>
				</details>
			</div>

			<div class="sde-block">
				<div class="sde-blockhead"><h3>Beheermeldingen verbergen</h3><?php toggle( '', ! empty( $s['modules']['hide_notices'] ), 'Aan', '', 'data-mirror="hide_notices"' ); ?></div>
				<p class="sde-hint">Deze rollen blijven de meldingen zien:</p>
				<?php role_checks( name( 'notices_roles' ), \SDE\lines( $s['notices_roles'] ) ); ?>
			</div>

			<div class="sde-block">
				<div class="sde-blockhead"><h3>Publieke preview van concepten</h3><?php toggle( '', ! empty( $s['modules']['public_preview'] ), 'Aan', '', 'data-mirror="public_preview"' ); ?></div>
				<p class="sde-hint">Deel een concept via een geheime link. Wie de link heeft, ziet het concept zonder in te loggen. In de editor staat daarvoor het paneel "Publieke preview"; op de previewpagina (ook van Elementor) staat de knop "Publieke preview kopiëren" in de admin bar.</p>
				<p class="sde-hint">De link vervalt vanzelf na het aantal dagen hieronder, en meteen als je publiceert of het bericht weggooit. Zoekmachines en de cache slaan de preview niet op. Links uit ASE worden niet overgenomen: maak na de overstap nieuwe links.</p>

				<div class="sde-field sde-spaced">
					<label>Berichttypes</label>
					<div class="sde-chips">
						<?php $picked = \SDE\lines( $s['preview_post_types'] ); ?>
						<?php foreach ( preview_types() as $key => $label ) : ?>
							<label class="sde-chip"><input type="checkbox" name="<?php echo esc_attr( name( 'preview_post_types' ) ); ?>[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $picked, true ) ); ?>><span><?php echo esc_html( $label ); ?></span></label>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="sde-row2">
					<?php number( 'preview_days', 'Link geldig gedurende', $s['preview_days'], 'dagen', 1, 30 ); ?>
				</div>
				<div class="sde-field sde-spaced">
					<label>Wie mag een link maken</label>
					<?php role_checks( name( 'preview_roles' ), \SDE\lines( $s['preview_roles'] ), array( 'administrator' ) ); ?>
					<p class="sde-hint">Administrators mogen het altijd. Andere rollen enkel bij berichten die ze zelf mogen bewerken.</p>
				</div>
			</div>
		</section>

		<?php /* ---------------- ASE ---------------- */ ?>
		<section class="sde-pane" data-pane="ase" hidden>
			<h2 class="sde-title">Overstap van ASE Pro</h2>
			<p class="sde-intro">Gevonden: <strong><?php echo esc_html( ase_status() ); ?></strong>.<?php echo get_option( \SDE\Ase\BACKUP_OPT ) ? ' Er is een back-up van de ASE-instellingen.' : ''; ?></p>
			<p class="sde-intro">Wat ASE gratis kan, blijft in ASE. Toolkit SD neemt enkel over wat Pro is, zodat geen functie dubbel werkt.</p>
			<ol class="sde-steps">
				<li><strong>Bekijk</strong> wat Toolkit SD overneemt en wat in ASE blijft.</li>
				<li><strong>Overnemen</strong> vult de instellingen van deze plugin (overschrijft wat hier staat).</li>
				<li><strong>ASE-functies uitzetten</strong> zet in ASE uit wat Toolkit SD overgenomen heeft, en zet aan of om wat in ASE gratis blijft (bv. Disable REST API). Er wordt eerst een back-up gemaakt.</li>
				<li>Vervang daarna ASE Pro door de gratis versie van ASE.</li>
			</ol>
			<div class="sde-actions">
				<button type="button" class="sde-btn" data-ase="preview">Bekijk wat overgenomen wordt</button>
				<button type="button" class="sde-btn" data-ase="import" data-confirm="De instellingen van deze plugin overschrijven met wat in ASE staat?">Overnemen uit ASE</button>
				<button type="button" class="sde-btn" data-ase="switch" data-confirm="ASE afstemmen op Toolkit SD? Wat Toolkit SD overgenomen heeft gaat in ASE uit, wat gratis in ASE blijft gaat aan of wordt omgezet. Er wordt eerst een back-up gemaakt.">ASE-functies uitzetten</button>
				<button type="button" class="sde-btn sde-btn--danger" data-ase="restore" data-confirm="ASE-instellingen terugzetten uit de back-up?">ASE terugzetten</button>
			</div>
			<div data-ase-result class="sde-spaced"></div>
		</section>

	</form>
	<div class="sde-toast" data-toast hidden></div>
	</div>
	<?php
}

function ase_status() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$out = array();
	foreach ( get_plugins() as $file => $p ) {
		if ( 0 === strpos( $file, 'admin-site-enhancements' ) ) {
			$out[] = $p['Name'] . ' ' . $p['Version'] . ( is_plugin_active( $file ) ? ' (actief)' : ' (inactief)' );
		}
	}
	return $out ? implode( ', ', $out ) : 'geen ASE';
}
