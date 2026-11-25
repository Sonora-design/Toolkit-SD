<?php
/**
 * Overname uit ASE (Admin and Site Enhancements).
 *
 * import_from_ase()  Leest de ASE-instellingen en vult de instellingen van deze plugin.
 * switch_from_ase()  Zet de overgenomen Pro-functies in ASE uit (met back-up), zodat
 *                    ASE Pro daarna vervangen kan worden door ASE Free.
 * restore_ase()      Zet de back-up van ASE terug.
 */

namespace SDE\Ase;

defined( 'ABSPATH' ) || exit;

const ASE_OPTION  = 'admin_site_enhancements';
const ASE_EXTRA   = 'admin_site_enhancements_extra';
const BACKUP_OPT  = 'sde_ase_backup';

function ase_options() {
	$o = get_option( ASE_OPTION, array() );
	return is_array( $o ) ? $o : array();
}

function ase_extra() {
	$e = get_option( ASE_EXTRA, array() );
	return is_array( $e ) ? $e : array();
}

function on( array $o, $key ) {
	return ! empty( $o[ $key ] );
}

/**
 * ASE codeert de bovenliggende menu-slug in de sleutel: "." wordt "__", "?" wordt "___", "=" wordt "____".
 */
function decode_parent( $key ) {
	$parent = explode( '_-_', $key, 2 )[0];
	return str_replace( array( '____', '___', '__' ), array( '=', '?', '.' ), $parent );
}

/**
 * Pad zoals ASE het opbouwt: zonder slash achteraan bij .php/.html.
 */
function ase_path( $slug ) {
	$slug = trim( trim( (string) $slug ), '/' );
	if ( '' === $slug ) {
		return '/';
	}
	$end = ( false !== strpos( $slug, '.php' ) || false !== strpos( $slug, '.html' ) ) ? '' : '/';
	return '/' . $slug . $end;
}

function roles_rule( array $item ) {
	$roles = array_values( array_filter( (array) ( $item['which_roles'] ?? array() ) ) );
	$for   = (string) ( $item['always_hide_for'] ?? '' );
	if ( false !== strpos( $for, 'except' ) ) {
		return $roles ? 'behalve: ' . implode( ', ', $roles ) : 'iedereen';
	}
	if ( $roles ) {
		return 'alleen: ' . implode( ', ', $roles );
	}
	return 'iedereen';
}

/**
 * Menu-regels uit ASE opbouwen.
 *
 * @return string[]
 */
function menu_rules_from_ase( array $o, array $e ) {
	$rules = array();
	if ( ! on( $o, 'customize_admin_menu' ) || empty( $e['admin_menu'] ) ) {
		return $rules;
	}
	$am = $e['admin_menu'];

	foreach ( (array) json_decode( $am['custom_menu_always_hidden'] ?? '[]', true ) as $item ) {
		if ( empty( $item['always_hide'] ) || empty( $item['menu_url_fragment'] ) ) {
			continue;
		}
		$rules[] = $item['menu_url_fragment'] . ' | ' . roles_rule( $item );
	}

	foreach ( (array) json_decode( $am['custom_submenu_always_hidden'] ?? '[]', true ) as $key => $item ) {
		if ( empty( $item['always_hide'] ) || empty( $item['original_menu_id'] ) ) {
			continue;
		}
		$rules[] = decode_parent( $key ) . ' > ' . $item['original_menu_id'] . ' | ' . roles_rule( $item );
	}

	return $rules;
}

/**
 * Instellingen voor deze plugin opbouwen uit ASE, zonder iets op te slaan.
 *
 * @return array{settings: array, report: string[], not_covered: string[]}
 */
function build_from_ase() {
	$o   = ase_options();
	$e   = ase_extra();
	$s   = \SDE\defaults();
	$rep = array();

	// REST.
	if ( on( $o, 'disable_rest_api' ) ) {
		$s['modules']['rest'] = true;
		$s['rest_allow']      = trim( str_replace( "\r\n", "\n", (string) ( $o['disable_rest_api_excluded_routes'] ?? '' ) ) );
		$rep[]                = 'REST-afscherming overgenomen';
	}

	// Loginpagina.
	if ( on( $o, 'login_page_customizer' ) ) {
		$s['modules']['login'] = true;
		$rep[]                 = 'Loginpagina in Sonora-stijl';
	}

	// Menu per rol + admin bar-knooppunten.
	$rules = menu_rules_from_ase( $o, $e );
	if ( $rules ) {
		$s['modules']['admin_menu'] = true;
		$s['admin_menu_rules']      = implode( "\n", $rules );
		$rep[]                      = count( $rules ) . ' menuregels overgenomen';
	}
	$nodes = array_keys( array_filter( (array) ( $o['disabled_plugins_admin_bar_items'] ?? array() ) ) );
	if ( $nodes ) {
		$s['modules']['admin_menu'] = true;
		$s['admin_bar_nodes']       = implode( "\n", $nodes );
		$rep[]                      = 'Admin bar: ' . implode( ', ', $nodes ) . ' verborgen';
	}

	// Redirect na inloggen.
	if ( on( $o, 'redirect_after_login' ) ) {
		$lines = array();
		if ( 'separate_urls' === ( $o['redirect_after_login_type'] ?? '' ) ) {
			foreach ( (array) ( $o['redirect_after_login_for_separate_role'] ?? array() ) as $role => $yes ) {
				if ( $yes ) {
					$lines[] = $role . '=' . ase_path( $o['redirect_after_login_for_separate_slug'][ $role ] ?? '' );
				}
			}
		} else {
			$path = ase_path( $o['redirect_after_login_to_slug'] ?? '' );
			foreach ( (array) ( $o['redirect_after_login_for'] ?? array() ) as $role => $yes ) {
				if ( $yes ) {
					$lines[] = $role . '=' . $path;
				}
			}
		}
		if ( $lines ) {
			$s['modules']['login_redirect'] = true;
			$s['login_redirects']           = implode( "\n", $lines );
			$rep[]                          = 'Redirect na inloggen: ' . implode( ', ', $lines );
		}
	}

	// Admin bar in de backend.
	if ( on( $o, 'hide_admin_bar' ) ) {
		$roles = array_keys( array_filter( (array) ( $o['hide_admin_bar_on_backend_for'] ?? array() ) ) );
		if ( $roles ) {
			$s['modules']['admin_bar'] = true;
			$s['admin_bar_roles']      = implode( "\n", $roles );
			$rep[]                     = 'Admin bar backend verborgen voor: ' . implode( ', ', $roles );
		}
	}

	// Mediacategorieën.
	if ( on( $o, 'enable_media_categories' ) ) {
		$s['modules']['media_categories'] = true;
		$rep[]                            = 'Mediacategorieën';
	}

	// E-mailadressen: shortcode [obfuscate] en/of automatisch in de inhoud.
	if ( on( $o, 'obfuscate_email_address' ) || on( $o, 'obfuscate_email_address_in_content' ) ) {
		$s['modules']['email_obfuscate'] = true;
		$s['email_shortcode']            = on( $o, 'obfuscate_email_address' );
		$s['email_auto']                 = on( $o, 'obfuscate_email_address_in_content' );
		$s['email_visitors_only']        = on( $o, 'obfuscate_email_address_visitor_only' );
		$s['email_mode']                 = 'entities';
		$bits                            = array();
		if ( $s['email_shortcode'] ) {
			$bits[] = 'shortcode [obfuscate]';
		}
		if ( $s['email_auto'] ) {
			$bits[] = 'automatisch in de inhoud';
		}
		$rep[] = 'E-mailadressen verbergen: ' . implode( ', ', $bits );
	}

	// Meldingen voor niet-admins.
	if ( on( $o, 'hide_admin_notices_for_nonadmins' ) || on( $o, 'hide_admin_notices_menu_for_nonadmins' ) ) {
		$s['modules']['hide_notices'] = true;
		$rep[]                        = 'Beheermeldingen verbergen voor niet-admins';
	}

	// Pro-functies die deze plugin (nog) niet vervangt.
	$not = array();
	$map = array(
		'public_preview_for_drafts' => 'Publieke preview van concepten',
		'smtp_email_log'            => 'E-maillog (SMTP)',
		'captcha_protection'        => 'CAPTCHA (Altcha/reCAPTCHA/Turnstile)',
		'custom_content_types'      => 'Eigen berichttypes',
		'custom_field_groups'       => 'Aangepaste velden',
		'form_builder'              => 'Formulierenbouwer',
		'enable_code_snippets_manager' => 'Code Snippets Manager',
		'admin_columns_manager'     => 'Kolommen in beheerlijsten',
		'local_user_avatar'         => 'Lokale profielfoto',
		'terms_order'               => 'Volgorde van termen',
		'admin_logo'                => 'Logo in het beheer',
	);
	foreach ( $map as $key => $label ) {
		if ( on( $o, $key ) ) {
			$not[] = $label;
		}
	}

	return array(
		'settings'    => $s,
		'report'      => $rep,
		'not_covered' => $not,
	);
}

/**
 * Instellingen uit ASE overnemen en opslaan.
 */
function import_from_ase() {
	$result = build_from_ase();
	update_option( \SDE\OPTION, $result['settings'] );
	return $result;
}

/**
 * De overgenomen functies in ASE uitzetten (met back-up), zodat ze niet dubbel werken.
 */
function switch_from_ase() {
	$o = ase_options();
	$e = ase_extra();

	if ( ! get_option( BACKUP_OPT ) ) {
		add_option(
			BACKUP_OPT,
			array(
				'date'  => current_time( 'mysql' ),
				'ase'   => $o,
				'extra' => $e,
			),
			'',
			'no'
		);
	}

	$saved   = get_option( \SDE\OPTION, array() );
	$s       = array_merge( \SDE\defaults(), is_array( $saved ) ? $saved : array() );
	$changed = array();

	$off = array(
		'rest'             => array( 'disable_rest_api' ),
		'login'            => array( 'login_page_customizer' ),
		'login_redirect'   => array( 'redirect_after_login' ),
		'media_categories' => array( 'enable_media_categories' ),
		'email_obfuscate'  => array( 'obfuscate_email_address_in_content', 'obfuscate_email_address_visitor_only' ),
	);
	// De shortcode van ASE enkel uitzetten als deze plugin hem overneemt.
	if ( ! empty( $s['modules']['email_obfuscate'] ) && ! empty( $s['email_shortcode'] ) ) {
		$off['email_obfuscate'][] = 'obfuscate_email_address';
	}
	foreach ( $off as $module => $keys ) {
		if ( empty( $s['modules'][ $module ] ) ) {
			continue;
		}
		foreach ( $keys as $key ) {
			if ( ! empty( $o[ $key ] ) ) {
				$o[ $key ] = false;
				$changed[] = $key;
			}
		}
	}

	// Verborgen menu-items per rol vrijgeven in ASE (deze plugin neemt het over).
	if ( ! empty( $s['modules']['admin_menu'] ) && ! empty( $e['admin_menu'] ) ) {
		$n = 0;
		foreach ( array( 'custom_menu_always_hidden', 'custom_submenu_always_hidden' ) as $k ) {
			$items = json_decode( $e['admin_menu'][ $k ] ?? '[]', true );
			if ( ! is_array( $items ) ) {
				continue;
			}
			foreach ( $items as &$item ) {
				if ( ! empty( $item['always_hide'] ) ) {
					$item['always_hide']     = false;
					$item['always_hide_for'] = '';
					$item['which_roles']     = array();
					$n++;
				}
			}
			unset( $item );
			$e['admin_menu'][ $k ] = wp_json_encode( $items );
		}
		if ( $n ) {
			$changed[] = $n . ' verborgen menu-items';
			update_option( ASE_EXTRA, $e );
		}
	}

	update_option( ASE_OPTION, $o );

	return $changed;
}

/**
 * ASE-instellingen terugzetten uit de back-up.
 */
function restore_ase() {
	$b = get_option( BACKUP_OPT );
	if ( ! is_array( $b ) || empty( $b['ase'] ) ) {
		return false;
	}
	update_option( ASE_OPTION, $b['ase'] );
	if ( isset( $b['extra'] ) ) {
		update_option( ASE_EXTRA, $b['extra'] );
	}
	return true;
}
