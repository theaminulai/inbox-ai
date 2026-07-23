<?php
/**
 * Contract every AI provider (OpenAI, Anthropic, Google, ...) implements.
 *
 * @package CF7AIInbox\Interfaces
 */

namespace CF7AIInbox\Interfaces;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;

/**
 * Interface AIProviderInterface
 *
 * Covers what the Settings page's AI Provider tab needs (credential
 * validation, model listing). The submission-analysis half of this
 * contract (`analyze()`, working against `AnalysisRequest`/`AnalysisResult`
 * value objects) belongs to the AI Inbox List page's build — see
 * docs/plans/02-ai-inbox-list-plan.md, section 3.2 — and will extend this
 * interface once that phase starts, rather than being guessed at here.
 */
interface AIProviderInterface {

	/**
	 * Stable machine id, e.g. `openai`.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Human-readable label, e.g. `OpenAI`.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Verifies that an API key is accepted by the provider.
	 *
	 * Never persists anything — callers decide whether/when to store the
	 * key. Implementations must never log the key.
	 *
	 * @param string $api_key Candidate API key (may be a not-yet-saved value).
	 *
	 * @return true|WP_Error True on success, a WP_Error with a
	 *                       user-safe message on failure.
	 */
	public function validate_credentials( string $api_key );

	/**
	 * Lists the models available to this API key.
	 *
	 * @param string $api_key API key to authenticate with.
	 *
	 * @return string[]|WP_Error List of model ids, or a WP_Error on failure.
	 */
	public function get_models( string $api_key );
}
