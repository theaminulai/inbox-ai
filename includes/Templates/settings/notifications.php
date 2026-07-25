<?php
/**
 * Settings page — Notifications tab.
 *
 * @var string $active_tab    Currently visible tab key.
 * @var array  $notifications {@see \CF7AIInbox\Settings\Repository::get_notifications()}.
 *
 * @package CF7AIInbox\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<section class="cf7-ai-inbox-screen<?php echo 'notifications' === $active_tab ? ' cf7-ai-inbox-is-active' : ''; ?>" id="screen-notifications">
	<div class="cf7-ai-inbox-page-header">
		<div>
			<h1><?php esc_html_e( 'Notifications', 'cf7-ai-inbox' ); ?></h1>
			<p><?php esc_html_e( 'Choose how you and your team hear about inbox activity.', 'cf7-ai-inbox' ); ?></p>
		</div>
	</div>
	<div class="cf7-ai-inbox-settings__shell">
		<div class="cf7-ai-inbox-settings__tabs" id="settings-tabs-5">
			<a href="#" data-subnav="ai-settings" class="<?php echo 'ai-settings' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'AI Provider', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="general-settings" class="<?php echo 'general-settings' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'General', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="prompts" class="<?php echo 'prompts' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Prompts', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="usage" class="<?php echo 'usage' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Usage & Billing', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="notifications" class="<?php echo 'notifications' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Notifications', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="flamingo" class="<?php echo 'flamingo' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Import & Migration', 'cf7-ai-inbox' ); ?></a>
		</div>
		<div class="cf7-ai-inbox-stack">

			<div class="cf7-ai-inbox-card">
				<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Email Notifications', 'cf7-ai-inbox' ); ?></h2></div>
				<div class="cf7-ai-inbox-card__body">
					<div class="cf7-ai-inbox-switch-row">
						<div>
							<div class="cf7-ai-inbox-switch-row__text"><?php esc_html_e( 'Notify me on urgent messages', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-switch-row__sub"><?php esc_html_e( 'Sent immediately when priority is Urgent', 'cf7-ai-inbox' ); ?></div>
						</div>
						<div class="cf7-ai-inbox-switch<?php echo $notifications['notify_urgent'] ? ' cf7-ai-inbox-is-on' : ''; ?>" data-field="notify_urgent"></div>
					</div>
					<div class="cf7-ai-inbox-switch-row">
						<div>
							<div class="cf7-ai-inbox-switch-row__text"><?php esc_html_e( 'Daily summary digest', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-switch-row__sub"><?php esc_html_e( 'Sent every morning at 9:00 AM', 'cf7-ai-inbox' ); ?></div>
						</div>
						<div class="cf7-ai-inbox-switch<?php echo $notifications['daily_digest'] ? ' cf7-ai-inbox-is-on' : ''; ?>" data-field="daily_digest"></div>
					</div>
					<div class="cf7-ai-inbox-switch-row">
						<div>
							<div class="cf7-ai-inbox-switch-row__text"><?php esc_html_e( 'Notify on AI analysis failure', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-switch-row__sub"><?php esc_html_e( 'Sent when a submission fails processing', 'cf7-ai-inbox' ); ?></div>
						</div>
						<div class="cf7-ai-inbox-switch<?php echo $notifications['notify_analysis_failure'] ? ' cf7-ai-inbox-is-on' : ''; ?>" data-field="notify_analysis_failure"></div>
					</div>
					<div class="cf7-ai-inbox-switch-row">
						<div>
							<div class="cf7-ai-inbox-switch-row__text"><?php esc_html_e( 'Notify when a reply draft is ready', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-switch-row__sub"><?php esc_html_e( 'For messages awaiting approval', 'cf7-ai-inbox' ); ?></div>
						</div>
						<div class="cf7-ai-inbox-switch<?php echo $notifications['notify_draft_ready'] ? ' cf7-ai-inbox-is-on' : ''; ?>" data-field="notify_draft_ready"></div>
					</div>
				</div>
			</div>

			<div class="cf7-ai-inbox-card">
				<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Slack Integration', 'cf7-ai-inbox' ); ?></h2></div>
				<div class="cf7-ai-inbox-card__body">
					<div class="cf7-ai-inbox-switch-row">
						<div>
							<div class="cf7-ai-inbox-switch-row__text"><?php esc_html_e( 'Send a Slack message for urgent submissions', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-switch-row__sub"><?php esc_html_e( 'Requires a valid HTTPS webhook URL below', 'cf7-ai-inbox' ); ?></div>
						</div>
						<div class="cf7-ai-inbox-switch<?php echo $notifications['slack_enabled'] ? ' cf7-ai-inbox-is-on' : ''; ?>" data-field="slack_enabled"></div>
					</div>
					<div class="cf7-ai-inbox-field" style="margin-top:14px;margin-bottom:0;">
						<label><?php esc_html_e( 'Slack channel webhook URL', 'cf7-ai-inbox' ); ?></label>
						<input class="cf7-ai-inbox-field__input" data-field="slack_webhook_url" value="<?php echo esc_attr( $notifications['slack_webhook_url'] ); ?>" placeholder="https://hooks.slack.com/services/&hellip;">
					</div>
				</div>
			</div>

			<div style="display:flex;justify-content:flex-end;">
				<button class="cf7-ai-inbox-btn--primary" id="notifications-save-btn"><?php esc_html_e( 'Save Notification Settings', 'cf7-ai-inbox' ); ?></button>
			</div>

		</div>
	</div>
</section>
