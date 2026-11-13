/**
 * Classic editor: the "English slug (AI)" box (AI slug suggestions).
 *
 * Same endpoint and behaviour as the block editor panel: the suggestion is only
 * applied when the author clicks "Use this slug", through WordPress's own
 * permalink save, so the slug can still be edited like any other.
 */
( function ( $, wp ) {
	'use strict';

	var settings = window.arabicToLatAIClassic;
	var box = document.getElementById( 'arabic-to-lat-ai-slug-box' );
	if ( ! settings || ! box || ! wp || ! wp.apiFetch ) {
		return;
	}

	var suggestButton = box.querySelector( '.atl-ai-suggest' );
	var spinner = box.querySelector( '.spinner' );
	var styleSelect = box.querySelector( '.atl-ai-style' );
	var result = box.querySelector( '.atl-ai-result' );
	var resultSlug = box.querySelector( '.atl-ai-slug' );
	var useButton = box.querySelector( '.atl-ai-use' );
	var message = box.querySelector( '.atl-ai-message' );
	var suggested = '';

	function field( id ) {
		var node = document.getElementById( id );
		return node ? node.value : '';
	}

	function content() {
		var editor = window.tinymce && window.tinymce.get( 'content' );
		if ( editor && ! editor.isHidden() ) {
			return editor.getContent();
		}
		return field( 'content' );
	}

	function showMessage( text ) {
		message.textContent = text;
		message.hidden = '' === text;
	}

	function refreshUseButton() {
		var inUse = '' !== suggested && field( 'post_name' ) === suggested;
		useButton.textContent = inUse ? settings.inUse : settings.useThis;
		useButton.disabled = inUse;
	}

	suggestButton.addEventListener( 'click', function () {
		var title = field( 'title' );
		showMessage( '' );
		if ( ! title.trim() ) {
			showMessage( settings.noTitle );
			return;
		}
		suggestButton.disabled = true;
		spinner.classList.add( 'is-active' );
		result.hidden = true;

		wp.apiFetch( {
			path: '/arabic-to-lat/v1/ai-slug',
			method: 'POST',
			data: {
				post_id: parseInt( field( 'post_ID' ), 10 ) || 0,
				title: title,
				content: content(),
				style: styleSelect.value,
			},
		} ).then( function ( response ) {
			suggested = response.unique || response.slug;
			resultSlug.textContent = suggested;
			result.hidden = false;
			refreshUseButton();
		} ).catch( function ( error ) {
			showMessage( ( error && error.message ) || settings.failed );
		} ).then( function () {
			suggestButton.disabled = false;
			spinner.classList.remove( 'is-active' );
		} );
	} );

	useButton.addEventListener( 'click', function () {
		if ( '' === suggested ) {
			return;
		}
		useButton.disabled = true;
		// WordPress's own permalink save: same nonce, same uniqueness check, same slug box.
		$.post(
			window.ajaxurl,
			{
				action: 'sample-permalink',
				post_id: field( 'post_ID' ),
				new_slug: suggested,
				new_title: field( 'title' ),
				samplepermalinknonce: field( 'samplepermalinknonce' ),
			},
			function ( html ) {
				var slugBox = $( '#edit-slug-box' );
				slugBox.html( html ).removeClass( 'hidden' ).show();
				// The saved slug is what WordPress made of it (it may add -2).
				var saved = slugBox.find( '#editable-post-name-full' ).text() || suggested;
				$( '#post_name' ).val( saved );
				suggested = saved;
				resultSlug.textContent = saved;
				refreshUseButton();
				if ( wp.a11y && wp.a11y.speak ) {
					wp.a11y.speak( settings.inUse );
				}
			}
		).fail( function () {
			useButton.disabled = false;
			showMessage( settings.failed );
		} );
	} );
}( window.jQuery, window.wp ) );
