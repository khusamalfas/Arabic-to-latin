<?php
// requires: polylang
// Scenario I3: Polylang.
require __DIR__ . '/../lib/bootstrap.php';

wp_set_current_user( 1 );
$model = PLL()->model;
foreach ( array( array( 'English', 'en', 'en_US', 0 ), array( 'العربية', 'ar', 'ar', 1 ), array( 'Deutsch', 'de', 'de_DE', 0 ) ) as $i => $l ) {
	if ( ! $model->get_language( $l[1] ) ) {
		$model->languages->add( array( 'name' => $l[0], 'slug' => $l[1], 'locale' => $l[2], 'rtl' => $l[3], 'term_group' => $i ) );
	}
}
$model->clean_languages_cache();
t_check( 'I3', 'Languages set up', $model->get_languages_list( array( 'fields' => 'slug' ) ), array( 'en', 'ar', 'de' ) );

$en = wp_insert_post( array( 'post_title' => 'About us', 'post_status' => 'publish', 'post_type' => 'page' ) );
pll_set_post_language( $en, 'en' );
$ar = wp_insert_post( array( 'post_title' => 'من نحن', 'post_status' => 'publish', 'post_type' => 'page' ) );
pll_set_post_language( $ar, 'ar' );
pll_save_post_translations( array( 'en' => $en, 'ar' => $ar ) );
t_check( 'I3', 'Arabic translation slug', get_post( $ar )->post_name, 'mn-nhn' );

$cat = wp_insert_term( 'أخبار', 'category' );
pll_set_term_language( $cat['term_id'], 'ar' );
t_check( 'I3', 'Arabic term slug', get_term( $cat['term_id'] )->slug, 'akhbar' );

// Polylang re-runs sanitize_title for German: Arabic still converted, umlauts as WordPress does them.
$de = wp_insert_post( array( 'post_title' => 'Größe من نحن', 'post_status' => 'publish', 'post_type' => 'page' ) );
t_check( 'I3', 'German language active: Arabic converted, rest unchanged', get_post( $de )->post_name, t_without_plugin( function () { return sanitize_title( 'Größe' ); } ) . '-mn-nhn' );

// Rules are rebuilt by the next request, with Polylang's language rules loaded.
delete_option( 'rewrite_rules' );
t_check( 'I3', '/ar/ page URL', t_http( '/ar/mn-nhn/' )['code'], 200 );
$x = wp_insert_post( array( 'post_title' => 'p', 'post_status' => 'publish', 'post_category' => array( $cat['term_id'] ) ) );
pll_set_post_language( $x, 'ar' );
t_check( 'I3', '/ar/ category URL', t_http( '/ar/category/akhbar/' )['code'], 200 );

// Legacy Arabic content in Polylang, then converted.
$legacy = t_without_plugin( function () { return wp_insert_post( array( 'post_title' => 'صفحة قديمة', 'post_status' => 'publish', 'post_type' => 'page' ) ); } );
pll_set_post_language( $legacy, 'ar' );
$old = '/ar/' . get_post( $legacy )->post_name . '/';
t_check( 'I3', 'Legacy Arabic page URL works', t_http( $old )['code'], 200 );
( new ArabicToLat\Converter( ArabicToLat\Plugin::instance()->transliterator() ) )->run( array( 'dry_run' => false ) );
$r = t_http( $old );
t_true( 'I3', 'Converted: old /ar/ URL redirects to the new /ar/ URL', 301 === $r['code'] && false !== strpos( $r['location'], '/ar/sfha-qdima/' ), $r['code'] . ' ' . $r['location'] );
