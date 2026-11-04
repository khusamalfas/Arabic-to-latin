<?php
// Scenarios H1–H3: moving content between sites.
require __DIR__ . '/../lib/bootstrap.php';

wp_set_current_user( 1 );

// H3 first: an explicit Arabic slug outside an import is a deliberate new slug.
$id = wp_insert_post( array( 'post_title' => 'عنوان', 'post_name' => 'رابط-مستورد', 'post_status' => 'publish' ) );
t_check( 'H3', 'Explicit Arabic slug outside an import → Latin', get_post( $id )->post_name, sanitize_title( 'رابط-مستورد' ) );
t_true( 'H3', 'and it is Latin', (bool) preg_match( '/^[a-z0-9-]+$/', get_post( $id )->post_name ), get_post( $id )->post_name );

// The WordPress Importer (and most importers) define WP_IMPORTING.
define( 'WP_IMPORTING', true );
$id = wp_insert_post( array( 'post_title' => 'من نحن', 'post_name' => rawurlencode( 'من-نحن' ), 'post_status' => 'publish', 'post_type' => 'page' ) );
t_check( 'H1', 'Import: encoded Arabic slug from the export kept', get_post( $id )->post_name, '%d9%85%d9%86-%d9%86%d8%ad%d9%86' );
$id = wp_insert_post( array( 'post_title' => 'اتصل بنا', 'post_name' => 'اتصل-بنا', 'post_status' => 'publish', 'post_type' => 'page' ) );
t_check( 'H1', 'Import: decoded Arabic slug from the export kept', get_post( $id )->post_name, '%d8%a7%d8%aa%d8%b5%d9%84-%d8%a8%d9%86%d8%a7' );
$t = wp_insert_term( 'أخبار', 'category', array( 'slug' => 'أخبار' ) );
t_check( 'H1', 'Import: Arabic term slug kept', get_term( $t['term_id'] )->slug, '%d8%a3%d8%ae%d8%a8%d8%a7%d8%b1' );

// H2: imported items without a slug.
$id = wp_insert_post( array( 'post_title' => 'خدماتنا', 'post_status' => 'publish' ) );
t_check( 'H2', 'Import without slug: Latin from the title', get_post( $id )->post_name, 'khdmatna' );
