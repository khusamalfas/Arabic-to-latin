<?php
// Scenarios C6, F1–F8, G1–G3, J1–J2: real HTTP requests to the local server.
require __DIR__ . '/../lib/bootstrap.php';

wp_set_current_user( 1 );
$legacy = t_without_plugin(
	function () {
		$cat    = wp_insert_term( 'تقنية', 'category' );
		$post   = wp_insert_post( array( 'post_title' => 'مقالة قديمة', 'post_status' => 'publish', 'post_category' => array( $cat['term_id'] ) ) );
		$parent = wp_insert_post( array( 'post_title' => 'خدماتنا', 'post_status' => 'publish', 'post_type' => 'page' ) );
		$child  = wp_insert_post( array( 'post_title' => 'تصميم المواقع', 'post_status' => 'publish', 'post_type' => 'page', 'post_parent' => $parent ) );
		$same   = wp_insert_post( array( 'post_title' => 'تصميم-المواقع', 'post_status' => 'publish' ) );
		$hidden = wp_insert_post( array( 'post_title' => 'مقالة مخفية', 'post_status' => 'publish' ) );
		$priv   = wp_insert_post( array( 'post_title' => 'مقالة خاصة', 'post_status' => 'publish' ) );
		for ( $i = 1; $i <= 12; $i++ ) {
			wp_insert_post( array( 'post_title' => "post $i", 'post_status' => 'publish', 'post_category' => array( $cat['term_id'] ) ) );
		}
		return compact( 'cat', 'post', 'parent', 'child', 'same', 'hidden', 'priv' );
	}
);
update_option( 'posts_per_page', 5 );
$u = array(
	'post'   => '/' . get_post( $legacy['post'] )->post_name . '/',
	'child'  => '/' . get_post( $legacy['parent'] )->post_name . '/' . get_post( $legacy['child'] )->post_name . '/',
	'cat'    => '/category/' . get_term( $legacy['cat']['term_id'] )->slug . '/',
	'hidden' => '/' . get_post( $legacy['hidden'] )->post_name . '/',
	'priv'   => '/' . get_post( $legacy['priv'] )->post_name . '/',
);

// C6 / G3: before conversion, with the plugin active.
t_check( 'C6', 'Old Arabic category URL works (not converted)', t_http( $u['cat'] )['code'], 200 );
t_check( 'C6', 'Old Arabic child page URL works', t_http( $u['child'] )['code'], 200 );
$r = t_http( 'wp-json/wp/v2/posts?_fields=id&slug=' . rawurlencode( rawurldecode( get_post( $legacy['post'] )->post_name ) ) );
t_check( 'G3', 'REST ?slug= finds the old post (headless)', json_decode( $r['body'], true ), array( array( 'id' => $legacy['post'] ) ) );
$r = t_http( 'wp-json/wp/v2/categories?_fields=id&slug=' . rawurlencode( 'تقنية' ) );
t_check( 'G3', 'REST ?slug= finds the old category', json_decode( $r['body'], true ), array( array( 'id' => $legacy['cat']['term_id'] ) ) );

// G1 / G2: public pages.
t_check( 'G1', 'Heading anchor on a public page keeps Arabic', t_http( '/?atl_probe=anchor' )['body'], '%d9%85%d9%82%d8%af%d9%85%d8%a9-%d8%a7%d9%84%d9%85%d9%82%d8%a7%d9%84' );
t_check( 'G2', 'Post created by a front-end form gets a Latin slug', t_http( '/?atl_probe=create' )['body'], 'msharka-mn-alwajha' );

// Convert, then hide two posts.
( new ArabicToLat\Converter( ArabicToLat\Plugin::instance()->transliterator() ) )->run( array( 'dry_run' => false ) );
wp_trash_post( $legacy['hidden'] );
wp_update_post( array( 'ID' => $legacy['priv'], 'post_status' => 'private' ) );

$redirects = function ( $id, $label, $path, $want ) {
	$r = t_http( $path );
	t_true( $id, $label, 301 === $r['code'] && $want === wp_parse_url( $r['location'], PHP_URL_PATH ) . ( wp_parse_url( $r['location'], PHP_URL_QUERY ) ? '?' . wp_parse_url( $r['location'], PHP_URL_QUERY ) : '' ), $r['code'] . ' → ' . $r['location'] );
};

// F1.
$redirects( 'F1', 'Old post → new', $u['post'], '/mqala-qdima/' );
$redirects( 'F1', 'Old child page → new child page (not the same-slug post)', $u['child'], '/khdmatna/tsmim-almwaqa/' );
$redirects( 'F1', 'Old category → new', $u['cat'], '/category/tqniya/' );
// F2–F4.
$redirects( 'F2', 'Query string kept (post)', $u['post'] . '?utm_source=news&x=1', '/mqala-qdima/?utm_source=news&x=1' );
$redirects( 'F2', 'Query string kept (category)', $u['cat'] . '?utm_source=news', '/category/tqniya/?utm_source=news' );
$redirects( 'F3', 'Paginated old category', $u['cat'] . 'page/2/', '/category/tqniya/page/2/' );
$redirects( 'F4', 'Old category feed', $u['cat'] . 'feed/', '/category/tqniya/feed/' );
// F5–F8.
foreach ( array( 'trashed' => $u['hidden'], 'private' => $u['priv'] ) as $what => $path ) {
	$r     = t_http( $path );
	$final = 301 === $r['code'] ? t_http( $r['location'] )['code'] : $r['code'];
	t_check( 'F5', "Old slug of a $what post: visitors end on 404", $final, 404 );
	t_true( 'F5', "Old slug of a $what post: plugin itself does not redirect", 'Arabic to Latin' !== $r['by'], $r['code'] . ' by ' . $r['by'] );
}
$hidden_page = t_without_plugin( function () { return wp_insert_post( array( 'post_title' => 'صفحة مخفية', 'post_status' => 'publish', 'post_type' => 'page' ) ); } );
$hidden_old  = '/' . get_post( $hidden_page )->post_name . '/';
( new ArabicToLat\Converter( ArabicToLat\Plugin::instance()->transliterator() ) )->run( array( 'dry_run' => false ) );
wp_trash_post( $hidden_page );
t_check( 'F5', 'Old slug of a trashed page: 404 (plugin redirect skips it)', t_http( $hidden_old )['code'], 404 );
t_known( 'F6', 'New post reusing an old Arabic slug', 'a new Arabic slug is transliterated on save, so an old Arabic URL can only be reused through WP-CLI or the database; not testable through normal editing' );
t_check( 'F7', 'Unknown Arabic URL: 404', t_http( '/' . rawurlencode( 'لا يوجد' ) . '/' )['code'], 404 );
$redirects( 'F8', 'Uppercase percent-encoding', strtoupper( $u['post'] ), '/mqala-qdima/' );

// J2: /%category%/%postname%/ permalinks.
global $wp_rewrite;
$wp_rewrite->set_permalink_structure( '/%category%/%postname%/' );
flush_rewrite_rules();
clean_post_cache( $legacy['post'] );
clean_term_cache( $legacy['cat']['term_id'], 'category' );
$r = t_http( get_permalink( $legacy['post'] ) );
t_check( 'J2', 'Category-based permalink of a converted post works (' . wp_parse_url( get_permalink( $legacy['post'] ), PHP_URL_PATH ) . ')', $r['code'], 200 );
$r = t_http( '/tqniya' . $u['post'] );
t_true( 'J2', 'Old post slug under category base redirects', in_array( $r['code'], array( 301, 302 ), true ) && false !== strpos( $r['location'], 'mqala-qdima' ), $r['code'] . ' ' . $r['location'] );

// J1: plain permalinks.
$wp_rewrite->set_permalink_structure( '' );
flush_rewrite_rules();
$id = wp_insert_post( array( 'post_title' => 'مقالة جديدة', 'post_status' => 'publish' ) );
t_check( 'J1', 'Plain permalinks: slug still Latin', get_post( $id )->post_name, 'mqala-jdida' );
t_check( 'J1', 'Plain permalinks: ?p= works', t_http( '/?p=' . $id )['code'], 200 );
$wp_rewrite->set_permalink_structure( '/%postname%/' );
flush_rewrite_rules();
