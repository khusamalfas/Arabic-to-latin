/**
 * Block editor: "Suggest an English slug" panel (AI slug suggestions).
 *
 * Asks the site's REST endpoint, which calls Claude or ChatGPT with the site
 * owner's key. The suggestion is only applied when the author clicks "Use",
 * as a normal slug edit they can still change. Loaded only when AI is on and
 * a key is set.
 */
( function ( wp ) {
	'use strict';

	var settings = window.arabicToLatAI;
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	if ( ! settings || ! Panel || ! wp.plugins ) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;
	var SelectControl = wp.components.SelectControl;
	var Notice = wp.components.Notice;

	function SlugPanel() {
		var editor = wp.data.select( 'core/editor' );
		var state = useState( { busy: false, slug: '', error: '', cached: false } );
		var view = state[ 0 ];
		var setView = state[ 1 ];
		var styleState = useState( settings.style );
		var style = styleState[ 0 ];
		var setStyle = styleState[ 1 ];

		function suggest() {
			var title = editor.getEditedPostAttribute( 'title' ) || '';
			if ( ! title.trim() ) {
				setView( { busy: false, slug: '', error: __( 'Write a title first, then ask for a slug.', 'arabic-to-lat' ), cached: false } );
				return;
			}
			setView( { busy: true, slug: '', error: '', cached: false } );
			wp.apiFetch( {
				path: '/arabic-to-lat/v1/ai-slug',
				method: 'POST',
				data: {
					post_id: editor.getCurrentPostId(),
					title: title,
					content: editor.getEditedPostContent() || '',
					style: style,
				},
			} ).then( function ( response ) {
				setView( { busy: false, slug: response.unique || response.slug, error: '', cached: !! response.cached } );
			} ).catch( function ( error ) {
				setView( { busy: false, slug: '', error: ( error && error.message ) || __( 'The suggestion failed. Try again.', 'arabic-to-lat' ), cached: false } );
			} );
		}

		function applySlug() {
			wp.data.dispatch( 'core/editor' ).editPost( { slug: view.slug } );
		}

		var current = editor.getEditedPostSlug ? editor.getEditedPostSlug() : '';

		return el(
			Panel,
			{ name: 'arabic-to-lat-ai-slug', title: __( 'English slug (AI)', 'arabic-to-lat' ), className: 'arabic-to-lat-ai-slug' },
			el( SelectControl, {
				label: __( 'Style', 'arabic-to-lat' ),
				value: style,
				options: [
					{ value: 'describe', label: __( 'Describe the post', 'arabic-to-lat' ) },
					{ value: 'translate', label: __( 'Translate the title', 'arabic-to-lat' ) },
				],
				onChange: setStyle,
				__nextHasNoMarginBottom: true,
			} ),
			el( 'p', null,
				el( Button, { variant: 'secondary', onClick: suggest, disabled: view.busy, isBusy: view.busy },
					/* translators: %s: Claude or ChatGPT */
					wp.i18n.sprintf( __( 'Suggest with %s', 'arabic-to-lat' ), settings.provider )
				),
				view.busy ? el( Spinner ) : null
			),
			view.error ? el( Notice, { status: 'warning', isDismissible: false }, view.error ) : null,
			view.slug ? el( 'div', null,
				el( 'p', null, el( 'code', null, view.slug ) ),
				el( Button, { variant: 'primary', onClick: applySlug, disabled: current === view.slug }, current === view.slug ? __( 'In use', 'arabic-to-lat' ) : __( 'Use this slug', 'arabic-to-lat' ) )
			) : null,
			el( 'p', { className: 'description', style: { marginTop: '8px', color: '#757575' } },
				__( 'Sends the title and the start of the post to the AI provider. Nothing changes until you click "Use this slug".', 'arabic-to-lat' )
			)
		);
	}

	wp.plugins.registerPlugin( 'arabic-to-lat-ai-slug', { render: SlugPanel } );
}( window.wp ) );
