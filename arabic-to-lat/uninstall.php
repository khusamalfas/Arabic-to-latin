<?php
/**
 * Removes plugin settings. Old-slug records are kept on purpose: they are what
 * redirects old Arabic URLs, and removing them would turn those URLs into 404s.
 *
 * @package ArabicToLat
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'arabic_to_lat_settings' );

// AI slug suggestions: settings (including any stored API key), usage counter, cached suggestions.
delete_option( 'arabic_to_lat_ai' );
delete_option( 'arabic_to_lat_ai_usage' );
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off cleanup of this plugin's own transients on uninstall.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_arabic_to_lat_ai_' ) . '%', $wpdb->esc_like( '_transient_timeout_arabic_to_lat_ai_' ) . '%' ) );
