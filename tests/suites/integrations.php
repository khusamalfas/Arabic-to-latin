<?php
// requires: duplicate-post
// Scenario I8: Yoast Duplicate Post.
require __DIR__ . '/../lib/bootstrap.php';

wp_set_current_user( 1 );
$duplicator = new Yoast\WP\Duplicate_Post\Post_Duplicator();
$source     = wp_insert_post( array( 'post_title' => 'فنادق دبي', 'post_status' => 'publish' ) );

$copy = $duplicator->create_duplicate( get_post( $source ), array( 'copy_status' => false ) );
t_check( 'I8', 'Duplicate draft has no slug yet', get_post( $copy )->post_name, '' );
wp_update_post( array( 'ID' => $copy, 'post_status' => 'publish' ) );
t_check( 'I8', 'Duplicate published: Latin, unique', get_post( $copy )->post_name, 'fnadq-dbi-2' );

$copy2 = $duplicator->create_duplicate( get_post( $source ), array( 'copy_status' => true, 'copy_name' => true ) );
t_check( 'I8', 'Duplicate copying status and slug: Latin, unique', get_post( $copy2 )->post_name, 'fnadq-dbi-3' );
