<?php
/**
 * Runtime hooks.
 *
 * @package ArabicToLat
 */

namespace ArabicToLat;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the transliterator into WordPress.
 *
 * Everything runs through core's own sanitize_title() and sanitize_file_name()
 * filters, so slugs created by the classic editor, the block editor (REST API),
 * Quick Edit, XML-RPC, WP-CLI, cron, imports, WooCommerce and multilingual
 * plugins (WPML, Polylang, TranslatePress, ...) are all covered by one path.
 */
final class Plugin {

	const OPTION = 'arabic_to_lat_settings';

	/**
	 * Caller classes returned by caller().
	 */
	const CALLER_CREATE = 'create';
	const CALLER_LOOKUP = 'lookup';
	const CALLER_OTHER  = 'other';

	/**
	 * Functions that create a slug. When one of them is the nearest caller,
	 * the text is always transliterated.
	 */
	const SLUG_CREATORS = array( 'wp_insert_post', 'wp_update_post', 'wp_insert_term', 'wp_update_term', 'wp_unique_post_slug', 'get_sample_permalink', 'wp_insert_attachment' );

	/**
	 * @var Plugin|null
	 */
	private static $instance;

	/**
	 * @var Transliterator|null
	 */
	private $transliterator;

	/**
	 * Converted file name (no extension) => original name, for this request.
	 *
	 * @var array<string,string>
	 */
	private $renamed_files = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		// Before core's sanitize_title_with_dashes (10), after TranslatePress (1).
		add_filter( 'sanitize_title', array( $this, 'sanitize_title' ), 9, 3 );
		add_filter( 'sanitize_file_name', array( $this, 'sanitize_file_name' ), 10, 2 );
		// After core's wp_old_slug_redirect (10), which skips pages and terms.
		add_action( 'template_redirect', array( $this, 'redirect_old_slug' ), 20 );
		add_filter( 'old_slug_redirect_url', array( $this, 'keep_query_on_core_redirect' ) );

		add_filter( 'wp_insert_attachment_data', array( $this, 'restore_attachment_title' ) );
		add_filter( 'wp_update_term_data', array( $this, 'record_old_term_slug' ), 10, 4 );
		add_action( 'post_updated', array( $this, 'record_old_page_slug' ), 10, 3 );
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_script' ) );

		foreach ( array( 'add_option_', 'update_option_', 'delete_option_' ) as $hook ) {
			add_action( $hook . self::OPTION, array( $this, 'reset' ) );
		}
		// Multisite: each site has its own preset and replacements.
		add_action( 'switch_blog', array( $this, 'reset' ) );
	}

	/**
	 * @return array{files: bool, preset: string, custom: array<string,string>}
	 */
	public static function settings() {
		$saved = get_option( self::OPTION, array() );

		return array(
			'files'  => isset( $saved['files'] ) ? (bool) $saved['files'] : true,
			'preset' => isset( $saved['preset'] ) && isset( Transliterator::presets()[ $saved['preset'] ] ) ? $saved['preset'] : 'standard',
			'custom' => isset( $saved['custom'] ) && is_array( $saved['custom'] ) ? $saved['custom'] : array(),
		);
	}

	public function transliterator() {
		if ( null === $this->transliterator ) {
			$settings             = self::settings();
			$this->transliterator = new Transliterator( $settings['custom'], $settings['preset'] );
		}
		return $this->transliterator;
	}

	/**
	 * Drops the cached transliterator after the settings change.
	 */
	public function reset() {
		$this->transliterator = null;
	}

	/**
	 * Transliterates titles being turned into slugs.
	 *
	 * Only the 'save' context is touched: 'query' lookups keep matching
	 * existing Arabic slugs, so old URLs never break because of this filter.
	 *
	 * @param string $title     Title after remove_accents().
	 * @param string $raw_title Title as passed to sanitize_title().
	 * @param string $context   'save', 'query' or 'display'.
	 */
	public function sanitize_title( $title, $raw_title = '', $context = 'save' ) {
		if ( 'save' !== $context || ! Transliterator::has_arabic( $title ) ) {
			return $title;
		}

		$caller = self::caller();
		if ( self::CALLER_LOOKUP === $caller || ( self::CALLER_OTHER === $caller && self::is_public_request() ) ) {
			return $title;
		}
		if ( self::CALLER_CREATE === $caller && ( self::is_unchanged_term_slug( $title ) || self::is_imported_slug( $title ) ) ) {
			return $title;
		}

		if ( ! $this->should_transliterate( $title, $raw_title, $context ) ) {
			return $title;
		}

		return $this->transliterate( $title, 'save' );
	}

	/**
	 * Runs the transliterator unless a developer supplies the result.
	 *
	 * @param string $text    Text containing Arabic script.
	 * @param string $context 'save' for slugs, 'file' for file names.
	 */
	private function transliterate( $text, $context ) {
		/**
		 * Short-circuits transliteration: return a string to use it instead of
		 * the plugin's result (e.g. a translation service), or null to continue.
		 *
		 * @param string|null $result  Default null.
		 * @param string      $text    Text containing Arabic script.
		 * @param string      $context 'save' for slugs, 'file' for file names.
		 */
		$result = apply_filters( 'arabic_to_lat_pre_transliterate', null, $text, $context );

		return is_string( $result ) ? $result : $this->transliterator()->transliterate( $text );
	}

	/**
	 * Classifies the code that called sanitize_title(); the nearest relevant
	 * frame decides.
	 *
	 * - CALLER_CREATE: a core function creating a post, term or sample permalink.
	 * - CALLER_LOOKUP: the text is matched against existing slugs or keys, so it
	 *   must stay as it is:
	 *   - WP_Term_Query and get_category_by_path() look terms up by slug in the
	 *     'save' context; transliterating would 404 every existing Arabic
	 *     category, tag and product-category URL.
	 *   - WooCommerce builds internal keys from attribute names at runtime
	 *     (attribute_<name>, pa_<name>); transliterating would detach variations
	 *     saved before the plugin was active. They never appear in a permalink.
	 *     Exception: a new global attribute being created (see is_new_wc_attribute()).
	 * - CALLER_OTHER: anything else (admin "try it", CLI, customizer, other
	 *   plugins).
	 *
	 * @return string One of the CALLER_* constants.
	 */
	private static function caller() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Only reached for Arabic text in the 'save' context.
		$frames = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 40 );
		foreach ( $frames as $i => $frame ) {
			$function = isset( $frame['function'] ) ? $frame['function'] : '';
			$class    = isset( $frame['class'] ) ? $frame['class'] : '';

			if ( 'wc_sanitize_taxonomy_name' === $function && isset( $frames[ $i + 1 ]['function'] ) && 'wc_create_attribute' === $frames[ $i + 1 ]['function'] ) {
				return self::is_new_wc_attribute() ? self::CALLER_CREATE : self::CALLER_LOOKUP;
			}

			if ( '' === $class && in_array( $function, self::SLUG_CREATORS, true ) ) {
				return self::CALLER_CREATE;
			}
			if ( ( 'WP_Term_Query' === $class && 'get_terms' === $function ) || 'get_category_by_path' === $function ) {
				return self::CALLER_LOOKUP;
			}
			if ( 0 === strpos( $function, 'wc_' ) || 0 === strpos( $class, 'WC_' ) || 0 === strpos( $class, 'Automattic\\WooCommerce\\' ) ) {
				return self::CALLER_LOOKUP;
			}
		}
		return self::CALLER_OTHER;
	}

	/**
	 * Term edit screens and Quick Edit show an existing Arabic slug decoded
	 * (editable_slug) and send it back on save. wp_insert_post() recognises an
	 * unchanged slug ('old-save'); wp_update_term() does not, so editing only
	 * a description would silently give the term a new URL. Same comparison
	 * as core makes for posts: the text encodes to the term's current slug.
	 *
	 * @param string $title Text being sanitized.
	 */
	private static function is_unchanged_term_slug( $title ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Needs the arguments; only reached for Arabic text while a term is saved.
		foreach ( debug_backtrace( 0, 40 ) as $frame ) {
			if ( isset( $frame['function'] ) && 'wp_update_term' === $frame['function'] && empty( $frame['class'] ) ) {
				$term = isset( $frame['args'][0], $frame['args'][1] ) ? get_term( (int) $frame['args'][0], (string) $frame['args'][1] ) : null;
				return $term && ! is_wp_error( $term ) && sanitize_title_with_dashes( $title, '', 'save' ) === $term->slug;
			}
		}
		return false;
	}

	/**
	 * Importers (WordPress Importer and others that define WP_IMPORTING) pass
	 * the slugs of the source site. Keeping them keeps the migrated site's
	 * URLs. Items imported without a slug still get a Latin one.
	 *
	 * @param string $title Text being sanitized.
	 */
	private static function is_imported_slug( $title ) {
		if ( ! defined( 'WP_IMPORTING' ) || ! WP_IMPORTING ) {
			return false;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Needs the arguments; only reached for Arabic text during imports.
		foreach ( debug_backtrace( 0, 40 ) as $frame ) {
			$function = isset( $frame['function'] ) ? $frame['function'] : '';
			if ( ! empty( $frame['class'] ) || ! in_array( $function, array( 'wp_insert_post', 'wp_insert_term' ), true ) ) {
				continue;
			}
			$args     = 'wp_insert_post' === $function ? ( isset( $frame['args'][0] ) ? (array) $frame['args'][0] : array() ) : ( isset( $frame['args'][2] ) ? (array) $frame['args'][2] : array() );
			$explicit = 'wp_insert_post' === $function ? ( isset( $args['post_name'] ) ? $args['post_name'] : '' ) : ( isset( $args['slug'] ) ? $args['slug'] : '' );
			return '' !== (string) $explicit && trim( remove_accents( rawurldecode( (string) $explicit ) ) ) === trim( rawurldecode( $title ) );
		}
		return false;
	}

	/**
	 * Whether wc_create_attribute() is creating a new attribute (no id) rather
	 * than updating one. New attributes get a Latin taxonomy name (pa_allun):
	 * WooCommerce can't add variations of Arabic-named attribute taxonomies to
	 * the cart and rejects Arabic names longer than ~14 letters. Existing
	 * attributes keep their name, because products and variations store it.
	 */
	private static function is_new_wc_attribute() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Needs the arguments; only reached inside wc_create_attribute().
		foreach ( debug_backtrace( 0, 40 ) as $frame ) {
			if ( isset( $frame['function'] ) && 'wc_create_attribute' === $frame['function'] ) {
				$args = isset( $frame['args'][0] ) ? (array) $frame['args'][0] : array();
				return empty( $args['id'] );
			}
		}
		return false;
	}

	/**
	 * A page view on the public site (not admin, AJAX, REST, cron or WP-CLI).
	 *
	 * There, sanitize_title() calls that don't create a slug are display work:
	 * heading anchors built by table-of-contents plugins, CSS IDs. Changing
	 * them would break deep links people already shared, so they keep Arabic.
	 */
	private static function is_public_request() {
		if ( is_admin() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return false;
		}
		/**
		 * Filters whether the current request counts as a public page view,
		 * where only slug-creating calls are transliterated.
		 *
		 * @param bool $is_public Default: true for front-end page views.
		 */
		return (bool) apply_filters( 'arabic_to_lat_is_public_request', true );
	}

	/**
	 * When an Arabic term slug really changes, records the old one so the old
	 * URL redirects (see redirect_old_slug()). Unchanged slugs never get here
	 * changed: see is_unchanged_term_slug().
	 *
	 * @param array  $data     Term data about to be saved.
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @param array  $args     Arguments passed to wp_update_term().
	 */
	public function record_old_term_slug( $data, $term_id, $taxonomy, $args ) {
		$old = get_term( $term_id, $taxonomy );
		if ( ! $old || is_wp_error( $old ) || ! isset( $data['slug'] ) || $data['slug'] === $old->slug ) {
			return $data;
		}
		if ( Transliterator::has_arabic( rawurldecode( $old->slug ) ) ) {
			add_term_meta( $term_id, '_arabic_to_lat_old_slug', $old->slug );
		}
		return $data;
	}

	/**
	 * Core records old slugs (_wp_old_slug) only for non-hierarchical posts.
	 * When an Arabic slug of a page is changed, record it too so the old URL
	 * redirects (see redirect_old_slug()).
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after  Post after the update.
	 * @param \WP_Post $post_before Post before the update.
	 */
	public function record_old_page_slug( $post_id, $post_after, $post_before ) {
		if ( $post_after->post_name === $post_before->post_name || 'publish' !== $post_before->post_status || ! is_post_type_hierarchical( $post_after->post_type ) ) {
			return;
		}
		if ( ! Transliterator::has_arabic( rawurldecode( $post_before->post_name ) ) ) {
			return;
		}
		if ( ! in_array( $post_before->post_name, get_post_meta( $post_id, '_wp_old_slug' ), true ) ) {
			add_post_meta( $post_id, '_wp_old_slug', $post_before->post_name );
		}
	}

	/**
	 * GET /arabic-to-lat/v1/slug?title=… → the slug the plugin would create.
	 * Used by the block editor to preview the Latin slug before the first save.
	 */
	public function register_rest_route() {
		register_rest_route(
			'arabic-to-lat/v1',
			'/slug',
			array(
				'methods'             => 'GET',
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'title' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
				'callback'            => static function ( \WP_REST_Request $request ) {
					return array( 'slug' => sanitize_title( (string) $request['title'] ) );
				},
			)
		);
	}

	public function enqueue_editor_script() {
		wp_enqueue_script(
			'arabic-to-lat-editor-slug',
			plugins_url( 'assets/js/editor-slug.js', ARABIC_TO_LAT_FILE ),
			array( 'wp-api-fetch', 'wp-data', 'wp-dom-ready', 'wp-editor', 'wp-url' ),
			ARABIC_TO_LAT_VERSION,
			true
		);
	}

	/**
	 * Transliterates uploaded file names: صورة.jpg → sura.jpg.
	 *
	 * @param string $filename     File name after core sanitization.
	 * @param string $filename_raw Original file name.
	 */
	public function sanitize_file_name( $filename, $filename_raw = '' ) {
		// The nested sanitize_file_name() call below runs this filter again; never transliterate twice.
		static $cleaning = false;
		if ( $cleaning || ! Transliterator::has_arabic( $filename ) ) {
			return $filename;
		}

		$settings = self::settings();
		if ( ! $settings['files'] || ! $this->should_transliterate( $filename, $filename_raw, 'file' ) ) {
			return $filename;
		}

		$converted = $this->transliterate( $filename, 'file' );
		$converted = preg_replace( '/[\s-]+/', '-', $converted );
		$converted = preg_replace( '/-*\.-*/', '.', $converted );
		$converted = trim( $converted, '-' );

		// WordPress has already cleaned the name before this filter, but transliteration
		// (NFKC: ／ → /, ‥ → ..) and Arabic-spelled extensions (پهپ → php) can bring back
		// what it removed. Clean the Latin result again with WordPress's own rules: no path
		// characters, and inner extensions like .php. neutralised (evil.php_.jpg).
		$cleaning  = true;
		$converted = sanitize_file_name( $converted );
		$cleaning  = false;
		if ( '' === $converted || wp_basename( $converted ) !== $converted ) {
			return $filename;
		}

		// Nothing usable left in front of the extension (e.g. a name made only of punctuation).
		if ( '' === $converted || '.' === $converted[0] ) {
			return $filename;
		}

		$this->renamed_files[ pathinfo( $converted, PATHINFO_FILENAME ) ] = pathinfo( '' !== $filename_raw ? $filename_raw : $filename, PATHINFO_FILENAME );

		return $converted;
	}

	/**
	 * Block editor (REST) and sideload uploads title the attachment after the
	 * stored file name, which is now Latin. Give it back the original Arabic
	 * name; the file name and attachment slug stay Latin.
	 *
	 * @param array $data Attachment post data.
	 */
	public function restore_attachment_title( $data ) {
		if ( empty( $data['post_title'] ) || ! $this->renamed_files ) {
			return $data;
		}
		$title = $data['post_title'];
		// wp_unique_filename() may have added -1, -2 … after the name was converted.
		$key = isset( $this->renamed_files[ $title ] ) ? $title : preg_replace( '/-\d+$/', '', $title );
		if ( isset( $this->renamed_files[ $key ] ) ) {
			$data['post_title'] = sanitize_text_field( $this->renamed_files[ $key ] );
		}
		return $data;
	}

	/**
	 * @param string $text      Text about to be transliterated.
	 * @param string $raw       Text before sanitization.
	 * @param string $context   'save' for slugs, 'file' for file names.
	 */
	private function should_transliterate( $text, $raw, $context ) {
		/**
		 * Filters whether a slug or file name is transliterated.
		 *
		 * Return false to keep native Arabic slugs in a specific case, e.g. for
		 * one language of a multilingual site or for one post type.
		 *
		 * @param bool   $transliterate Default true.
		 * @param string $text          Text about to be transliterated.
		 * @param string $raw           Text before sanitization.
		 * @param string $context       'save' for slugs, 'file' for file names.
		 */
		return (bool) apply_filters( 'arabic_to_lat_should_transliterate', true, $text, $raw, $context );
	}

	/**
	 * Sends a 301 from an old Arabic slug to the current URL.
	 *
	 * Core already does this for posts, but not for pages, other hierarchical
	 * post types or terms — exactly what the converter changes on Arabic sites.
	 */
	public function redirect_old_slug() {
		if ( empty( $_SERVER['REQUEST_URI'] ) || ! ( is_404() || is_feed() ) ) {
			return;
		}

		// Only the path is used, and only to build a slug for a prepared query.
		$path     = (string) wp_parse_url( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$segments = array_values( array_filter( explode( '/', $path ), 'strlen' ) );

		// The old slug is the last Arabic segment; what follows it (page/2, feed) is kept.
		$index = null;
		foreach ( $segments as $i => $segment ) {
			if ( Transliterator::has_arabic( rawurldecode( $segment ) ) ) {
				$index = $i;
			}
		}
		if ( null === $index ) {
			return;
		}

		$old_slug = sanitize_title_for_query( rawurldecode( $segments[ $index ] ) );
		// A feed of a slug that still exists is a real feed, not an old URL.
		if ( ! is_404() && $this->slug_exists( $old_slug ) ) {
			return;
		}

		$parent = $index > 0 ? sanitize_title_for_query( rawurldecode( $segments[ $index - 1 ] ) ) : '';
		$url    = $this->find_url_for_old_slug( $old_slug, $parent );
		if ( ! $url ) {
			return;
		}

		$tail = array_slice( $segments, $index + 1 );
		if ( $tail ) {
			$url = user_trailingslashit( trailingslashit( $url ) . implode( '/', array_map( 'rawurlencode', array_map( 'rawurldecode', $tail ) ) ) );
		}
		// Keep campaign and tracking parameters.
		$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_safe_redirect() sanitizes the final URL.
		if ( '' !== $query ) {
			$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query;
		}

		wp_safe_redirect( $url, 301, 'Arabic to Latin' );
		exit;
	}

	/**
	 * Core's own old-slug redirect (posts) drops the query string. For old
	 * Arabic URLs, keep it, as redirect_old_slug() does for pages and terms.
	 *
	 * @param string $link Redirect target.
	 */
	public function keep_query_on_core_redirect( $link ) {
		$uri   = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only tested for Arabic and split.
		$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_redirect() sanitizes the final URL.
		if ( ! $link || '' === $query || false !== strpos( $link, '?' ) || ! Transliterator::has_arabic( rawurldecode( (string) wp_parse_url( $uri, PHP_URL_PATH ) ) ) ) {
			return $link;
		}
		return $link . '?' . $query;
	}

	/**
	 * @param string $slug Encoded slug.
	 */
	private function slug_exists( $slug ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Rare: only for feeds whose path contains Arabic.
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->terms} WHERE slug = %s UNION SELECT 1 FROM {$wpdb->posts} WHERE post_name = %s LIMIT 1", $slug, $slug ) );
	}

	/**
	 * @param string $old_slug    Old slug as stored by WordPress (percent-encoded).
	 * @param string $parent_slug Path segment before it, '' at the top level.
	 * @return string URL, or '' when nothing matches.
	 */
	private function find_url_for_old_slug( $old_slug, $parent_slug ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Rare 404-only lookup on an indexed meta key.
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_old_slug' AND meta_value = %s ORDER BY meta_id DESC LIMIT 10",
				$old_slug
			)
		);

		// Several posts can share an old slug (a post and a child page, say):
		// prefer the one whose place in the URL hierarchy matches the request.
		$best       = 0;
		$best_score = -1;
		foreach ( $post_ids as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post || ! is_post_publicly_viewable( $post ) ) {
				continue;
			}
			$score = 0;
			if ( '' === $parent_slug ) {
				$score = $post->post_parent ? 0 : 1;
			} elseif ( $post->post_parent && get_post( $post->post_parent ) ) {
				$parent = get_post( $post->post_parent );
				$names  = array_merge( array( $parent->post_name ), get_post_meta( $parent->ID, '_wp_old_slug' ) );
				$score  = in_array( $parent_slug, $names, true ) ? 2 : 1;
			}
			if ( $score > $best_score ) {
				$best       = $post->ID;
				$best_score = $score;
			}
		}
		if ( $best ) {
			return (string) get_permalink( $best );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Rare 404-only lookup.
		$term_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = '_arabic_to_lat_old_slug' AND meta_value = %s ORDER BY meta_id DESC LIMIT 1",
				$old_slug
			)
		);
		if ( $term_id ) {
			$link = get_term_link( (int) $term_id );
			if ( ! is_wp_error( $link ) ) {
				return $link;
			}
		}

		return '';
	}
}
