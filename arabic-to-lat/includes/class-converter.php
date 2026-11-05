<?php
/**
 * Converter for slugs created before the plugin was active.
 *
 * @package ArabicToLat
 */

namespace ArabicToLat;

defined( 'ABSPATH' ) || exit;

/**
 * Finds existing Arabic post and term slugs and rewrites them.
 *
 * Never runs on its own: only from the settings page or WP-CLI, with a
 * dry run available first. Old slugs are kept so old URLs 301 to new ones.
 */
final class Converter {

	/**
	 * UTF-8 lead bytes of the Arabic blocks, as they appear in percent-encoded slugs.
	 */
	const ENCODED_PREFIXES = array( '%d8', '%d9', '%da', '%db', '%dd', '%e0%a2', '%e0%a3', '%ef%ad', '%ef%ae', '%ef%af', '%ef%b4', '%ef%b5', '%ef%b6', '%ef%b7', '%ef%b9', '%ef%ba', '%ef%bb' );

	/**
	 * @var Transliterator
	 */
	private $transliterator;

	/**
	 * @var string[] Post types this converter works on.
	 */
	private $post_types;

	/**
	 * @var string[] Taxonomies this converter works on.
	 */
	private $taxonomies;

	/**
	 * Whether to also rewrite slugs this plugin already converted, so that a
	 * site which changed its spelling style can bring old URLs in line.
	 *
	 * Off by default and never switched on implicitly: it rewrites URLs that
	 * currently work, and only the site owner can judge whether that is worth
	 * it. See can_reconvert() for the rule that keeps hand-written slugs safe.
	 *
	 * @var bool
	 */
	private $reconvert = false;

	public function __construct( Transliterator $transliterator ) {
		$this->transliterator = $transliterator;
		$this->post_types     = self::post_types();
		$this->taxonomies     = self::taxonomies();
	}

	/**
	 * Narrows the converter to some post types and taxonomies. Only names from
	 * post_types() / taxonomies() are accepted, so a request can never reach
	 * menus, revisions or WooCommerce attribute terms.
	 *
	 * @param string[]|null $post_types Null keeps all.
	 * @param string[]|null $taxonomies Null keeps all.
	 * @return $this
	 */
	public function scope( $post_types = null, $taxonomies = null ) {
		if ( null !== $post_types ) {
			$this->post_types = array_values( array_intersect( self::post_types(), array_map( 'strval', (array) $post_types ) ) );
		}
		if ( null !== $taxonomies ) {
			$this->taxonomies = array_values( array_intersect( self::taxonomies(), array_map( 'strval', (array) $taxonomies ) ) );
		}
		return $this;
	}

	/**
	 * Also rewrite already-Latin slugs that this plugin produced, using the
	 * current spelling style.
	 *
	 * Attachments are dropped from the scope here whatever was requested:
	 * renaming an uploaded file breaks every hotlink and embed pointing at it,
	 * and /wp-content/uploads/ has no redirect to repair it with.
	 *
	 * @param bool $on Whether to re-convert.
	 * @return $this
	 */
	public function reconvert( $on = true ) {
		$this->reconvert = (bool) $on;
		if ( $this->reconvert ) {
			$this->post_types = array_values( array_diff( $this->post_types, array( 'attachment' ) ) );
		}
		return $this;
	}

	/**
	 * Whether the converter is in re-conversion mode.
	 */
	public function is_reconverting() {
		return $this->reconvert;
	}

	/**
	 * Post types whose slugs are converted.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$types = array_values( get_post_types( array( 'public' => true ) ) );

		/**
		 * Filters the post types whose existing slugs the converter rewrites.
		 *
		 * @param string[] $types Public post types, attachments included.
		 */
		return (array) apply_filters( 'arabic_to_lat_convert_post_types', $types );
	}

	/**
	 * Taxonomies whose slugs are converted.
	 *
	 * WooCommerce attribute taxonomies (pa_*) are left out: existing variations
	 * store those term slugs, and rewriting them would detach the variations.
	 *
	 * @return string[]
	 */
	public static function taxonomies() {
		$taxonomies = array_values(
			array_filter(
				get_taxonomies( array( 'public' => true ) ),
				static function ( $taxonomy ) {
					return 0 !== strpos( $taxonomy, 'pa_' ) && 'post_format' !== $taxonomy;
				}
			)
		);

		/**
		 * Filters the taxonomies whose existing term slugs the converter rewrites.
		 *
		 * @param string[] $taxonomies Public taxonomies except WooCommerce attributes and post formats.
		 */
		return (array) apply_filters( 'arabic_to_lat_convert_taxonomies', $taxonomies );
	}

	/**
	 * @return array{posts: int, terms: int}
	 */
	public function count() {
		global $wpdb;

		list( $post_where, $post_args ) = $this->post_where();
		list( $term_where, $term_args ) = $this->term_where();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin-only count; the WHERE clauses contain only %s placeholders, filled by prepare().
		$posts = $post_where ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$post_where}", $post_args ) ) : 0;
		$terms = $term_where ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE {$term_where}", $term_args ) ) : 0;
		// phpcs:enable

		return array(
			'posts' => $posts,
			'terms' => $terms,
		);
	}

	/**
	 * Arabic slugs per post type and per taxonomy, for the scope checkboxes.
	 *
	 * @return array{posts: array<string,int>, terms: array<string,int>}
	 */
	public function count_by_type() {
		global $wpdb;

		list( $post_where, $post_args ) = $this->post_where();
		list( $term_where, $term_args ) = $this->term_where();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin-only count; the WHERE clauses contain only %s placeholders, filled by prepare().
		$posts = $post_where ? $wpdb->get_results( $wpdb->prepare( "SELECT post_type AS name, COUNT(*) AS n FROM {$wpdb->posts} WHERE {$post_where} GROUP BY post_type", $post_args ) ) : array();
		$terms = $term_where ? $wpdb->get_results( $wpdb->prepare( "SELECT tt.taxonomy AS name, COUNT(*) AS n FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE {$term_where} GROUP BY tt.taxonomy", $term_args ) ) : array();
		// phpcs:enable

		return array(
			'posts' => array_map( 'intval', wp_list_pluck( $posts, 'n', 'name' ) ),
			'terms' => array_map( 'intval', wp_list_pluck( $terms, 'n', 'name' ) ),
		);
	}

	/**
	 * Converts (or previews) slugs until the batch or time budget runs out.
	 *
	 * Pass the returned cursor back to continue where a batch stopped; -1
	 * marks a finished type.
	 *
	 * @param array{dry_run?: bool, limit?: int, seconds?: int, posts?: bool, terms?: bool, cursor?: array{posts?: int, terms?: int}} $args Options.
	 * @return array{changes: array<int, array{type: string, id: int, old: string, new: string, label: string}>, done: bool, cursor: array{posts: int, terms: int}}
	 */
	public function run( array $args = array() ) {
		$args = array_merge(
			array(
				'dry_run' => true,
				'limit'   => 0,
				'seconds' => 0,
				'posts'   => true,
				'terms'   => true,
				'cursor'  => array(),
			),
			$args
		);

		$started = microtime( true );
		$changes = array();
		$cursor  = array_merge(
			array(
				'posts' => 0,
				'terms' => 0,
			),
			array_map( 'intval', (array) $args['cursor'] )
		);

		foreach ( array( 'posts', 'terms' ) as $type ) {
			if ( ! $args[ $type ] || $cursor[ $type ] < 0 ) {
				continue;
			}
			$after = $cursor[ $type ];
			do {
				$rows = 'posts' === $type ? $this->fetch_posts( $after ) : $this->fetch_terms( $after );
				foreach ( $rows as $row ) {
					$after            = (int) $row->id;
					$cursor[ $type ] = $after;
					$change = 'posts' === $type ? $this->convert_post( $row, $args['dry_run'] ) : $this->convert_term( $row, $args['dry_run'] );
					if ( $change ) {
						$changes[] = $change;
					}
					$out_of_items = $args['limit'] && count( $changes ) >= $args['limit'];
					$out_of_time  = $args['seconds'] && ( microtime( true ) - $started ) >= $args['seconds'];
					if ( $out_of_items || $out_of_time ) {
						return array(
							'changes' => $changes,
							'done'    => false,
							'cursor'  => $cursor,
						);
					}
				}
			} while ( $rows );
			$cursor[ $type ] = -1;
		}

		return array(
			'changes' => $changes,
			'done'    => true,
			'cursor'  => $cursor,
		);
	}

	/**
	 * Builds the slug for an existing encoded slug, or '' when nothing changes.
	 *
	 * @param string $encoded Slug as stored by WordPress.
	 */
	public function new_slug( $encoded, $source = '' ) {
		$decoded = rawurldecode( $encoded );

		if ( ! Transliterator::has_arabic( $decoded ) ) {
			// A Latin slug can only be rebuilt from the title it came from.
			if ( ! $this->reconvert || ! self::can_reconvert( $encoded, $source ) ) {
				return '';
			}
			$slug = self::slug_from( $source, $this->transliterator );
			return ( '' === $slug || $slug === $encoded ) ? '' : $slug;
		}

		$slug = sanitize_title_with_dashes( $this->transliterator->transliterate( $decoded ), '', 'save' );
		return ( '' === $slug || $slug === $encoded ) ? '' : $slug;
	}

	/**
	 * Whether a Latin slug may be rebuilt from its title.
	 *
	 * Only when the slug is one this plugin produced: the title is run through
	 * every spelling style, and the slug has to match one of them. A slug the
	 * author typed by hand matches none and is left alone — which is the whole
	 * safety of re-conversion, because a title can always be transliterated,
	 * but only a generated slug may be overwritten without losing a decision
	 * somebody made on purpose.
	 *
	 * A trailing uniqueness suffix (-2, -3) is allowed, since
	 * wp_unique_post_slug() may have added one when the slug was created.
	 *
	 * @param string $slug   Current slug, as stored.
	 * @param string $source Post title or term name.
	 */
	public static function can_reconvert( $slug, $source ) {
		if ( '' === $slug || ! is_string( $source ) || ! Transliterator::has_arabic( $source ) ) {
			return false;
		}

		$bare = (string) preg_replace( '/-\d+$/', '', $slug );

		foreach ( array_keys( Transliterator::presets() ) as $preset ) {
			$candidate = self::slug_from( $source, new Transliterator( array(), $preset ) );
			if ( '' !== $candidate && ( $candidate === $slug || $candidate === $bare ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The slug a title would get from one transliterator.
	 *
	 * @param string         $source        Post title or term name.
	 * @param Transliterator $transliterator Style to use.
	 */
	private static function slug_from( $source, Transliterator $transliterator ) {
		return sanitize_title_with_dashes( $transliterator->transliterate( (string) $source ), '', 'save' );
	}

	private function convert_post( $row, $dry_run ) {
		global $wpdb;

		$slug = $this->new_slug( $row->slug, isset( $row->title ) ? $row->title : '' );
		if ( '' === $slug ) {
			return null;
		}
		$slug = wp_unique_post_slug( $slug, (int) $row->id, $row->status, $row->type, (int) $row->parent );

		if ( ! $dry_run ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- wp_update_post() would fire every save hook (and translation sync) for a slug-only change.
			$wpdb->update( $wpdb->posts, array( 'post_name' => $slug ), array( 'ID' => (int) $row->id ) );
			add_post_meta( (int) $row->id, '_wp_old_slug', $row->slug );
			clean_post_cache( (int) $row->id );

			/**
			 * Fires after the converter rewrote a slug.
			 *
			 * @param string $object_type 'post' or 'term'.
			 * @param int    $object_id   Post or term ID.
			 * @param string $old_slug    Previous (encoded) slug.
			 * @param string $new_slug    New slug.
			 */
			do_action( 'arabic_to_lat_slug_converted', 'post', (int) $row->id, $row->slug, $slug );
		}

		return array(
			'type'  => 'post',
			'id'    => (int) $row->id,
			'old'   => rawurldecode( $row->slug ),
			'new'   => $slug,
			'label' => $row->type,
		);
	}

	private function convert_term( $row, $dry_run ) {
		global $wpdb;

		$slug = $this->new_slug( $row->slug, isset( $row->name ) ? $row->name : '' );
		if ( '' === $slug ) {
			return null;
		}
		$term = get_term( (int) $row->id, $row->taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}
		$slug = $this->unique_term_slug( $slug, $term );

		if ( ! $dry_run ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Same reason as for posts.
			$wpdb->update( $wpdb->terms, array( 'slug' => $slug ), array( 'term_id' => (int) $row->id ) );
			add_term_meta( (int) $row->id, '_arabic_to_lat_old_slug', $row->slug );
			clean_term_cache( (int) $row->id, $row->taxonomy );

			/** This action is documented in includes/class-converter.php */
			do_action( 'arabic_to_lat_slug_converted', 'term', (int) $row->id, $row->slug, $slug );
		}

		return array(
			'type'  => 'term',
			'id'    => (int) $row->id,
			'old'   => rawurldecode( $row->slug ),
			'new'   => $slug,
			'label' => $row->taxonomy,
		);
	}

	/**
	 * Unique slug within the taxonomy, checked in the database directly.
	 *
	 * wp_unique_term_slug() looks for clashes through get_term_by(), which
	 * WPML filters by the current language: run from WP-CLI or an English
	 * admin screen, an Arabic term with the same slug is invisible and two
	 * Arabic terms end up sharing one slug.
	 *
	 * @param string   $slug Proposed slug.
	 * @param \WP_Term $term Term being converted.
	 */
	private function unique_term_slug( $slug, $term ) {
		global $wpdb;

		$candidate = $slug;
		$suffix    = 2;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Must bypass language-filtered term queries; see above.
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT t.term_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE t.slug = %s AND tt.taxonomy = %s AND t.term_id != %d LIMIT 1", $candidate, $term->taxonomy, $term->term_id ) ) ) {
			$candidate = $slug . '-' . $suffix++;
		}
		return $candidate;
	}

	private function fetch_posts( $after ) {
		global $wpdb;

		list( $where, $args ) = $this->post_where();
		if ( ! $where ) {
			return array();
		}
		$args[] = $after;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Batched admin/CLI tool; WHERE contains only %s placeholders, filled by prepare().
		return $wpdb->get_results( $wpdb->prepare( "SELECT ID AS id, post_name AS slug, post_title AS title, post_type AS type, post_status AS status, post_parent AS parent FROM {$wpdb->posts} WHERE {$where} AND ID > %d ORDER BY ID ASC LIMIT 100", $args ) );
	}

	private function fetch_terms( $after ) {
		global $wpdb;

		list( $where, $args ) = $this->term_where();
		if ( ! $where ) {
			return array();
		}
		$args[] = $after;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Batched admin/CLI tool; WHERE contains only %s placeholders, filled by prepare().
		return $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id AS id, t.slug AS slug, t.name AS name, tt.taxonomy AS taxonomy FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE {$where} AND t.term_id > %d ORDER BY t.term_id ASC LIMIT 100", $args ) );
	}

	/**
	 * @return array{0: string, 1: array}
	 */
	private function post_where() {
		$types = $this->post_types;
		if ( ! $types ) {
			return array( '', array() );
		}
		$where = 'post_type IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ") AND post_status NOT IN ('auto-draft', 'trash')";
		if ( $this->reconvert ) {
			// Latin slugs do not match the encoded-Arabic filter, so every row
			// has to be looked at; can_reconvert() decides row by row.
			return array( $where, $types );
		}
		list( $like, $args ) = $this->like_clause( 'post_name' );
		return array( $where . " AND ({$like})", array_merge( $types, $args ) );
	}

	/**
	 * @return array{0: string, 1: array}
	 */
	private function term_where() {
		$taxonomies = $this->taxonomies;
		if ( ! $taxonomies ) {
			return array( '', array() );
		}
		$where = 'tt.taxonomy IN (' . implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) ) . ')';
		if ( $this->reconvert ) {
			/** This comment is documented in includes/class-converter.php */
			return array( $where, $taxonomies );
		}
		list( $like, $args ) = $this->like_clause( 't.slug' );
		return array( $where . " AND ({$like})", array_merge( $taxonomies, $args ) );
	}

	private function like_clause( $column ) {
		global $wpdb;

		$parts = array();
		$args  = array();
		foreach ( self::ENCODED_PREFIXES as $prefix ) {
			$parts[] = "{$column} LIKE %s";
			$args[]  = '%' . $wpdb->esc_like( $prefix ) . '%';
		}
		return array( implode( ' OR ', $parts ), $args );
	}
}
