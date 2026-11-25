<?php
/**
 * Updates via de releases op GitHub (publieke repository Sonora-design/Toolkit-SD).
 *
 * De site leest twee keer per dag het versiebestand info.json van de laatste release.
 * Is er een nieuwere versie, dan verschijnt die bij Plugins en Dashboard > Updates,
 * zoals elke andere plugin. Geen sleutels op de site.
 *
 * Privacy: de vraag naar GitHub bevat enkel de naam "Toolkit-SD" als user-agent,
 * niet het adres van de site. Er worden geen gegevens van bezoekers verstuurd.
 *
 * Formaat van info.json:
 * {
 *   "version": "1.4.1",
 *   "download_url": "https://github.com/Sonora-design/Toolkit-SD/releases/download/v1.4.1/toolkit-sd-1.4.1.zip",
 *   "requires": "6.4",
 *   "requires_php": "7.4",
 *   "tested": "6.9",
 *   "last_updated": "2026-11-25",
 *   "sha256": "…",
 *   "changelog": "<h4>1.4.0</h4><ul><li>…</li></ul>"
 * }
 */

namespace SDE\Updater;

defined( 'ABSPATH' ) || exit;

const CACHE = 'sde_update_info';
const UA    = 'Toolkit-SD';

/**
 * Enkel downloads van deze adressen worden aanvaard.
 */
function allowed_host( $url ) {
	$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
	return 0 === strpos( (string) $url, 'https://' )
		&& in_array( $host, array( 'github.com', 'sonoradesign.be' ), true );
}

/**
 * Adres van het versiebestand. Overschrijfbaar met de constante SDE_UPDATE_URL in wp-config.php.
 */
function info_url() {
	return defined( 'SDE_UPDATE_URL' ) ? SDE_UPDATE_URL : 'https://github.com/Sonora-design/Toolkit-SD/releases/latest/download/info.json';
}

function plugin_file() {
	return plugin_basename( SDE_FILE );
}

/**
 * Versiebestand ophalen, 12 uur in cache. Bij een fout 1 uur wachten.
 *
 * @param bool $fresh Cache negeren.
 * @return array|null
 */
function info( $fresh = false ) {
	$cached = get_site_transient( CACHE );
	if ( ! $fresh && false !== $cached ) {
		return is_array( $cached ) ? $cached : null;
	}

	$res  = wp_remote_get(
		info_url(),
		array(
			'timeout'     => 8,
			'redirection' => 5,
			'user-agent'  => UA,
			'headers'     => array( 'Accept' => 'application/json' ),
		)
	);
	$data = null;
	if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( is_array( $json ) && ! empty( $json['version'] ) && ! empty( $json['download_url'] )
			&& allowed_host( $json['download_url'] ) ) {
			$data = $json;
		}
	}

	set_site_transient( CACHE, $data ? $data : 'fout', $data ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $data;
}

/**
 * Antwoord in het formaat dat WordPress verwacht.
 */
function response( array $i ) {
	return (object) array(
		'id'            => 'sonoradesign.be/toolkit-sd',
		'slug'          => 'toolkit-sd',
		'plugin'        => plugin_file(),
		'new_version'   => (string) $i['version'],
		'url'           => 'https://sonoradesign.be',
		'package'       => (string) $i['download_url'],
		'requires'      => (string) ( $i['requires'] ?? '' ),
		'requires_php'  => (string) ( $i['requires_php'] ?? '' ),
		'tested'        => (string) ( $i['tested'] ?? '' ),
		'icons'         => array(),
		'banners'       => array(),
		'compatibility' => new \stdClass(),
	);
}

/**
 * Nieuwe versie melden aan WordPress.
 */
add_filter(
	'pre_set_site_transient_update_plugins',
	function ( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		$i = info();
		if ( ! $i ) {
			return $transient;
		}
		$r = response( $i );
		if ( version_compare( $r->new_version, \SDE\VERSION, '>' ) ) {
			$transient->response[ plugin_file() ] = $r;
			unset( $transient->no_update[ plugin_file() ] );
		} else {
			// Ook "geen update" melden, zodat de schakelaar voor automatische updates verschijnt.
			$transient->no_update[ plugin_file() ] = $r;
			unset( $transient->response[ plugin_file() ] );
		}
		return $transient;
	}
);

/**
 * Venster "Details bekijken" met de changelog.
 */
add_filter(
	'plugins_api',
	function ( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'toolkit-sd' !== $args->slug ) {
			return $result;
		}
		$i = info();
		if ( ! $i ) {
			return $result;
		}
		return (object) array(
			'name'          => 'Toolkit SD',
			'slug'          => 'toolkit-sd',
			'version'       => (string) $i['version'],
			'author'        => '<a href="https://sonoradesign.be">Sonora design</a>',
			'homepage'      => 'https://sonoradesign.be',
			'requires'      => (string) ( $i['requires'] ?? '' ),
			'requires_php'  => (string) ( $i['requires_php'] ?? '' ),
			'tested'        => (string) ( $i['tested'] ?? '' ),
			'last_updated'  => (string) ( $i['last_updated'] ?? '' ),
			'download_link' => (string) $i['download_url'],
			'sections'      => array(
				'description' => '<p>Vaste toolkit van Sonora design voor praktijkwebsites.</p>',
				'changelog'   => wp_kses_post( (string) ( $i['changelog'] ?? '' ) ),
			),
		);
	},
	10,
	3
);

/**
 * Download controleren met de controlesom (sha256) uit het versiebestand.
 * Een onvolledige of beschadigde zip wordt zo nooit geïnstalleerd.
 */
add_filter(
	'upgrader_pre_download',
	function ( $reply, $package ) {
		if ( false !== $reply ) {
			return $reply;
		}
		$i = info();
		if ( ! $i || $package !== $i['download_url'] || empty( $i['sha256'] ) ) {
			return $reply;
		}
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$ua = function ( $args, $url ) {
			$host = (string) wp_parse_url( $url, PHP_URL_HOST );
			if ( 'github.com' === $host || '.githubusercontent.com' === substr( $host, -22 ) ) {
				$args['user-agent'] = UA;
			}
			return $args;
		};
		add_filter( 'http_request_args', $ua, 10, 2 );
		$tmp = download_url( $package, 300 );
		remove_filter( 'http_request_args', $ua, 10 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		if ( ! hash_equals( strtolower( (string) $i['sha256'] ), hash_file( 'sha256', $tmp ) ) ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'sde_checksum', 'De download van Toolkit SD klopt niet met de controlesom. De update is afgebroken; er is niets gewijzigd.' );
		}
		return $tmp;
	},
	10,
	2
);

/**
 * Na een update of bij "Opnieuw controleren" het versiebestand opnieuw lezen.
 */
add_action(
	'upgrader_process_complete',
	function () {
		delete_site_transient( CACHE );
	}
);
add_action(
	'load-update-core.php',
	function () {
		if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- enkel lezen.
			delete_site_transient( CACHE );
		}
	}
);
