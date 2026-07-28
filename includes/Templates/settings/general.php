<?php
/**
 * Settings page — General tab.
 *
 * @var string $active_tab Currently visible tab key.
 * @var array  $general    {@see \InboxAI\Settings\Repository::get_general()}.
 * @var array  $cf7_forms  Real Contact Form 7 forms: array{id:int,title:string,monitored:bool,submissions_this_month:int}[].
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$inboxai_retention_labels = array(
	'forever'   => __( 'Forever', 'inbox-ai' ),
	'24_months' => __( '24 months', 'inbox-ai' ),
	'12_months' => __( '12 months', 'inbox-ai' ),
	'6_months'  => __( '6 months', 'inbox-ai' ),
);

?>
<section class="inboxai-screen<?php echo 'general-settings' === $active_tab ? ' inboxai-is-active' : ''; ?>" id="screen-general-settings">
	<div class="inboxai-page-header">
		<div>
			<h1><?php esc_html_e( 'General Settings', 'inbox-ai' ); ?></h1>
			<p><?php esc_html_e( 'Choose which forms feed the AI Inbox and how new submissions are handled.', 'inbox-ai' ); ?></p>
		</div>
	</div>
	<div class="inboxai-settings__shell">
		<div class="inboxai-settings__tabs" id="settings-tabs-2">
			<a href="#" data-subnav="ai-settings" class="<?php echo 'ai-settings' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'AI Provider', 'inbox-ai' ); ?></a>
			<a href="#" data-subnav="general-settings" class="<?php echo 'general-settings' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'General', 'inbox-ai' ); ?></a>
			<a href="#" data-subnav="prompts" class="<?php echo 'prompts' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'Prompts', 'inbox-ai' ); ?></a>
			<a href="#" data-subnav="usage" class="<?php echo 'usage' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'Usage & Billing', 'inbox-ai' ); ?></a>
			<a href="#" data-subnav="notifications" class="<?php echo 'notifications' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'Notifications', 'inbox-ai' ); ?></a>
			<a href="#" data-subnav="flamingo" class="<?php echo 'flamingo' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'Import & Migration', 'inbox-ai' ); ?></a>
		</div>
		<div class="inboxai-stack">

			<div class="inboxai-card">
				<div class="inboxai-card__header"><h2><?php esc_html_e( 'Monitored Forms', 'inbox-ai' ); ?></h2></div>
				<div class="inboxai-card__body">
					<?php if ( array() === $cf7_forms ) : ?>
						<p style="color:var(--text-tertiary);font-size:13px;"><?php esc_html_e( 'No Contact Form 7 forms found yet. Create a form to start monitoring submissions.', 'inbox-ai' ); ?></p>
					<?php else : ?>
						<?php foreach ( $cf7_forms as $inboxai_form ) : ?>
							<div class="inboxai-switch-row">
								<div>
									<div class="inboxai-switch-row__text"><?php echo esc_html( $inboxai_form['title'] ); ?></div>
									<div class="inboxai-switch-row__sub">
										<?php
										printf(
											/* translators: %d: number of submissions this calendar month. */
											esc_html( _n( '%d submission this month', '%d submissions this month', $inboxai_form['submissions_this_month'], 'inbox-ai' ) ),
											absint( $inboxai_form['submissions_this_month'] )
										);
										?>
									</div>
								</div>
								<div
									class="inboxai-switch<?php echo $inboxai_form['monitored'] ? ' inboxai-is-on' : ''; ?>"
									data-form-toggle="<?php echo esc_attr( $inboxai_form['title'] ); ?>"
									data-form-id="<?php echo esc_attr( (string) $inboxai_form['id'] ); ?>"
								></div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<div class="inboxai-card">
				<div class="inboxai-card__header"><h2><?php esc_html_e( 'Automatic Processing', 'inbox-ai' ); ?></h2></div>
				<div class="inboxai-card__body">
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Analyze new submissions automatically', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Runs AI analysis as soon as a message arrives', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $general['auto_analyze'] ? ' inboxai-is-on' : ''; ?>" data-field="auto_analyze"></div>
					</div>
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Auto-draft replies for high-confidence messages', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Drafts are never sent without approval', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $general['auto_draft_high_confidence'] ? ' inboxai-is-on' : ''; ?>" data-field="auto_draft_high_confidence"></div>
					</div>
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Auto-archive detected spam', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Applies only above 95% spam confidence', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $general['auto_archive_spam'] ? ' inboxai-is-on' : ''; ?>" data-field="auto_archive_spam"></div>
					</div>
					<div class="inboxai-field" style="margin-top:16px;">
						<label><?php esc_html_e( 'Confidence threshold for "Needs Review"', 'inbox-ai' ); ?></label>
						<input class="inboxai-field__input" type="range" min="0" max="100" data-field="confidence_threshold" value="<?php echo esc_attr( (string) $general['confidence_threshold'] ); ?>">
						<div class="inboxai-field__hint">
							<?php
							printf(
								/* translators: %d: confidence percentage. */
								esc_html__( 'Messages analyzed below %d%% confidence are flagged for manual review.', 'inbox-ai' ),
								absint( $general['confidence_threshold'] )
							);
							?>
						</div>
					</div>
				</div>
			</div>

			<div class="inboxai-card">
				<div class="inboxai-card__header"><h2><?php esc_html_e( 'Data Retention', 'inbox-ai' ); ?></h2></div>
				<div class="inboxai-card__body">
					<div class="inboxai-field">
						<label><?php esc_html_e( 'Keep submissions for', 'inbox-ai' ); ?></label>
						<select class="inboxai-field__input" data-field="retention_period">
							<?php foreach ( $inboxai_retention_labels as $inboxai_value => $inboxai_label ) : ?>
								<option value="<?php echo esc_attr( $inboxai_value ); ?>" <?php selected( $general['retention_period'], $inboxai_value ); ?>><?php echo esc_html( $inboxai_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Delete attachments after reply is sent', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Reduces storage use; text content is kept', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $general['delete_attachments_after_reply'] ? ' inboxai-is-on' : ''; ?>" data-field="delete_attachments_after_reply"></div>
					</div>
				</div>
			</div>

			<div style="display:flex;gap:10px;justify-content:flex-end;">
				<button class="inboxai-btn--primary" id="general-settings-save-btn"><?php esc_html_e( 'Save Changes', 'inbox-ai' ); ?></button>
			</div>

		</div>
	</div>
</section>
