/**
 * Settings → Arabic to Latin: convert existing slugs in batches with a
 * progress bar, so large sites never hit a PHP time limit. Without
 * JavaScript the form still posts to admin-post.php and converts one batch.
 */
( function () {
	'use strict';

	var settings = window.arabicToLatConvert;
	var form = document.getElementById( 'atl-convert-form' );
	if ( ! settings || ! form || ! window.fetch || ! window.FormData ) {
		return;
	}

	function format( template, done, total ) {
		return template.replace( '%1$d', done ).replace( '%2$d', total );
	}

	form.addEventListener( 'submit', function ( event ) {
		var submitter = event.submitter || document.activeElement;
		var backup = form.querySelector( '[name="arabic_to_lat_backup"]' );
		// Preview keeps the normal form post; only the conversion runs in batches.
		if ( ! submitter || 'arabic_to_lat_apply' !== submitter.name || ! backup || ! backup.checked ) {
			return;
		}
		event.preventDefault();

		var scope = form.querySelectorAll( '[name="arabic_to_lat_post_types[]"]:checked, [name="arabic_to_lat_taxonomies[]"]:checked' );
		var total = 0;
		Array.prototype.forEach.call( scope, function ( box ) {
			total += parseInt( box.getAttribute( 'data-count' ), 10 ) || 0;
		} );
		var wrap = form.querySelector( '.atl-progress' );
		var bar = form.querySelector( 'progress' );
		var status = form.querySelector( '.atl-progress-status' );
		var button = submitter;
		var nonce = form.querySelector( '[name="_wpnonce"]' ).value;
		var cursor = { posts: 0, terms: 0 };
		var converted = 0;

		wrap.hidden = false;
		bar.max = Math.max( total, 1 );
		button.disabled = true;
		status.textContent = format( settings.progress, 0, total );

		function batch() {
			var data = new FormData();
			data.append( 'action', 'arabic_to_lat_convert_batch' );
			data.append( '_wpnonce', nonce );
			data.append( 'cursor_posts', cursor.posts );
			data.append( 'cursor_terms', cursor.terms );
			data.append( 'arabic_to_lat_scope', '1' );
			Array.prototype.forEach.call( scope, function ( box ) {
				data.append( box.name, box.value );
			} );

			fetch( settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( response ) {
					if ( ! response || ! response.success ) {
						throw new Error( 'batch failed' );
					}
					converted += response.data.converted;
					cursor = response.data.cursor;
					bar.value = Math.min( converted, total );
					status.textContent = format( settings.progress, converted, total );
					if ( response.data.done ) {
						// A fresh query value forces a reload even when the page is already on the result URL.
						var url = new URL( settings.resultUrl, window.location.href );
						url.searchParams.set( 'arabic_to_lat_run', Date.now() );
						window.location.assign( url.toString() );
					} else {
						batch();
					}
				} )
				.catch( function () {
					status.textContent = settings.error;
					button.disabled = false;
				} );
		}

		batch();
	} );
}() );
