<?php
/**
 * Module: beheermenu vereenvoudigen per rol.
 *
 * Eén regel per menu-item:
 *   edit.php                          -> verborgen voor iedereen behalve administrator (standaard)
 *   plugins.php | behalve: administrator
 *   upload.php > webp-converter | behalve: administrator, manager
 *   tools.php | alleen: editor
 *   edit-tags.php?taxonomy=x | iedereen
 *
 * "behalve" = verborgen voor iedereen, behalve deze rollen.
 * "alleen"  = enkel verborgen voor deze rollen.
 * Rollen worden op naam gecontroleerd, niet op rechten: een Manager met
 * dezelfde rechten als een administrator ziet dus toch het vereenvoudigde menu.
 *
 * Volgorde en namen van het menu blijven in ASE (gratis functie).
 */

namespace SDE\AdminMenu;

defined( 'ABSPATH' ) || exit;

/**
 * Regels inlezen.
 *
 * @return array[] Elke regel: parent (of ''), slug, mode (except|only|all), roles.
 */
function rules() {
	$out = array();
	foreach ( \SDE\lines( \SDE\settings()['admin_menu_rules'] ) as $line ) {
		$parts  = array_map( 'trim', explode( '|', $line, 2 ) );
		$target = $parts[0];
		$rule   = isset( $parts[1] ) ? strtolower( $parts[1] ) : 'behalve: administrator';

		$parent = '';
		$slug   = $target;
		if ( false !== strpos( $target, ' > ' ) ) {
			list( $parent, $slug ) = array_map( 'trim', explode( ' > ', $target, 2 ) );
		}

		$mode  = 'except';
		$roles = array();
		if ( 0 === strpos( $rule, 'iedereen' ) ) {
			$mode = 'all';
		} elseif ( preg_match( '/^(behalve|alleen)\s*:\s*(.*)$/', $rule, $m ) ) {
			$mode  = ( 'alleen' === $m[1] ) ? 'only' : 'except';
			$roles = array_filter( array_map( 'trim', explode( ',', $m[2] ) ) );
		}

		$out[] = array(
			'parent' => $parent,
			'slug'   => $slug,
			'mode'   => $mode,
			'roles'  => $roles,
		);
	}
	return $out;
}

function hidden_for_current_user( array $rule ) {
	switch ( $rule['mode'] ) {
		case 'all':
			return true;
		case 'only':
			return \SDE\user_has_role( $rule['roles'] );
		default: // except
			return ! \SDE\user_has_role( $rule['roles'] );
	}
}

function same_slug( $a, $b ) {
	$norm = function ( $s ) {
		return html_entity_decode( (string) $s, ENT_QUOTES );
	};
	return $norm( $a ) === $norm( $b );
}

add_action(
	'admin_menu',
	function () {
		global $menu, $submenu;

		foreach ( rules() as $rule ) {
			if ( ! hidden_for_current_user( $rule ) ) {
				continue;
			}

			if ( '' === $rule['parent'] ) {
				foreach ( (array) $menu as $i => $item ) {
					if ( isset( $item[2] ) && same_slug( $item[2], $rule['slug'] ) ) {
						unset( $menu[ $i ] );
					}
				}
				continue;
			}

			foreach ( array( $rule['parent'], html_entity_decode( $rule['parent'] ) ) as $parent ) {
				if ( empty( $submenu[ $parent ] ) ) {
					continue;
				}
				foreach ( $submenu[ $parent ] as $i => $item ) {
					if ( isset( $item[2] ) && same_slug( $item[2], $rule['slug'] ) ) {
						unset( $submenu[ $parent ][ $i ] );
					}
				}
			}
		}
	},
	PHP_INT_MAX
);

// Knooppunten uit de admin bar halen (voor iedereen), bv. "search".
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		foreach ( \SDE\lines( \SDE\settings()['admin_bar_nodes'] ) as $node ) {
			$bar->remove_node( $node );
		}
	},
	PHP_INT_MAX
);
