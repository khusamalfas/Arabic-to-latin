<?php
// requires: translatepress-multilingual
// Scenario I4: TranslatePress filters sanitize_title itself (priority 1).
require __DIR__ . '/../lib/bootstrap.php';

wp_set_current_user( 1 );
$settings                          = get_option( 'trp_settings', array() );
$settings['default-language']      = 'en_US';
$settings['translation-languages'] = array( 'en_US', 'ar' );
$settings['publish-languages']     = array( 'en_US', 'ar' );
$settings['url-slugs']             = array( 'en_US' => 'en', 'ar' => 'ar' );
update_option( 'trp_settings', $settings );

t_true( 'I4', 'TranslatePress filter is active on sanitize_title', has_filter( 'sanitize_title' ) && count( $GLOBALS['wp_filter']['sanitize_title']->callbacks ) >= 3 );
$id = wp_insert_post( array( 'post_title' => 'مرحبا بالعالم', 'post_status' => 'publish' ) );
t_check( 'I4', 'Post slug', get_post( $id )->post_name, 'mrhba-balaalm' );
t_check( 'I4', 'Mixed title', sanitize_title( 'iPhone 17 مراجعة' ), 'iphone-17-mrajaa' );
t_check( 'I4', 'English title unchanged', sanitize_title( 'Hello World' ), 'hello-world' );
$cat = wp_insert_term( 'أخبار', 'category' );
t_check( 'I4', 'Term slug', get_term( $cat['term_id'] )->slug, 'akhbar' );
t_check( 'I4', 'Legacy term lookup still works', t_without_plugin( function () { return wp_insert_term( 'قديم', 'category' )['term_id']; } ) > 0 && (bool) get_term_by( 'slug', 'قديم', 'category' ), true );

flush_rewrite_rules();
t_check( 'I4', 'Default-language URL', t_http( '/mrhba-balaalm/' )['code'], 200 );
t_check( 'I4', '/ar/ URL', t_http( '/ar/mrhba-balaalm/' )['code'], 200 );
