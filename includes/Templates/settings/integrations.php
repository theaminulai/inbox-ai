<?php
/**
 * Settings page — Integrations tab.
 *
 * Two unrelated kinds of "integration" live here, deliberately split into
 * their own cards, own settings classes, and own storage — nothing shared
 * between them: Slack (a notification channel, {@see \InboxAI\Settings\SlackRepository}
 * for settings, {@see \InboxAI\Services\SlackIntegrationService} for
 * actually sending) and CRM Data Collection (HubSpot/Mailchimp/etc.,
 * {@see \InboxAI\Settings\CrmRepository} — a settings scaffold only;
 * nothing here pushes data anywhere yet, see that class's own docblock).
 *
 * @var string $active_tab Currently visible tab key.
 * @var array  $slack      {@see \InboxAI\Settings\SlackRepository::get()}.
 * @var array  $crm        {@see \InboxAI\Settings\CrmRepository::get()}, plus `api_key_masked`/`has_api_key` (see `SettingsPage::build_view_model()`).
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$inboxai_crm_providers = array(
	'none'      => __( 'Not connected', 'inbox-ai' ),
	'hubspot'   => __( 'HubSpot', 'inbox-ai' ),
	'mailchimp' => __( 'Mailchimp', 'inbox-ai' ),
);

?>
<section class="inboxai-screen<?php echo 'integrations' === $active_tab ? ' inboxai-is-active' : ''; ?>" id="screen-integrations">
	<div class="inboxai-page-header">
		<div>
			<h1><?php esc_html_e( 'Integrations', 'inbox-ai' ); ?></h1>
			<p><?php esc_html_e( 'Connect Inbox AI to the other tools your team already uses.', 'inbox-ai' ); ?></p>
		</div>
	</div>
	<div class="inboxai-settings__shell">
		<?php \InboxAI\Support\Template::render( 'settings/partials/subnav', array( 'active_tab' => $active_tab ) ); ?>
		<div class="inboxai-stack">

			<div class="inboxai-card">
				<div class="inboxai-card__header"><h2><?php esc_html_e( 'Slack Integration', 'inbox-ai' ); ?></h2></div>
				<div class="inboxai-card__body">
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Send a Slack message for urgent submissions', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Requires a valid HTTPS webhook URL below', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $slack['enabled'] ? ' inboxai-is-on' : ''; ?>" data-field="slack_enabled"></div>
					</div>
					<div class="inboxai-field" style="margin-top:14px;margin-bottom:0;">
						<label><?php esc_html_e( 'Slack channel webhook URL', 'inbox-ai' ); ?></label>
						<input class="inboxai-field__input" id="slack-webhook-url" data-field="slack_webhook_url" value="<?php echo esc_attr( $slack['webhook_url'] ); ?>" placeholder="https://hooks.slack.com/services/&hellip;">
						<div class="inboxai-field__hint"><?php esc_html_e( 'Create an Incoming Webhook for a channel in your Slack workspace, then paste its URL here.', 'inbox-ai' ); ?></div>
					</div>
					<div style="margin-top:14px;">
						<button type="button" class="inboxai-btn--secondary" id="slack-test-btn"><?php esc_html_e( 'Send test message', 'inbox-ai' ); ?></button>
					</div>
				</div>
			</div>

			<div class="inboxai-card">
				<div class="inboxai-card__header">
					<div>
						<h2><?php esc_html_e( 'CRM Data Collection', 'inbox-ai' ); ?></h2>
						<span class="inboxai-card__muted"><?php esc_html_e( 'Save connection details for a CRM so they\'re ready once automatic syncing is available.', 'inbox-ai' ); ?></span>
					</div>
				</div>
				<div class="inboxai-card__body">
					<div class="inboxai-notice inboxai-notice--info" style="margin-bottom:16px;">
						<?php esc_html_e( 'Coming soon: Inbox AI doesn\'t push submissions to a CRM automatically yet. Your provider and API key are saved securely below so nothing needs re-entering once syncing ships.', 'inbox-ai' ); ?>
					</div>
					<div class="inboxai-field-row">
						<div class="inboxai-field">
							<label><?php esc_html_e( 'CRM provider', 'inbox-ai' ); ?></label>
							<select class="inboxai-field__input" data-field="crm_provider">
								<?php foreach ( $inboxai_crm_providers as $inboxai_id => $inboxai_label ) : ?>
									<option value="<?php echo esc_attr( $inboxai_id ); ?>" <?php selected( $crm['provider'], $inboxai_id ); ?>><?php echo esc_html( $inboxai_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="inboxai-field">
							<label><?php esc_html_e( 'API key', 'inbox-ai' ); ?></label>
							<input
								class="inboxai-field__input"
								type="text"
								data-field="crm_api_key"
								value="<?php echo esc_attr( $crm['api_key_masked'] ); ?>"
								placeholder="<?php esc_attr_e( 'Paste your CRM API key', 'inbox-ai' ); ?>"
								style="font-family:var(--mono);"
							>
						</div>
					</div>
					<div class="inboxai-field__hint"><?php esc_html_e( 'Encrypted at rest and never shown in full.', 'inbox-ai' ); ?></div>
				</div>
			</div>

			<div style="display:flex;justify-content:flex-end;">
				<button class="inboxai-btn--primary" id="integrations-save-btn"><?php esc_html_e( 'Save Integration Settings', 'inbox-ai' ); ?></button>
			</div>

		</div>
	</div>
</section>
