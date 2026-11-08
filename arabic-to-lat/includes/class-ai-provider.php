<?php
/**
 * HTTP calls to the AI providers.
 *
 * @package ArabicToLat
 */

namespace ArabicToLat;

defined( 'ABSPATH' ) || exit;

/**
 * Sends the prompt through the WordPress AI Client (7.0+, Settings →
 * Connectors), or builds the request for Claude (Anthropic Messages API) or
 * ChatGPT (OpenAI Responses API) and sends it with the WordPress HTTP API.
 * Returns the model's JSON text, or a WP_Error with a plain message. Provider
 * error text is never passed on: some providers echo part of the key in it.
 */
final class AI_Provider {

	const ANTHROPIC_URL     = 'https://api.anthropic.com/v1/messages';
	const ANTHROPIC_VERSION = '2026-10-21';
	const OPENAI_URL        = 'https://api.openai.com/v1/responses';
	const TIMEOUT           = 25;
	const MAX_TOKENS        = 2048;

	/**
	 * The JSON the model must return.
	 */
	public static function schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'slug' => array(
					'type'        => 'string',
					'description' => 'URL slug: lowercase a-z, digits, single hyphens, 2-6 words.',
				),
			),
			'required'             => array( 'slug' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * @param string $provider anthropic|openai.
	 * @param string $key      API key.
	 * @param string $model    Model ID.
	 * @param string $system   Instructions.
	 * @param string $user     Delimited title and text.
	 * @return string|\WP_Error JSON text from the model.
	 */
	public static function request( $provider, $key, $model, $system, $user ) {
		$request = 'openai' === $provider ? self::openai_request( $key, $model, $system, $user ) : self::anthropic_request( $key, $model, $system, $user );

		$response = wp_remote_post(
			$request['url'],
			array(
				'timeout' => self::TIMEOUT,
				'headers' => $request['headers'],
				'body'    => wp_json_encode( $request['body'] ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$timeout = false !== stripos( $response->get_error_message(), 'timed out' ) || false !== stripos( $response->get_error_message(), 'timeout' );
			return $timeout
				? new \WP_Error( 'ai_timeout', __( 'The AI provider did not answer in time. Try again in a moment.', 'arabic-to-lat' ), array( 'status' => 504 ) )
				: new \WP_Error( 'ai_network', __( 'This site could not reach the AI provider. Check that the server can make outgoing HTTPS requests.', 'arabic-to-lat' ), array( 'status' => 502 ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return self::http_error( $code );
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return self::invalid();
		}
		return 'openai' === $provider ? self::openai_text( $data ) : self::anthropic_text( $data );
	}

	/**
	 * WordPress AI Client: the connector set up in Settings → Connectors picks
	 * the provider and model. Same prompt, schema and validation as the direct
	 * path. Core's error messages include the provider's text, so only the
	 * error code and HTTP status are used.
	 *
	 * @param string $system Instructions.
	 * @param string $user   Delimited title and text.
	 * @return string|\WP_Error JSON text from the model.
	 */
	public static function wordpress_request( $system, $user ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new \WP_Error( 'ai_no_wordpress_client', __( 'This WordPress version has no AI connectors. Choose Claude or ChatGPT with your own key in Settings → Arabic to Latin.', 'arabic-to-lat' ), array( 'status' => 400 ) );
		}
		$text = wp_ai_client_prompt( $user )
			->using_system_instruction( $system )
			->using_max_tokens( self::MAX_TOKENS )
			->as_json_response( self::schema() )
			->generate_text();

		if ( ! is_wp_error( $text ) ) {
			return is_string( $text ) && '' !== $text ? $text : self::invalid();
		}

		$code   = $text->get_error_code();
		$data   = $text->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;

		switch ( $code ) {
			case 'prompt_prevented':
				return new \WP_Error( 'ai_wordpress_off', __( 'AI features are switched off on this site, so no suggestion can be made.', 'arabic-to-lat' ), array( 'status' => 503 ) );
			case 'prompt_invalid_argument':
				return new \WP_Error( 'ai_no_connector', __( 'No AI connector is set up. Add one in Settings → Connectors, or choose Claude or ChatGPT with your own key in Settings → Arabic to Latin.', 'arabic-to-lat' ), array( 'status' => 400 ) );
			case 'prompt_network_error':
				return false !== stripos( $text->get_error_message(), 'timed out' )
					? new \WP_Error( 'ai_timeout', __( 'The AI provider did not answer in time. Try again in a moment.', 'arabic-to-lat' ), array( 'status' => 504 ) )
					: new \WP_Error( 'ai_network', __( 'This site could not reach the AI provider. Check that the server can make outgoing HTTPS requests.', 'arabic-to-lat' ), array( 'status' => 502 ) );
		}
		if ( 401 === $status || 403 === $status ) {
			return new \WP_Error( 'ai_key_rejected', __( 'The AI provider rejected the API key. Check it in Settings → Connectors.', 'arabic-to-lat' ), array( 'status' => 502 ) );
		}
		if ( $status >= 400 ) {
			return self::http_error( $status );
		}
		return new \WP_Error( 'ai_request', __( 'The AI provider did not accept the request.', 'arabic-to-lat' ), array( 'status' => 502 ) );
	}

	/**
	 * Claude: Messages API with JSON-schema output and low effort (a slug needs
	 * little reasoning). Opus 5.5 and Sonnet 5.5 get server-side fallbacks, so a
	 * safety decline is retried on a recommended model instead of failing.
	 */
	public static function anthropic_request( $key, $model, $system, $user ) {
		$headers = array(
			'Content-Type'      => 'application/json',
			'x-api-key'         => $key,
			'anthropic-version' => self::ANTHROPIC_VERSION,
		);
		$body    = array(
			'model'         => $model,
			'max_tokens'    => self::MAX_TOKENS,
			'system'        => $system,
			'messages'      => array(
				array(
					'role'    => 'user',
					'content' => $user,
				),
			),
			'output_config' => array(
				'effort' => 'low',
				'format' => array(
					'type'   => 'json_schema',
					'schema' => self::schema(),
				),
			),
		);
		if ( in_array( $model, array( 'claude-opus-5-5', 'claude-sonnet-5-5' ), true ) ) {
			$headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
			$body['fallbacks']         = 'default';
		}
		return array(
			'url'     => self::ANTHROPIC_URL,
			'headers' => $headers,
			'body'    => $body,
		);
	}

	/**
	 * @param array $data Decoded response.
	 * @return string|\WP_Error
	 */
	public static function anthropic_text( array $data ) {
		$stop = isset( $data['stop_reason'] ) ? (string) $data['stop_reason'] : '';
		if ( 'refusal' === $stop ) {
			return self::refused();
		}
		if ( 'max_tokens' === $stop ) {
			return self::cut_off();
		}
		$text = '';
		foreach ( isset( $data['content'] ) && is_array( $data['content'] ) ? $data['content'] : array() as $block ) {
			if ( is_array( $block ) && isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
				$text .= (string) $block['text'];
			}
		}
		return '' === $text ? self::invalid() : $text;
	}

	/**
	 * ChatGPT: Responses API with a strict JSON schema.
	 */
	public static function openai_request( $key, $model, $system, $user ) {
		return array(
			'url'     => self::OPENAI_URL,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $key,
			),
			'body'    => array(
				'model'             => $model,
				'input'             => array(
					array(
						'role'    => 'system',
						'content' => $system,
					),
					array(
						'role'    => 'user',
						'content' => $user,
					),
				),
				'max_output_tokens' => self::MAX_TOKENS,
				'text'              => array(
					'format' => array(
						'type'   => 'json_schema',
						'name'   => 'url_slug',
						'schema' => self::schema(),
						'strict' => true,
					),
				),
			),
		);
	}

	/**
	 * @param array $data Decoded response.
	 * @return string|\WP_Error
	 */
	public static function openai_text( array $data ) {
		if ( isset( $data['status'] ) && 'incomplete' === $data['status'] ) {
			return self::cut_off();
		}
		$text = '';
		foreach ( isset( $data['output'] ) && is_array( $data['output'] ) ? $data['output'] : array() as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['type'] ) || 'message' !== $item['type'] || empty( $item['content'] ) || ! is_array( $item['content'] ) ) {
				continue;
			}
			foreach ( $item['content'] as $part ) {
				if ( ! is_array( $part ) || ! isset( $part['type'] ) ) {
					continue;
				}
				if ( 'refusal' === $part['type'] ) {
					return self::refused();
				}
				if ( 'output_text' === $part['type'] && isset( $part['text'] ) ) {
					$text .= (string) $part['text'];
				}
			}
		}
		return '' === $text ? self::invalid() : $text;
	}

	/**
	 * @param int $code HTTP status other than 200.
	 */
	private static function http_error( $code ) {
		switch ( true ) {
			case 401 === $code || 403 === $code:
				return new \WP_Error( 'ai_key_rejected', __( 'The AI provider rejected the API key. Check it in Settings → Arabic to Latin.', 'arabic-to-lat' ), array( 'status' => 502 ) );
			case 402 === $code:
				return new \WP_Error( 'ai_billing', __( 'The AI provider account has no credit or a billing problem.', 'arabic-to-lat' ), array( 'status' => 502 ) );
			case 404 === $code:
				return new \WP_Error( 'ai_model', __( 'The chosen model is not available to this API key. Choose another model in Settings → Arabic to Latin.', 'arabic-to-lat' ), array( 'status' => 502 ) );
			case 429 === $code:
				return new \WP_Error( 'ai_rate_limited', __( 'The AI provider is limiting requests from this key. Wait a minute and try again.', 'arabic-to-lat' ), array( 'status' => 429 ) );
			case $code >= 500:
				return new \WP_Error( 'ai_unavailable', __( 'The AI provider is busy or unavailable right now. Try again later.', 'arabic-to-lat' ), array( 'status' => 503 ) );
			default:
				return new \WP_Error( 'ai_request', __( 'The AI provider did not accept the request.', 'arabic-to-lat' ), array( 'status' => 502 ) );
		}
	}

	private static function refused() {
		return new \WP_Error( 'ai_refused', __( 'The AI provider declined to suggest a slug for this post. Keep the transliterated slug or write one yourself.', 'arabic-to-lat' ), array( 'status' => 422 ) );
	}

	private static function cut_off() {
		return new \WP_Error( 'ai_incomplete', __( 'The AI answer was cut off. Try again.', 'arabic-to-lat' ), array( 'status' => 502 ) );
	}

	private static function invalid() {
		return new \WP_Error( 'ai_invalid_output', __( 'The AI returned something that is not a usable slug. Try again, or keep the transliterated slug.', 'arabic-to-lat' ), array( 'status' => 502 ) );
	}
}
