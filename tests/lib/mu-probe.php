<?php
/**
 * Test site only: front-end probes used by the http suite (G1, G2).
 *
 * @package ArabicToLat\Tests
 */

// phpcs:ignoreFile -- Test code, never shipped.

add_action(
	'template_redirect',
	function () {
		if ( empty( $_GET['atl_probe'] ) ) {
			return;
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		switch ( $_GET['atl_probe'] ) {
			case 'anchor': // What a table-of-contents plugin does with an Arabic heading.
				echo sanitize_title( 'مقدمة المقال' );
				break;
			case 'create': // A front-end form creating a post.
				$id = wp_insert_post( array( 'post_title' => 'مشاركة من الواجهة', 'post_status' => 'publish' ) );
				echo get_post( $id )->post_name;
				wp_delete_post( $id, true );
				break;
		}
		exit;
	}
);
