<?php
/**
 * Anthropic provider — credential validation and model listing.
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
 * Class AnthropicProvider
 *
 * Second provider wired, once OpenAI's path is proven (see
 * docs/plans/05-settings-plan.md, section 8, step 9 — that step is written
 * for the AI Inbox List page's `analyze()` half; this class only needs to
 * satisfy the Settings page's configuration half).
 */
final class AnthropicProvider implements AIProviderInterface {

	private const MODELS_ENDPOINT = 'https://api.anthropic.com/v1/models';
	private const API_VERSION     = '2023-06-01';

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
					'x-api-key'         => $api_key,
					'anthropic-version' => self::API_VERSION,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'cf7ai_provider_unreachable', __( 'Could not reach Anthropic. Please try again.', 'cf7-ai-inbox' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'cf7ai_invalid_key', __( 'That API key was rejected by Anthropic.', 'cf7-ai-inbox' ) );
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'cf7ai_provider_error', __( 'Anthropic returned an unexpected error. Please try again.', 'cf7-ai-inbox' ) );
		}

		return $response;
	}
}
