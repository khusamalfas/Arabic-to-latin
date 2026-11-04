<?php
/**
 * WPML test site: English default, Arabic second, language directories.
 *
 * @package ArabicToLat\Tests
 */

// phpcs:ignoreFile -- Test code, never shipped.

global $sitepress, $wpdb;
icl_set_setting( 'default_language', 'en', true );
( new WPML_Installation( $wpdb, $sitepress ) )->set_active_languages( array( 'en', 'ar' ) );
foreach ( array(
	'setup_complete'                     => 1,
	'setup_wizard_step'                  => 0,
	'existing_content_language_verified' => 1,
	'language_negotiation_type'          => 1,
	'urls'                               => array( 'directory_for_default_language' => 0, 'show_on_root' => '' ),
	'custom_posts_sync_option'           => array( 'post' => 1, 'page' => 1 ),
	'taxonomies_sync_option'             => array( 'category' => 1, 'post_tag' => 1 ),
) as $key => $value ) {
	icl_set_setting( $key, $value );
}
icl_save_settings();
