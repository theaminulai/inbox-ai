<?php
/**
 * OpenAI provider — credential validation and model listing.
 *
 * @package CF7AIInbox\AI
 */

namespace CF7AIInbox\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIInbox\Interfaces\AIProviderInterface;
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
	 * Performs the shared authenticated request both public methods need.
	 *
	 * @param string $api_key API key to authenticate with.
	 *
	 * @return array|WP_Error wp_remote_get()'s response array on success.
	 */
	private function request( string $api_key ) {
		if ( '' === trim( $api_key ) ) {
			return new WP_Error( 'cf7ai_missing_key', __( 'Enter an API key first.', 'cf7-ai-inbox' ) );
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
			return new WP_Error( 'cf7ai_provider_unreachable', __( 'Could not reach OpenAI. Please try again.', 'cf7-ai-inbox' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'cf7ai_invalid_key', __( 'That API key was rejected by OpenAI.', 'cf7-ai-inbox' ) );
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'cf7ai_provider_error', __( 'OpenAI returned an unexpected error. Please try again.', 'cf7-ai-inbox' ) );
		}

		return $response;
	}
}
