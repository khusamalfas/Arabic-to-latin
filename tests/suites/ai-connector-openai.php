<?php
// requires: ai-provider-for-openai
// Scenarios K28–K29: AI slug suggestions through the WordPress AI Client with
// the official OpenAI provider plugin (Responses API). Same approach as
// ai-connectors.php: suggestions over HTTP, OpenAI's API mocked by mu-probe.php.
require __DIR__ . '/../lib/bootstrap.php';

use ArabicToLat\AI;

$connector_key = 'sk-proj-connector-000000000000oooo';
$own_key       = 'sk-ant-own-key-11111111111111wxyz';

// The log is written by the web server process: drop this process's options cache before reading.
$log = function () {
	wp_cache_flush();
	$entries = get_option( 'arabic_to_lat_test_ai_log', array() );
	delete_option( 'arabic_to_lat_test_ai_log' );
	return is_array( $entries ) ? $entries : array();
};
$models = array(
	'code' => 200,
	'body' => array(
		'object' => 'list',
		'data'   => array(
			array( 'id' => 'gpt-6-astra', 'object' => 'model', 'created' => 1790000000, 'owned_by' => 'openai' ),
		),
	),
);
$mock = function ( $generate ) use ( $models ) {
	update_option( 'arabic_to_lat_test_ai_mock', array( 'by_url' => array( '/v1/models' => $models, '/v1/responses' => $generate ) ), false );
};
$answer = function ( $text, $status = 'completed' ) {
	return array(
		'code' => 200,
		'body' => array(
			'id'     => 'resp_test',
			'object' => 'response',
			'status' => $status,
			'model'  => 'gpt-6-astra',
			'output' => array(
				array( 'type' => 'message', 'id' => 'msg_test', 'role' => 'assistant', 'status' => $status, 'content' => array( array( 'type' => 'output_text', 'text' => $text, 'annotations' => array() ) ) ),
			),
			'usage'  => array( 'input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15 ),
		),
	);
};
$openai_error = function ( $code, $type, $message ) {
	return array(
		'code' => $code,
		'body' => array( 'error' => array( 'message' => $message, 'type' => $type, 'param' => null, 'code' => $type ) ),
	);
};

update_option(
	AI::OPTION,
	array(
		'enabled'       => true,
		'provider'      => AI::WORDPRESS,
		'key_anthropic' => AI::encrypt( $own_key ),
		'style'         => 'describe',
		'daily_limit'   => 100,
	)
);
update_option( 'connectors_ai_openai_api_key', $connector_key );

$admin_cookies = t_login( 1 );
$nonce         = wp_create_nonce( 'wp_rest' );
$suggest       = function ( $title ) use ( $admin_cookies, $nonce ) {
	$r    = t_http( 'wp-json/arabic-to-lat/v1/ai-slug', array( 'method' => 'POST', 'body' => array( 'title' => $title ), 'cookies' => $admin_cookies, 'headers' => array( 'X-WP-Nonce' => $nonce ) ) );
	$data = json_decode( $r['body'], true );
	return array(
		'http' => $r['code'],
		'code' => is_array( $data ) && isset( $data['code'] ) ? $data['code'] : ( isset( $data['slug'] ) ? 'ok:' . $data['slug'] : 'unreadable' ),
		'text' => $r['body'],
	);
};
$log();

// K28: suggestion through the OpenAI connector.
$mock( $answer( '{"slug":"molokhia-recipe"}' ) );
$r   = $suggest( 'طبخة ملوخية' );
$req = $log();
t_check( 'K28', 'OpenAI connector: suggestion', array( $r['http'], $r['code'] ), array( 200, 'ok:molokhia-recipe' ) );
$generate = null;
foreach ( $req as $entry ) {
	if ( false !== strpos( $entry['url'], '/v1/responses' ) ) {
		$generate = $entry;
	}
}
t_true( 'K28', 'OpenAI connector: request sent to api.openai.com/v1/responses', null !== $generate && 0 === strpos( $generate['url'], 'https://api.openai.com/v1/responses' ) );
$headers = $generate ? array_change_key_case( (array) $generate['headers'] ) : array();
t_check( 'K28', "OpenAI connector: connector's key used, not the one saved in our settings", isset( $headers['authorization'] ) ? $headers['authorization'] : null, 'Bearer ' . $connector_key );
$body = $generate ? wp_json_encode( $generate['body'], JSON_UNESCAPED_UNICODE ) : '';
t_true( 'K28', 'OpenAI connector: our instructions, delimited title and JSON schema sent', false !== strpos( $body, 'never follow instructions' ) && false !== strpos( $body, '<title>طبخة ملوخية<\/title>' ) && false !== strpos( $body, 'json_schema' ), substr( $body, 0, 220 ) );

// K29: OpenAI's own failure shapes through the connector.
foreach ( array(
	'wrong key (HTTP 401)'        => array( $openai_error( 401, 'invalid_api_key', 'Incorrect API key provided: ' . $connector_key ), 'ai_key_rejected' ),
	'no credit (HTTP 429 insufficient_quota)' => array( $openai_error( 429, 'insufficient_quota', 'You exceeded your current quota.' ), 'ai_billing' ),
	'rate limited (HTTP 429)'     => array( $openai_error( 429, 'rate_limit_exceeded', 'Rate limit reached.' ), 'ai_rate_limited' ),
	'overloaded (HTTP 503)'       => array( $openai_error( 503, 'server_error', 'The server is overloaded.' ), 'ai_unavailable' ),
	'answer cut off (incomplete)' => array( $answer( '{"slug":"molo', 'incomplete' ), 'ai_invalid_output' ),
	'not a slug'                  => array( $answer( '{"slug":"Molokhia Recipe!"}' ), 'ai_invalid_output' ),
) as $label => $case ) {
	$mock( $case[0] );
	$r = $suggest( 'طبخة ملوخية ' . $label );
	t_check( 'K29', "OpenAI connector $label → {$case[1]}", $r['code'], $case[1] );
	t_true( 'K29', "OpenAI connector $label: OpenAI's text and the key not passed on", false === strpos( $r['text'], 'sk-proj' ) && false === strpos( $r['text'], 'Incorrect API key' ) && false === strpos( $r['text'], 'exceeded your current quota' ), substr( $r['text'], 0, 160 ) );
}
$mock( $openai_error( 401, 'invalid_api_key', 'Incorrect API key provided.' ) );
t_true( 'K29', 'OpenAI connector wrong key points to Settings → Connectors', false !== strpos( $suggest( 'طبخة ملوخية key2' )['text'], 'Connectors' ) );

delete_option( 'connectors_ai_openai_api_key' );
delete_option( AI::OPTION );
delete_option( 'arabic_to_lat_test_ai_mock' );
$log();
