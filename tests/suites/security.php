<?php
// Scenarios E5–E8: permissions, nonces, the no-JavaScript fallback.
require __DIR__ . '/../lib/bootstrap.php';

$legacy = t_without_plugin( function () { return wp_insert_post( array( 'post_title' => 'مقالة قديمة', 'post_status' => 'publish' ) ); } );
$slug   = get_post( $legacy )->post_name;

// E5: no-JavaScript convert form without the backup tick.
$admin = t_login( 1 );
$r = t_http( 'wp-admin/admin-post.php', array( 'method' => 'POST', 'cookies' => $admin, 'body' => array(
	'action'              => 'arabic_to_lat_convert',
	'arabic_to_lat_apply' => '1',
	'_wpnonce'            => wp_create_nonce( 'arabic_to_lat_convert' ),
) ) );
t_true( 'E5', 'Convert without backup tick is refused', 302 === $r['code'] && false !== strpos( $r['location'], 'arabic_to_lat_error=backup' ), $r['code'] . ' ' . $r['location'] );
clean_post_cache( $legacy );
t_check( 'E5', 'Nothing converted', get_post( $legacy )->post_name, $slug );

// E6: an editor can't run conversions.
$editor = t_user( 'editor' );
$ed     = t_login( $editor );
$r = t_http( 'wp-admin/admin-ajax.php', array( 'method' => 'POST', 'cookies' => $ed, 'body' => array(
	'action'   => 'arabic_to_lat_convert_batch',
	'_wpnonce' => wp_create_nonce( 'arabic_to_lat_convert' ),
) ) );
t_check( 'E6', 'Editor: batch AJAX denied', $r['code'], 403 );
$r = t_http( 'wp-admin/options-general.php?page=arabic-to-lat', array( 'cookies' => $ed ) );
t_check( 'E6', 'Editor: settings page denied', $r['code'], 403 );
clean_post_cache( $legacy );
t_check( 'E6', 'Nothing converted', get_post( $legacy )->post_name, $slug );

// E7: admin without a valid nonce.
$admin = t_login( 1 );
$r = t_http( 'wp-admin/admin-ajax.php', array( 'method' => 'POST', 'cookies' => $admin, 'body' => array( 'action' => 'arabic_to_lat_convert_batch', '_wpnonce' => 'invalid' ) ) );
t_check( 'E7', 'Batch AJAX with a bad nonce denied', $r['code'], 403 );
$r = t_http( 'wp-admin/admin-post.php', array( 'method' => 'POST', 'cookies' => $admin, 'body' => array( 'action' => 'arabic_to_lat_convert', 'arabic_to_lat_apply' => '1', 'arabic_to_lat_backup' => '1' ) ) );
t_true( 'E7', 'Convert form without nonce denied', in_array( $r['code'], array( 403, 400 ), true ), (string) $r['code'] );
clean_post_cache( $legacy );
t_check( 'E7', 'Nothing converted', get_post( $legacy )->post_name, $slug );

// E8: REST slug endpoint permissions.
$endpoint = 'wp-json/arabic-to-lat/v1/slug?title=' . rawurlencode( 'مرحبا' );
t_check( 'E8', 'Logged out: 401', t_http( $endpoint )['code'], 401 );
$sub    = t_user( 'subscriber' );
$cookie = t_login( $sub );
t_check( 'E8', 'Subscriber: 403', t_http( $endpoint, array( 'cookies' => $cookie, 'headers' => array( 'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ) ) ) )['code'], 403 );
$author = t_user( 'author' );
$cookie = t_login( $author );
$r      = t_http( $endpoint, array( 'cookies' => $cookie, 'headers' => array( 'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ) ) ) );
t_check( 'E8', 'Author: slug returned', array( $r['code'], json_decode( $r['body'], true ) ), array( 200, array( 'slug' => 'mrhba' ) ) );
