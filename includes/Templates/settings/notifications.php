<?php
/**
 * Settings page — Notifications tab.
 *
 * @var string $active_tab    Currently visible tab key.
 * @var array  $notifications {@see \InboxAI\Settings\Repository::get_notifications()}.
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<section class="inboxai-screen<?php echo 'notifications' === $active_tab ? ' inboxai-is-active' : ''; ?>" id="screen-notifications">
	<div class="inboxai-page-header">
		<div>
			<h1><?php esc_html_e( 'Notifications', 'inbox-ai' ); ?></h1>
			<p><?php esc_html_e( 'Choose how you and your team hear about inbox activity.', 'inbox-ai' ); ?></p>
		</div>
	</div>
	<div class="inboxai-settings__shell">
		<div class="inboxai-settings__tabs" id="settings-tabs-5">
			<a href="#" data-subnav="ai-settings" class="<?php echo 'ai-settings' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'AI Provider', 'inbox-ai' ); ?></a>
			<a href="#" data-subnav="general-settings" class="<?php echo 'general-settings' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'General', 'inbox-ai' ); ?></a>
			<a href="#" data-subnav="prompts" class="<?php echo 'prompts' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'Prompts', 'inbox-ai' ); ?></a>
			<a href="#" data-subnav="usage" class="<?php echo 'usage' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'Usage & Billing', 'inbox-ai' ); ?></a>
			<a href="#" data-subnav="notifications" class="<?php echo 'notifications' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'Notifications', 'inbox-ai' ); ?></a>
			<a href="#" data-subnav="flamingo" class="<?php echo 'flamingo' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'Import & Migration', 'inbox-ai' ); ?></a>
		</div>
		<div class="inboxai-stack">

			<div class="inboxai-card">
				<div class="inboxai-card__header"><h2><?php esc_html_e( 'Email Notifications', 'inbox-ai' ); ?></h2></div>
				<div class="inboxai-card__body">
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Notify me on urgent messages', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Sent immediately when priority is Urgent', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $notifications['notify_urgent'] ? ' inboxai-is-on' : ''; ?>" data-field="notify_urgent"></div>
					</div>
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Daily summary digest', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Sent every morning at 9:00 AM', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $notifications['daily_digest'] ? ' inboxai-is-on' : ''; ?>" data-field="daily_digest"></div>
					</div>
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Notify on AI analysis failure', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Sent when a submission fails processing', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $notifications['notify_analysis_failure'] ? ' inboxai-is-on' : ''; ?>" data-field="notify_analysis_failure"></div>
					</div>
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Notify when a reply draft is ready', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'For messages awaiting approval', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $notifications['notify_draft_ready'] ? ' inboxai-is-on' : ''; ?>" data-field="notify_draft_ready"></div>
					</div>
				</div>
			</div>

			<div class="inboxai-card">
				<div class="inboxai-card__header"><h2><?php esc_html_e( 'Slack Integration', 'inbox-ai' ); ?></h2></div>
				<div class="inboxai-card__body">
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Send a Slack message for urgent submissions', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Requires a valid HTTPS webhook URL below', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $notifications['slack_enabled'] ? ' inboxai-is-on' : ''; ?>" data-field="slack_enabled"></div>
					</div>
					<div class="inboxai-field" style="margin-top:14px;margin-bottom:0;">
						<label><?php esc_html_e( 'Slack channel webhook URL', 'inbox-ai' ); ?></label>
						<input class="inboxai-field__input" data-field="slack_webhook_url" value="<?php echo esc_attr( $notifications['slack_webhook_url'] ); ?>" placeholder="https://hooks.slack.com/services/&hellip;">
					</div>
				</div>
			</div>

			<div style="display:flex;justify-content:flex-end;">
				<button class="inboxai-btn--primary" id="notifications-save-btn"><?php esc_html_e( 'Save Notification Settings', 'inbox-ai' ); ?></button>
			</div>

		</div>
	</div>
</section>
