<?php
/**
 * Settings page — General tab.
 *
 * @var string $active_tab Currently visible tab key.
 * @var array  $general    {@see \CF7AIInbox\Settings\Repository::get_general()}.
 * @var array  $cf7_forms  Real Contact Form 7 forms: array{id:int,title:string,monitored:bool}[].
 *
 * @package CF7AIInbox\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cf7ai_retention_labels = array(
	'forever'   => __( 'Forever', 'cf7-ai-inbox' ),
	'24_months' => __( '24 months', 'cf7-ai-inbox' ),
	'12_months' => __( '12 months', 'cf7-ai-inbox' ),
	'6_months'  => __( '6 months', 'cf7-ai-inbox' ),
);

?>
<section class="cf7-ai-inbox-screen<?php echo 'general-settings' === $active_tab ? ' cf7-ai-inbox-is-active' : ''; ?>" id="screen-general-settings">
	<div class="cf7-ai-inbox-page-header">
		<div>
			<h1><?php esc_html_e( 'General Settings', 'cf7-ai-inbox' ); ?></h1>
			<p><?php esc_html_e( 'Choose which forms feed the AI Inbox and how new submissions are handled.', 'cf7-ai-inbox' ); ?></p>
		</div>
	</div>
	<div class="cf7-ai-inbox-settings__shell">
		<div class="cf7-ai-inbox-settings__tabs" id="settings-tabs-2">
			<a href="#" data-subnav="ai-settings" class="<?php echo 'ai-settings' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'AI Provider', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="general-settings" class="<?php echo 'general-settings' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'General', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="prompts" class="<?php echo 'prompts' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Prompts', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="usage" class="<?php echo 'usage' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Usage & Billing', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="notifications" class="<?php echo 'notifications' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Notifications', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="flamingo" class="<?php echo 'flamingo' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Import & Migration', 'cf7-ai-inbox' ); ?></a>
		</div>
		<div class="cf7-ai-inbox-stack">

			<div class="cf7-ai-inbox-card">
				<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Monitored Forms', 'cf7-ai-inbox' ); ?></h2></div>
				<div class="cf7-ai-inbox-card__body">
					<?php if ( array() === $cf7_forms ) : ?>
						<p style="color:var(--text-tertiary);font-size:13px;"><?php esc_html_e( 'No Contact Form 7 forms found yet. Create a form to start monitoring submissions.', 'cf7-ai-inbox' ); ?></p>
					<?php else : ?>
						<?php foreach ( $cf7_forms as $cf7ai_form ) : ?>
							<div class="cf7-ai-inbox-switch-row">
								<div>
									<div class="cf7-ai-inbox-switch-row__text"><?php echo esc_html( $cf7ai_form['title'] ); ?></div>
								</div>
								<div
									class="cf7-ai-inbox-switch<?php echo $cf7ai_form['monitored'] ? ' cf7-ai-inbox-is-on' : ''; ?>"
									data-form-toggle="<?php echo esc_attr( $cf7ai_form['title'] ); ?>"
									data-form-id="<?php echo esc_attr( (string) $cf7ai_form['id'] ); ?>"
								></div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<div class="cf7-ai-inbox-card">
				<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Automatic Processing', 'cf7-ai-inbox' ); ?></h2></div>
				<div class="cf7-ai-inbox-card__body">
					<div class="cf7-ai-inbox-switch-row">
						<div>
							<div class="cf7-ai-inbox-switch-row__text"><?php esc_html_e( 'Analyze new submissions automatically', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-switch-row__sub"><?php esc_html_e( 'Runs AI analysis as soon as a message arrives', 'cf7-ai-inbox' ); ?></div>
						</div>
						<div class="cf7-ai-inbox-switch<?php echo $general['auto_analyze'] ? ' cf7-ai-inbox-is-on' : ''; ?>" data-field="auto_analyze"></div>
					</div>
					<div class="cf7-ai-inbox-switch-row">
						<div>
							<div class="cf7-ai-inbox-switch-row__text"><?php esc_html_e( 'Auto-draft replies for high-confidence messages', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-switch-row__sub"><?php esc_html_e( 'Drafts are never sent without approval', 'cf7-ai-inbox' ); ?></div>
						</div>
						<div class="cf7-ai-inbox-switch<?php echo $general['auto_draft_high_confidence'] ? ' cf7-ai-inbox-is-on' : ''; ?>" data-field="auto_draft_high_confidence"></div>
					</div>
					<div class="cf7-ai-inbox-switch-row">
						<div>
							<div class="cf7-ai-inbox-switch-row__text"><?php esc_html_e( 'Auto-archive detected spam', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-switch-row__sub"><?php esc_html_e( 'Applies only above 95% spam confidence', 'cf7-ai-inbox' ); ?></div>
						</div>
						<div class="cf7-ai-inbox-switch<?php echo $general['auto_archive_spam'] ? ' cf7-ai-inbox-is-on' : ''; ?>" data-field="auto_archive_spam"></div>
					</div>
					<div class="cf7-ai-inbox-field" style="margin-top:16px;">
						<label><?php esc_html_e( 'Confidence threshold for "Needs Review"', 'cf7-ai-inbox' ); ?></label>
						<input class="cf7-ai-inbox-field__input" type="range" min="0" max="100" data-field="confidence_threshold" value="<?php echo esc_attr( (string) $general['confidence_threshold'] ); ?>">
						<div class="cf7-ai-inbox-field__hint">
							<?php
							printf(
								/* translators: %d: confidence percentage. */
								esc_html__( 'Messages analyzed below %d%% confidence are flagged for manual review.', 'cf7-ai-inbox' ),
								absint( $general['confidence_threshold'] )
							);
							?>
						</div>
					</div>
				</div>
			</div>

			<div class="cf7-ai-inbox-card">
				<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Data Retention', 'cf7-ai-inbox' ); ?></h2></div>
				<div class="cf7-ai-inbox-card__body">
					<div class="cf7-ai-inbox-field">
						<label><?php esc_html_e( 'Keep submissions for', 'cf7-ai-inbox' ); ?></label>
						<select class="cf7-ai-inbox-field__input" data-field="retention_period">
							<?php foreach ( $cf7ai_retention_labels as $cf7ai_value => $cf7ai_label ) : ?>
								<option value="<?php echo esc_attr( $cf7ai_value ); ?>" <?php selected( $general['retention_period'], $cf7ai_value ); ?>><?php echo esc_html( $cf7ai_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="cf7-ai-inbox-switch-row">
						<div>
							<div class="cf7-ai-inbox-switch-row__text"><?php esc_html_e( 'Delete attachments after reply is sent', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-switch-row__sub"><?php esc_html_e( 'Reduces storage use; text content is kept', 'cf7-ai-inbox' ); ?></div>
						</div>
						<div class="cf7-ai-inbox-switch<?php echo $general['delete_attachments_after_reply'] ? ' cf7-ai-inbox-is-on' : ''; ?>" data-field="delete_attachments_after_reply"></div>
					</div>
				</div>
			</div>

			<div style="display:flex;gap:10px;justify-content:flex-end;">
				<button class="cf7-ai-inbox-btn--primary" id="general-settings-save-btn"><?php esc_html_e( 'Save Changes', 'cf7-ai-inbox' ); ?></button>
			</div>

		</div>
	</div>
</section>
