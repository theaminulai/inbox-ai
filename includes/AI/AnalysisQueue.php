<?php
/**
 * Runs AI analysis for one captured message, off the visitor-facing request.
 *
 * @package InboxAI\AI
 */

namespace InboxAI\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\CF7\CategoryTaxonomy;
use InboxAI\Database\ActivityRepository;
use InboxAI\Database\MessageRepository;
use InboxAI\Database\UsageRepository;
use InboxAI\Settings\Repository as SettingsRepository;
use WP_Error;

/**
 * Class AnalysisQueue
 *
 * A WP-Cron-driven queue (no Action Scheduler dependency): capturing a
 * submission never calls an AI provider inline — it only schedules a single
 * event via {@see self::enqueue()}, which WP-Cron runs on a subsequent
 * request. This keeps a slow or unreachable AI provider from ever delaying
 * the visitor's own form submission.
 *
 * One request = one message = at most two AI calls: the analysis call
 * (always, if enabled) and, only for messages that clear the confidence
 * threshold, a second reply-draft call — matching the two separate,
 * admin-editable prompt templates on the Settings page's Prompts tab
 * (`analysis_prompt` and `reply_prompt` are deliberately two different
 * calls, not one combined request, so each can be edited/reasoned about
 * independently).
 */
final class AnalysisQueue {

	/**
	 * The `wp_schedule_single_event()` action hook name.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'inboxai_process_message';

	/**
	 * Rough blended cost-per-1,000-tokens estimate, by provider id. Not
	 * exact per-model billing (providers price individual models
	 * differently) — good enough for the Usage & Billing tab's ballpark
	 * figures without maintaining a full per-model price table.
	 *
	 * @var array<string, float>
	 */
	private const COST_PER_1K_TOKENS = array(
		'openai'    => 0.003,
		'anthropic' => 0.006,
		'google'    => 0.002,
	);

	/**
	 * Human-readable tone labels for the `{tone}` reply-prompt placeholder.
	 *
	 * @var array<string, string>
	 */
	private const TONE_LABELS = array(
		'friendly_professional' => 'friendly, professional',
		'formal'                => 'formal',
		'casual'                => 'casual',
		'concise'               => 'concise',
	);

	/**
	 * Registers the WordPress hook. Must run on every request (not just
	 * `is_admin()`), since WP-Cron requests are their own unauthenticated
	 * request type.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( self::CRON_HOOK, array( $this, 'process' ) );
	}

	/**
	 * Schedules one message for analysis, unless it's already scheduled.
	 *
	 * @param int $message_id Message row id.
	 *
	 * @return void
	 */
	public static function enqueue( int $message_id ): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK, array( $message_id ) ) ) {
			wp_schedule_single_event( time(), self::CRON_HOOK, array( $message_id ) );
		}
	}

	/**
	 * Runs the analysis (and, if warranted, reply-draft) pipeline for one
	 * message. Registered against {@see self::CRON_HOOK}; never called
	 * directly except by WP-Cron or a manual retry re-enqueue.
	 *
	 * @param int $message_id Message row id.
	 *
	 * @return void
	 */
	public function process( int $message_id ): void {
		$message = MessageRepository::find( $message_id );

		if ( null === $message ) {
			return;
		}

		$general = SettingsRepository::get_general();

		if ( ! $general['auto_analyze'] ) {
			return;
		}

		$provider_settings = SettingsRepository::get_provider();
		$api_key           = SettingsRepository::get_api_key();

		if ( null === $api_key || '' === $api_key ) {
			$this->fail( $message_id, __( 'No API key has been configured. Add one on the Settings page, then retry.', 'inbox-ai' ) );
			return;
		}

		$provider = ProviderFactory::create( $provider_settings['provider'] );

		if ( null === $provider ) {
			$this->fail( $message_id, __( 'The configured AI provider is not recognized.', 'inbox-ai' ) );
			return;
		}

		$prompts    = SettingsRepository::get_prompts();
		$categories = self::get_categories_for_form( (int) $message['form_id'] );

		$analysis_prompt = PromptBuilder::build_analysis_prompt(
			$prompts['analysis_prompt'],
			array(
				'{message}'          => (string) $message['message'],
				'{customer_name}'    => (string) $message['sender_name'],
				'{form_name}'        => (string) $message['form_title'],
				'{submitted_fields}' => (string) $message['message'],
				'{categories}'       => implode( ', ', $categories ),
			),
			$categories
		);

		$result = $provider->analyze( $api_key, $provider_settings['model'], '', $analysis_prompt );

		if ( $result instanceof WP_Error ) {
			$this->fail( $message_id, $result->get_error_message() );
			return;
		}

		$data = ResponseValidator::extract_json( $result['content'] );

		if ( null === $data ) {
			$this->fail( $message_id, __( 'The AI response could not be parsed as JSON.', 'inbox-ai' ) );
			return;
		}

		UsageRepository::record(
			$message_id,
			$provider_settings['provider'],
			$provider_settings['model'],
			$result['prompt_tokens'],
			$result['completion_tokens'],
			self::estimate_cost( $provider_settings['provider'], $result['prompt_tokens'], $result['completion_tokens'] ),
			'analysis'
		);

		$category   = ResponseValidator::normalize_category( (string) ( $data['category'] ?? '' ), $categories );
		$priority   = ResponseValidator::normalize_priority( (string) ( $data['priority'] ?? '' ) );
		$confidence = ResponseValidator::normalize_confidence( $data['confidence'] ?? 0 );
		$summary    = trim( (string) ( $data['summary'] ?? '' ) );
		$reasoning  = trim( (string) ( $data['reasoning'] ?? '' ) );

		$workflow_status = 'new';

		if ( 'Spam' === $category && $general['auto_archive_spam'] ) {
			$workflow_status = 'archived';
		} elseif ( $confidence < (int) $general['confidence_threshold'] ) {
			$workflow_status = 'review';
		}

		$analysis_fields = array(
			'ai_summary'      => $summary,
			'ai_reasoning'    => $reasoning,
			'category'        => $category,
			'priority'        => $priority,
			'confidence'      => $confidence,
			'ai_provider'     => $provider_settings['provider'],
			'ai_model'        => $provider_settings['model'],
			'workflow_status' => $workflow_status,
		);

		// Only draft a reply for rows that are actually headed for human
		// review — never for auto-archived spam, and never below the
		// confidence threshold (an unreliable draft is worse than none).
		if ( 'archived' !== $workflow_status && $general['auto_draft_high_confidence'] && $confidence >= (int) $general['confidence_threshold'] ) {
			$draft = $this->draft_reply( $provider, $api_key, $provider_settings, $prompts, $message, $summary );

			if ( null !== $draft ) {
				$analysis_fields['reply_subject']   = $draft['subject'];
				$analysis_fields['reply_draft']     = $draft['body'];
				$analysis_fields['workflow_status'] = 'drafted';
			}
		}

		MessageRepository::update_analysis( $message_id, $analysis_fields );

		ActivityRepository::log(
			$message_id,
			'ai_analysis_completed',
			array(
				'confidence' => $confidence,
				'category'   => $category,
				'priority'   => $priority,
			)
		);
	}

	/**
	 * Runs the second, optional AI call that drafts a suggested reply.
	 *
	 * A failure here never fails the overall analysis — the message still
	 * lands correctly categorized and prioritized even if drafting the
	 * reply didn't work.
	 *
	 * @param \InboxAI\Interfaces\AIProviderInterface $provider          Provider instance.
	 * @param string                                     $api_key           Decrypted API key.
	 * @param array<string, mixed>                       $provider_settings From `Settings\Repository::get_provider()`.
	 * @param array<string, mixed>                       $prompts           From `Settings\Repository::get_prompts()`.
	 * @param array<string, mixed>                       $message           The message row.
	 * @param string                                     $summary           The just-generated AI summary.
	 *
	 * @return array{subject:string,body:string}|null
	 */
	private function draft_reply( $provider, string $api_key, array $provider_settings, array $prompts, array $message, string $summary ): ?array {
		$reply_prompt = PromptBuilder::build_reply_prompt(
			$prompts['reply_prompt'],
			array(
				'{tone}'      => self::TONE_LABELS[ $prompts['reply_tone'] ] ?? self::TONE_LABELS['friendly_professional'],
				'{summary}'   => $summary,
				'{message}'   => (string) $message['message'],
				'{signature}' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			)
		);

		$result = $provider->analyze( $api_key, $provider_settings['model'], '', $reply_prompt );

		if ( $result instanceof WP_Error ) {
			return null;
		}

		UsageRepository::record(
			(int) $message['id'],
			$provider_settings['provider'],
			$provider_settings['model'],
			$result['prompt_tokens'],
			$result['completion_tokens'],
			self::estimate_cost( $provider_settings['provider'], $result['prompt_tokens'], $result['completion_tokens'] ),
			'reply_draft'
		);

		return array(
			'subject' => 'Re: ' . (string) $message['subject'],
			'body'    => $result['content'],
		);
	}

	/**
	 * Marks a message as failed and logs the failure to its timeline.
	 *
	 * @param int    $message_id Message row id.
	 * @param string $message    User-safe error message.
	 *
	 * @return void
	 */
	private function fail( int $message_id, string $message ): void {
		MessageRepository::mark_failed( $message_id, $message );

		ActivityRepository::log( $message_id, 'ai_analysis_failed', array( 'error' => $message ) );
	}

	/**
	 * The category names a specific form's submissions may be classified
	 * into — that form's own {@see \InboxAI\CF7\CategoryTaxonomy} terms.
	 *
	 * There is no fallback list: a form nobody has added categories to yet
	 * returns an empty array, and {@see \InboxAI\AI\PromptBuilder::build_analysis_prompt()}
	 * responds to that by not asking the model for a category at all — the
	 * message still gets summarized/prioritized/scored normally, just left
	 * uncategorized until the admin adds real categories to that form.
	 *
	 * @param int $form_id Contact Form 7 form post id.
	 *
	 * @return string[]
	 */
	private static function get_categories_for_form( int $form_id ): array {
		if ( $form_id <= 0 ) {
			return array();
		}

		$terms = wp_get_post_terms( $form_id, CategoryTaxonomy::TAXONOMY, array( 'fields' => 'names' ) );

		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * A rough, blended cost estimate — not exact per-model billing.
	 *
	 * @param string $provider          Provider id.
	 * @param int    $prompt_tokens     Prompt tokens used.
	 * @param int    $completion_tokens Completion tokens used.
	 *
	 * @return float
	 */
	private static function estimate_cost( string $provider, int $prompt_tokens, int $completion_tokens ): float {
		$rate = self::COST_PER_1K_TOKENS[ $provider ] ?? 0.0;

		return round( ( ( $prompt_tokens + $completion_tokens ) / 1000 ) * $rate, 6 );
	}
}
