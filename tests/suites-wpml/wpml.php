<?php
// Scenario I5: WPML (MySQL). Run with: tests/run.sh --wpml=/path/to/sitepress-multilingual-cms
require __DIR__ . '/../lib/bootstrap.php';

global $sitepress;
wp_set_current_user( 1 );
$lang = function ( $id, $type ) { return apply_filters( 'wpml_element_language_code', null, array( 'element_id' => $id, 'element_type' => $type ) ); };

t_check( 'I5', 'WPML languages', array_keys( $sitepress->get_active_languages( true ) ), array( 'ar', 'en' ) );

// Taxonomy Translation screen (wp_ajax_wpml_save_term). Read the returned term:
// WPML's "adjust IDs" maps get_term( <ar id> ) to the English translation while English is current.
$en   = wp_insert_term( 'News', 'category' );
$trid = apply_filters( 'wpml_element_trid', null, $en['term_taxonomy_id'], 'tax_category' );
$ar   = WPML_Post_Edit_Ajax::save_term_ajax( $sitepress, 'ar', 'category', '', 'أخبار', $trid, '', array() );
t_check( 'I5', 'Taxonomy Translation: Arabic category slug', array( $ar->slug, $ar->language_code ), array( 'akhbar', 'ar' ) );
$en2  = wp_insert_term( 'Hotels', 'category' );
$ar2  = WPML_Post_Edit_Ajax::save_term_ajax( $sitepress, 'ar', 'category', 'فنادق-دبي', 'فنادق', apply_filters( 'wpml_element_trid', null, $en2['term_taxonomy_id'], 'tax_category' ), '', array() );
t_check( 'I5', 'Taxonomy Translation: typed Arabic slug', $ar2->slug, 'fnadq-dbi' );

// Arabic translation from the block editor (REST with ?lang=ar).
$page = wp_insert_post( array( 'post_title' => 'About us', 'post_status' => 'publish', 'post_type' => 'page' ) );
$sitepress->switch_lang( 'ar' );
$req = new WP_REST_Request( 'POST', '/wp/v2/pages' );
$req->set_query_params( array( 'lang' => 'ar' ) );
$req->set_header( 'content-type', 'application/json' );
$req->set_body( wp_json_encode( array( 'title' => 'من نحن', 'status' => 'publish' ) ) );
$ar_page = rest_do_request( $req )->get_data()['id'];
do_action( 'wpml_set_element_language_details', array( 'element_id' => $ar_page, 'element_type' => 'post_page', 'trid' => apply_filters( 'wpml_element_trid', null, $page, 'post_page' ), 'language_code' => 'ar', 'source_language_code' => 'en' ) );
$sitepress->switch_lang( 'en' );
t_check( 'I5', 'Arabic translation (block editor): slug and language', array( get_post( $ar_page )->post_name, $lang( $ar_page, 'post_page' ) ), array( 'mn-nhn', 'ar' ) );
t_check( 'I5', 'Translations linked', (int) apply_filters( 'wpml_object_id', $page, 'page', false, 'ar' ), $ar_page );

// Duplicate.
$src = wp_insert_post( array( 'post_title' => 'دليل السفر', 'post_status' => 'publish' ) );
$dup = $sitepress->make_duplicate( $src, 'ar' );
t_true( 'I5', 'make_duplicate: Latin slug', (bool) preg_match( '/^dlil-alsfr(-\d+|-ar)?$/', get_post( $dup )->post_name ), get_post( $dup )->post_name );

// Old Arabic category (created before the plugin) colliding with a new one; conversion keeps slugs unique.
$trid3  = apply_filters( 'wpml_element_trid', null, wp_insert_term( 'Travel old', 'category' )['term_taxonomy_id'], 'tax_category' );
$legacy = t_without_plugin( function () use ( $sitepress, $trid3 ) { return WPML_Post_Edit_Ajax::save_term_ajax( $sitepress, 'ar', 'category', '', 'سفر وسياحة', $trid3, '', array() ); } );
$trid4  = apply_filters( 'wpml_element_trid', null, wp_insert_term( 'Travel new', 'category' )['term_taxonomy_id'], 'tax_category' );
$new    = WPML_Post_Edit_Ajax::save_term_ajax( $sitepress, 'ar', 'category', '', 'سفر وسياحة', $trid4, '', array() );
t_check( 'I5', 'New Arabic category next to an old one', $new->slug, 'sfr-wsiyaha' );
$post = wp_insert_post( array( 'post_title' => 'p', 'post_status' => 'publish', 'post_category' => array( $legacy->term_id ) ) );
do_action( 'wpml_set_element_language_details', array( 'element_id' => $post, 'element_type' => 'post_post', 'trid' => false, 'language_code' => 'ar' ) );
$old_url = '/ar/category/' . $legacy->slug . '/';
t_check( 'I5', 'Old Arabic category URL works before conversion', t_http( $old_url )['code'], 200 );

( new ArabicToLat\Converter( ArabicToLat\Plugin::instance()->transliterator() ) )->run( array( 'dry_run' => false ) );
global $wpdb;
t_check( 'I5', 'No duplicate slugs after conversion', (int) $wpdb->get_var( "SELECT COUNT(*) FROM (SELECT t.slug, tt.taxonomy FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id GROUP BY t.slug, tt.taxonomy HAVING COUNT(*) > 1) d" ), 0 );
$r = t_http( $old_url );
t_true( 'I5', 'Old Arabic category URL redirects to the unique new one', 301 === $r['code'] && false !== strpos( $r['location'], '/ar/category/sfr-wsiyaha-2/' ), $r['code'] . ' ' . $r['location'] );

// Editing the old category in WPML keeps its (new) slug; editing a never-converted Arabic term keeps its Arabic slug.
$keep = t_without_plugin( function () use ( $sitepress ) { return WPML_Post_Edit_Ajax::save_term_ajax( $sitepress, 'ar', 'category', '', 'رياضة', apply_filters( 'wpml_element_trid', null, wp_insert_term( 'Sport', 'category' )['term_taxonomy_id'], 'tax_category' ), '', array() ); } );
$sitepress->switch_lang( 'ar' );
wp_update_term( $keep->term_id, 'category', array( 'name' => 'رياضة', 'slug' => urldecode( $keep->slug ), 'description' => 'وصف' ) );
$after = get_term( $keep->term_id, 'category' );
$sitepress->switch_lang( 'en' );
t_check( 'I5', 'Editing an old Arabic category in Arabic keeps its slug (B3 under WPML)', $after->slug, $keep->slug );
