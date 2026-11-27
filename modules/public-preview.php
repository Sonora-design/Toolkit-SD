<?php
/**
 * Module: publieke preview van concepten.
 *
 * Een beheerder of redacteur deelt een concept via een geheime link. Wie de link heeft,
 * ziet het concept op de site zonder in te loggen.
 *
 * - De sleutel staat enkel in de link. In de database staat alleen een HMAC ervan
 *   (_sde_preview_hash) en de vervaldatum (_sde_preview_expires). Een link kan daarom later
 *   niet opnieuw getoond worden: "Nieuwe link maken" maakt de oude ongeldig.
 * - Enkel de hoofdquery op de voorkant wordt aangepast, en enkel voor dat ene bericht.
 *   Feeds, sitemaps, embeds en de REST API tonen het concept nooit.
 * - De link vervalt bij publiceren, bij prullenbak of verwijderen, en na de vervaldatum.
 * - De previewpagina krijgt noindex, geen referrer en geen cache. Er wordt niets over
 *   bezoekers bewaard of gelogd.
 */

namespace SDE\Preview;

defined( 'ABSPATH' ) || exit;

const PARAM        = 'sde_preview';
const META_HASH    = '_sde_preview_hash';
const META_EXPIRES = '_sde_preview_expires';
const CRON         = 'sde_preview_cleanup';
const STATUSES     = array( 'draft', 'pending', 'future' );

/* -------------------------------------------------------------------------
 * Instellingen en rechten
 * ---------------------------------------------------------------------- */

function post_types() {
	return array_map( 'sanitize_key', \SDE\lines( \SDE\settings()['preview_post_types'] ) );
}

function days() {
	return max( 1, min( 30, (int) \SDE\settings()['preview_days'] ) );
}

/**
 * Kan dit bericht een publieke preview hebben (berichttype en status)?
 */
function eligible( $post ) {
	return $post instanceof \WP_Post
		&& in_array( $post->post_type, post_types(), true )
		&& in_array( $post->post_status, STATUSES, true );
}

/**
 * Mag de huidige gebruiker de preview van dit bericht beheren?
 * Nodig: het bericht mogen bewerken, en administrator zijn of een gekozen rol hebben.
 */
function can_manage( $post_id ) {
	if ( ! is_user_logged_in() || ! current_user_can( 'edit_post', $post_id ) ) {
		return false;
	}
	if ( is_super_admin() || \SDE\user_has_role( array( 'administrator' ) ) ) {
		return true;
	}
	return \SDE\user_has_role( \SDE\lines( \SDE\settings()['preview_roles'] ) );
}

/* -------------------------------------------------------------------------
 * Link maken, uitzetten en nakijken
 * ---------------------------------------------------------------------- */

function hash_key( $key ) {
	return hash_hmac( 'sha256', (string) $key, wp_salt( 'auth' ) );
}

function clear( $post_id ) {
	delete_post_meta( $post_id, META_HASH );
	delete_post_meta( $post_id, META_EXPIRES );
}

/**
 * Tot wanneer is er een link actief? 0 = geen (of verlopen).
 */
function active_until( $post_id ) {
	$expires = (int) get_post_meta( $post_id, META_EXPIRES, true );
	if ( ! $expires || '' === (string) get_post_meta( $post_id, META_HASH, true ) ) {
		return 0;
	}
	return $expires > time() ? $expires : 0;
}

/**
 * Nieuwe link maken. Een bestaande link wordt ongeldig.
 *
 * @return string|\WP_Error De link, of waarom het niet kan.
 */
function create( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, post_types(), true ) ) {
		return new \WP_Error( 'sde_preview_type', 'Voor dit berichttype staat de publieke preview niet aan.' );
	}
	if ( 'auto-draft' === $post->post_status ) {
		return new \WP_Error( 'sde_preview_status', 'Bewaar het concept eerst.' );
	}
	if ( ! in_array( $post->post_status, STATUSES, true ) ) {
		return new \WP_Error( 'sde_preview_status', 'Een publieke preview kan enkel bij een concept, een bericht in afwachting of een ingepland bericht.' );
	}

	$key = wp_generate_password( 32, false );
	update_post_meta( $post->ID, META_HASH, hash_key( $key ) );
	update_post_meta( $post->ID, META_EXPIRES, time() + days() * DAY_IN_SECONDS );

	// Andere berichttypes dan pagina's: het type zetten we zelf bij het openen van de link.
	$arg = ( 'page' === $post->post_type ) ? 'page_id' : 'p';
	return add_query_arg(
		array(
			$arg  => $post->ID,
			PARAM => $key,
		),
		home_url( '/' )
	);
}

/**
 * Klopt de sleutel voor dit bericht, en is de link nog geldig?
 * Een verlopen link wordt meteen opgeruimd.
 */
function valid( $post, $key ) {
	if ( ! eligible( $post ) || ! is_string( $key ) || ! preg_match( '/^[A-Za-z0-9]{32}$/', $key ) ) {
		return false;
	}
	$hash    = (string) get_post_meta( $post->ID, META_HASH, true );
	$expires = (int) get_post_meta( $post->ID, META_EXPIRES, true );
	if ( '' === $hash || ! hash_equals( $hash, hash_key( $key ) ) ) {
		return false;
	}
	if ( $expires <= time() ) {
		clear( $post->ID );
		return false;
	}
	return true;
}

/**
 * Toestand voor het paneel in de editor.
 */
function state( $post_id ) {
	$until = active_until( $post_id );
	return array(
		'active'  => (bool) $until,
		'expires' => $until ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $until ) : '',
	);
}

/* -------------------------------------------------------------------------
 * Link openen (enkel voorkant, enkel de hoofdquery)
 * ---------------------------------------------------------------------- */

/**
 * ID van het concept dat in deze aanvraag via een geldige link getoond wordt (0 = geen).
 */
function shown( $set = null ) {
	static $id = 0;
	if ( null !== $set ) {
		$id = (int) $set;
	}
	return $id;
}

add_action(
	'pre_get_posts',
	function ( $q ) {
		// phpcs:disable WordPress.Security.NonceVerification -- publieke link, de sleutel is de controle.
		if ( is_admin() || ! $q->is_main_query() || ! isset( $_GET[ PARAM ] ) ) {
			return;
		}
		// Een pagina met een sleutel in het adres hoort nooit in een paginacache.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! $q->is_singular || $q->is_feed || $q->is_embed || $q->is_trackback ) {
			return;
		}
		$key = wp_unslash( $_GET[ PARAM ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wordt in valid() strikt nagekeken.
		// phpcs:enable
		$id  = absint( $q->get( 'page_id' ) );
		$id  = $id ? $id : absint( $q->get( 'p' ) );
		// get_post() zet het echte bericht in de cache, vóór de query het als gepubliceerd toont.
		$post = $id ? get_post( $id ) : null;
		if ( ! valid( $post, $key ) ) {
			return; // WordPress geeft zelf een gewone 404, net als zonder sleutel.
		}

		shown( $post->ID );
		if ( 'page' !== $post->post_type ) {
			$q->set( 'post_type', $post->post_type );
		}
		add_filter( 'posts_results', __NAMESPACE__ . '\unlock', 10, 2 );
		add_filter( 'redirect_canonical', '__return_false' );
		add_filter( 'comments_open', '__return_false' );
		add_filter( 'pings_open', '__return_false' );
		add_filter( 'wp_robots', __NAMESPACE__ . '\robots', PHP_INT_MAX );
	}
);

/**
 * Enkel dat ene bericht tonen alsof het gepubliceerd is. Geldt voor één query.
 */
function unlock( $posts, $q ) {
	if ( ! $q->is_main_query() ) {
		return $posts;
	}
	remove_filter( 'posts_results', __NAMESPACE__ . '\unlock', 10 );
	if ( 1 === count( (array) $posts ) && (int) $posts[0]->ID === shown() ) {
		$posts[0]->post_status = 'publish';
	} else {
		shown( 0 );
	}
	return $posts;
}

function robots( $robots ) {
	unset( $robots['follow'], $robots['index'] );
	$robots['noindex']  = true;
	$robots['nofollow'] = true;
	return $robots;
}

/**
 * Wordt nu echt het concept via de geheime link getoond?
 */
function is_public_preview() {
	return shown() && is_singular() && get_queried_object_id() === shown();
}

// Headers: niet indexeren, geen referrer (de sleutel staat in het adres) en geen cache.
add_action(
	'wp',
	function () {
		if ( ! is_public_preview() || headers_sent() ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Referrer-Policy: no-referrer', true );
	}
);

add_action(
	'wp_head',
	function () {
		if ( is_public_preview() ) {
			echo '<meta name="referrer" content="no-referrer">' . "\n";
		}
	},
	1
);

// Na publiceren: de oude link toont gewoon de pagina, zonder de sleutel in het adres.
add_action(
	'template_redirect',
	function () {
		// phpcs:ignore WordPress.Security.NonceVerification -- enkel doorsturen naar een publieke pagina.
		if ( ! isset( $_GET[ PARAM ] ) || shown() || ! is_singular() ) {
			return;
		}
		$post = get_post( get_queried_object_id() );
		if ( $post && 'publish' === $post->post_status && '' === (string) $post->post_password ) {
			$url = get_permalink( $post );
			if ( $url ) {
				wp_safe_redirect( $url, 302 );
				exit;
			}
		}
	},
	1
);

// Elementor: de opmaak van het concept laden als Elementor dat zelf niet deed.
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_public_preview() || ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			return;
		}
		$id = shown();
		if ( 'builder' !== get_post_meta( $id, '_elementor_edit_mode', true ) || wp_style_is( 'elementor-post-' . $id, 'enqueued' ) ) {
			return;
		}
		try {
			\Elementor\Core\Files\CSS\Post::create( $id )->enqueue();
		} catch ( \Throwable $e ) {
			return; // Zonder de extra opmaak verder gaan.
		}
	},
	50
);

/* -------------------------------------------------------------------------
 * Automatisch ongeldig
 * ---------------------------------------------------------------------- */

// Publiceren, privé zetten of naar de prullenbak: de link vervalt.
add_action(
	'transition_post_status',
	function ( $new, $old, $post ) {
		if ( $new !== $old && ! in_array( $new, STATUSES, true ) && $post instanceof \WP_Post ) {
			clear( $post->ID );
		}
	},
	10,
	3
);
add_action( 'wp_trash_post', __NAMESPACE__ . '\clear' );
add_action( 'before_delete_post', __NAMESPACE__ . '\clear' );

// Dagelijks verlopen links opruimen. Het uitschrijven gebeurt in toolkit-sd.php.
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', CRON );
		}
	}
);

add_action(
	CRON,
	function () {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND CAST( meta_value AS UNSIGNED ) < %d LIMIT 500",
				META_EXPIRES,
				time()
			)
		);
		foreach ( (array) $ids as $id ) {
			clear( (int) $id );
		}
	}
);

/* -------------------------------------------------------------------------
 * Beheer: paneel in de editor en knop in de admin bar
 * ---------------------------------------------------------------------- */

function script( array $extra ) {
	$file = 'assets/public-preview.js';
	wp_enqueue_script( 'sde-public-preview', SDE_URL . $file, array(), \SDE\VERSION . '.' . (int) @filemtime( SDE_DIR . $file ), true );
	wp_add_inline_script(
		'sde-public-preview',
		'window.SDE_PREVIEW = ' . wp_json_encode(
			array_merge(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'text'    => array(
						'off'     => 'Niet gedeeld.',
						'on'      => 'Er is een link actief tot %s.',
						'lost'    => 'De link zelf bewaren we niet. Ben je hem kwijt, maak dan een nieuwe.',
						'fresh'   => 'Kopieer de link nu: je kan hem later niet opnieuw bekijken. Geldig tot %s.',
						'copy'    => 'Kopiëren',
						'copied'  => 'Gekopieerd',
						'busy'    => 'Even geduld…',
						'error'   => 'Dat lukte niet. Probeer het opnieuw.',
						'replace' => 'Er is al een link actief. Een nieuwe link maken? De oude werkt dan niet meer.',
						'manual'  => 'Kopieer de link:',
						'bar'     => 'Publieke preview kopiëren',
					),
				),
				$extra
			)
		) . ';',
		'before'
	);
}

// Paneel: in de blokeditor staat het in de zijbalk, in de klassieke editor als metabox.
add_action(
	'add_meta_boxes',
	function ( $post_type, $post = null ) {
		if ( ! ( $post instanceof \WP_Post ) || ! in_array( $post_type, post_types(), true ) || ! can_manage( $post->ID ) ) {
			return;
		}
		add_meta_box( 'sde-public-preview', 'Publieke preview', __NAMESPACE__ . '\panel', $post_type, 'side', 'default' );
	},
	10,
	2
);

function panel( $post ) {
	$state = state( $post->ID );
	?>
	<div class="sde-pp" data-sde-preview data-post="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'sde_preview_' . $post->ID ) ); ?>" data-active="<?php echo $state['active'] ? '1' : '0'; ?>" data-expires="<?php echo esc_attr( $state['expires'] ); ?>">
		<p><label><input type="checkbox" data-pp-toggle <?php checked( $state['active'] ); ?>> Deel dit concept via een geheime link</label></p>
		<p class="description" data-pp-state><?php echo esc_html( $state['active'] ? 'Er is een link actief tot ' . $state['expires'] . '.' : 'Niet gedeeld.' ); ?></p>
		<div data-pp-link hidden>
			<input type="text" readonly class="widefat" data-pp-url aria-label="Link naar de publieke preview" onfocus="this.select()">
			<p><button type="button" class="button button-primary" data-pp-copy>Kopiëren</button></p>
		</div>
		<p data-pp-actions<?php echo $state['active'] ? '' : ' hidden'; ?>>
			<button type="button" class="button" data-pp-new>Nieuwe link maken</button>
			<button type="button" class="button-link button-link-delete" data-pp-off style="margin-left:8px">Uitzetten</button>
		</p>
		<p class="description">Wie de link heeft, ziet dit concept zonder in te loggen. De link vervalt na <?php echo (int) days(); ?> <?php echo 1 === days() ? 'dag' : 'dagen'; ?> en zodra je publiceert.</p>
	</div>
	<?php
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$post = get_post();
		if ( $post && in_array( $post->post_type, post_types(), true ) && can_manage( $post->ID ) ) {
			script( array() );
		}
	}
);

/**
 * Concept dat een ingelogde gebruiker nu op de voorkant bekijkt en mag delen (0 = geen).
 * Dit is de gewone previewpagina van WordPress of Elementor.
 */
function bar_target() {
	static $id = null;
	if ( null === $id ) {
		$id = 0;
		if ( ! is_admin() && is_singular() && is_admin_bar_showing() ) {
			$post = get_post( get_queried_object_id() ); // Het echte bericht, met zijn echte status.
			if ( eligible( $post ) && can_manage( $post->ID ) ) {
				$id = (int) $post->ID;
			}
		}
	}
	return $id;
}

add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! did_action( 'wp' ) || ! bar_target() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'sde-preview',
				'title' => 'Publieke preview kopiëren',
				'href'  => '#',
			)
		);
	},
	90
);

// De module "Menu voor editors" laat deze knop staan.
add_filter(
	'toolkit_sd_editor_bar_keep',
	function ( $keep ) {
		$keep   = (array) $keep;
		$keep[] = 'sde-preview';
		return $keep;
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		$id = bar_target();
		if ( ! $id ) {
			return;
		}
		script(
			array(
				'bar' => array(
					'post'   => $id,
					'nonce'  => wp_create_nonce( 'sde_preview_' . $id ),
					'active' => (bool) active_until( $id ),
				),
			)
		);
	}
);

// Acties uit het paneel en de admin bar. Enkel voor ingelogde gebruikers (geen nopriv).
add_action(
	'wp_ajax_sde_preview',
	function () {
		$id = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0;
		check_ajax_referer( 'sde_preview_' . $id, 'nonce' );
		if ( ! $id || ! can_manage( $id ) ) {
			wp_send_json_error( array( 'message' => 'Geen toestemming.' ), 403 );
		}
		$do = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';

		if ( 'disable' === $do ) {
			clear( $id );
			wp_send_json_success( state( $id ) );
		}
		if ( 'create' === $do ) {
			$url = create( $id );
			if ( is_wp_error( $url ) ) {
				wp_send_json_error( array_merge( state( $id ), array( 'message' => $url->get_error_message() ) ) );
			}
			wp_send_json_success( array_merge( state( $id ), array( 'url' => $url ) ) );
		}
		wp_send_json_error( array( 'message' => 'Onbekende actie.' ) );
	}
);
