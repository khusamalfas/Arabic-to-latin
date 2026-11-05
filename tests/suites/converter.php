<?php
// Scenario E4 and the converter engine (batches, cursor, scope).
require __DIR__ . '/../lib/bootstrap.php';

wp_set_current_user( 1 );
t_without_plugin(
	function () {
		for ( $i = 1; $i <= 250; $i++ ) {
			wp_insert_post( array( 'post_title' => "مقالة رقم $i", 'post_status' => 'publish' ) );
		}
		foreach ( array( 'من نحن', 'اتصل بنا' ) as $t ) {
			wp_insert_post( array( 'post_title' => $t, 'post_status' => 'publish', 'post_type' => 'page' ) );
		}
		for ( $i = 1; $i <= 20; $i++ ) {
			wp_insert_term( "قسم $i", 'category' );
		}
		// Cannot be transliterated (a lone annotation sign): must not loop forever.
		wp_insert_post( array( 'post_title' => 'x', 'post_name' => rawurlencode( "\u{0610}" ), 'post_status' => 'publish' ) );
		// A menu item must never be converted.
		wp_insert_post( array( 'post_title' => 'قائمة', 'post_status' => 'publish', 'post_type' => 'nav_menu_item' ) );
	}
);

$converter = function () { return new ArabicToLat\Converter( ArabicToLat\Plugin::instance()->transliterator() ); };

t_check( 'E4', 'Found before', $converter()->count(), array( 'posts' => 253, 'terms' => 20 ) );
t_check( 'E4', 'Per type', $converter()->count_by_type(), array( 'posts' => array( 'page' => 2, 'post' => 251 ), 'terms' => array( 'category' => 20 ) ) );

// Scope: pages only, dry run.
$r = $converter()->scope( array( 'page' ), array() )->run( array( 'dry_run' => true ) );
t_check( 'E4', 'Scope pages only (dry run)', count( $r['changes'] ), 2 );
$r = $converter()->scope( array( 'nav_menu_item', 'revision' ), array() )->run( array( 'dry_run' => true ) );
t_check( 'E4', 'Disallowed types refused', count( $r['changes'] ), 0 );

// Batches with a cursor, like the settings page.
$cursor = array(); $batches = 0; $total = 0; $seen = array();
do {
	$r = $converter()->run( array( 'dry_run' => false, 'limit' => 100, 'cursor' => $cursor ) );
	$cursor = $r['cursor']; $batches++; $total += count( $r['changes'] );
	foreach ( $r['changes'] as $c ) {
		$seen[ $c['type'] . $c['id'] ] = ( isset( $seen[ $c['type'] . $c['id'] ] ) ? $seen[ $c['type'] . $c['id'] ] : 0 ) + 1;
	}
} while ( ! $r['done'] && $batches < 50 );
t_check( 'E4', 'Finished in 3 batches of 100', array( $r['done'], $batches ), array( true, 3 ) );
t_check( 'E4', 'Converted', $total, 272 );
t_check( 'E4', 'Nothing converted twice', max( $seen ), 1 );
t_check( 'E4', 'Second run converts nothing (idempotent)', count( $converter()->run( array( 'dry_run' => false ) )['changes'] ), 0 );
t_check( 'E4', 'Left: only the unconvertible slug', $converter()->count(), array( 'posts' => 1, 'terms' => 0 ) );
$menu = get_posts( array( 'post_type' => 'nav_menu_item', 'post_status' => 'any', 'numberposts' => 1 ) );
t_true( 'E4', 'Menu item slug untouched', $menu && 0 === strpos( $menu[0]->post_name, '%d9%82' ), $menu ? $menu[0]->post_name : 'none' );
t_check( 'E4', 'Old slug recorded for redirects', count( get_post_meta( get_page_by_path( 'mn-nhn' )->ID, '_wp_old_slug' ) ), 1 );

// E12: re-converting slugs this plugin already made, after a spelling-style change.
$fa_post   = wp_insert_post( array( 'post_title' => 'خانه ما', 'post_status' => 'publish' ) );
$custom_id = wp_insert_post( array( 'post_title' => 'خانه شما', 'post_name' => 'my-own-slug', 'post_status' => 'publish' ) );
$media_id  = wp_insert_post( array( 'post_title' => 'خانه', 'post_status' => 'inherit', 'post_type' => 'attachment' ) );
$cat       = wp_insert_term( 'نامه', 'category' );
$cat_id    = is_array( $cat ) ? (int) $cat['term_id'] : 0;

t_check( 'E12', 'Standard style first', get_post( $fa_post )->post_name, 'khanh-ma' );
t_check( 'E12', 'A hand-written slug is kept as typed', get_post( $custom_id )->post_name, 'my-own-slug' );

t_settings( array( 'preset' => 'persian' ) );
ArabicToLat\Plugin::instance()->reset();

t_true( 'E12', 'Re-conversion is off by default', ! $converter()->is_reconverting() );
t_check( 'E12', 'The normal converter leaves Latin slugs alone', count( $converter()->run( array( 'dry_run' => true ) )['changes'] ), 0 );

$preview = $converter()->reconvert()->run( array( 'dry_run' => true ) );
t_true( 'E12', 'Preview finds the Persian slug', (bool) array_filter( $preview['changes'], function ( $c ) { return 'khanh-ma' === $c['old']; } ), count( $preview['changes'] ) );
t_check( 'E12', 'Preview changed nothing', get_post( $fa_post )->post_name, 'khanh-ma' );

$converter()->reconvert()->run( array( 'dry_run' => false ) );
t_check( 'E12', 'Persian spelling applied', get_post( $fa_post )->post_name, 'khane-ma' );
t_check( 'E12', 'Old URL still redirects', get_post_meta( $fa_post, '_wp_old_slug' ), array( 'khanh-ma' ) );
t_check( 'E12', 'Hand-written slug untouched', get_post( $custom_id )->post_name, 'my-own-slug' );
t_check( 'E12', 'Uploaded file never renamed', get_post( $media_id )->post_name, 'khanh' );
t_check( 'E12', 'Term re-converted', get_term( $cat_id )->slug, 'name' );
t_check( 'E12', 'Term old slug recorded', get_term_meta( $cat_id, '_arabic_to_lat_old_slug' ), array( 'namh' ) );
t_check( 'E12', 'Re-conversion is idempotent', count( $converter()->reconvert()->run( array( 'dry_run' => false ) )['changes'] ), 0 );

t_check( 'E12', 'A slug with a uniqueness suffix is still recognised', ArabicToLat\Converter::can_reconvert( 'khanh-ma-2', 'خانه ما' ), true );
t_check( 'E12', 'A hand-written slug is not', ArabicToLat\Converter::can_reconvert( 'my-own-slug', 'خانه شما' ), false );
t_check( 'E12', 'A Latin title gives nothing to rebuild from', ArabicToLat\Converter::can_reconvert( 'hello-world', 'Hello world' ), false );

t_reset_settings();
ArabicToLat\Plugin::instance()->reset();
