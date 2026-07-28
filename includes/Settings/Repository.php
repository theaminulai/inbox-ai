<?php
/**
 * Typed access to every setting on the Settings admin page.
 *
 * @package InboxAI\Settings
 */

namespace InboxAI\Settings;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\Security\Encryption;

/**
 * Class Repository
 *
 * Backed by several discrete `wp_options` rows (one per tab) rather than
 * one large serialized blob, so reading/saving one tab never has to
 * deserialize or rewrite another. The API key is stored in its own option,
 * encrypted, and is never mixed into the plain settings arrays returned by
 * this class.
 */
final class Repository {

	private const PROVIDER_OPTION      = 'inboxai_settings_provider';
	private const API_KEY_OPTION       = 'inboxai_api_key';
	private const GENERAL_OPTION       = 'inboxai_settings_general';
	private const PROMPTS_OPTION       = 'inboxai_settings_prompts';
	private const NOTIFICATIONS_OPTION = 'inboxai_settings_notifications';

	/**
	 * Allowed provider ids, retention periods, and reply tones — kept here
	 * so every save method whitelists against the same source of truth.
	 */
	private const PROVIDERS         = array( 'openai', 'anthropic', 'google' );
	private const RETENTION_PERIODS = array( 'forever', '24_months', '12_months', '6_months' );
	private const REPLY_TONES       = array( 'friendly_professional', 'formal', 'casual', 'concise' );

	/**
	 * AI Provider tab settings (excluding the API key itself).
	 *
	 * @return array{provider:string,model:string,request_timeout:int,auto_retry:bool,fallback_manual_review:bool,email_alert_outage:bool}
	 */
	public static function get_provider(): array {
		$defaults = array(
			'provider'               => 'openai',
			'model'                  => 'gpt-4.1-mini',
			'request_timeout'        => 30,
			'auto_retry'             => true,
			'fallback_manual_review' => true,
			'email_alert_outage'     => false,
		);

		$stored = get_option( self::PROVIDER_OPTION, array() );

		return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
	}

	/**
	 * Saves the AI Provider tab (excluding the API key — see {@see self::save_api_key()}).
	 *
	 * @param array<string, mixed> $data Raw, unsanitized input.
	 *
	 * @return void
	 */
	public static function save_provider( array $data ): void {
		$current = self::get_provider();

		update_option(
			self::PROVIDER_OPTION,
			array(
				'provider'               => in_array( $data['provider'] ?? '', self::PROVIDERS, true ) ? $data['provider'] : $current['provider'],
				'model'                  => sanitize_text_field( (string) ( $data['model'] ?? $current['model'] ) ),
				'request_timeout'        => ( absint( $data['request_timeout'] ?? 0 ) ) ?: $current['request_timeout'],
				'auto_retry'             => ! empty( $data['auto_retry'] ),
				'fallback_manual_review' => ! empty( $data['fallback_manual_review'] ),
				'email_alert_outage'     => ! empty( $data['email_alert_outage'] ),
			),
			false
		);
	}

	/**
	 * Whether an API key is currently stored.
	 *
	 * @return bool
	 */
	public static function has_api_key(): bool {
		return '' !== (string) get_option( self::API_KEY_OPTION, '' );
	}

	/**
	 * Decrypts and returns the stored API key.
	 *
	 * @return string|null Null if none is stored or it fails to decrypt.
	 */
	public static function get_api_key(): ?string {
		$stored = (string) get_option( self::API_KEY_OPTION, '' );

		if ( '' === $stored ) {
			return null;
		}

		return Encryption::decrypt( $stored );
	}

	/**
	 * A masked representation safe to send back to the browser, e.g.
	 * `sk-••••••••••••••••••••7f2A`. Never returns the real key.
	 *
	 * @return string Empty string if no key is stored.
	 */
	public static function get_masked_api_key(): string {
		$key = self::get_api_key();

		if ( null === $key || strlen( $key ) < 8 ) {
			return '';
		}

		return substr( $key, 0, 3 ) . str_repeat( '•', 20 ) . substr( $key, -4 );
	}

	/**
	 * Encrypts and stores a new API key.
	 *
	 * Refuses to store an empty value or a masked placeholder (anything
	 * containing the `•` mask character) — a save request re-submitting the
	 * masked display value must never overwrite the real stored key.
	 *
	 * @param string $plain New plaintext API key.
	 *
	 * @return void
	 */
	public static function save_api_key( string $plain ): void {
		if ( '' === $plain || false !== strpos( $plain, "\u{2022}" ) ) {
			return;
		}

		update_option( self::API_KEY_OPTION, Encryption::encrypt( $plain ), false );
	}

	/**
	 * Removes the stored API key entirely.
	 *
	 * @return void
	 */
	public static function clear_api_key(): void {
		delete_option( self::API_KEY_OPTION );
	}

	/**
	 * General tab settings.
	 *
	 * @return array{monitored_forms:int[],auto_analyze:bool,auto_draft_high_confidence:bool,auto_archive_spam:bool,confidence_threshold:int,retention_period:string,delete_attachments_after_reply:bool}
	 */
	public static function get_general(): array {
		$defaults = array(
			'monitored_forms'                => array(),
			'auto_analyze'                   => true,
			'auto_draft_high_confidence'     => true,
			'auto_archive_spam'              => true,
			'confidence_threshold'           => 60,
			'retention_period'               => 'forever',
			'delete_attachments_after_reply' => false,
		);

		$stored = get_option( self::GENERAL_OPTION, array() );

		return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
	}

	/**
	 * Saves the General tab.
	 *
	 * @param array<string, mixed> $data Raw, unsanitized input.
	 *
	 * @return void
	 */
	public static function save_general( array $data ): void {
		$current = self::get_general();

		$monitored_forms = array();

		if ( isset( $data['monitored_forms'] ) && is_array( $data['monitored_forms'] ) ) {
			$monitored_forms = array_values( array_unique( array_map( 'absint', $data['monitored_forms'] ) ) );
		}

		update_option(
			self::GENERAL_OPTION,
			array(
				'monitored_forms'                => $monitored_forms,
				'auto_analyze'                   => ! empty( $data['auto_analyze'] ),
				'auto_draft_high_confidence'     => ! empty( $data['auto_draft_high_confidence'] ),
				'auto_archive_spam'              => ! empty( $data['auto_archive_spam'] ),
				'confidence_threshold'           => min( 100, max( 0, absint( $data['confidence_threshold'] ?? $current['confidence_threshold'] ) ) ),
				'retention_period'               => in_array( $data['retention_period'] ?? '', self::RETENTION_PERIODS, true ) ? $data['retention_period'] : $current['retention_period'],
				'delete_attachments_after_reply' => ! empty( $data['delete_attachments_after_reply'] ),
			),
			false
		);
	}

	/**
	 * Whether a given Contact Form 7 form id is in the monitored-forms list.
	 *
	 * Read by Plan 2's `SubmissionHandler` before capturing a submission,
	 * and by Plan 1's Overview empty-state check.
	 *
	 * @param int $form_id Contact Form 7 form (post) id.
	 *
	 * @return bool
	 */
	public static function is_form_monitored( int $form_id ): bool {
		return in_array( $form_id, self::get_general()['monitored_forms'], true );
	}

	/**
	 * The built-in prompt templates, matching the mockup's pre-filled copy.
	 *
	 * @return array{analysis_prompt:string,reply_prompt:string,reply_tone:string}
	 */
	public static function get_default_prompts(): array {
		return array(
			'analysis_prompt' => "You are a support triage assistant. Read the submission below and:\n"
				. "1. Summarize the request in 1-2 sentences.\n"
				. "2. Assign one category from: {categories}\n"
				. "3. Assign a priority: urgent, high, normal, or low.\n"
				. "4. Explain your reasoning briefly.\n\n"
				. "Customer: {customer_name}\n"
				. "Form: {form_name}\n"
				. "Message: {message}\n"
				. "Submitted fields: {submitted_fields}\n\n"
				. 'Respond in the required structured format only.',
			'reply_prompt'    => 'Draft a helpful, {tone} reply to this customer based on the summary below. '
				. "Keep it concise, address every question they asked, and end with the signature.\n\n"
				. "Summary: {summary}\n"
				. "Original message: {message}\n"
				. 'Signature: {signature}',
			'reply_tone'      => 'friendly_professional',
		);
	}

	/**
	 * Prompts tab settings.
	 *
	 * @return array{analysis_prompt:string,reply_prompt:string,reply_tone:string}
	 */
	public static function get_prompts(): array {
		$stored = get_option( self::PROMPTS_OPTION, array() );

		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::get_default_prompts() );
	}

	/**
	 * Saves the Prompts tab.
	 *
	 * @param array<string, mixed> $data Raw, unsanitized input.
	 *
	 * @return void
	 */
	public static function save_prompts( array $data ): void {
		$current = self::get_prompts();

		update_option(
			self::PROMPTS_OPTION,
			array(
				'analysis_prompt' => sanitize_textarea_field( (string) ( $data['analysis_prompt'] ?? $current['analysis_prompt'] ) ),
				'reply_prompt'    => sanitize_textarea_field( (string) ( $data['reply_prompt'] ?? $current['reply_prompt'] ) ),
				'reply_tone'      => in_array( $data['reply_tone'] ?? '', self::REPLY_TONES, true ) ? $data['reply_tone'] : $current['reply_tone'],
			),
			false
		);
	}

	/**
	 * Resets the Prompts tab to its built-in defaults and returns them.
	 *
	 * @return array{analysis_prompt:string,reply_prompt:string,reply_tone:string}
	 */
	public static function reset_prompts_to_defaults(): array {
		$defaults = self::get_default_prompts();

		update_option( self::PROMPTS_OPTION, $defaults, false );

		return $defaults;
	}

	/**
	 * Notifications tab settings.
	 *
	 * @return array{notify_urgent:bool,daily_digest:bool,notify_analysis_failure:bool,notify_draft_ready:bool,slack_enabled:bool,slack_webhook_url:string}
	 */
	public static function get_notifications(): array {
		$defaults = array(
			'notify_urgent'           => true,
			'daily_digest'            => true,
			'notify_analysis_failure' => true,
			'notify_draft_ready'      => false,
			'slack_enabled'           => false,
			'slack_webhook_url'       => '',
		);

		$stored = get_option( self::NOTIFICATIONS_OPTION, array() );

		return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
	}

	/**
	 * Saves the Notifications tab.
	 *
	 * The Slack webhook URL is only accepted if it's a well-formed HTTPS
	 * URL; otherwise whatever was already stored is kept.
	 *
	 * @param array<string, mixed> $data Raw, unsanitized input.
	 *
	 * @return void
	 */
	public static function save_notifications( array $data ): void {
		$current = self::get_notifications();

		$webhook = isset( $data['slack_webhook_url'] ) ? esc_url_raw( (string) $data['slack_webhook_url'] ) : $current['slack_webhook_url'];

		if ( '' !== $webhook && ( 0 !== strpos( $webhook, 'https://' ) || ! wp_http_validate_url( $webhook ) ) ) {
			$webhook = $current['slack_webhook_url'];
		}

		update_option(
			self::NOTIFICATIONS_OPTION,
			array(
				'notify_urgent'           => ! empty( $data['notify_urgent'] ),
				'daily_digest'            => ! empty( $data['daily_digest'] ),
				'notify_analysis_failure' => ! empty( $data['notify_analysis_failure'] ),
				'notify_draft_ready'      => ! empty( $data['notify_draft_ready'] ),
				'slack_enabled'           => ! empty( $data['slack_enabled'] ),
				'slack_webhook_url'       => $webhook,
			),
			false
		);
	}
}
