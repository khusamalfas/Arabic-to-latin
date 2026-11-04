<?php
/**
 * Test helpers, loaded by every suite (suites run inside WordPress via `wp eval-file`).
 *
 * Results are appended as JSON lines to $ATL_TEST_RESULTS so run.sh can build
 * one summary across suites. Every assertion carries a scenario ID from
 * SCENARIOS.md.
 *
 * @package ArabicToLat\Tests
 */

// phpcs:ignoreFile -- Test code, never shipped.

if ( ! function_exists( 't_check' ) ) {

	$GLOBALS['t_suite'] = basename( (string) ( getenv( 'ATL_TEST_SUITE' ) ?: 'suite' ) );

	/**
	 * Asserts strict equality and records the result.
	 *
	 * @param string $id    Scenario ID (A1, B3 …).
	 * @param string $label What is checked.
	 * @param mixed  $got   Actual value.
	 * @param mixed  $want  Expected value.
	 */
	function t_check( $id, $label, $got, $want ) {
		$ok = $got === $want;
		t_record( $id, $label, $ok, $ok ? '' : 'got ' . t_export( $got ) . ', want ' . t_export( $want ) );
		return $ok;
	}

	/**
	 * Asserts a condition.
	 */
	function t_true( $id, $label, $condition, $detail = '' ) {
		t_record( $id, $label, (bool) $condition, $condition ? '' : $detail );
		return (bool) $condition;
	}

	/**
	 * Records an expected, documented limitation (counts as pass, listed separately).
	 */
	function t_known( $id, $label, $detail ) {
		t_record( $id, $label, true, $detail, 'known' );
	}

	function t_record( $id, $label, $ok, $detail, $kind = 'check' ) {
		$row = array(
			'suite'  => $GLOBALS['t_suite'],
			'id'     => $id,
			'label'  => $label,
			'ok'     => $ok,
			'kind'   => $kind,
			'detail' => $detail,
		);
		printf( "%s %-4s %-58s %s\n", 'known' === $kind ? 'NOTE' : ( $ok ? 'PASS' : 'FAIL' ), $id, $label, $detail );
		$file = getenv( 'ATL_TEST_RESULTS' );
		if ( $file ) {
			file_put_contents( $file, wp_json_encode( $row, JSON_UNESCAPED_UNICODE ) . "\n", FILE_APPEND );
		}
	}

	function t_export( $value ) {
		$s = var_export( $value, true );
		return strlen( $s ) > 160 ? substr( $s, 0, 160 ) . '…' : $s;
	}

	/**
	 * The plugin's sanitize_title filter, to create "legacy" content as if the
	 * plugin had not been active.
	 */
	function t_plugin_filter() {
		return array( ArabicToLat\Plugin::instance(), 'sanitize_title' );
	}

	function t_without_plugin( callable $callback ) {
		remove_filter( 'sanitize_title', t_plugin_filter(), 9 );
		try {
			return $callback();
		} finally {
			add_filter( 'sanitize_title', t_plugin_filter(), 9, 3 );
		}
	}

	/**
	 * Base URL of the local test server.
	 */
	function t_url( $path = '/' ) {
		return rtrim( (string) getenv( 'ATL_TEST_URL' ), '/' ) . '/' . ltrim( $path, '/' );
	}

	/**
	 * GET without following redirects.
	 *
	 * @return array{code: int, location: string, body: string}
	 */
	function t_http( $path, array $args = array() ) {
		$url      = 0 === strpos( $path, 'http' ) ? $path : t_url( $path );
		$args     = array_merge( array( 'redirection' => 0, 'timeout' => 30, 'reject_unsafe_urls' => false ), $args );
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array( 'code' => 0, 'location' => '', 'by' => '', 'body' => $response->get_error_message() );
		}
		return array(
			'code'     => (int) wp_remote_retrieve_response_code( $response ),
			'location' => (string) wp_remote_retrieve_header( $response, 'location' ),
			'by'       => (string) wp_remote_retrieve_header( $response, 'x-redirect-by' ),
			'body'     => (string) wp_remote_retrieve_body( $response ),
		);
	}

	/**
	 * Real logged-in session for $user_id: cookies for HTTP requests, and the
	 * current CLI request set up so wp_create_nonce() matches that session.
	 *
	 * @return array Cookies for wp_remote_request().
	 */
	function t_login( $user_id ) {
		$expiration = time() + HOUR_IN_SECONDS;
		$token      = WP_Session_Tokens::get_instance( $user_id )->create( $expiration );
		$auth       = wp_generate_auth_cookie( $user_id, $expiration, 'auth', $token );
		$logged_in  = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in', $token );

		$_COOKIE[ LOGGED_IN_COOKIE ] = $logged_in;
		wp_set_current_user( $user_id );

		return array(
			AUTH_COOKIE      => $auth,
			LOGGED_IN_COOKIE => $logged_in,
		);
	}

	function t_user( $role ) {
		$login = 'atl_' . $role . '_' . wp_rand( 1000, 9999 );
		return wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => $login . '@example.test',
				'role'       => $role,
			)
		);
	}

	function t_settings( array $settings ) {
		update_option( 'arabic_to_lat_settings', array_merge( array( 'files' => true, 'preset' => 'standard', 'custom' => array() ), $settings ) );
	}

	function t_reset_settings() {
		delete_option( 'arabic_to_lat_settings' );
	}
}
