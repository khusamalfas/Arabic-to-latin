<?php
/**
 * Plugin Name:       Arabic to Latin – Arabic Slugs & Permalinks in English Letters
 * Plugin URI:        https://wordpress.org/plugins/arabic-to-lat/
 * Description:       Converts Arabic, Persian and Urdu characters in post, page, product and term slugs and in uploaded file names to readable Latin (English) letters. Works with WPML, Polylang, TranslatePress, WooCommerce and the block editor.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Khusam Alfas
 * Author URI:        https://profiles.wordpress.org/5vlast/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       arabic-to-lat
 *
 * @package ArabicToLat
 */

defined( 'ABSPATH' ) || exit;

define( 'ARABIC_TO_LAT_VERSION', '1.1.0' );
define( 'ARABIC_TO_LAT_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-transliterator.php';
require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/includes/class-converter.php';

// Registered at load time, not on a later hook, so slugs created early
// (imports, cron, REST requests) are covered too.
ArabicToLat\Plugin::instance()->init();

if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-admin.php';
	( new ArabicToLat\Admin() )->init();
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/includes/class-cli.php';
	WP_CLI::add_command( 'arabic-to-lat', 'ArabicToLat\\CLI' );
}

register_activation_hook(
	__FILE__,
	static function () {
		// Existing slugs are no longer rewritten on activation (0.3 did); the
		// notice points to the opt-in converter instead.
		set_transient( 'arabic_to_lat_activated', 1, MINUTE_IN_SECONDS * 10 );
	}
);
