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
// Since 1.2.1 inner parts get WordPress's own treatment, as photo.copy.jpg → photo.copy_.jpg (see E13).
t_check( 'D3', 'Several dots: same rule as WordPress for Latin names', array( sanitize_file_name( 'صورة.نسخة.jpg' ), sanitize_file_name( 'photo.copy.jpg' ) ), array( 'sura.nskha_.jpg', 'photo.copy_.jpg' ) );
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

// D9: WordPress 7.2 resizes images in the browser. The browser uploads the original without sizes,
// then sends each size under a name it builds from the ORIGINAL (Arabic) file name, then finalizes.
// The route exists only when the request comes from a secure context (localhost counts), so ask over HTTP.
$index  = json_decode( t_http( 'wp-json/' )['body'], true );
$routes = is_array( $index ) && isset( $index['routes'] ) ? $index['routes'] : array();
if ( isset( $routes['/wp/v2/media/(?P<id>[\d]+)/finalize'] ) ) {
	$multipart = function ( $name, $fields ) use ( $tmp ) {
		$boundary = 'atl' . wp_generate_password( 12, false );
		$body     = '';
		foreach ( $fields as $key => $value ) {
			$body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n";
		}
		$body .= "--$boundary\r\nContent-Disposition: form-data; name=\"file\"; filename=\"$name\"\r\nContent-Type: image/jpeg\r\n\r\n" . file_get_contents( $tmp ) . "\r\n--$boundary--\r\n";
		return array( $body, "multipart/form-data; boundary=$boundary" );
	};
	$rest = function ( $path, $body, $type ) use ( $cookies ) {
		$r = t_http( 'wp-json/wp/v2/media' . $path, array( 'method' => 'POST', 'cookies' => $cookies, 'body' => $body, 'headers' => array( 'Content-Type' => $type, 'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ) ) ) );
		return json_decode( $r['body'], true );
	};
	wp_set_current_user( $author );
	list( $body, $type ) = $multipart( 'طبق ملوخية.jpg', array( 'generate_sub_sizes' => 'false', 'convert_format' => 'false' ) );
	$created             = $rest( '', $body, $type );
	$id                  = isset( $created['id'] ) ? (int) $created['id'] : 0;
	list( $body, $type ) = $multipart( 'طبق ملوخية-150x150.jpg', array( 'image_size' => 'thumbnail', 'convert_format' => 'false' ) );
	$size                = $id ? $rest( "/$id/sideload", $body, $type ) : array();
	$size_file           = isset( $size['file'] ) ? $size['file'] : '';
	$final               = $id && $size_file ? $rest( "/$id/finalize", wp_json_encode( array( 'sub_sizes' => array( array( 'image_size' => 'thumbnail', 'file' => $size_file, 'width' => 150, 'height' => 150, 'mime_type' => 'image/jpeg' ) ) ) ), 'application/json' ) : array();
	wp_set_current_user( 1 );
	$meta = $id ? wp_get_attachment_metadata( $id ) : array();
	t_check( 'D9', 'Browser-side processing (7.2): original stored with a Latin name', $id ? wp_basename( get_attached_file( $id ) ) : wp_json_encode( $created ), 'tbq-mlukhiya.jpg' );
	t_check( 'D9', 'Browser-named size gets the matching Latin name', $size_file, 'tbq-mlukhiya-150x150.jpg' );
	t_check( 'D9', 'Finalize stores the size in the attachment', isset( $meta['sizes']['thumbnail']['file'] ) ? $meta['sizes']['thumbnail']['file'] : wp_json_encode( $final ), 'tbq-mlukhiya-150x150.jpg' );
	t_check( 'D9', 'Arabic title kept', $id ? get_post( $id )->post_title : null, 'طبق ملوخية' );
} else {
	t_true( 'D9', 'Browser-side image processing not in this WordPress version (7.2+): skipped', true );
}

t_settings( array( 'files' => false ) );
t_check( 'D7', 'File names setting off: Arabic kept', sanitize_file_name( 'صورة.jpg' ), 'صورة.jpg' );
t_reset_settings();
