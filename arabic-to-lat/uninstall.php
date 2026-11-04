<?php
/**
 * Removes plugin settings. Old-slug records are kept on purpose: they are what
 * redirects old Arabic URLs, and removing them would turn those URLs into 404s.
 *
 * @package ArabicToLat
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'arabic_to_lat_settings' );
