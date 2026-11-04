<?php
// Scenarios D1–D7: uploaded file names.
require __DIR__ . '/../lib/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

wp_set_current_user( 1 );

// Uploads a real file the way the media library does; returns [file basename, attachment].
$upload = function ( $name ) {
	$tmp = wp_tempnam( 'atl' );
	if ( function_exists( 'imagecreatetruecolor' ) ) {
		$img = imagecreatetruecolor( 2, 2 );
		imagejpeg( $img, $tmp );
	} else {
		file_put_contents( $tmp, "\xFF\xD8\xFF\xE0" . str_repeat( "\0", 64 ) );
	}
	$id = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), 0 );
	if ( is_wp_error( $id ) ) {
		return array( 'WP_Error: ' . $id->get_error_message(), null );
	}
	return array( wp_basename( get_attached_file( $id ) ), get_post( $id ) );
};

list( $file, $attachment ) = $upload( 'صورة جميلة.jpg' );
t_check( 'D1', 'Arabic file name → Latin', $file, 'sura-jmila.jpg' );
t_check( 'D1', 'Attachment title stays Arabic', $attachment ? $attachment->post_title : null, 'صورة جميلة' );
t_check( 'D1', 'Attachment page slug Latin', $attachment ? $attachment->post_name : null, 'sura-jmila' );

t_check( 'D2', 'Name with no letters (؟.jpg) keeps WordPress behaviour', sanitize_file_name( '؟.jpg' ), t_without_plugin( function () { return sanitize_file_name( '؟.jpg' ); } ) );
t_check( 'D3', 'Several dots', sanitize_file_name( 'صورة.نسخة.jpg' ), 'sura.nskha.jpg' );
t_check( 'D4', 'Uppercase extension kept', sanitize_file_name( 'صورة.JPG' ), 'sura.JPG' );

list( $again ) = $upload( 'صورة جميلة.jpg' );
t_check( 'D5', 'Same Arabic name uploaded twice gets -1', $again, 'sura-jmila-1.jpg' );

t_check( 'D6', 'English file name untouched', sanitize_file_name( 'Team Photo 2026.png' ), 'Team-Photo-2026.png' );

// D8: the block editor uploads with multipart/form-data to /wp/v2/media.
$author   = t_user( 'author' );
$cookies  = t_login( $author );
$tmp      = wp_tempnam( 'atl' );
if ( function_exists( 'imagecreatetruecolor' ) ) { imagejpeg( imagecreatetruecolor( 2, 2 ), $tmp ); }
$boundary = 'atl' . wp_generate_password( 12, false );
$body     = "--$boundary\r\nContent-Disposition: form-data; name=\"file\"; filename=\"شعار الشركة.jpg\"\r\nContent-Type: image/jpeg\r\n\r\n" . file_get_contents( $tmp ) . "\r\n--$boundary--\r\n";
$r        = t_http( 'wp-json/wp/v2/media', array( 'method' => 'POST', 'cookies' => $cookies, 'body' => $body, 'headers' => array( 'Content-Type' => "multipart/form-data; boundary=$boundary", 'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ) ) ) );
$res      = json_decode( $r['body'], true );
wp_set_current_user( 1 );
$file = isset( $res['id'] ) ? wp_basename( get_attached_file( $res['id'] ) ) : $r['code'] . ' ' . substr( $r['body'], 0, 120 );
t_check( 'D8', 'Block editor upload: Latin file name', $file, 'shaar-alshrka.jpg' );
t_check( 'D8', 'Block editor upload: Arabic title kept', isset( $res['id'] ) ? get_post( $res['id'] )->post_title : null, 'شعار الشركة' );

t_settings( array( 'files' => false ) );
t_check( 'D7', 'File names setting off: Arabic kept', sanitize_file_name( 'صورة.jpg' ), 'صورة.jpg' );
t_reset_settings();
