<?php
/**
 * Google (Gemini) provider — credential validation and model listing.
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
 * Class GeminiProvider
 *
 * Third provider wired, once OpenAI's path is proven (see
 * docs/plans/05-settings-plan.md, section 8, step 9).
 */
final class GeminiProvider implements AIProviderInterface {

	private const MODELS_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models';

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
	 * @param string $api_key API key to authenticate with.
	 *
	 * @return array|WP_Error wp_remote_get()'s response array on success.
	 */
	private function request( string $api_key ) {
		if ( '' === trim( $api_key ) ) {
			return new WP_Error( 'cf7ai_missing_key', __( 'Enter an API key first.', 'cf7-ai-inbox' ) );
		}

		$response = wp_remote_get(
			add_query_arg( 'key', rawurlencode( $api_key ), self::MODELS_ENDPOINT ),
			array( 'timeout' => 15 )
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'cf7ai_provider_unreachable', __( 'Could not reach Google. Please try again.', 'cf7-ai-inbox' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'cf7ai_invalid_key', __( 'That API key was rejected by Google.', 'cf7-ai-inbox' ) );
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'cf7ai_provider_error', __( 'Google returned an unexpected error. Please try again.', 'cf7-ai-inbox' ) );
		}

		return $response;
	}
}
