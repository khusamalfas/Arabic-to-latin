/**
 * ACF field group editor: Latin field names from Arabic labels.
 *
 * ACF builds a new field's name from its label and keeps Arabic letters
 * ("الاسم_الكامل"), which become meta keys. Once ACF has filled the name,
 * this asks the server for the transliteration (same rules and preset as
 * slugs) and swaps it in, unless the name was changed in the meantime.
 * Existing fields are never touched: their names are stored data keys.
 */
( function ( wp, acf ) {
	'use strict';

	if ( ! acf || ! acf.addFilter || ! wp || ! wp.apiFetch ) {
		return;
	}

	var ARABIC = /[؀-ۿݐ-ݿࢠ-ࣿﭐ-﷿ﹰ-﻿]/;

	acf.addFilter( 'generate_field_object_name', function ( name, field ) {
		if ( ! field || 'function' !== typeof field.prop || ! ARABIC.test( name ) ) {
			return name;
		}
		wp.apiFetch( { path: wp.url.addQueryArgs( '/arabic-to-lat/v1/slug', { title: name.replace( /_/g, ' ' ) } ) } )
			.then( function ( response ) {
				if ( ! response || ! response.slug || field.prop( 'name' ) !== name ) {
					return;
				}
				var latin = response.slug.replace( /-/g, '_' ).replace( /[^a-z0-9_]/g, '' );
				if ( latin ) {
					field.prop( 'name', latin );
				}
			} )
			.catch( function () {} );
		return name;
	} );
}( window.wp, window.acf ) );
