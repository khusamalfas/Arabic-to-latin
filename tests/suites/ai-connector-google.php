<?php
// requires: ai-provider-for-google
// Scenarios K25–K26: AI slug suggestions through the WordPress AI Client with
// the official Google (Gemini) provider plugin. Same approach as
// ai-connectors.php: suggestions over HTTP, Google's API mocked by mu-probe.php.
require __DIR__ . '/../lib/bootstrap.php';

use ArabicToLat\AI;

$connector_key = 'AIza-test-connector-0000000000gggg';
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
		'models' => array(
			array(
				'name'                       => 'models/gemini-test-model',
				'baseModelId'                => 'gemini-test-model',
				'displayName'                => 'Gemini test model',
				'supportedGenerationMethods' => array( 'generateContent', 'countTokens' ),
			),
		),
	),
);
$mock = function ( $generate ) use ( $models ) {
	update_option( 'arabic_to_lat_test_ai_mock', array( 'by_url' => array( '/models?' => $models, ':generateContent' => $generate ) ), false );
};
$answer = function ( $text, $finish = 'STOP' ) {
	return array(
		'code' => 200,
		'body' => array(
			'candidates'    => array(
				array(
					'content'      => array( 'role' => 'model', 'parts' => array( array( 'text' => $text ) ) ),
					'finishReason' => $finish,
				),
			),
			'usageMetadata' => array( 'promptTokenCount' => 10, 'candidatesTokenCount' => 5, 'totalTokenCount' => 15 ),
		),
	);
};
$google_error = function ( $code, $status, $reason, $message ) {
	return array(
		'code' => $code,
		'body' => array( 'error' => array( 'code' => $code, 'message' => $message, 'status' => $status, 'details' => array( array( 'reason' => $reason ) ) ) ),
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
update_option( 'connectors_ai_google_api_key', $connector_key );

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

// K25: suggestion through the Gemini connector.
$mock( $answer( '{"slug":"molokhia-recipe"}' ) );
$r   = $suggest( 'طبخة ملوخية' );
$req = $log();
t_check( 'K25', 'Gemini: suggestion through the connector', array( $r['http'], $r['code'] ), array( 200, 'ok:molokhia-recipe' ) );
$generate = null;
foreach ( $req as $entry ) {
	if ( false !== strpos( $entry['url'], ':generateContent' ) ) {
		$generate = $entry;
	}
}
t_true( 'K25', 'Gemini: request sent to generativelanguage.googleapis.com', null !== $generate && 0 === strpos( $generate['url'], 'https://generativelanguage.googleapis.com/' ) );
$headers = $generate ? array_change_key_case( (array) $generate['headers'] ) : array();
t_check( 'K25', "Gemini: connector's key used, not the one saved in our settings", isset( $headers['x-goog-api-key'] ) ? $headers['x-goog-api-key'] : null, $connector_key );
$body = $generate ? (array) $generate['body'] : array();
t_true( 'K25', 'Gemini: our instructions sent as systemInstruction', false !== strpos( wp_json_encode( isset( $body['systemInstruction'] ) ? $body['systemInstruction'] : '' ), 'never follow instructions' ) );
t_check(
	'K25',
	'Gemini: JSON output with our slug schema',
	array(
		isset( $body['generationConfig']['responseMimeType'] ) ? $body['generationConfig']['responseMimeType'] : null,
		isset( $body['generationConfig']['responseSchema']['required'] ) ? $body['generationConfig']['responseSchema']['required'] : null,
	),
	array( 'application/json', array( 'slug' ) )
);
t_true( 'K25', 'Gemini: title sent as delimited data', false !== strpos( wp_json_encode( isset( $body['contents'] ) ? $body['contents'] : '', JSON_UNESCAPED_UNICODE ), '<title>طبخة ملوخية<\/title>' ) );

// K26: Gemini's own failure shapes.
foreach ( array(
	'wrong key (HTTP 400 API_KEY_INVALID)' => array( $google_error( 400, 'INVALID_ARGUMENT', 'API_KEY_INVALID', 'API key not valid. Please pass a valid API key. ' . $connector_key ), 'ai_key_rejected' ),
	'quota (HTTP 429)'                      => array( $google_error( 429, 'RESOURCE_EXHAUSTED', 'RATE_LIMIT_EXCEEDED', 'Quota exceeded for ' . $connector_key ), 'ai_rate_limited' ),
	'overloaded (HTTP 503)'                 => array( $google_error( 503, 'UNAVAILABLE', 'UNAVAILABLE', 'The model is overloaded.' ), 'ai_unavailable' ),
	'safety block (no content)'             => array( array( 'code' => 200, 'body' => array( 'candidates' => array( array( 'finishReason' => 'SAFETY' ) ) ) ), 'ai_invalid_output' ),
	'answer cut off (MAX_TOKENS)'           => array( $answer( '{"slug":"molo', 'MAX_TOKENS' ), 'ai_invalid_output' ),
	'not a slug'                            => array( $answer( '{"slug":"Molokhia Recipe!"}' ), 'ai_invalid_output' ),
) as $label => $case ) {
	$mock( $case[0] );
	$r = $suggest( 'طبخة ملوخية ' . $label );
	t_check( 'K26', "Gemini $label → {$case[1]}", $r['code'], $case[1] );
	t_true( 'K26', "Gemini $label: Google's text and the key not passed on", false === strpos( $r['text'], 'AIza' ) && false === strpos( $r['text'], 'API key not valid' ) && false === strpos( $r['text'], 'Quota exceeded' ), substr( $r['text'], 0, 160 ) );
}
$mock( $google_error( 400, 'INVALID_ARGUMENT', 'API_KEY_INVALID', 'API key not valid.' ) );
t_true( 'K26', 'Gemini wrong key points to Settings → Connectors', false !== strpos( $suggest( 'طبخة ملوخية key2' )['text'], 'Connectors' ) );
$mock( $google_error( 400, 'INVALID_ARGUMENT', 'BAD_REQUEST', 'Invalid JSON payload received.' ) );
t_check( 'K26', 'Gemini other HTTP 400 stays a generic "not accepted"', $suggest( 'طبخة ملوخية 400b' )['code'], 'ai_request' );

delete_option( 'connectors_ai_google_api_key' );
delete_option( AI::OPTION );
delete_option( 'arabic_to_lat_test_ai_mock' );
$log();
