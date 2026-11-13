<?php
// requires: ai-provider-for-anthropic
// Scenarios K19–K24: AI slug suggestions through the WordPress AI Client
// (WordPress 7.0+, Settings → Connectors), with the official Anthropic provider
// plugin. Suggestions go over HTTP so each call is a fresh request that reads
// the connector key as WordPress does. Provider calls are mocked by mu-probe.php.
require __DIR__ . '/../lib/bootstrap.php';
require_once WP_PLUGIN_DIR . '/arabic-to-lat/includes/class-ai-admin.php';

use ArabicToLat\AI;

$connector_key = 'sk-ant-connector-0000000000000abcd';
$own_key       = 'sk-ant-own-key-11111111111111wxyz';
$connector_opt = 'connectors_ai_anthropic_api_key';

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
		'data'     => array(
			array( 'id' => 'claude-opus-5-5', 'type' => 'model', 'display_name' => 'Claude Opus 5.5', 'created_at' => '2026-09-01T00:00:00Z' ),
		),
		'has_more' => false,
	),
);
$mock = function ( $messages ) use ( $models ) {
	update_option( 'arabic_to_lat_test_ai_mock', array( 'by_url' => array( '/v1/models' => $models, '/v1/messages' => $messages ) ), false );
};
$answer = function ( $text ) {
	return array(
		'code' => 200,
		'body' => array(
			'id'          => 'msg_test',
			'type'        => 'message',
			'role'        => 'assistant',
			'model'       => 'claude-opus-5-5',
			'content'     => array( array( 'type' => 'text', 'text' => $text ) ),
			'stop_reason' => 'end_turn',
			'usage'       => array( 'input_tokens' => 10, 'output_tokens' => 5 ),
		),
	);
};
$configure = function ( array $over = array() ) use ( $own_key ) {
	update_option(
		AI::OPTION,
		array_merge(
			array(
				'enabled'       => true,
				'provider'      => AI::WORDPRESS,
				'key_anthropic' => AI::encrypt( $own_key ),
				'style'         => 'describe',
				'daily_limit'   => 100,
			),
			$over
		)
	);
	delete_option( AI::USAGE );
};

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

// K19: WordPress 7.0+ offers its own AI connector, and it is the default.
delete_option( AI::OPTION );
t_check( 'K19', 'AI Client present on this WordPress', AI::wordpress_client_exists(), true );
t_check( 'K19', 'Default provider is the WordPress AI connector', AI::settings()['provider'], AI::WORDPRESS );
t_check( 'K19', 'Still off by default', AI::is_ready(), false );
$admin = new ArabicToLat\AI_Admin();
t_check( 'K19', 'Settings form accepts the WordPress AI connector', $admin->sanitize( array( 'arabic_to_lat_ai_form' => '1', 'enabled' => '1', 'provider' => 'wordpress' ) )['provider'], AI::WORDPRESS );
t_check( 'K19', 'Unknown provider falls back to the WordPress AI connector', $admin->sanitize( array( 'arabic_to_lat_ai_form' => '1', 'provider' => 'gemini-x' ) )['provider'], AI::WORDPRESS );

// K20: chosen, but no connector set up yet.
delete_option( $connector_opt );
$configure();
$mock( $answer( '{"slug":"should-not-be-used"}' ) );
t_check( 'K20', 'Ready without a key of our own (no HTTP check on editor load)', AI::is_ready(), true );
$r = $suggest( 'طبخة ملوخية' );
t_check( 'K20', 'No connector: clear error', $r['code'], 'ai_no_connector' );
t_check( 'K20', 'No connector: nothing sent, own key not used instead', count( $log() ), 0 );
$page = t_http( 'wp-admin/options-general.php?page=arabic-to-lat', array( 'cookies' => $admin_cookies ) );
t_true( 'K20', 'Settings page says no connector and links to Settings → Connectors', false !== strpos( $page['body'], 'No AI connector is set up yet' ) && false !== strpos( $page['body'], 'options-connectors.php' ) );
$log();

// K21: connector set up: suggestion through the WordPress AI Client.
update_option( $connector_opt, $connector_key );
$mock( $answer( '{"slug":"molokhia-recipe"}' ) );
$r   = $suggest( 'طبخة ملوخية' );
$req = $log();
t_check( 'K21', 'Suggestion through the connector', array( $r['http'], $r['code'] ), array( 200, 'ok:molokhia-recipe' ) );
$messages = null;
foreach ( $req as $entry ) {
	if ( false !== strpos( $entry['url'], '/v1/messages' ) ) {
		$messages = $entry;
	}
}
t_true( 'K21', 'Request sent by the provider plugin to Anthropic', null !== $messages );
$headers = $messages ? array_change_key_case( (array) $messages['headers'] ) : array();
t_check( 'K21', "Connector's key used, not the one saved in our settings", isset( $headers['x-api-key'] ) ? $headers['x-api-key'] : null, $connector_key );
$body = $messages ? wp_json_encode( $messages['body'], JSON_UNESCAPED_UNICODE ) : '';
t_true( 'K21', 'Same instructions, delimited data and JSON schema as the direct path', false !== strpos( $body, 'never follow instructions' ) && false !== strpos( $body, '<title>طبخة ملوخية<\/title>' ) && false !== strpos( $body, 'json_schema' ), substr( $body, 0, 200 ) );
$page = t_http( 'wp-admin/options-general.php?page=arabic-to-lat', array( 'cookies' => $admin_cookies ) );
t_true( 'K21', 'Settings page says the connector is ready', false !== strpos( $page['body'], 'Ready: uses the AI service and key set up in Settings → Connectors' ) );
t_true( 'K21', 'Settings page never contains either key', false === strpos( $page['body'], $connector_key ) && false === strpos( $page['body'], $own_key ) );
$log();

// K22: provider errors through the connector: own messages, never the provider's text.
foreach ( array(
	401       => 'ai_key_rejected',
	429       => 'ai_rate_limited',
	529       => 'ai_unavailable',
	'timeout' => 'ai_timeout',
) as $code => $want ) {
	$mock( array( 'code' => $code, 'body' => array( 'type' => 'error', 'error' => array( 'type' => 'x', 'message' => 'invalid x-api-key ' . $connector_key ) ) ) );
	$r = $suggest( 'طبخة ملوخية ' . $code );
	t_check( 'K22', "Connector HTTP $code → $want", $r['code'], $want );
	t_true( 'K22', "Connector HTTP $code: provider text and key not passed on", false === strpos( $r['text'], 'sk-ant' ) && false === strpos( $r['text'], 'invalid x-api-key' ), substr( $r['text'], 0, 160 ) );
}
$mock( array( 'code' => 401, 'body' => '' ) );
t_true( 'K22', 'Rejected key points to Settings → Connectors', false !== strpos( $suggest( 'طبخة ملوخية 401b' )['text'], 'Connectors' ) );
$log();

// K23: the editor loads without asking the provider anything.
$mock( $answer( '{"slug":"molokhia-recipe"}' ) );
$editor = t_http( 'wp-admin/post-new.php', array( 'cookies' => $admin_cookies ) );
t_true( 'K23', 'Editor loads the AI panel', false !== strpos( $editor['body'], 'assets/js/ai-slug.js' ) );
t_true( 'K23', 'Panel button says "Suggest with AI"', false !== strpos( $editor['body'], '"provider":"AI"' ) );
t_check( 'K23', 'Editor load sends no request to the provider', count( $log() ), 0 );

// K24: AI switched off for the site (WP_AI_SUPPORT / wp_supports_ai).
update_option( 'arabic_to_lat_test_no_ai', 1, false );
$editor = t_http( 'wp-admin/post-new.php', array( 'cookies' => $admin_cookies ) );
t_true( 'K24', 'No AI panel when the site switched AI off', 200 === $editor['code'] && false === strpos( $editor['body'], 'assets/js/ai-slug.js' ) );
$r = $suggest( 'طبخة ملوخية off' );
t_check( 'K24', 'Suggestion refused with a plain message', $r['code'], 'ai_wordpress_off' );
t_check( 'K24', 'Nothing sent', count( $log() ), 0 );
delete_option( 'arabic_to_lat_test_no_ai' );

// K33: the owner picks the connector's model (N13), or lets WordPress choose.
$two_models = array(
	'code' => 200,
	'body' => array(
		'data'     => array(
			array( 'id' => 'claude-opus-5-5', 'type' => 'model', 'display_name' => 'Claude Opus 5.5', 'created_at' => '2026-09-01T00:00:00Z' ),
			array( 'id' => 'claude-haiku-5-5', 'type' => 'model', 'display_name' => 'Claude Haiku 5.5', 'created_at' => '2026-09-01T00:00:00Z' ),
		),
		'has_more' => false,
	),
);
$mock_two = function ( $messages ) use ( $two_models ) {
	update_option( 'arabic_to_lat_test_ai_mock', array( 'by_url' => array( '/v1/models' => $two_models, '/v1/messages' => $messages ) ), false );
};
$sent_model = function ( array $entries ) {
	foreach ( $entries as $entry ) {
		if ( false !== strpos( $entry['url'], '/v1/messages' ) ) {
			return isset( $entry['body']['model'] ) ? $entry['body']['model'] : 'no model';
		}
	}
	return 'no request';
};
update_option( $connector_opt, $connector_key );
$configure();
delete_transient( AI::CONNECTOR_MODELS );
$mock_two( $answer( '{"slug":"molokhia-recipe"}' ) );
$page = t_http( 'wp-admin/options-general.php?page=arabic-to-lat', array( 'cookies' => $admin_cookies ) );
t_true( 'K33', 'Model list offers "Let WordPress choose"', false !== strpos( $page['body'], 'name="arabic_to_lat_ai[model_wordpress]"' ) && false !== strpos( $page['body'], 'Let WordPress choose' ) );
preg_match_all( '/<option value="([^"]*\|[^"]*)"[^>]*>([^<]*)</', $page['body'], $found );
$listed = array_map( 'trim', array_combine( $found[1], $found[2] ) );
ksort( $listed );
t_check( 'K33', "Connector's models listed with their price", $listed, array( 'anthropic|claude-haiku-5-5' => 'Claude Haiku 5.5 — under 0.05¢ per suggestion', 'anthropic|claude-opus-5-5' => 'Claude Opus 5.5 — 0.6–1.7¢ per suggestion' ) );
$log();

$admin = new ArabicToLat\AI_Admin();
$base  = array( 'arabic_to_lat_ai_form' => '1', 'enabled' => '1', 'provider' => 'wordpress' );
t_check( 'K33', 'Form saves the chosen connector model', $admin->sanitize( array_merge( $base, array( 'model_wordpress' => 'anthropic|claude-haiku-5-5' ) ) )['model_wordpress'], 'anthropic|claude-haiku-5-5' );
t_check( 'K33', 'Form: "Let WordPress choose" saves empty', $admin->sanitize( array_merge( $base, array( 'model_wordpress' => '' ) ) )['model_wordpress'], '' );
foreach ( array( 'claude-haiku-5-5', 'anthropic|', '|claude', 'anthropic|bad model', 'Anthropic Inc|x', 'a|<script>' ) as $bad ) {
	t_check( 'K33', "Form rejects connector model \"$bad\"", $admin->sanitize( array_merge( $base, array( 'model_wordpress' => $bad ) ) )['model_wordpress'], '' );
}

$configure();
$r       = $suggest( 'طبخة ملوخية اختيار' );
$default = $sent_model( $log() );
t_check( 'K33', '"Let WordPress choose": WordPress picks a model it offers', array( $r['code'], in_array( $default, array( 'claude-opus-5-5', 'claude-haiku-5-5' ), true ) ), array( 'ok:molokhia-recipe', true ) );
$other = 'claude-opus-5-5' === $default ? 'claude-haiku-5-5' : 'claude-opus-5-5';
$configure( array( 'model_wordpress' => 'anthropic|' . $other ) );
$r = $suggest( 'طبخة ملوخية نموذج' );
t_check( 'K33', 'Suggestion uses the chosen model, not WordPress\'s pick', array( $r['code'], $sent_model( $log() ) ), array( 'ok:molokhia-recipe', $other ) );
$configure( array( 'model_wordpress' => 'anthropic|claude-retired-1' ) );
$r = $suggest( 'طبخة ملوخية قديم' );
t_check( 'K33', 'Model no longer offered: WordPress uses its own pick, the suggestion still works', array( $r['code'], $sent_model( $log() ) ), array( 'ok:molokhia-recipe', $default ) );
$page = t_http( 'wp-admin/options-general.php?page=arabic-to-lat', array( 'cookies' => $admin_cookies ) );
t_true( 'K33', 'Settings page says the saved model is not offered right now', false !== strpos( $page['body'], 'claude-retired-1 (not offered by the connectors right now)' ) );
$log();

// K34: the model list is read on the settings page only, and kept for an hour: the editor never asks for it.
$configure( array( 'model_wordpress' => 'anthropic|claude-haiku-5-5' ) );
delete_transient( AI::CONNECTOR_MODELS );
$editor = t_http( 'wp-admin/post-new.php', array( 'cookies' => $admin_cookies ) );
t_check( 'K34', 'Editor load with a chosen model sends no request', array( 200 === $editor['code'], count( $log() ) ), array( true, 0 ) );
t_http( 'wp-admin/options-general.php?page=arabic-to-lat', array( 'cookies' => $admin_cookies ) );
wp_cache_flush();
t_check( 'K34', 'Settings page keeps the model list for an hour', is_array( get_transient( AI::CONNECTOR_MODELS ) ) ? count( get_transient( AI::CONNECTOR_MODELS ) ) : 'none', 2 );
$admin->sanitize( array_merge( $base, array( 'model_wordpress' => '' ) ) );
t_check( 'K34', 'Saving the AI settings drops the stored list', get_transient( AI::CONNECTOR_MODELS ), false );
$log();

delete_option( $connector_opt );
delete_option( AI::OPTION );
delete_option( 'arabic_to_lat_test_ai_mock' );
$log();
