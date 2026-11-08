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

/*
 * AI provider mock (suite ai.php). Requests to Anthropic or OpenAI never leave
 * the test site: they are recorded in the arabic_to_lat_test_ai_log option and
 * answered from arabic_to_lat_test_ai_mock ({code, body} or code "timeout").
 * Only the live test (ATL_TEST_*_KEY set) lets them through.
 */
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		if ( 0 !== strpos( $url, 'https://api.anthropic.com/' ) && 0 !== strpos( $url, 'https://api.openai.com/' ) ) {
			return $pre;
		}
		if ( get_option( 'arabic_to_lat_test_ai_live' ) ) {
			return $pre;
		}
		$log   = get_option( 'arabic_to_lat_test_ai_log', array() );
		$log[] = array(
			'url'     => $url,
			'headers' => $args['headers'],
			'body'    => json_decode( (string) $args['body'], true ),
		);
		update_option( 'arabic_to_lat_test_ai_log', $log, false );

		$mock = get_option( 'arabic_to_lat_test_ai_mock' );
		if ( ! is_array( $mock ) ) {
			return new WP_Error( 'http_request_failed', 'Test site: outgoing AI request without a mock.' );
		}
		if ( 'timeout' === $mock['code'] ) {
			return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 25001 milliseconds with 0 bytes received' );
		}
		return array(
			'headers'  => array(),
			'body'     => is_string( $mock['body'] ) ? $mock['body'] : wp_json_encode( $mock['body'] ),
			'response' => array(
				'code'    => (int) $mock['code'],
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);
