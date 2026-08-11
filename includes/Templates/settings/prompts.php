<?php
/**
 * Settings page — Prompts tab.
 *
 * @var string $active_tab Currently visible tab key.
 * @var array  $prompts    {@see \InboxAI\Settings\Repository::get_prompts()}.
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$inboxai_tone_labels = array(
	'friendly_professional' => __( 'Friendly and professional', 'inbox-ai' ),
	'formal'                => __( 'Formal', 'inbox-ai' ),
	'casual'                => __( 'Casual', 'inbox-ai' ),
	'concise'               => __( 'Concise', 'inbox-ai' ),
);

?>
<section class="inboxai-screen<?php echo 'prompts' === $active_tab ? ' inboxai-is-active' : ''; ?>" id="screen-prompts">
	<div class="inboxai-page-header">
		<div>
			<h1><?php esc_html_e( 'Prompt Configuration', 'inbox-ai' ); ?></h1>
			<p><?php esc_html_e( 'Customize the instructions the AI uses to analyze submissions and draft replies.', 'inbox-ai' ); ?></p>
		</div>
	</div>
	<div class="inboxai-settings__shell">
		<?php \InboxAI\Support\Template::render( 'settings/partials/subnav', array( 'active_tab' => $active_tab ) ); ?>
		<div class="inboxai-stack">

			<div class="inboxai-card">
				<div class="inboxai-card__header">
					<h2><?php esc_html_e( 'Analysis Prompt', 'inbox-ai' ); ?></h2>
					<span class="inboxai-card__muted"><?php esc_html_e( 'Used to summarize, categorize, and score priority', 'inbox-ai' ); ?></span>
				</div>
				<div class="inboxai-card__body">
					<div class="inboxai-field">
						<label><?php esc_html_e( 'Available variables', 'inbox-ai' ); ?></label>
						<div style="display:flex;gap:6px;flex-wrap:wrap;">
							<span class="inboxai-prompt-var">{message}</span>
							<span class="inboxai-prompt-var">{customer_name}</span>
							<span class="inboxai-prompt-var">{form_name}</span>
							<span class="inboxai-prompt-var">{submitted_fields}</span>
							<span class="inboxai-prompt-var">{categories}</span>
						</div>
					</div>
					<div class="inboxai-field" style="margin-bottom:8px;">
						<label><?php esc_html_e( 'Prompt template', 'inbox-ai' ); ?></label>
						<textarea class="inboxai-field__input" data-field="analysis_prompt" style="min-height:180px;"><?php echo esc_textarea( $prompts['analysis_prompt'] ); ?></textarea>
					</div>
					<div class="inboxai-field__hint"><?php esc_html_e( 'Changes apply to new submissions only. Existing analyses are not reprocessed.', 'inbox-ai' ); ?></div>
				</div>
			</div>

			<div class="inboxai-card">
				<div class="inboxai-card__header">
					<h2><?php esc_html_e( 'Reply Draft Prompt', 'inbox-ai' ); ?></h2>
					<span class="inboxai-card__muted"><?php esc_html_e( 'Used to generate suggested replies', 'inbox-ai' ); ?></span>
				</div>
				<div class="inboxai-card__body">
					<div class="inboxai-field">
						<label><?php esc_html_e( 'Available variables', 'inbox-ai' ); ?></label>
						<div style="display:flex;gap:6px;flex-wrap:wrap;">
							<span class="inboxai-prompt-var">{message}</span>
							<span class="inboxai-prompt-var">{customer_name}</span>
							<span class="inboxai-prompt-var">{summary}</span>
							<span class="inboxai-prompt-var">{tone}</span>
							<span class="inboxai-prompt-var">{signature}</span>
						</div>
					</div>
					<div class="inboxai-field" style="margin-bottom:0;">
						<label><?php esc_html_e( 'Prompt template', 'inbox-ai' ); ?></label>
						<textarea class="inboxai-field__input" data-field="reply_prompt" style="min-height:130px;"><?php echo esc_textarea( $prompts['reply_prompt'] ); ?></textarea>
					</div>
				</div>
			</div>

			<div class="inboxai-card">
				<div class="inboxai-card__header"><h2><?php esc_html_e( 'Reply Tone', 'inbox-ai' ); ?></h2></div>
				<div class="inboxai-card__body">
					<div class="inboxai-field" style="margin-bottom:0;">
						<label><?php esc_html_e( 'Default tone', 'inbox-ai' ); ?></label>
						<select class="inboxai-field__input" data-field="reply_tone">
							<?php foreach ( $inboxai_tone_labels as $inboxai_value => $inboxai_label ) : ?>
								<option value="<?php echo esc_attr( $inboxai_value ); ?>" <?php selected( $prompts['reply_tone'], $inboxai_value ); ?>><?php echo esc_html( $inboxai_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			</div>

			<div style="display:flex;gap:10px;justify-content:flex-end;">
				<button class="inboxai-btn--secondary" id="prompts-reset-btn"><?php esc_html_e( 'Reset to defaults', 'inbox-ai' ); ?></button>
				<button class="inboxai-btn--primary" id="prompts-save-btn"><?php esc_html_e( 'Save Prompts', 'inbox-ai' ); ?></button>
			</div>

		</div>
	</div>
</section>
