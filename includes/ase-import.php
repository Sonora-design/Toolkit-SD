<?php
/**
 * Overname uit ASE (Admin and Site Enhancements).
 *
 * Regel: wat ASE gratis kan, blijft in ASE. Toolkit SD vult enkel aan wat Pro is,
 * en één functie wordt nooit door beide plugins tegelijk uitgevoerd.
 *
 * import_from_ase()  Leest de ASE-instellingen en vult de instellingen van deze plugin.
 * switch_from_ase()  Zet in ASE uit wat Toolkit SD overgenomen heeft, en zet aan of om wat
 *                    in ASE gratis blijft (met back-up), zodat ASE Pro daarna vervangen kan
 *                    worden door ASE Free.
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

/** Admin bar-items die ASE gratis zelf kan verbergen (met - in plaats van _). */
const FREE_BAR = array( 'wp-logo', 'site', 'site-name', 'customize', 'updates', 'comments', 'new-content', 'howdy' );

/**
 * Is ASE (gratis of Pro) actief op deze site?
 */
function ase_active() {
	$files = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$files = array_merge( $files, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}
	foreach ( $files as $file ) {
		if ( 0 === strpos( (string) $file, 'admin-site-enhancements' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Sluit ASE de REST API af voor bezoekers ("Disable REST API")?
 */
function ase_rest_on() {
	return ase_active() && on( ase_options(), 'disable_rest_api' );
}

/**
 * Stuurt ASE zelf door na in- of uitloggen? ASE doet dat meteen (met exit),
 * waardoor Toolkit SD niet meer aan bod komt.
 *
 * @return string[] 'login' en/of 'logout'.
 */
function ase_redirects_on() {
	$out = array();
	if ( ase_active() ) {
		$o = ase_options();
		foreach ( array( 'login', 'logout' ) as $kind ) {
			if ( on( $o, 'redirect_after_' . $kind ) ) {
				$out[] = $kind;
			}
		}
	}
	return $out;
}

/**
 * Doorsturen na in- of uitloggen zoals het in ASE staat.
 *
 * @param string $kind login of logout.
 * @return array|null map (rol => pad), separate (apart adres per rol ingesteld), uniform (overal hetzelfde adres), slug.
 */
function ase_redirect( array $o, $kind ) {
	$key = 'redirect_after_' . $kind;
	if ( ! on( $o, $key ) ) {
		return null;
	}
	$map      = array();
	$slug     = '';
	$separate = 'separate_urls' === ( $o[ $key . '_type' ] ?? '' );
	if ( $separate ) {
		$slugs = (array) ( $o[ $key . '_for_separate_slug' ] ?? array() );
		foreach ( (array) ( $o[ $key . '_for_separate_role' ] ?? array() ) as $role => $yes ) {
			if ( $yes ) {
				$map[ $role ] = ase_path( $slugs[ $role ] ?? '' );
				if ( 1 === count( $map ) ) {
					$slug = (string) ( $slugs[ $role ] ?? '' ); // Enkel gebruikt als alle adressen gelijk zijn.
				}
			}
		}
	} else {
		$slug = (string) ( $o[ $key . '_to_slug' ] ?? '' );
		foreach ( (array) ( $o[ $key . '_for' ] ?? array() ) as $role => $yes ) {
			if ( $yes ) {
				$map[ $role ] = ase_path( $slug );
			}
		}
	}
	return array(
		'map'      => $map,
		'separate' => $separate,
		'uniform'  => count( array_unique( $map ) ) <= 1,
		'slug'     => $slug,
	);
}

/**
 * Kan ASE gratis dit admin bar-item zelf verbergen?
 */
function bar_is_free( $node ) {
	return in_array( str_replace( '_', '-', strtolower( (string) $node ) ), FREE_BAR, true );
}

/**
 * Berichten die [obfuscate] gebruiken met link=, subject= of text= (dat kan ASE gratis niet).
 * Zoekt in de inhoud en in Elementor, voor gepubliceerde, concept- en privéberichten van alle berichttypes.
 *
 * @return int Aantal berichten.
 */
function obfuscate_pro_usage() {
	global $wpdb;
	$like = '%' . $wpdb->esc_like( '[obfuscate' ) . '%';
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT p.ID, p.post_content, m.meta_value FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
			 WHERE p.post_status IN ('publish','draft','private')
			 AND ( p.post_content LIKE %s OR m.meta_value LIKE %s )",
			$like,
			$like
		)
	);
	$ids = array();
	foreach ( (array) $rows as $row ) {
		if ( preg_match( '/\[obfuscate\b[^\]]*\b(?:link|subject|text)\s*=/i', $row->post_content . "\n" . $row->meta_value ) ) {
			$ids[ (int) $row->ID ] = true;
		}
	}
	return count( $ids );
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
 * Verbergt een menuregel ("doel | behalve: administrator", "| alleen: editor", "| iedereen") het item voor deze rol?
 */
function rule_hides_role( $rule, $role ) {
	$rule = strtolower( trim( (string) $rule ) );
	if ( 0 === strpos( $rule, 'iedereen' ) ) {
		return true;
	}
	if ( preg_match( '/^(behalve|alleen)\s*:\s*(.*)$/', $rule, $m ) ) {
		$in = in_array( $role, array_map( 'trim', explode( ',', $m[2] ) ), true );
		return ( 'alleen' === $m[1] ) ? $in : ! $in;
	}
	return 'administrator' !== $role; // Standaard: behalve administrator.
}

/**
 * Menu voor editors opbouwen uit ASE, zonder iets op te slaan of ASE te wijzigen.
 *
 * ASE houdt bij wat verborgen is; alles daarbuiten zag de editor. Het menu zoals de editor
 * het nu ziet, wordt daarom de lijst van toegelaten items. Nieuwe items van plugins blijven
 * daarna verborgen.
 *
 * @param array $snapshot Volledig menu: array( 'menu' => …, 'submenu' => … ).
 * @return array{allow: string[], hide: string[], report: string[], ase: bool}
 */
function editor_from_ase( array $snapshot ) {
	$o       = ase_options();
	$e       = ase_extra();
	$default = \SDE\lines( \SDE\defaults()['editor_allow'] );
	$roles   = \SDE\restricted_roles();

	if ( ! $o ) {
		return array(
			'allow'  => $default,
			'hide'   => array(),
			'report' => array( __( 'ASE is niet gevonden: de standaard geldt (Pagina\'s, Media, Profiel).', 'toolkit-sd' ) ),
			'ase'    => false,
		);
	}

	$lines = menu_rules_from_ase( $o, $e );
	if ( ! $lines ) {
		return array(
			'allow'  => $default,
			'hide'   => array(),
			'report' => array( __( 'ASE verbergt geen menu-items per rol: de standaard geldt (Pagina\'s, Media, Profiel).', 'toolkit-sd' ) ),
			'ase'    => false,
		);
	}

	// Regels per doel.
	$rules = array();
	foreach ( $lines as $line ) {
		$parts                                       = array_map( 'trim', explode( '|', $line, 2 ) );
		$rules[ html_entity_decode( $parts[0] ) ] = $parts[1] ?? '';
	}
	$hidden = function ( $target ) use ( $rules, $roles ) {
		if ( ! isset( $rules[ $target ] ) ) {
			return false;
		}
		foreach ( $roles as $role ) {
			if ( ! rule_hides_role( $rules[ $target ], $role ) ) {
				return false; // Minstens één afgeschermde rol zag het.
			}
		}
		return true;
	};
	$can = function ( $cap ) use ( $roles ) {
		foreach ( $roles as $role ) {
			$r = get_role( $role );
			if ( $r && $r->has_cap( (string) $cap ) ) {
				return true;
			}
		}
		return false;
	};

	$allow = array();
	$hide  = array();
	foreach ( (array) ( $snapshot['menu'] ?? array() ) as $item ) {
		if ( empty( $item[2] ) || ( isset( $item[4] ) && false !== strpos( (string) $item[4], 'wp-menu-separator' ) ) ) {
			continue;
		}
		$slug = html_entity_decode( (string) $item[2] );
		if ( ! $can( $item[1] ?? '' ) || $hidden( $slug ) ) {
			continue;
		}
		$allow[] = $slug;
		foreach ( (array) ( $snapshot['submenu'][ $item[2] ] ?? array() ) as $sub ) {
			if ( empty( $sub[2] ) ) {
				continue;
			}
			$target = $slug . ' > ' . html_entity_decode( (string) $sub[2] );
			if ( $can( $sub[1] ?? '' ) && $hidden( $target ) ) {
				$hide[] = $target;
			}
		}
	}
	// Profiel hoort er altijd bij (staat bij een editor als eigen menu-item).
	if ( ! in_array( 'profile.php', $allow, true ) ) {
		$allow[] = 'profile.php';
	}

	$report = array(
		/* translators: %d: aantal menu-items */
		sprintf( __( '%d menu-items overgenomen die editors in ASE zagen.', 'toolkit-sd' ), count( $allow ) ),
	);
	if ( $hide ) {
		/* translators: %d: aantal submenu's */
		$report[] = sprintf( __( '%d submenu\'s blijven verborgen, zoals in ASE.', 'toolkit-sd' ), count( $hide ) );
	}
	$report[] = __( 'Volgorde en namen van het menu blijven in ASE ingesteld en zijn niet overgenomen.', 'toolkit-sd' );
	$report[] = __( 'Admin bar: ASE heeft geen lijst van zichtbare items, dus editors zien enkel hun profiel en afmelden.', 'toolkit-sd' );

	return array(
		'allow'  => array_values( array_unique( $allow ) ),
		'hide'   => array_values( array_unique( $hide ) ),
		'report' => $report,
		'ase'    => true,
	);
}

/**
 * Menu voor editors uit ASE overnemen en opslaan in de instellingen van deze plugin.
 * ASE zelf wordt niet gewijzigd. Zet de module niet aan.
 *
 * @param array $snapshot Volledig menu.
 * @param bool  $notice   Melding tonen in het beheer.
 */
function import_editor_from_ase( array $snapshot, $notice = true ) {
	$r = editor_from_ase( $snapshot );
	$s = \SDE\settings( true );

	$s['editor_allow']    = implode( "\n", $r['allow'] );
	$s['editor_hide']     = implode( "\n", $r['hide'] );
	$s['editor_migrated'] = true;
	$s['editor_import']   = array(
		'time'   => time(),
		'report' => $r['report'],
		'ase'    => $r['ase'],
		'notice' => $notice && $r['ase'],
	);
	update_option( \SDE\OPTION, $s );
	\SDE\settings( true );
	return $r;
}

/**
 * Eén regel voor het verslag.
 *
 * @param string $where toolkit (Pro → Toolkit SD) of ase (Gratis → blijft in ASE).
 */
function item( $where, $text ) {
	return array(
		'where' => $where,
		'text'  => $text,
	);
}

/**
 * Instellingen voor deze plugin opbouwen uit ASE, zonder iets op te slaan.
 * Wat ASE gratis kan, blijft in ASE en wordt enkel in het verslag genoteerd.
 *
 * @return array{settings: array, items: array[], report: string[], not_covered: string[]}
 */
function build_from_ase() {
	$o     = ase_options();
	$e     = ase_extra();
	$s     = \SDE\defaults();
	$items = array();

	// REST: afsluiten blijft in ASE, de uitzonderingen en de beperking per rol komen hier.
	$routes = \SDE\lines( (string) ( $o['disable_rest_api_excluded_routes'] ?? '' ) );
	if ( on( $o, 'disable_rest_api' ) || $routes ) {
		$s['modules']['rest'] = true;
		$s['rest_allow']      = implode( "\n", $routes );
		$items[]              = item( 'ase', 'REST API afsluiten voor bezoekers (Disable REST API)' );
		$items[]              = item(
			'toolkit',
			$routes
				? 'REST: uitzonderingen op de afscherming (' . implode( ', ', $routes ) . ', en automatisch SureCookie, Independent Analytics en Novamira)'
				: 'REST: uitzonderingen op de afscherming (automatisch SureCookie, Independent Analytics en Novamira)'
		);

		// Beperking per rol: enkel als niet alle rollen toegang hebben.
		$all    = array_keys( wp_roles()->get_names() );
		$chosen = array_values( array_intersect( $all, array_keys( array_filter( (array) ( $o['enable_rest_api_for'] ?? array() ) ) ) ) );
		if ( $chosen && array_diff( $all, $chosen ) ) {
			$s['rest_roles_mode'] = 'some';
			$s['rest_roles']      = $chosen;
			$items[]              = item( 'toolkit', 'REST: enkel voor de rollen ' . implode( ', ', $chosen ) );
		}
	}

	// Loginpagina.
	if ( on( $o, 'login_page_customizer' ) ) {
		$s['modules']['login'] = true;
		$items[]               = item( 'toolkit', 'Loginpagina in Sonora-stijl' );
	}

	// Menu: enkel wat per rol of altijd verborgen is. Herordenen en hernoemen blijven in ASE.
	$rules = menu_rules_from_ase( $o, $e );
	if ( $rules ) {
		$s['modules']['admin_menu'] = true;
		$s['admin_menu_rules']      = implode( "\n", $rules );
		$items[]                    = item( 'toolkit', count( $rules ) . ' menu-items die per rol of altijd verborgen zijn' );
	}
	if ( on( $o, 'customize_admin_menu' ) ) {
		$items[] = item( 'ase', 'Menu herordenen, hernoemen en verbergen achter "toon alles"' );
	}

	// Admin bar: enkel items die ASE gratis niet kan verbergen.
	$nodes = array_keys( array_filter( (array) ( $o['disabled_plugins_admin_bar_items'] ?? array() ) ) );
	$free  = array_values( array_filter( $nodes, __NAMESPACE__ . '\bar_is_free' ) );
	$nodes = array_values( array_diff( $nodes, $free ) );
	if ( $nodes ) {
		$s['modules']['admin_menu'] = true;
		$s['admin_bar_nodes']       = implode( "\n", $nodes );
		$items[]                    = item( 'toolkit', 'Admin bar: ' . implode( ', ', $nodes ) . ' verborgen' );
	}
	if ( $free ) {
		$items[] = item( 'ase', 'Admin bar: ' . implode( ', ', $free ) . ' verbergen' );
	}

	// Doorsturen na in- en uitloggen: enkel overnemen als rollen een verschillend adres hebben.
	$taken = array();
	foreach ( array( 'login' => 'inloggen', 'logout' => 'uitloggen' ) as $kind => $word ) {
		$r = ase_redirect( $o, $kind );
		if ( ! $r || ! $r['map'] ) {
			continue;
		}
		if ( $r['uniform'] ) {
			$items[] = item( 'ase', 'Doorsturen na ' . $word . ' naar ' . reset( $r['map'] ) . ' voor ' . implode( ', ', array_keys( $r['map'] ) ) );
			continue;
		}
		$lines = array();
		foreach ( $r['map'] as $role => $path ) {
			$lines[] = $role . '=' . $path;
		}
		$taken[ $kind ] = implode( "\n", $lines );
		$items[]        = item( 'toolkit', 'Doorsturen na ' . $word . ' per rol: ' . implode( ', ', $lines ) );
	}
	// Wat in ASE blijft of daar niet stond, mag hier niet ook doorsturen.
	if ( $taken || ase_redirect( $o, 'login' ) ) {
		$s['login_redirects'] = $taken['login'] ?? '';
	}
	if ( $taken ) {
		$s['modules']['login_redirect'] = true;
		$s['logout_redirects']          = $taken['logout'] ?? '';
	}

	// Admin bar in de backend.
	if ( on( $o, 'hide_admin_bar' ) ) {
		$roles = array_keys( array_filter( (array) ( $o['hide_admin_bar_on_backend_for'] ?? array() ) ) );
		if ( $roles ) {
			$s['modules']['admin_bar'] = true;
			$s['admin_bar_roles']      = implode( "\n", $roles );
			$items[]                   = item( 'toolkit', 'Admin bar in de backend verborgen voor: ' . implode( ', ', $roles ) );
		}
	}

	// Mediacategorieën.
	if ( on( $o, 'enable_media_categories' ) ) {
		$s['modules']['media_categories'] = true;
		$items[]                          = item( 'toolkit', 'Mediacategorieën' );
	}

	// E-mailadressen: de gewone shortcode [obfuscate] blijft in ASE. Enkel overnemen als ASE alle
	// adressen in de inhoud verbergt, of als de site de shortcode met link, subject of text gebruikt.
	if ( on( $o, 'obfuscate_email_address' ) ) {
		$auto = on( $o, 'obfuscate_email_address_in_content' );
		$used = obfuscate_pro_usage();
		if ( $auto || $used ) {
			$s['modules']['email_obfuscate'] = true;
			$s['email_shortcode']            = true;
			$s['email_auto']                 = $auto;
			$s['email_visitors_only']        = on( $o, 'obfuscate_email_address_visitor_only' );
			$s['email_mode']                 = 'entities';
			$bits                            = array();
			if ( $used ) {
				$bits[] = 'shortcode [obfuscate] met link, subject of text op ' . $used . ( 1 === $used ? ' pagina' : ' pagina\'s' );
			} else {
				$bits[] = 'shortcode [obfuscate]';
			}
			if ( $auto ) {
				$bits[] = 'automatisch in de inhoud';
			}
			$items[] = item( 'toolkit', 'E-mailadressen verbergen: ' . implode( ', ', $bits ) );
		} else {
			$items[] = item( 'ase', 'E-mailadressen verbergen met de shortcode [obfuscate]' );
		}
	}

	// Meldingen voor niet-admins.
	if ( on( $o, 'hide_admin_notices_for_nonadmins' ) || on( $o, 'hide_admin_notices_menu_for_nonadmins' ) ) {
		$s['modules']['hide_notices'] = true;
		$items[]                      = item( 'toolkit', 'Beheermeldingen verbergen voor niet-admins' );
	}

	// Publieke preview van concepten. Bestaande links uit ASE gaan niet mee.
	if ( on( $o, 'public_preview_for_drafts' ) ) {
		$s['modules']['public_preview'] = true;
		$items[]                        = item( 'toolkit', 'Publieke preview van concepten' );
	}

	// Pro-functies die deze plugin (nog) niet vervangt.
	$not = array();
	$map = array(
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

	// Enkel wat Toolkit SD overneemt, als gewone tekst (voor de melding na het overnemen).
	$rep = array();
	foreach ( $items as $it ) {
		if ( 'toolkit' === $it['where'] ) {
			$rep[] = $it['text'];
		}
	}

	return array(
		'settings'    => $s,
		'items'       => $items,
		'report'      => $rep,
		'not_covered' => $not,
	);
}

/**
 * Instellingen uit ASE overnemen en opslaan.
 */
function import_from_ase() {
	$result = build_from_ase();
	// Het menu voor editors heeft een eigen overname: die instellingen blijven staan.
	$now = \SDE\settings( true );
	foreach ( array( 'editor_allow', 'editor_hide', 'editor_bar_allow', 'editor_import', 'editor_migrated' ) as $key ) {
		$result['settings'][ $key ] = $now[ $key ];
	}
	$result['settings']['modules']['editor_menu'] = ! empty( $now['modules']['editor_menu'] );
	update_option( \SDE\OPTION, $result['settings'] );
	return $result;
}

/**
 * Wat er in ASE verandert bij "ASE-functies uitzetten", zonder iets op te slaan.
 *
 * Beslist op wat Toolkit SD nu echt doet (module aan en ingesteld): dat gaat in ASE uit.
 * Wat ASE gratis blijft doen, blijft aan of wordt omgezet naar de gratis vorm.
 *
 * @param array $s Instellingen van Toolkit SD.
 * @param array $o Instellingen van ASE.
 * @param array $e Extra instellingen van ASE (menu).
 * @return array{ase: array, extra: array, settings: array, changes: string[]}
 */
function plan_switch( array $s, array $o, array $e ) {
	$changes = array();
	$mod     = function ( $key ) use ( $s ) {
		return ! empty( $s['modules'][ $key ] );
	};
	$done    = function () use ( &$s, &$o, &$e, &$changes ) {
		return array(
			'ase'      => $o,
			'extra'    => $e,
			'settings' => $s,
			'changes'  => $changes,
		);
	};
	if ( ! $o ) {
		return $done();
	}

	// REST: ASE sluit af, Toolkit SD beheert de uitzonderingen.
	if ( $mod( 'rest' ) ) {
		if ( ! on( $o, 'disable_rest_api' ) ) {
			$o['disable_rest_api'] = true;
			$changes[]             = 'Aan: Disable REST API (ASE sluit af voor bezoekers)';
		}
		$routes = \SDE\lines( (string) ( $o['disable_rest_api_excluded_routes'] ?? '' ) );
		if ( $routes ) {
			$norm    = function ( $r ) {
				return trim( $r, '/' );
			};
			$have    = array_map( $norm, \SDE\lines( $s['rest_allow'] ) );
			$missing = array();
			foreach ( $routes as $route ) {
				if ( ! in_array( $norm( $route ), $have, true ) ) {
					$missing[] = $route;
				}
			}
			if ( $missing ) {
				// Geen uitzondering mag verloren gaan.
				$s['rest_allow'] = trim( $s['rest_allow'] . "\n" . implode( "\n", $missing ) );
				$changes[]       = 'Toegevoegd aan de uitzonderingen in Toolkit SD: ' . implode( ', ', $missing );
			}
			$o['disable_rest_api_excluded_routes'] = '';
			$changes[]                             = 'Geleegd: uitgesloten REST-routes (de uitzonderingen staan in Toolkit SD)';
		}
	}

	// Doorsturen: uit als Toolkit SD het doet. Anders blijft het in ASE, in de gratis vorm (één adres).
	foreach ( array( 'login' => 'login', 'logout' => 'logout' ) as $kind => $word ) {
		$key  = 'redirect_after_' . $kind;
		$mine = $mod( 'login_redirect' ) && array_filter( \SDE\pairs( $s[ $kind . '_redirects' ] ), 'strlen' );
		if ( $mine ) {
			if ( on( $o, $key ) ) {
				$o[ $key ] = false;
				$changes[] = 'Uit: Redirect after ' . $word . ' (Toolkit SD stuurt per rol door)';
			}
			continue;
		}
		$r = ase_redirect( $o, $kind );
		if ( $r && $r['map'] && $r['separate'] && $r['uniform'] ) {
			$for = array();
			foreach ( (array) ( $o[ $key . '_for' ] ?? array() ) as $role => $yes ) {
				$for[ $role ] = false;
			}
			foreach ( array_keys( $r['map'] ) as $role ) {
				$for[ $role ] = true;
			}
			$o[ $key . '_type' ]    = 'single_url';
			$o[ $key . '_to_slug' ] = $r['slug'];
			$o[ $key . '_for' ]     = $for;
			$changes[]              = 'Omgezet: Redirect after ' . $word . ' naar één adres (' . reset( $r['map'] ) . ') voor ' . implode( ', ', array_keys( $r['map'] ) );
		}
	}

	// Pro-functies die Toolkit SD overneemt.
	$off = array(
		'login'            => array( 'login_page_customizer' => 'Login Page Customizer' ),
		'media_categories' => array( 'enable_media_categories' => 'Media Categories' ),
		'public_preview'   => array( 'public_preview_for_drafts' => 'Public Preview for Drafts' ),
		'email_obfuscate'  => array(
			'obfuscate_email_address_in_content'   => 'e-mailadressen automatisch verbergen in de inhoud',
			'obfuscate_email_address_visitor_only' => 'e-mailadressen enkel verbergen voor bezoekers',
		),
	);
	// De shortcode van ASE enkel uitzetten als deze plugin hem overneemt.
	if ( ! empty( $s['email_shortcode'] ) ) {
		$off['email_obfuscate']['obfuscate_email_address'] = 'Email Address Obfuscator (shortcode [obfuscate])';
	}
	foreach ( $off as $module => $keys ) {
		if ( ! $mod( $module ) ) {
			continue;
		}
		foreach ( $keys as $key => $label ) {
			if ( on( $o, $key ) ) {
				$o[ $key ] = false;
				$changes[] = 'Uit: ' . $label;
			}
		}
	}

	if ( $mod( 'admin_menu' ) ) {
		// Verborgen menu-items per rol vrijgeven in ASE, enkel waar Toolkit SD een regel voor heeft.
		$mine = array();
		foreach ( \SDE\lines( $s['admin_menu_rules'] ) as $line ) {
			$mine[] = html_entity_decode( trim( explode( '|', $line, 2 )[0] ) );
		}
		$n = 0;
		foreach ( array( 'custom_menu_always_hidden', 'custom_submenu_always_hidden' ) as $k ) {
			$list = json_decode( $e['admin_menu'][ $k ] ?? '[]', true );
			if ( ! is_array( $list ) || ! $list ) {
				continue;
			}
			$touched = false;
			foreach ( $list as $id => &$entry ) {
				if ( empty( $entry['always_hide'] ) ) {
					continue;
				}
				$target = ( 'custom_menu_always_hidden' === $k )
					? (string) ( $entry['menu_url_fragment'] ?? '' )
					: decode_parent( $id ) . ' > ' . ( $entry['original_menu_id'] ?? '' );
				if ( ! in_array( html_entity_decode( $target ), $mine, true ) ) {
					continue;
				}
				$entry['always_hide']     = false;
				$entry['always_hide_for'] = '';
				$entry['which_roles']     = array();
				$touched                  = true;
				$n++;
			}
			unset( $entry );
			if ( $touched ) {
				$e['admin_menu'][ $k ] = wp_json_encode( $list );
			}
		}
		if ( $n ) {
			$changes[] = 'Uit: ' . $n . ( 1 === $n ? ' verborgen menu-item' : ' verborgen menu-items' ) . ' per rol (volgorde en namen blijven in ASE)';
		}

		// Admin bar-items die Toolkit SD verbergt.
		$nodes = \SDE\lines( $s['admin_bar_nodes'] );
		$gone  = array();
		foreach ( (array) ( $o['disabled_plugins_admin_bar_items'] ?? array() ) as $node => $yes ) {
			if ( $yes && in_array( (string) $node, $nodes, true ) ) {
				$o['disabled_plugins_admin_bar_items'][ $node ] = false;
				$gone[]                                         = $node;
			}
		}
		if ( $gone ) {
			$changes[] = 'Uit: admin bar-items ' . implode( ', ', $gone );
		}
	}

	// Admin bar in de backend per rol.
	if ( $mod( 'admin_bar' ) ) {
		$roles = \SDE\lines( $s['admin_bar_roles'] );
		$gone  = array();
		foreach ( (array) ( $o['hide_admin_bar_on_backend_for'] ?? array() ) as $role => $yes ) {
			if ( $yes && in_array( (string) $role, $roles, true ) ) {
				$o['hide_admin_bar_on_backend_for'][ $role ] = false;
				$gone[]                                      = $role;
			}
		}
		if ( $gone ) {
			$changes[] = 'Uit: admin bar in de backend verbergen voor ' . implode( ', ', $gone );
		}
	}

	return $done();
}

/**
 * ASE afstemmen op Toolkit SD (met back-up), zodat geen functie dubbel werkt.
 *
 * @return string[] Wat er in ASE aan, uit of omgezet is.
 */
function switch_from_ase() {
	$o = ase_options();
	$e = ase_extra();
	if ( ! $o ) {
		return array();
	}

	// Back-up vóór de eerste wijziging; een bestaande back-up blijft staan.
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

	$s = \SDE\settings( true );
	$p = plan_switch( $s, $o, $e );

	if ( $p['settings'] !== $s ) {
		update_option( \SDE\OPTION, $p['settings'] );
		\SDE\settings( true );
	}
	if ( $p['extra'] !== $e ) {
		update_option( ASE_EXTRA, $p['extra'] );
	}
	if ( $p['ase'] !== $o ) {
		update_option( ASE_OPTION, $p['ase'] );
	}

	return $p['changes'];
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
