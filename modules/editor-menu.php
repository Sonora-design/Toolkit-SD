<?php
/**
 * Module: vast, afgeschermd beheermenu voor editors.
 *
 * Werkt met een lijst van toegelaten menu-items (editor_allow, één slug per regel).
 * Alles wat niet in die lijst staat, verdwijnt, ook items die plugins later toevoegen.
 * Daarnaast:
 *  - editor_hide: submenu's die binnen een toegelaten item toch verborgen blijven ("parent > slug").
 *  - de admin bar toont enkel het eigen profiel en afmelden (plus editor_bar_allow);
 *  - wie rechtstreeks naar een verborgen beheerpagina surft, krijgt een nette melding.
 *
 * Keuze Dashboard: index.php blijft altijd bereikbaar (WordPress stuurt er zelf vaak naartoe),
 * maar het menu-item staat er enkel als het in de lijst staat. Na het inloggen stuurt de module
 * Doorsturen editors naar /dashboard/.
 *
 * Er worden geen rechten van rollen gewijzigd: alles gebeurt tijdens het laden van de pagina.
 * Welke rollen afgeschermd worden: filter toolkit_sd_restricted_roles (standaard enkel editor).
 */

namespace SDE\EditorMenu;

defined( 'ABSPATH' ) || exit;

function allow_list() {
	return array_map( 'html_entity_decode', \SDE\lines( \SDE\settings()['editor_allow'] ) );
}

function hide_list() {
	return array_map( 'html_entity_decode', \SDE\lines( \SDE\settings()['editor_hide'] ) );
}

/* -------------------------------------------------------------------------
 * Menu links
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		if ( ! \SDE\is_restricted_user() ) {
			return;
		}
		global $menu, $submenu;
		$allow = allow_list();
		$hide  = hide_list();

		foreach ( (array) $menu as $i => $item ) {
			$slug = isset( $item[2] ) ? html_entity_decode( (string) $item[2] ) : '';
			if ( '' === $slug || ! in_array( $slug, $allow, true ) ) {
				// Ook scheidingslijnen weg: met een kort menu staan die er los bij.
				unset( $menu[ $i ] );
				continue;
			}
			foreach ( array( $item[2], $slug ) as $parent ) {
				if ( empty( $submenu[ $parent ] ) ) {
					continue;
				}
				foreach ( $submenu[ $parent ] as $j => $sub ) {
					if ( isset( $sub[2] ) && in_array( $slug . ' > ' . html_entity_decode( (string) $sub[2] ), $hide, true ) ) {
						unset( $submenu[ $parent ][ $j ] );
					}
				}
			}
		}

		// Submenu's van verborgen items weghalen, zodat ze ook niet via een omweg verschijnen.
		$kept = array();
		foreach ( (array) $menu as $item ) {
			$kept[] = html_entity_decode( (string) $item[2] );
		}
		foreach ( array_keys( (array) $submenu ) as $parent ) {
			if ( ! in_array( html_entity_decode( (string) $parent ), $kept, true ) ) {
				unset( $submenu[ $parent ] );
			}
		}
	},
	PHP_INT_MAX - 2 // Zo laat mogelijk, zodat ook items van plugins die laat inhaken meegenomen worden.
);

/* -------------------------------------------------------------------------
 * Toegang: verborgen pagina's ook echt afsluiten
 * ---------------------------------------------------------------------- */

/**
 * Past de huidige aanvraag bij deze menu-slug?
 *
 * @param string $slug    Menu-slug, bv. "edit.php?post_type=page" of "wpforms-overview".
 * @param string $pagenow Huidig beheerbestand.
 */
function matches( $slug, $pagenow ) {
	$get = array_filter( wp_unslash( $_GET ), "is_scalar" ); // phpcs:ignore WordPress.Security.NonceVerification -- enkel lezen om te vergelijken.

	// Plugin-pagina zonder bestand: admin.php?page=slug (of onder een ander bestand).
	if ( false === strpos( $slug, '.php' ) ) {
		return isset( $get['page'] ) && (string) $get['page'] === $slug;
	}

	$file = (string) wp_parse_url( $slug, PHP_URL_PATH );
	parse_str( (string) wp_parse_url( $slug, PHP_URL_QUERY ), $args );
	$now = ( 'term.php' === $pagenow ) ? 'edit-tags.php' : $pagenow;
	if ( basename( $file ) !== $now ) {
		return false;
	}

	if ( isset( $args['taxonomy'] ) ) {
		return isset( $get['taxonomy'] ) && (string) $get['taxonomy'] === (string) $args['taxonomy'];
	}
	foreach ( array( 'page', 'post_type' ) as $key ) {
		$want = isset( $args[ $key ] ) ? (string) $args[ $key ] : '';
		$have = isset( $get[ $key ] ) ? (string) $get[ $key ] : '';
		if ( 'post_type' === $key ) {
			$want = ( 'post' === $want ) ? '' : $want;
			$have = ( 'post' === $have ) ? '' : $have;
		}
		if ( $want !== $have ) {
			return false;
		}
	}
	return true;
}

/**
 * Alle slugs die na het filteren nog in het menu staan (hoofd- en submenu).
 */
function visible_slugs() {
	global $menu, $submenu;
	$out = array();
	foreach ( (array) $menu as $item ) {
		if ( ! empty( $item[2] ) ) {
			$out[] = html_entity_decode( (string) $item[2] );
		}
	}
	foreach ( (array) $submenu as $subs ) {
		foreach ( (array) $subs as $sub ) {
			if ( ! empty( $sub[2] ) ) {
				$out[] = html_entity_decode( (string) $sub[2] );
			}
		}
	}
	return array_unique( $out );
}

/**
 * Berichttypes die de editor mag bewerken: die van de zichtbare lijsten, plus bijlagen bij Media.
 */
function allowed_post_types( array $slugs ) {
	$types = array();
	foreach ( $slugs as $slug ) {
		if ( 'upload.php' === $slug ) {
			$types[] = 'attachment';
		} elseif ( 0 === strpos( $slug, 'edit.php' ) ) {
			parse_str( (string) wp_parse_url( $slug, PHP_URL_QUERY ), $args );
			$types[] = isset( $args['post_type'] ) ? (string) $args['post_type'] : 'post';
		}
	}
	return array_unique( $types );
}

function request_allowed() {
	global $pagenow;
	// phpcs:disable WordPress.Security.NonceVerification -- enkel lezen om toegang te bepalen.

	// Altijd bereikbaar: Dashboard, profiel en de achterliggende acties.
	$always = array( 'index.php', 'profile.php', 'admin-ajax.php', 'admin-post.php', 'async-upload.php', 'media-upload.php', 'media-new.php', 'revision.php' );
	$always = (array) apply_filters( 'toolkit_sd_editor_always_allowed', $always );
	if ( in_array( $pagenow, $always, true ) ) {
		return true;
	}

	// Eigen profiel via user-edit.php.
	if ( 'user-edit.php' === $pagenow ) {
		return isset( $_GET['user_id'] ) && (int) $_GET['user_id'] === get_current_user_id();
	}

	$slugs = visible_slugs();

	// Een bericht bewerken of aanmaken (ook in Elementor): enkel voor de toegelaten berichttypes.
	if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
		if ( 'post-new.php' === $pagenow ) {
			$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
		} else {
			$id   = isset( $_REQUEST['post'] ) ? (int) $_REQUEST['post'] : ( isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0 );
			$type = $id ? get_post_type( $id ) : '';
			if ( ! $type ) {
				// Geen geldig bericht: WordPress geeft zelf een melding.
				return true;
			}
		}
		return in_array( $type, allowed_post_types( $slugs ), true );
	}

	foreach ( $slugs as $slug ) {
		if ( matches( $slug, $pagenow ) ) {
			return true;
		}
	}
	// phpcs:enable
	return false;
}

add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! \SDE\is_restricted_user() ) {
			return;
		}
		if ( request_allowed() ) {
			return;
		}
		$pages = in_array( 'edit.php?post_type=page', allow_list(), true );
		wp_die(
			esc_html__( 'Deze pagina hoort niet bij jouw beheer. Ga terug naar je pagina\'s of naar het dashboard.', 'toolkit-sd' ),
			esc_html__( 'Niet beschikbaar', 'toolkit-sd' ),
			array(
				'response'  => 403,
				'link_url'  => $pages ? admin_url( 'edit.php?post_type=page' ) : admin_url(),
				'link_text' => $pages ? __( 'Naar Pagina\'s', 'toolkit-sd' ) : __( 'Naar het dashboard', 'toolkit-sd' ),
			)
		);
	},
	1
);

/* -------------------------------------------------------------------------
 * Admin bar
 * ---------------------------------------------------------------------- */

add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! \SDE\is_restricted_user() ) {
			return;
		}
		// Enkel deze items zelf (niet wat er onder hangt).
		$keep = (array) apply_filters(
			'toolkit_sd_editor_bar_keep',
			array( 'top-secondary', 'menu-toggle', 'my-account', 'user-actions', 'user-info', 'edit-profile', 'logout', 'site-name', 'view-site', 'dashboard', 'edit' )
		);
		// Deze items met alles wat er onder hangt.
		$tree = array_merge( array( 'elementor_edit_page' ), \SDE\lines( \SDE\settings()['editor_bar_allow'] ) );

		$nodes = $bar->get_nodes();
		foreach ( (array) $nodes as $id => $node ) {
			if ( in_array( $id, $keep, true ) ) {
				continue;
			}
			$check = $node;
			$ok    = false;
			$guard = 0;
			while ( $check && $guard++ < 20 ) {
				if ( in_array( $check->id, $tree, true ) ) {
					$ok = true;
					break;
				}
				$check = ( $check->parent && isset( $nodes[ $check->parent ] ) ) ? $nodes[ $check->parent ] : null;
			}
			if ( ! $ok ) {
				$bar->remove_node( $id );
			}
		}
	},
	PHP_INT_MAX
);
