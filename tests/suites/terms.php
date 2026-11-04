<?php
// Scenarios C1–C5: terms.
require __DIR__ . '/../lib/bootstrap.php';

wp_set_current_user( 1 );

$cat = wp_insert_term( 'أخبار', 'category' );
t_check( 'C1', 'New Arabic category', get_term( $cat['term_id'] )->slug, 'akhbar' );
$tag = wp_insert_term( 'رياضة', 'post_tag' );
t_check( 'C1', 'New Arabic tag', get_term( $tag['term_id'] )->slug, 'riyada' );
register_taxonomy( 'atl_genre', 'post', array( 'public' => true, 'hierarchical' => true ) );
$genre = wp_insert_term( 'روايات', 'atl_genre' );
t_true( 'C1', 'New term in a custom taxonomy is Latin', (bool) preg_match( '/^[a-z0-9-]+$/', get_term( $genre['term_id'] )->slug ), get_term( $genre['term_id'] )->slug );

// C2: block editor creates tags through REST.
$req = new WP_REST_Request( 'POST', '/wp/v2/tags' );
$req->set_param( 'name', 'سياحة' );
$data = rest_do_request( $req )->get_data();
t_check( 'C2', 'Tag created by the block editor (REST)', isset( $data['slug'] ) ? $data['slug'] : $data, 'siyaha' );

// C3: an existing (pre-plugin) Arabic tag typed again by name.
$legacy = t_without_plugin( function () { return wp_insert_term( 'كلمة', 'post_tag' ); } );
$post   = wp_insert_post( array( 'post_title' => 'x', 'post_status' => 'publish' ) );
wp_set_post_tags( $post, array( 'كلمة' ), true );
t_check( 'C3', 'Existing Arabic tag reused, no duplicate', count( get_terms( array( 'taxonomy' => 'post_tag', 'name' => 'كلمة', 'hide_empty' => false ) ) ), 1 );
t_true( 'C3', 'Post got the existing tag', in_array( $legacy['term_id'], wp_list_pluck( wp_get_post_tags( $post ), 'term_id' ), true ) );

// C4: same Arabic name under two parents.
$p1 = wp_insert_term( 'Parent One', 'category' );
$p2 = wp_insert_term( 'Parent Two', 'category' );
$c1 = wp_insert_term( 'عروض', 'category', array( 'parent' => $p1['term_id'] ) );
$c2 = wp_insert_term( 'عروض', 'category', array( 'parent' => $p2['term_id'] ) );
$s1 = is_wp_error( $c1 ) ? $c1->get_error_message() : get_term( $c1['term_id'] )->slug;
$s2 = is_wp_error( $c2 ) ? $c2->get_error_message() : get_term( $c2['term_id'] )->slug;
t_true( 'C4', 'Same Arabic name under two parents: two unique Latin slugs', $s1 !== $s2 && preg_match( '/^[a-z0-9-]+$/', $s1 . $s2 ), "$s1 / $s2" );

// C5: English term names untouched.
$en = wp_insert_term( 'Travel Tips', 'category' );
t_check( 'C5', 'English term name', get_term( $en['term_id'] )->slug, 'travel-tips' );
