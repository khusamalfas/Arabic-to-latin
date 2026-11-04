<?php
// Scenarios E9–E10: deactivation and uninstall after converting.
require __DIR__ . '/../lib/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

wp_set_current_user( 1 );
$post = t_without_plugin( function () { return wp_insert_post( array( 'post_title' => 'مقالة قديمة', 'post_status' => 'publish' ) ); } );
$page = t_without_plugin( function () { return wp_insert_post( array( 'post_title' => 'خدماتنا', 'post_status' => 'publish', 'post_type' => 'page' ) ); } );
$cat  = t_without_plugin( function () { return wp_insert_term( 'تقنية', 'category' ); } );
wp_set_post_categories( $post, array( $cat['term_id'] ) );
$old = array(
	'post' => '/' . get_post( $post )->post_name . '/',
	'page' => '/' . get_post( $page )->post_name . '/',
	'cat'  => '/category/' . get_term( $cat['term_id'] )->slug . '/',
);
( new ArabicToLat\Converter( ArabicToLat\Plugin::instance()->transliterator() ) )->run( array( 'dry_run' => false ) );
t_settings( array( 'preset' => 'egyptian' ) );

// E9: deactivated.
deactivate_plugins( 'arabic-to-lat/Arabic-to-lat.php' );
t_check( 'E9', 'Deactivated: converted post slug stays', get_post( $post )->post_name, 'mqala-qdima' );
t_check( 'E9', 'Deactivated: old post URL still redirects (WordPress core)', t_http( $old['post'] )['code'], 301 );
$page_code = t_http( $old['page'] )['code'];
$cat_code  = t_http( $old['cat'] )['code'];
t_known( 'E9', 'Deactivated: old page / category URLs', "HTTP $page_code / $cat_code — WordPress has no old-slug redirects for pages and terms; the plugin provides them while active (documented in the FAQ)" );
activate_plugin( 'arabic-to-lat/Arabic-to-lat.php' );
t_check( 'E9', 'Reactivated: old page URL redirects again', t_http( $old['page'] )['code'], 301 );

// E10: uninstall (runs uninstall.php only; files stay).
uninstall_plugin( 'arabic-to-lat/Arabic-to-lat.php' );
t_check( 'E10', 'Uninstall removes settings', get_option( 'arabic_to_lat_settings', 'gone' ), 'gone' );
t_true( 'E10', 'Uninstall keeps old-slug records', (bool) get_post_meta( $page, '_wp_old_slug' ) && (bool) get_term_meta( $cat['term_id'], '_arabic_to_lat_old_slug' ) );
activate_plugin( 'arabic-to-lat/Arabic-to-lat.php' );
