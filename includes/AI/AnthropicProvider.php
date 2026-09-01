<?php
/**
 * Anthropic provider — credential validation and model listing.
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
 * Class AnthropicProvider
 *
 * Second provider wired, once OpenAI's path is proven (see
 * docs/plans/05-settings-plan.md, section 8, step 9 — that step is written
 * for the AI Inbox List page's `analyze()` half; this class only needs to
 * satisfy the Settings page's configuration half).
 */
final class AnthropicProvider implements AIProviderInterface {

	private const MODELS_ENDPOINT   = 'https://api.anthropic.com/v1/models';
	private const MESSAGES_ENDPOINT = 'https://api.anthropic.com/v1/messages';
	private const API_VERSION       = '2023-06-01';
	private const MAX_TOKENS        = 1024;

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'anthropic';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return 'Anthropic';
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
			if ( isset( $model['id'] ) ) {
				$ids[] = (string) $model['id'];
			}
		}

		sort( $ids );

		return array() !== $ids ? $ids : array( 'claude-sonnet-4-5', 'claude-haiku-4-5' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function analyze( string $api_key, string $model, string $system_prompt, string $user_prompt, int $timeout = 45 ) {
		if ( '' === trim( $api_key ) ) {
			return new WP_Error( 'inboxai_missing_key', __( 'No API key has been configured.', 'inbox-ai' ) );
		}

		$payload = array(
			'model'      => $model,
			'max_tokens' => self::MAX_TOKENS,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $user_prompt,
				),
			),
		);

		if ( '' !== trim( $system_prompt ) ) {
			$payload['system'] = $system_prompt;
		}

		$response = wp_remote_post(
			self::MESSAGES_ENDPOINT,
			array(
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => self::API_VERSION,
					'Content-Type'      => 'application/json',
				),
				'timeout' => $timeout,
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'inboxai_provider_unreachable', __( 'Could not reach Anthropic. Please try again.', 'inbox-ai' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body = is_array( $body ) ? $body : array();

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'inboxai_provider_error', $this->error_message( $body, $code ) );
		}

		$content = (string) ( $body['content'][0]['text'] ?? '' );

		if ( '' === trim( $content ) ) {
			return new WP_Error( 'inboxai_empty_response', __( 'Anthropic returned an empty response.', 'inbox-ai' ) );
		}

		return array(
			'content'           => trim( $content ),
			'prompt_tokens'     => (int) ( $body['usage']['input_tokens'] ?? 0 ),
			'completion_tokens' => (int) ( $body['usage']['output_tokens'] ?? 0 ),
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
			__( 'Anthropic returned an unexpected error (HTTP %d).', 'inbox-ai' ),
			$code
		);
	}

	/**
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
					'x-api-key'         => $api_key,
					'anthropic-version' => self::API_VERSION,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'inboxai_provider_unreachable', __( 'Could not reach Anthropic. Please try again.', 'inbox-ai' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'inboxai_invalid_key', __( 'That API key was rejected by Anthropic.', 'inbox-ai' ) );
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'inboxai_provider_error', __( 'Anthropic returned an unexpected error. Please try again.', 'inbox-ai' ) );
		}

		return $response;
	}
}
