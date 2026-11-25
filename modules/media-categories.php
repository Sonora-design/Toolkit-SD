<?php
/**
 * Module: mediacategorieën.
 *
 * Gebruikt dezelfde indeling als ASE Pro (taxonomie "asenha-media-category"),
 * zodat bestaande categorieën en koppelingen behouden blijven na de overstap.
 * - Categorieën beheren onder Media > Mediacategorieën.
 * - Categorie kiezen bij het bewerken van een bestand.
 * - Filteren op categorie in de lijstweergave en (optioneel) in de rasterweergave en het mediavenster.
 */

namespace SDE\MediaCategories;

defined( 'ABSPATH' ) || exit;

const TAX = 'asenha-media-category';

add_action(
	'init',
	function () {
		if ( taxonomy_exists( TAX ) ) {
			return; // ASE Pro registreert ze nog zelf.
		}
		register_taxonomy(
			TAX,
			'attachment',
			array(
				'labels'                => array(
					'name'          => 'Mediacategorieën',
					'singular_name' => 'Mediacategorie',
					'menu_name'     => 'Mediacategorieën',
					'all_items'     => 'Alle categorieën',
					'edit_item'     => 'Categorie bewerken',
					'add_new_item'  => 'Nieuwe categorie',
					'search_items'  => 'Categorieën zoeken',
					'not_found'     => 'Geen categorieën gevonden',
				),
				'hierarchical'          => true,
				'public'                => false,
				'show_ui'               => true,
				'show_in_menu'          => true,
				'show_admin_column'     => true,
				'show_in_rest'          => false,
				'query_var'             => TAX,
				'rewrite'               => false,
				'update_count_callback' => '_update_generic_term_count',
			)
		);
	},
	20
);

// Filter in de lijstweergave van de mediabibliotheek.
add_action(
	'restrict_manage_posts',
	function ( $post_type ) {
		if ( 'attachment' !== $post_type || ! taxonomy_exists( TAX ) ) {
			return;
		}
		wp_dropdown_categories(
			array(
				'taxonomy'        => TAX,
				'name'            => TAX,
				'value_field'     => 'slug',
				'show_option_all' => 'Alle mediacategorieën',
				'hide_empty'      => false,
				'hierarchical'    => true,
				'orderby'         => 'name',
				'selected'        => isset( $_GET[ TAX ] ) ? sanitize_title( wp_unslash( $_GET[ TAX ] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
			)
		);
	}
);

// Filter in de rasterweergave en in het mediavenster (ook in Elementor).
add_action(
	'wp_enqueue_media',
	function () {
		if ( empty( \SDE\settings()['media_grid_filter'] ) || ! taxonomy_exists( TAX ) ) {
			return;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => TAX,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $terms ) || ! $terms ) {
			return;
		}
		$list = array();
		foreach ( $terms as $t ) {
			$list[] = array(
				'slug' => $t->slug,
				'name' => $t->name . ' (' . (int) $t->count . ')',
			);
		}
		wp_enqueue_script( 'sde-media-categories', SDE_URL . 'assets/media-categories.js', array( 'media-views' ), \SDE\VERSION, true );
		wp_add_inline_script(
			'sde-media-categories',
			'window.SDE_MEDIA_CATS = ' . wp_json_encode(
				array(
					'tax'   => TAX,
					'all'   => 'Alle mediacategorieën',
					'terms' => $list,
				)
			) . ';',
			'before'
		);
	}
);
