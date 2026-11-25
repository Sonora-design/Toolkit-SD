<?php
/**
 * Module: doorsturen na inloggen en na uitloggen, per rol.
 *
 * Eén regel per rol, bv.:
 *   editor=/dashboard/
 *   manager=/dashboard/
 * Rollen zonder regel gaan naar de standaardpagina van WordPress.
 */

namespace SDE\LoginRedirect;

defined( 'ABSPATH' ) || exit;

function target( $setting, $user ) {
	if ( ! ( $user instanceof \WP_User ) ) {
		return '';
	}
	// Bij meerdere rollen: wie de site mag instellen, volgt enkel een regel voor administrator.
	$admin = user_can( $user, 'manage_options' );
	foreach ( \SDE\pairs( \SDE\settings()[ $setting ] ) as $role => $path ) {
		if ( $admin && 'administrator' !== $role ) {
			continue;
		}
		if ( '' !== $path && in_array( $role, (array) $user->roles, true ) ) {
			return home_url( '/' . ltrim( $path, '/' ) );
		}
	}
	return '';
}

add_filter(
	'login_redirect',
	function ( $redirect_to, $requested, $user ) {
		$url = target( 'login_redirects', $user );
		return $url ? $url : $redirect_to;
	},
	99,
	3
);

add_filter(
	'logout_redirect',
	function ( $redirect_to, $requested, $user ) {
		$url = target( 'logout_redirects', $user );
		return $url ? $url : $redirect_to;
	},
	99,
	3
);
