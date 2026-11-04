<?php
/**
 * WP-CLI commands.
 *
 * @package ArabicToLat
 */

namespace ArabicToLat;

defined( 'ABSPATH' ) || exit;

/**
 * Transliterate Arabic slugs from the command line.
 */
final class CLI {

	/**
	 * Converts existing Arabic post and term slugs to Latin.
	 *
	 * Old slugs are kept, so old URLs redirect (301) to the new ones.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : List the changes without saving them.
	 *
	 * [--posts-only]
	 * : Only convert post, page and custom post type slugs.
	 *
	 * [--terms-only]
	 * : Only convert category, tag and custom taxonomy slugs.
	 *
	 * [--post_type=<types>]
	 * : Comma-separated post types to convert, e.g. post,page. Default: all public ones.
	 *
	 * [--taxonomy=<taxonomies>]
	 * : Comma-separated taxonomies to convert, e.g. category. Default: all public ones except WooCommerce attributes.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp arabic-to-lat convert --dry-run
	 *     wp arabic-to-lat convert --posts-only
	 *     wp arabic-to-lat convert --post_type=product --taxonomy=product_cat
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function convert( $args, $assoc_args ) {
		$dry_run = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$format  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$converter = new Converter( Plugin::instance()->transliterator() );
		// Naming only post types (or only taxonomies) means "just these".
		$named      = isset( $assoc_args['post_type'] ) || isset( $assoc_args['taxonomy'] );
		$post_types = isset( $assoc_args['post_type'] ) ? array_filter( array_map( 'trim', explode( ',', $assoc_args['post_type'] ) ) ) : ( $named ? array() : null );
		$taxonomies = isset( $assoc_args['taxonomy'] ) ? array_filter( array_map( 'trim', explode( ',', $assoc_args['taxonomy'] ) ) ) : ( $named ? array() : null );
		$converter->scope( $post_types, $taxonomies );
		$result = $converter->run(
			array(
				'dry_run' => $dry_run,
				'posts'   => empty( $assoc_args['terms-only'] ),
				'terms'   => empty( $assoc_args['posts-only'] ),
			)
		);

		$count = count( $result['changes'] );
		if ( 'count' === $format ) {
			\WP_CLI::line( (string) $count );
			return;
		}
		// Machine formats always print (an empty JSON list is still "[]").
		if ( $result['changes'] || 'table' !== $format ) {
			\WP_CLI\Utils\format_items( $format, $result['changes'], array( 'type', 'label', 'id', 'old', 'new' ) );
		}

		if ( 'table' === $format ) {
			\WP_CLI::success( $dry_run ? sprintf( '%d slugs would change (dry run).', $count ) : sprintf( '%d slugs converted.', $count ) );
		}
	}

	/**
	 * Shows the slug a title would get.
	 *
	 * ## OPTIONS
	 *
	 * <text>
	 * : Arabic text.
	 *
	 * ## EXAMPLES
	 *
	 *     wp arabic-to-lat slug "مرحبا بالعالم"
	 *
	 * @param array $args Positional arguments.
	 */
	public function slug( $args ) {
		\WP_CLI::line( rawurldecode( sanitize_title( $args[0] ) ) );
	}
}
