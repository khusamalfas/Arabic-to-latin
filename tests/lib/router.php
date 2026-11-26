<?php
/**
 * Router for PHP's built-in web server, used by tests/run.sh instead of `wp server`.
 *
 * `wp server` loads parts of WP-CLI into every request; on PHP 8.6 those print their own
 * deprecation notices into the page, which breaks redirects and JSON. This router only
 * does what WordPress needs: real files are served as they are, everything else goes to
 * index.php (pretty permalinks), and a directory gets its own index.php (wp-admin/).
 *
 * @package ArabicToLat
 */

$root = rtrim( (string) $_SERVER['DOCUMENT_ROOT'], '/' );
$path = (string) parse_url( urldecode( (string) $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
$file = $root . $path;

// Never let a request leave the site folder.
$real = realpath( $file );
if ( false !== $real && 0 !== strpos( $real, (string) realpath( $root ) ) ) {
	http_response_code( 403 );
	return true;
}

if ( is_file( $file ) ) {
	return false; // A real file: the built-in server serves it, and runs it when it is PHP.
}

if ( is_dir( $file ) && is_file( rtrim( $file, '/' ) . '/index.php' ) ) {
	if ( '/' !== substr( $path, -1 ) ) {
		header( 'Location: ' . $path . '/' );
		http_response_code( 301 );
		return true;
	}
	$_SERVER['SCRIPT_NAME']     = rtrim( $path, '/' ) . '/index.php';
	$_SERVER['SCRIPT_FILENAME'] = rtrim( $file, '/' ) . '/index.php';
	chdir( dirname( $_SERVER['SCRIPT_FILENAME'] ) );
	require $_SERVER['SCRIPT_FILENAME'];
	return true;
}

// Everything else is a WordPress URL.
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
chdir( $root );
require $root . '/index.php';
return true;
