<?php
/**
 * Module: REST API afsluiten voor bezoekers, met uitzonderingen.
 *
 * - Ingelogde gebruikers (ook via applicatiewachtwoord, bv. Novamira): toegang,
 *   eventueel beperkt tot bepaalde rollen.
 * - Bezoekers: enkel de toegelaten routes (en desgewenst de index /wp-json/).
 * - SureCookie en Independent Analytics worden automatisch toegelaten als ze actief zijn.
 * - Werkt ook als ASE "Disable REST API" nog aan staat: toegelaten routes worden dan vrijgegeven.
 */

namespace SDE\Rest;

defined( 'ABSPATH' ) || exit;

function is_plugin_active_safe( $file ) {
	return in_array( $file, (array) get_option( 'active_plugins', array() ), true );
}

/**
 * Lijst van toegelaten routes (begin van de route).
 */
function allowed_prefixes() {
	$s    = \SDE\settings();
	$list = \SDE\lines( $s['rest_allow'] );

	if ( ! empty( $s['rest_auto_surecookie'] ) && is_plugin_active_safe( 'surecookie/surecookie.php' ) ) {
		$list[] = 'surecookie/v1/';
	}
	if ( ! empty( $s['rest_auto_iawp'] ) && ( is_plugin_active_safe( 'independent-analytics/iawp.php' ) || is_plugin_active_safe( 'independent-analytics-pro/iawp.php' ) ) ) {
		$list[] = 'iawp/';
	}
	if ( ! empty( $s['rest_auto_novamira'] ) && is_plugin_active_safe( 'novamira/novamira.php' ) ) {
		$list[] = 'novamira/v1/admin-access';
	}

	$list = array_map(
		function ( $p ) {
			return ltrim( trim( $p ), '/' );
		},
		$list
	);

	return array_values( array_unique( array_filter( $list ) ) );
}

function current_route() {
	$route = '';
	if ( isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
		$route = (string) $GLOBALS['wp']->query_vars['rest_route'];
	}
	return trim( $route, '/' );
}

function is_allowed( $route ) {
	if ( '' === $route ) {
		return ! empty( \SDE\settings()['rest_index_open'] );
	}
	foreach ( allowed_prefixes() as $prefix ) {
		if ( 0 === strpos( $route, $prefix ) || rtrim( $prefix, '/' ) === $route ) {
			return true;
		}
	}
	return false;
}

function blocked() {
	return new \WP_Error(
		'rest_api_authentication_required',
		'De REST API is enkel beschikbaar voor ingelogde gebruikers.',
		array( 'status' => rest_authorization_required_code() )
	);
}

add_filter(
	'rest_authentication_errors',
	function ( $result ) {
		$s = \SDE\settings();

		if ( is_user_logged_in() ) {
			// Administrators houden altijd toegang (ook de Novamira-koppeling).
			$roles = array_merge( array( 'administrator' ), (array) $s['rest_roles'] );
			if ( 'some' === $s['rest_roles_mode'] && ! \SDE\user_has_role( $roles ) && ! is_allowed( current_route() ) ) {
				return is_wp_error( $result ) ? $result : blocked();
			}
			return $result;
		}

		if ( is_allowed( current_route() ) ) {
			// Een blokkering door ASE (of door ons) vrijgeven; andere fouten blijven staan.
			if ( is_wp_error( $result ) && 'rest_api_authentication_required' === $result->get_error_code() ) {
				return null;
			}
			return $result;
		}

		return empty( $result ) ? blocked() : $result;
	},
	99
);
