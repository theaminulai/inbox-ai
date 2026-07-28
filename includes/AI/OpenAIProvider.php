<?php
/**
 * OpenAI provider — credential validation and model listing.
 *
 * @package InboxAI\AI
 */

namespace InboxAI\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\Interfaces\AIProviderInterface;
use WP_Error;

/**
 * Class OpenAIProvider
 *
 * The first provider fully wired (see docs/plans/05-settings-plan.md,
 * section 8, step 2) — Anthropic and Google follow the same shape once
 * this one is proven. `analyze()` (submission analysis) is out of scope
 * here; it lands with the AI Inbox List page's build.
 */
final class OpenAIProvider implements AIProviderInterface {

	private const MODELS_ENDPOINT = 'https://api.openai.com/v1/models';
	private const CHAT_ENDPOINT   = 'https://api.openai.com/v1/chat/completions';

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'openai';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return 'OpenAI';
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate_credentials( string $api_key ) {
		$response = $this->request( $api_key );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_models( string $api_key ) {
		$response = $this->request( $api_key );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$ids  = array();

		foreach ( (array) ( $body['data'] ?? array() ) as $model ) {
			if ( isset( $model['id'] ) && 0 === strpos( (string) $model['id'], 'gpt-' ) ) {
				$ids[] = (string) $model['id'];
			}
		}

		sort( $ids );

		return array() !== $ids ? $ids : array( 'gpt-4.1-mini', 'gpt-4.1', 'gpt-4o' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function analyze( string $api_key, string $model, string $system_prompt, string $user_prompt ) {
		if ( '' === trim( $api_key ) ) {
			return new WP_Error( 'inboxai_missing_key', __( 'No API key has been configured.', 'inbox-ai' ) );
		}

		$messages = array();

		if ( '' !== trim( $system_prompt ) ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $system_prompt,
			);
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $user_prompt,
		);

		$response = wp_remote_post(
			self::CHAT_ENDPOINT,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 45,
				'body'    => wp_json_encode(
					array(
						'model'       => $model,
						'messages'    => $messages,
						'temperature' => 0.3,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'inboxai_provider_unreachable', __( 'Could not reach OpenAI. Please try again.', 'inbox-ai' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body = is_array( $body ) ? $body : array();

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'inboxai_provider_error', $this->error_message( $body, $code ) );
		}

		$content = (string) ( $body['choices'][0]['message']['content'] ?? '' );

		if ( '' === trim( $content ) ) {
			return new WP_Error( 'inboxai_empty_response', __( 'OpenAI returned an empty response.', 'inbox-ai' ) );
		}

		return array(
			'content'           => trim( $content ),
			'prompt_tokens'     => (int) ( $body['usage']['prompt_tokens'] ?? 0 ),
			'completion_tokens' => (int) ( $body['usage']['completion_tokens'] ?? 0 ),
		);
	}

	/**
	 * Pulls a human-readable error message out of a decoded error response.
	 *
	 * @param array<string, mixed> $body Decoded JSON response body.
	 * @param int                  $code HTTP response status code.
	 *
	 * @return string
	 */
	private function error_message( array $body, int $code ): string {
		$message = $body['error']['message'] ?? null;

		if ( is_string( $message ) && '' !== $message ) {
			return $message;
		}

		return sprintf(
			/* translators: %d: HTTP status code */
			__( 'OpenAI returned an unexpected error (HTTP %d).', 'inbox-ai' ),
			$code
		);
	}

	/**
	 * Performs the shared authenticated request both public methods need.
	 *
	 * @param string $api_key API key to authenticate with.
	 *
	 * @return array|WP_Error wp_remote_get()'s response array on success.
	 */
	private function request( string $api_key ) {
		if ( '' === trim( $api_key ) ) {
			return new WP_Error( 'inboxai_missing_key', __( 'Enter an API key first.', 'inbox-ai' ) );
		}

		$response = wp_remote_get(
			self::MODELS_ENDPOINT,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'inboxai_provider_unreachable', __( 'Could not reach OpenAI. Please try again.', 'inbox-ai' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'inboxai_invalid_key', __( 'That API key was rejected by OpenAI.', 'inbox-ai' ) );
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'inboxai_provider_error', __( 'OpenAI returned an unexpected error. Please try again.', 'inbox-ai' ) );
		}

		return $response;
	}
}
