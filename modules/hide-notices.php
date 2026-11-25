<?php
/**
 * Module: beheermeldingen (van plugins en WordPress) verbergen,
 * behalve voor de gekozen rollen (standaard: administrator).
 * Zo zien klanten geen update- of reclameberichten in het dashboard.
 */

namespace SDE\HideNotices;

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_print_scripts',
	function () {
		$roles = \SDE\lines( \SDE\settings()['notices_roles'] );
		if ( ! $roles ) {
			$roles = array( 'administrator' );
		}
		if ( \SDE\user_has_role( $roles ) ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
	},
	PHP_INT_MAX
);
