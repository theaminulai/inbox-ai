<?php
/**
 * Google (Gemini) provider — credential validation and model listing.
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
 * Class GeminiProvider
 *
 * Third provider wired, once OpenAI's path is proven (see
 * docs/plans/05-settings-plan.md, section 8, step 9).
 */
final class GeminiProvider implements AIProviderInterface {

	private const MODELS_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models';
	private const API_BASE        = 'https://generativelanguage.googleapis.com/v1beta/models';

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'google';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return 'Google';
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

		foreach ( (array) ( $body['models'] ?? array() ) as $model ) {
			if ( isset( $model['name'] ) ) {
				$ids[] = str_replace( 'models/', '', (string) $model['name'] );
			}
		}

		sort( $ids );

		return array() !== $ids ? $ids : array( 'gemini-2.5-flash', 'gemini-2.5-pro' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function analyze( string $api_key, string $model, string $system_prompt, string $user_prompt, int $timeout = 45 ) {
		if ( '' === trim( $api_key ) ) {
			return new WP_Error( 'inboxai_missing_key', __( 'No API key has been configured.', 'inbox-ai' ) );
		}

		$payload = array(
			'contents' => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $user_prompt ) ),
				),
			),
		);

		if ( '' !== trim( $system_prompt ) ) {
			$payload['systemInstruction'] = array(
				'parts' => array( array( 'text' => $system_prompt ) ),
			);
		}

		$response = wp_remote_post(
			self::API_BASE . '/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key ),
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => $timeout,
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'inboxai_provider_unreachable', __( 'Could not reach Google. Please try again.', 'inbox-ai' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body = is_array( $body ) ? $body : array();

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'inboxai_provider_error', $this->error_message( $body, $code ) );
		}

		$content = (string) ( $body['candidates'][0]['content']['parts'][0]['text'] ?? '' );

		if ( '' === trim( $content ) ) {
			return new WP_Error( 'inboxai_empty_response', __( 'Google returned an empty response.', 'inbox-ai' ) );
		}

		return array(
			'content'           => trim( $content ),
			'prompt_tokens'     => (int) ( $body['usageMetadata']['promptTokenCount'] ?? 0 ),
			'completion_tokens' => (int) ( $body['usageMetadata']['candidatesTokenCount'] ?? 0 ),
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
			__( 'Google returned an unexpected error (HTTP %d).', 'inbox-ai' ),
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
			add_query_arg( 'key', rawurlencode( $api_key ), self::MODELS_ENDPOINT ),
			array( 'timeout' => 15 )
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'inboxai_provider_unreachable', __( 'Could not reach Google. Please try again.', 'inbox-ai' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'inboxai_invalid_key', __( 'That API key was rejected by Google.', 'inbox-ai' ) );
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'inboxai_provider_error', __( 'Google returned an unexpected error. Please try again.', 'inbox-ai' ) );
		}

		return $response;
	}
}
