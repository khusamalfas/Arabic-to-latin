<?php
// Scenarios E5–E8 and E13: permissions, nonces, the no-JavaScript fallback, unsafe upload names.
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
// The settings page is rendered by PHP that no other test executes: a fatal in
// its markup would otherwise ship unnoticed.
$r = t_http( 'wp-admin/options-general.php?page=arabic-to-lat', array( 'cookies' => $admin ) );
t_check( 'E5', 'Admin: settings page renders', $r['code'], 200 );
t_true( 'E5', 'Admin: converter section present', false !== strpos( $r['body'], 'id="atl-existing"' ), substr( $r['body'], 0, 120 ) );
t_true( 'E5', 'Admin: re-conversion section present', false !== strpos( $r['body'], 'id="atl-reconvert"' ), substr( $r['body'], 0, 120 ) );
t_true( 'E5', 'Admin: re-conversion needs its own confirmation', false !== strpos( $r['body'], 'arabic_to_lat_reconvert_confirm' ), substr( $r['body'], 0, 120 ) );
t_true( 'E5', 'Admin: no PHP notice in the page', false === stripos( $r['body'], 'Fatal error' ) && false === stripos( $r['body'], 'Warning:' ), substr( $r['body'], 0, 200 ) );
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

// E13: an Arabic-spelled executable extension in the middle of a file name
// (evil.پهپ.jpg) must not become evil.php.jpg: WordPress itself turns
// evil.php.jpg into evil.php_.jpg, and transliteration must not undo that.
$upload_as_author = function ( $name ) use ( $author ) {
	wp_set_current_user( $author );
	$img = imagecreatetruecolor( 4, 4 );
	ob_start();
	imagejpeg( $img );
	$jpeg = ob_get_clean();
	$req  = new WP_REST_Request( 'POST', '/wp/v2/media' );
	$req->set_header( 'Content-Type', 'image/jpeg' );
	$req->set_header( 'Content-Disposition', 'attachment; filename="' . $name . '"' );
	$req->set_body( $jpeg );
	$res = rest_do_request( $req );
	$data = $res->get_data();
	wp_set_current_user( 1 );
	return array( $res->get_status(), isset( $data['id'] ) ? basename( (string) get_attached_file( $data['id'] ) ) : '' );
};
foreach ( array( 'evil.پهپ.jpg' => 'php', 'evil.پهتمل.jpg' => 'phtml', 'evil.ﭘﻬﭗ.jpg' => 'php (presentation forms)' ) as $name => $what ) {
	list( $status, $stored ) = $upload_as_author( $name );
	t_true( 'E13', "Author upload: Arabic-spelled .$what. is not left as an executable middle extension", 201 === $status && ! preg_match( '/\.(php|phtml|phar|pht|php\d)\./i', $stored ), "$status $stored" );
}
t_check( 'E13', 'Same as WordPress for a Latin name: evil.php.jpg', sanitize_file_name( 'evil.php.jpg' ), 'evil.php_.jpg' );
t_check( 'E13', 'Normal Arabic file names still transliterated', array( sanitize_file_name( 'صورة.jpg' ), sanitize_file_name( 'صورة المنتج.png' ) ), array( 'sura.jpg', 'sura-almntj.png' ) );

// E14: path characters produced by transliteration (full-width ／ and ‥ next to an
// Arabic ligature) must never reach the stored file path.
require_once ABSPATH . 'wp-admin/includes/file.php';
$uploads = wp_upload_dir();
foreach ( array( '／‥／‥／ﻻx.jpg', '‥／‥／‥／ﻻ．jpg．jpg', '＼‥＼ﻻx.jpg' ) as $name ) {
	wp_set_current_user( $author );
	$tmp = wp_tempnam( 'atl' );
	imagejpeg( imagecreatetruecolor( 4, 4 ), $tmp );
	$upload = array( 'name' => $name, 'type' => 'image/jpeg', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize( $tmp ) );
	$r      = wp_handle_sideload( $upload, array( 'test_form' => false ) );
	wp_set_current_user( 1 );
	$file = isset( $r['file'] ) ? $r['file'] : '';
	// Refused by WordPress (no real extension) is as safe as stored in the uploads folder.
	t_true( 'E14', "Upload '$name' refused or kept in the uploads folder", isset( $r['error'] ) || ( '' !== $file && dirname( $file ) === $uploads['path'] && false === strpos( $file, '..' ) ), $file ? $file : wp_json_encode( $r ) );
	t_true( 'E14', "sanitize_file_name('$name') has no path characters", ! preg_match( '#[/\\\\]|\.\.#', sanitize_file_name( $name ) ), sanitize_file_name( $name ) );
}
