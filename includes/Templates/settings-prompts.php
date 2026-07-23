<?php
/**
 * Settings page — Prompts tab.
 *
 * @var string $active_tab Currently visible tab key.
 * @var array  $prompts    {@see \CF7AIInbox\Settings\Repository::get_prompts()}.
 *
 * @package CF7AIInbox\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cf7ai_tone_labels = array(
	'friendly_professional' => __( 'Friendly and professional', 'cf7-ai-inbox' ),
	'formal'                => __( 'Formal', 'cf7-ai-inbox' ),
	'casual'                => __( 'Casual', 'cf7-ai-inbox' ),
	'concise'               => __( 'Concise', 'cf7-ai-inbox' ),
);

?>
<section class="cf7-ai-inbox-screen<?php echo 'prompts' === $active_tab ? ' cf7-ai-inbox-is-active' : ''; ?>" id="screen-prompts">
	<div class="cf7-ai-inbox-page-header">
		<div>
			<h1><?php esc_html_e( 'Prompt Configuration', 'cf7-ai-inbox' ); ?></h1>
			<p><?php esc_html_e( 'Customize the instructions the AI uses to analyze submissions and draft replies.', 'cf7-ai-inbox' ); ?></p>
		</div>
	</div>
	<div class="cf7-ai-inbox-settings__shell">
		<div class="cf7-ai-inbox-settings__tabs" id="settings-tabs-3">
			<a href="#" data-subnav="ai-settings" class="<?php echo 'ai-settings' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'AI Provider', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="general-settings" class="<?php echo 'general-settings' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'General', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="prompts" class="<?php echo 'prompts' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Prompts', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="usage" class="<?php echo 'usage' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Usage & Billing', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="notifications" class="<?php echo 'notifications' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Notifications', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="flamingo" class="<?php echo 'flamingo' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Import & Migration', 'cf7-ai-inbox' ); ?></a>
		</div>
		<div class="cf7-ai-inbox-stack">

			<div class="cf7-ai-inbox-card">
				<div class="cf7-ai-inbox-card__header">
					<h2><?php esc_html_e( 'Analysis Prompt', 'cf7-ai-inbox' ); ?></h2>
					<span class="cf7-ai-inbox-card__muted"><?php esc_html_e( 'Used to summarize, categorize, and score priority', 'cf7-ai-inbox' ); ?></span>
				</div>
				<div class="cf7-ai-inbox-card__body">
					<div class="cf7-ai-inbox-field">
						<label><?php esc_html_e( 'Available variables', 'cf7-ai-inbox' ); ?></label>
						<div style="display:flex;gap:6px;flex-wrap:wrap;">
							<span class="cf7-ai-inbox-prompt-var">{message}</span>
							<span class="cf7-ai-inbox-prompt-var">{customer_name}</span>
							<span class="cf7-ai-inbox-prompt-var">{form_name}</span>
							<span class="cf7-ai-inbox-prompt-var">{submitted_fields}</span>
							<span class="cf7-ai-inbox-prompt-var">{categories}</span>
						</div>
					</div>
					<div class="cf7-ai-inbox-field" style="margin-bottom:8px;">
						<label><?php esc_html_e( 'Prompt template', 'cf7-ai-inbox' ); ?></label>
						<textarea class="cf7-ai-inbox-field__input" data-field="analysis_prompt" style="min-height:180px;"><?php echo esc_textarea( $prompts['analysis_prompt'] ); ?></textarea>
					</div>
					<div class="cf7-ai-inbox-field__hint"><?php esc_html_e( 'Changes apply to new submissions only. Existing analyses are not reprocessed.', 'cf7-ai-inbox' ); ?></div>
				</div>
			</div>

			<div class="cf7-ai-inbox-card">
				<div class="cf7-ai-inbox-card__header">
					<h2><?php esc_html_e( 'Reply Draft Prompt', 'cf7-ai-inbox' ); ?></h2>
					<span class="cf7-ai-inbox-card__muted"><?php esc_html_e( 'Used to generate suggested replies', 'cf7-ai-inbox' ); ?></span>
				</div>
				<div class="cf7-ai-inbox-card__body">
					<div class="cf7-ai-inbox-field">
						<label><?php esc_html_e( 'Available variables', 'cf7-ai-inbox' ); ?></label>
						<div style="display:flex;gap:6px;flex-wrap:wrap;">
							<span class="cf7-ai-inbox-prompt-var">{message}</span>
							<span class="cf7-ai-inbox-prompt-var">{summary}</span>
							<span class="cf7-ai-inbox-prompt-var">{tone}</span>
							<span class="cf7-ai-inbox-prompt-var">{signature}</span>
						</div>
					</div>
					<div class="cf7-ai-inbox-field" style="margin-bottom:0;">
						<label><?php esc_html_e( 'Prompt template', 'cf7-ai-inbox' ); ?></label>
						<textarea class="cf7-ai-inbox-field__input" data-field="reply_prompt" style="min-height:130px;"><?php echo esc_textarea( $prompts['reply_prompt'] ); ?></textarea>
					</div>
				</div>
			</div>

			<div class="cf7-ai-inbox-card">
				<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Reply Tone', 'cf7-ai-inbox' ); ?></h2></div>
				<div class="cf7-ai-inbox-card__body">
					<div class="cf7-ai-inbox-field" style="margin-bottom:0;">
						<label><?php esc_html_e( 'Default tone', 'cf7-ai-inbox' ); ?></label>
						<select class="cf7-ai-inbox-field__input" data-field="reply_tone">
							<?php foreach ( $cf7ai_tone_labels as $cf7ai_value => $cf7ai_label ) : ?>
								<option value="<?php echo esc_attr( $cf7ai_value ); ?>" <?php selected( $prompts['reply_tone'], $cf7ai_value ); ?>><?php echo esc_html( $cf7ai_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			</div>

			<div style="display:flex;gap:10px;justify-content:flex-end;">
				<button class="cf7-ai-inbox-btn--secondary" id="prompts-reset-btn"><?php esc_html_e( 'Reset to defaults', 'cf7-ai-inbox' ); ?></button>
				<button class="cf7-ai-inbox-btn--primary" id="prompts-save-btn"><?php esc_html_e( 'Save Prompts', 'cf7-ai-inbox' ); ?></button>
			</div>

		</div>
	</div>
</section>
