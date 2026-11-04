/**
 * Block editor: show the Latin slug before the first save.
 *
 * Until a post has a slug, the editor previews one in the browser with
 * cleanForSlug(title), which keeps Arabic letters. This asks the server for
 * the slug the plugin will really create (same rules, preset and custom
 * replacements) and puts it into the editor's copy of the saved post. It is
 * not an edit: the post is not marked as changed and the value is never sent;
 * WordPress creates the slug itself on save.
 */
( function ( wp ) {
	'use strict';

	var ARABIC = /[؀-ۿݐ-ݿࢠ-ࣿﭐ-﷿ﹰ-﻿]/;
	var cache = {};
	var pendingTitle = null;
	var timer = null;
	// Slug this script put into the saved record; it doesn't count as a real slug.
	var injected = '';

	function hasRealSlug( post ) {
		return !! post.slug && post.slug !== injected;
	}

	function apply( slug ) {
		var editor = wp.data.select( 'core/editor' );
		var post = editor.getCurrentPost();
		if ( ! post || ! post.id || hasRealSlug( post ) || post.slug === slug ) {
			return;
		}
		injected = slug;
		var record = wp.data.select( 'core' ).getEntityRecord( 'postType', post.type, post.id );
		if ( record ) {
			wp.data.dispatch( 'core' ).receiveEntityRecords( 'postType', post.type, [ Object.assign( {}, record, { slug: slug } ) ] );
		}
	}

	function update() {
		var editor = wp.data.select( 'core/editor' );
		var post = editor && editor.getCurrentPost();
		// Only posts without a real slug yet, and only when nobody typed one.
		if ( ! post || ! post.id || hasRealSlug( post ) || editor.getPostEdits().slug ) {
			return;
		}
		var title = editor.getEditedPostAttribute( 'title' ) || '';
		if ( ! ARABIC.test( title ) ) {
			return;
		}
		if ( cache[ title ] ) {
			apply( cache[ title ] );
			return;
		}
		if ( title === pendingTitle ) {
			return;
		}
		pendingTitle = title;
		clearTimeout( timer );
		timer = setTimeout( function () {
			wp.apiFetch( { path: wp.url.addQueryArgs( '/arabic-to-lat/v1/slug', { title: title } ) } )
				.then( function ( response ) {
					if ( response && response.slug ) {
						cache[ title ] = response.slug;
						if ( ( wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '' ) === title ) {
							apply( response.slug );
						}
					}
				} )
				.catch( function () {} )
				.finally( function () {
					if ( pendingTitle === title ) {
						pendingTitle = null;
					}
				} );
		}, 400 );
	}

	wp.domReady( function () {
		wp.data.subscribe( update );
	} );
}( window.wp ) );
