<?php
/**
 * Module: admin bar verbergen in de backend voor bepaalde rollen.
 * Eén rol per regel, bv. editor of manager.
 */

namespace SDE\AdminBar;

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_head',
	function () {
		$roles = \SDE\lines( \SDE\settings()['admin_bar_roles'] );
		if ( ! $roles || ! \SDE\user_has_role( $roles ) ) {
			return;
		}
		echo '<style id="sde-admin-bar">html.wp-toolbar{padding-top:0}#wpadminbar{display:none}</style>';
	}
);
