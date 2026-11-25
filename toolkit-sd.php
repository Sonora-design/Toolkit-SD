<?php
/**
 * Plugin Name:       Toolkit SD
 * Description:       Vaste toolkit van Sonora design voor praktijkwebsites: REST-afscherming, loginpagina in Sonora-stijl, beheermenu per rol, redirect na inloggen en meer. Vervangt de Pro-functies van ASE.
 * Version:           1.4.1
 * Author:            Sonora design
 * Author URI:        https://sonoradesign.be
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * Text Domain:       toolkit-sd
 */

namespace SDE;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.4.1';
const OPTION  = 'sde_settings';
define( 'SDE_FILE', __FILE__ );
define( 'SDE_DIR', plugin_dir_path( __FILE__ ) );
define( 'SDE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Standaardinstellingen. Alle modules staan standaard uit,
 * zodat een nieuwe installatie niets verandert tot ze ingesteld is.
 */
function defaults() {
	return array(
		'modules'             => array(
			'rest'             => false,
			'login'            => false,
			'admin_menu'       => false,
			'login_redirect'   => false,
			'admin_bar'        => false,
			'media_categories' => false,
			'email_obfuscate'  => false,
			'hide_notices'     => false,
		),

		// REST API.
		'rest_allow'          => '',      // Extra routes voor bezoekers, één per regel.
		'rest_auto_surecookie' => true,   // SureCookie automatisch toelaten.
		'rest_auto_iawp'      => true,    // Independent Analytics automatisch toelaten.
		'rest_auto_novamira'  => true,    // Inlogkoppeling van Novamira (beveiligd met eenmalige sleutel).
		'rest_index_open'     => true,    // /wp-json/ zelf bereikbaar voor bezoekers.
		'rest_roles_mode'     => 'all',   // all = elke ingelogde gebruiker, some = enkel rest_roles.
		'rest_roles'          => array( 'administrator' ),

		// Loginpagina.
		'login_logo'          => '',      // Leeg = Sonora-logo uit de plugin.
		'login_logo_width'    => 420,
		'login_logo_height'   => 112,
		'login_show_domain'   => true,
		'login_domain_text'   => '',      // Leeg = domeinnaam van de site.
		'login_title'         => 'Log in',
		'login_text'          => 'om je website te beheren.',
		'lost_title'          => 'Wachtwoord vergeten',
		'lost_text'           => 'Je krijgt een link om een nieuw wachtwoord te kiezen.',
		'login_placeholder_user' => 'E-mail',
		'login_placeholder_pass' => 'Wachtwoord',
		'login_button_text'   => 'Inloggen',
		'login_color_accent'  => '#006fa0',
		'login_color_hover'   => '#005a82',
		'login_color_ink'     => '#001f58',
		'login_bg_top'        => '#f4f6fa',
		'login_bg_bottom'     => '#c7d1e7',
		'login_radius'        => 20,
		'login_show_lost'     => true,
		'login_show_remember' => false,
		'login_show_back'     => false,
		'login_show_privacy'  => false,
		'login_show_language' => false,
		'login_custom_css'    => '',

		// Beheermenu.
		'admin_menu_rules'    => '',      // Eén regel per item, zie modules/admin-menu.php.
		'admin_bar_nodes'     => '',      // Items uit de admin bar, één per regel.

		// Redirects: rol=/pad/ per regel.
		'login_redirects'     => '',
		'logout_redirects'    => '',

		// Admin bar in de backend: rollen, één per regel.
		'admin_bar_roles'     => '',

		// Mediacategorieën.
		'media_grid_filter'   => true,

		// E-mailadressen.
		'email_shortcode'       => true,       // Shortcode [obfuscate] (vervangt die van ASE).
		'email_auto'            => false,      // Alle adressen in de inhoud automatisch verbergen.
		'email_visitors_only'   => false,
		'email_mode'            => 'entities', // entities | reverse (zoals ASE).
		'email_default_link'    => 'no',       // Standaard voor de shortcode als link="" ontbreekt.
		'email_default_display' => 'newline',  // Standaard voor de shortcode als display="" ontbreekt.

		// Beheermeldingen: rollen die ze wél blijven zien.
		'notices_roles'       => "administrator",
	);
}

/**
 * Instellingen ophalen, aangevuld met de standaardwaarden.
 *
 * @param bool $fresh Cache overslaan.
 */
function settings( $fresh = false ) {
	static $cache = null;
	if ( null === $cache || $fresh ) {
		$saved = get_option( OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$def   = defaults();
		$cache = array_merge( $def, $saved );
		$cache['modules'] = array_merge( $def['modules'], isset( $saved['modules'] ) && is_array( $saved['modules'] ) ? $saved['modules'] : array() );
	}
	return $cache;
}

function module_on( $key ) {
	$s = settings();
	return ! empty( $s['modules'][ $key ] );
}

/**
 * Tekstveld met één waarde per regel omzetten naar een lijst.
 */
function lines( $text ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' !== $line && '#' !== $line[0] ) {
			$out[] = $line;
		}
	}
	return $out;
}

/**
 * Regels "sleutel=waarde" omzetten naar een array.
 */
function pairs( $text ) {
	$out = array();
	foreach ( lines( $text ) as $line ) {
		if ( false === strpos( $line, '=' ) ) {
			continue;
		}
		list( $k, $v ) = array_map( 'trim', explode( '=', $line, 2 ) );
		if ( '' !== $k ) {
			$out[ $k ] = $v;
		}
	}
	return $out;
}

/**
 * Heeft de huidige gebruiker (of de opgegeven gebruiker) één van deze rollen?
 */
function user_has_role( array $roles, $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return false;
	}
	return (bool) array_intersect( $roles, (array) $user->roles );
}

// Modules laden.
$sde_modules = array(
	'rest'             => 'modules/rest.php',
	'login'            => 'modules/login.php',
	'admin_menu'       => 'modules/admin-menu.php',
	'login_redirect'   => 'modules/login-redirect.php',
	'admin_bar'        => 'modules/admin-bar.php',
	'media_categories' => 'modules/media-categories.php',
	'email_obfuscate'  => 'modules/email-obfuscate.php',
	'hide_notices'     => 'modules/hide-notices.php',
);
foreach ( $sde_modules as $sde_key => $sde_file ) {
	if ( module_on( $sde_key ) ) {
		require_once SDE_DIR . $sde_file;
	}
}
unset( $sde_modules, $sde_key, $sde_file );

// Updates via sonoradesign.be, overname uit ASE en de beheerpagina.
require_once SDE_DIR . 'includes/updater.php';
require_once SDE_DIR . 'includes/ase-import.php';
if ( is_admin() ) {
	require_once SDE_DIR . 'includes/admin.php';
}
