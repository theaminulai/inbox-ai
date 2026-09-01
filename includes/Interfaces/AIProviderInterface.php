<?php
/**
 * Contract every AI provider (OpenAI, Anthropic, Google, ...) implements.
 *
 * @package InboxAI\Interfaces
 */

namespace InboxAI\Interfaces;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;

/**
 * Interface AIProviderInterface
 *
 * Covers both what the Settings page's AI Provider tab needs (credential
 * validation, model listing) and what the AI Inbox List page's analysis
 * queue needs ({@see self::analyze()}) — see
 * docs/plans/02-ai-inbox-list-plan.md, section 3.2.
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

	/**
	 * Runs one AI generation call (submission analysis or reply drafting —
	 * both use this same generic method, with different prompts built by
	 * {@see \InboxAI\AI\PromptBuilder}).
	 *
	 * Implementations must never log the API key or the raw prompt content.
	 *
	 * @param string $api_key       API key to authenticate with.
	 * @param string $model         Model identifier to use.
	 * @param string $system_prompt System/instruction prompt. May be an empty
	 *                              string, in which case implementations omit
	 *                              the system role entirely rather than sending
	 *                              an empty one.
	 * @param string $user_prompt   User/content prompt.
	 * @param int    $timeout       HTTP timeout in seconds for this request —
	 *                              Settings → AI Provider → "Request timeout"
	 *                              (see {@see \InboxAI\Settings\Repository::get_provider()}).
	 *                              Defaults to 45 for any caller that doesn't
	 *                              pass one explicitly.
	 *
	 * @return array{content:string,prompt_tokens:int,completion_tokens:int}|WP_Error
	 *               On success, the raw text response plus token usage (`0`
	 *               if the provider's response didn't include usage data).
	 *               A WP_Error with a user-safe message on failure.
	 */
	public function analyze( string $api_key, string $model, string $system_prompt, string $user_prompt, int $timeout = 45 );
}
