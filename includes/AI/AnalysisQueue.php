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
use InboxAI\Services\SlackIntegrationService;
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

		self::spawn_cron_now();
	}

	/**
	 * Fires WP-Cron immediately instead of waiting for the next incidental
	 * page load or an external cron job's own polling interval.
	 *
	 * WordPress core's own `spawn_cron()` (wp-includes/cron.php) already
	 * does this on every request — but it's a deliberate no-op whenever
	 * `DISABLE_WP_CRON` is set, which is the standard production setup once
	 * a real server cron job takes over polling `wp-cron.php` on its own
	 * schedule (e.g. every 5-15 minutes). That polling interval is exactly
	 * what produces a multi-minute gap between "submission captured" and
	 * "AI analysis completed" in practice, since the just-scheduled event
	 * just sits there until the next poll.
	 *
	 * This fires the same non-blocking loopback request core's spawn_cron()
	 * uses, but deliberately does NOT check `DISABLE_WP_CRON` first — so a
	 * message enqueued here starts processing within about a second
	 * regardless of the site's cron configuration, while still never
	 * blocking the visitor's own request: `blocking => false` means this
	 * call returns immediately without waiting for wp-cron.php's response,
	 * matching the "never delay the visitor's submission" goal documented
	 * on the class itself.
	 *
	 * @return void
	 */
	private static function spawn_cron_now(): void {
		$doing_wp_cron = sprintf( '%.22F', microtime( true ) );

		wp_remote_post(
			add_query_arg( 'doing_wp_cron', $doing_wp_cron, site_url( 'wp-cron.php' ) ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);
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

		// Mood is a one-time read on this message, not a re-scoreable field —
		// it captures the customer's tone in *this* submission, so re-running
		// analysis (the "Regenerate analysis"/"Regenerate reply"/"Retry"
		// buttons, all of which call this same method again on a message
		// that's already been analyzed once) must never overwrite it or add a
		// second entry to the Customer Mood panel's history. Only a message
		// that has never had a mood recorded gets one here; see
		// self::process_reply() for the case that legitimately does read a
		// fresh mood every time — an actual new customer reply is a different
		// message's worth of tone to read, not a re-analysis of this one.
		$mood_already_set = '' !== (string) $message['mood'];
		$mood             = $mood_already_set ? (string) $message['mood'] : ResponseValidator::normalize_mood( (string) ( $data['mood'] ?? '' ) );
		$mood_reason      = $mood_already_set ? '' : sanitize_text_field( trim( (string) ( $data['mood_reason'] ?? '' ) ) );

		$workflow_status = 'new';

		if ( 'Spam' === $category && $general['auto_archive_spam'] ) {
			$workflow_status = 'archived';
		} elseif ( $confidence < (int) $general['confidence_threshold'] ) {
			$workflow_status = 'review';
		}

		$analysis_fields = array(
			'ai_summary'      => $summary,
			'ai_reasoning'    => $reasoning,
			'priority'        => $priority,
			'mood'            => $mood,
			'confidence'      => $confidence,
			'ai_provider'     => $provider_settings['provider'],
			'ai_model'        => $provider_settings['model'],
			'workflow_status' => $workflow_status,
		);

		// Only overwrite the stored category when this run actually produced
		// one. `$category` comes back '' both when the form currently has no
		// categories configured (so the model was never asked) and when the
		// model's answer didn't match any allowed category — neither case
		// should blow away a category a previous, successful run already
		// set. See `ResponseValidator::normalize_category()`.
		if ( '' !== $category ) {
			$analysis_fields['category'] = $category;
		}

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

		$event_data = array(
			'confidence' => $confidence,
			'category'   => $category,
			'priority'   => $priority,
		);

		// Only recorded on the run that actually set the mood — see the
		// docblock above $mood_already_set. Leaving these keys out entirely
		// (rather than including the unchanged mood) is what keeps a
		// Retry/Regenerate click from adding a duplicate entry to the
		// Customer Mood panel's history, which only shows activities that
		// have a 'mood' key.
		if ( ! $mood_already_set ) {
			$event_data['mood']        = $mood;
			$event_data['mood_reason'] = $mood_reason;
		}

		ActivityRepository::log( $message_id, 'ai_analysis_completed', $event_data );

		SlackIntegrationService::notify_urgent( array_merge( $message, $analysis_fields ), $priority );
	}

	/**
	 * Re-runs AI analysis after a customer reply comes in, and (if drafting
	 * is enabled) drafts a follow-up reply — the reply-thread counterpart to
	 * {@see self::process()}, which only ever runs once, on the original
	 * submission.
	 *
	 * Called synchronously from {@see \InboxAI\Mail\InboundMailChecker::process_one()}
	 * right after it logs the `customer_replied` activity — not re-queued
	 * through {@see self::enqueue()}, since it's already running inside a
	 * WP-Cron request (the inbound-mail check itself), so there's no
	 * visitor-facing request to protect from delay the way a fresh
	 * submission's capture request is.
	 *
	 * Unlike {@see self::process()}, a failure here (no API key, provider
	 * error, unparseable response) is never treated as a hard failure —
	 * {@see \InboxAI\Mail\InboundMailChecker::process_one()} has already set
	 * `workflow_status` to `review` before this runs, which is exactly the
	 * right fallback state ("a human needs to look at this") if re-analysis
	 * can't run for any reason. This method only ever upgrades that state
	 * (to `drafted`, once a new reply draft is ready); it never sets
	 * `failed`.
	 *
	 * @param int $message_id Message row id.
	 *
	 * @return void
	 */
	public function process_reply( int $message_id ): void {
		$message = MessageRepository::find( $message_id );

		if ( null === $message ) {
			return;
		}

		$general = SettingsRepository::get_general();

		if ( ! $general['auto_analyze'] ) {
			return;
		}

		$api_key = SettingsRepository::get_api_key();

		if ( null === $api_key || '' === $api_key ) {
			return;
		}

		$provider_settings = SettingsRepository::get_provider();
		$provider          = ProviderFactory::create( $provider_settings['provider'] );

		if ( null === $provider ) {
			return;
		}

		$prompts    = SettingsRepository::get_prompts();
		$categories = self::get_categories_for_form( (int) $message['form_id'] );
		$transcript = self::build_conversation_transcript( $message );

		$analysis_prompt = PromptBuilder::build_analysis_prompt(
			$prompts['analysis_prompt'],
			array(
				'{message}'          => $transcript,
				'{customer_name}'    => (string) $message['sender_name'],
				'{form_name}'        => (string) $message['form_title'],
				'{submitted_fields}' => (string) $message['message'],
				'{categories}'       => implode( ', ', $categories ),
			),
			$categories
		);

		$result = $provider->analyze( $api_key, $provider_settings['model'], '', $analysis_prompt );

		if ( $result instanceof WP_Error ) {
			return;
		}

		$data = ResponseValidator::extract_json( $result['content'] );

		if ( null === $data ) {
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

		$category    = ResponseValidator::normalize_category( (string) ( $data['category'] ?? '' ), $categories );
		$priority    = ResponseValidator::normalize_priority( (string) ( $data['priority'] ?? '' ) );
		// Unlike self::process()'s guarded read, this always reads a fresh
		// mood — a customer reply is a genuinely new message, distinct from
		// whatever mood was recorded for the original submission or an
		// earlier reply, so it earns its own entry in the Customer Mood
		// panel's history rather than being treated as a re-analysis of
		// something already scored.
		$mood        = ResponseValidator::normalize_mood( (string) ( $data['mood'] ?? '' ) );
		$mood_reason = sanitize_text_field( trim( (string) ( $data['mood_reason'] ?? '' ) ) );
		$confidence  = ResponseValidator::normalize_confidence( $data['confidence'] ?? 0 );
		$summary     = trim( (string) ( $data['summary'] ?? '' ) );
		$reasoning   = trim( (string) ( $data['reasoning'] ?? '' ) );

		$analysis_fields = array(
			'ai_summary'      => $summary,
			'ai_reasoning'    => $reasoning,
			'priority'        => $priority,
			'mood'            => $mood,
			'confidence'      => $confidence,
			'ai_provider'     => $provider_settings['provider'],
			'ai_model'        => $provider_settings['model'],
			// Floor is always 'review' (already set by InboundMailChecker) —
			// this only ever gets upgraded to 'drafted' below, never
			// downgraded, and never set to 'failed' (see docblock above).
			'workflow_status' => 'review',
		);

		if ( '' !== $category ) {
			$analysis_fields['category'] = $category;
		}

		// Deliberately not gated by the confidence threshold the way
		// self::process()'s first-draft is — a known customer actively
		// replying always warrants a suggested response, regardless of how
		// confident the category/priority classification happens to be.
		// Still respects the master auto-draft toggle: turning that off
		// means "never draft for me," which should apply to replies too.
		if ( $general['auto_draft_high_confidence'] ) {
			$draft = $this->draft_reply( $provider, $api_key, $provider_settings, $prompts, $message, $summary, $transcript );

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
				'confidence'  => $confidence,
				'category'    => $category,
				'priority'    => $priority,
				'mood'        => $mood,
				'mood_reason' => $mood_reason,
			)
		);

		SlackIntegrationService::notify_urgent( array_merge( $message, $analysis_fields ), $priority );
	}

	/**
	 * Builds a plain-text back-and-forth transcript for a message: the
	 * original submission, then every `reply_sent`/`customer_replied`
	 * activity in chronological order (oldest first — the opposite of
	 * {@see \InboxAI\Database\ActivityRepository::get_for_message()}'s own
	 * newest-first order, since a transcript needs to read top-to-bottom).
	 * Used in place of the plain `{message}` placeholder value so both the
	 * analysis prompt and the reply-draft prompt see the whole conversation,
	 * not just the first message — see {@see self::process_reply()}.
	 *
	 * @param array<string, mixed> $message The message row.
	 *
	 * @return string
	 */
	private static function build_conversation_transcript( array $message ): string {
		$lines   = array();
		$lines[] = 'Customer (original message): ' . (string) $message['message'];

		$activities = array_reverse( ActivityRepository::get_for_message( (int) $message['id'], 50 ) );

		foreach ( $activities as $activity ) {
			if ( 'reply_sent' === $activity['event_type'] ) {
				$body = trim( wp_strip_all_tags( (string) ( $activity['event_data']['body'] ?? '' ) ) );

				if ( '' !== $body ) {
					$lines[] = 'You (staff reply): ' . $body;
				}
			} elseif ( 'customer_replied' === $activity['event_type'] ) {
				$body = trim( (string) ( $activity['event_data']['body'] ?? '' ) );

				if ( '' !== $body ) {
					$lines[] = 'Customer (reply): ' . $body;
				}
			}
		}

		return implode( "\n\n", $lines );
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
	 * @param string|null                                 $context_override  What to substitute for `{message}` in the
	 *                                                                        reply-prompt template — defaults to the
	 *                                                                        original submission text
	 *                                                                        (`$message['message']`). {@see self::process_reply()}
	 *                                                                        passes the full conversation transcript
	 *                                                                        instead, so the draft responds to the
	 *                                                                        customer's latest reply, not the original
	 *                                                                        message all over again.
	 *
	 * @return array{subject:string,body:string}|null
	 */
	private function draft_reply( $provider, string $api_key, array $provider_settings, array $prompts, array $message, string $summary, ?string $context_override = null ): ?array {
		$reply_prompt = PromptBuilder::build_reply_prompt(
			$prompts['reply_prompt'],
			array(
				'{tone}'          => self::TONE_LABELS[ $prompts['reply_tone'] ] ?? self::TONE_LABELS['friendly_professional'],
				// The customer this draft is addressed *to* — passed
				// explicitly (not just left for the model to infer from
				// `{message}`) so it can't confuse the customer's name with
				// `{signature}` (the site owner's own name) the way it did
				// before this was added; see `Settings\Repository::get_default_prompts()`'s
				// `reply_prompt` for how the two are distinguished in the
				// prompt text itself.
				'{customer_name}' => (string) $message['sender_name'],
				'{summary}'       => $summary,
				'{message}'       => $context_override ?? (string) $message['message'],
				'{signature}'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
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
	 * The category names a specific form's submissions should preferably be
	 * classified into — that form's own {@see \InboxAI\CF7\CategoryTaxonomy}
	 * terms.
	 *
	 * There is no fallback list: a form nobody has added categories to yet
	 * returns an empty array. That doesn't leave the AI category blank,
	 * though — {@see \InboxAI\AI\PromptBuilder::build_analysis_prompt()}
	 * still asks the model for a category either way, just without
	 * constraining it to a fixed list when this comes back empty (see
	 * {@see \InboxAI\AI\ResponseValidator::normalize_category()}).
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
