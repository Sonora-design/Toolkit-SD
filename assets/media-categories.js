/* Sonora design enhancements: filter op mediacategorie in de rasterweergave en het mediavenster. */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.media || ! wp.media.view || ! window.SDE_MEDIA_CATS ) {
		return;
	}

	var cfg = window.SDE_MEDIA_CATS;

	var Filter = wp.media.view.AttachmentFilters.extend( {
		id: 'sde-media-category-filter',
		createFilters: function () {
			var filters = {};
			var allProps = {};
			allProps[ cfg.tax ] = '';
			filters.all = { text: cfg.all, props: allProps, priority: 10 };
			cfg.terms.forEach( function ( term, i ) {
				var props = {};
				props[ cfg.tax ] = term.slug;
				filters[ 'term_' + term.slug ] = { text: term.name, props: props, priority: 20 + i };
			} );
			this.filters = filters;
		}
	} );

	var Browser = wp.media.view.AttachmentsBrowser;
	wp.media.view.AttachmentsBrowser = Browser.extend( {
		createToolbar: function () {
			Browser.prototype.createToolbar.apply( this, arguments );
			this.toolbar.set(
				'sdeMediaCategory',
				new Filter( {
					controller: this.controller,
					model: this.collection.props,
					priority: -75
				} ).render()
			);
		}
	} );
} )();
