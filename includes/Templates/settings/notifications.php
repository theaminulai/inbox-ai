<?php
/**
 * Settings page — Notifications tab.
 *
 * @var string $active_tab    Currently visible tab key.
 * @var array  $notifications {@see \InboxAI\Settings\Repository::get_notifications()}.
 * @var array  $inbound       {@see \InboxAI\Settings\Repository::get_inbound()}, plus
 *                             `password_masked`/`has_password`/`imap_available`
 *                             (see `SettingsPage::build_view_model()`).
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
		<?php \InboxAI\Support\Template::render( 'settings/partials/subnav', array( 'active_tab' => $active_tab ) ); ?>
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
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Notify me when a customer replies', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Sent as soon as a reply is pulled in from Inbound Email Replies', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $notifications['notify_customer_reply'] ? ' inboxai-is-on' : ''; ?>" data-field="notify_customer_reply"></div>
					</div>
				</div>
			</div>

			<div class="inboxai-card" id="inbound-email-card">
				<div class="inboxai-card__header">
					<div>
						<h2><?php esc_html_e( 'Inbound Email Replies', 'inbox-ai' ); ?></h2>
						<span class="inboxai-card__muted"><?php esc_html_e( 'When a customer replies to one of your sent emails, check this mailbox and bring their reply back into the submission thread.', 'inbox-ai' ); ?></span>
					</div>
					<span class="inboxai-connected-pill" id="inbound-connected-pill" style="<?php echo $inbound['connected'] ? '' : 'display:none;'; ?>">
						<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>
						<?php esc_html_e( 'Connected', 'inbox-ai' ); ?>
					</span>
				</div>
				<div class="inboxai-card__body">
					<?php if ( ! $inbound['imap_available'] ) : ?>
						<div class="inboxai-notice inboxai-notice--warning" style="margin-bottom:16px;">
							<?php esc_html_e( 'PHP\'s imap extension is not available on this server. Inbound checking cannot run until your host enables it, even if you save settings below.', 'inbox-ai' ); ?>
						</div>
					<?php endif; ?>

					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Check for replies', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Polls the mailbox below at the interval set here', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $inbound['enabled'] ? ' inboxai-is-on' : ''; ?>" data-field="inbound_enabled"></div>
					</div>

					<div class="inboxai-field-row" style="margin-top:14px;">
						<div class="inboxai-field">
							<label><?php esc_html_e( 'Check every', 'inbox-ai' ); ?></label>
							<select class="inboxai-field__input" data-field="inbound_check_interval">
								<?php foreach ( \InboxAI\Settings\Repository::get_inbound_check_interval_options() as $minutes ) : ?>
									<option value="<?php echo esc_attr( (string) $minutes ); ?>" <?php selected( $inbound['check_interval_minutes'], $minutes ); ?>>
										<?php
										/* translators: %d: number of minutes between inbound mail checks */
										echo esc_html( 1 === $minutes ? __( '1 minute', 'inbox-ai' ) : sprintf( __( '%d minutes', 'inbox-ai' ), $minutes ) );
										?>
									</option>
								<?php endforeach; ?>
							</select>
							<div class="inboxai-field__hint"><?php esc_html_e( 'How often WordPress actually reaches this depends on your site\'s own cron setup — a low-traffic site with no real system cron job may check less often than this on quiet days.', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-field">
							<label><?php esc_html_e( 'Mailbox address', 'inbox-ai' ); ?></label>
							<input class="inboxai-field__input" data-field="inbound_username" value="<?php echo esc_attr( $inbound['username'] ); ?>" placeholder="hello@yourdomain.com">
							<div class="inboxai-field__hint"><?php esc_html_e( 'The real mailbox your outbound replies are sent from. Outbound replies get a Reply-To on this same address (with a tracking marker added) so a customer\'s reply lands here.', 'inbox-ai' ); ?></div>
						</div>
					</div>

					<div class="inboxai-field-row">
						<div class="inboxai-field">
							<label><?php esc_html_e( 'IMAP host', 'inbox-ai' ); ?></label>
							<input class="inboxai-field__input" data-field="inbound_host" value="<?php echo esc_attr( $inbound['host'] ); ?>" placeholder="imap.yourhost.com">
						</div>
						<div class="inboxai-field">
							<label><?php esc_html_e( 'Port', 'inbox-ai' ); ?></label>
							<input class="inboxai-field__input" type="number" data-field="inbound_port" value="<?php echo esc_attr( (string) $inbound['port'] ); ?>">
						</div>
					</div>

					<div class="inboxai-field-row">
						<div class="inboxai-field">
							<label><?php esc_html_e( 'Encryption', 'inbox-ai' ); ?></label>
							<select class="inboxai-field__input" data-field="inbound_encryption">
								<option value="ssl" <?php selected( $inbound['encryption'], 'ssl' ); ?>><?php esc_html_e( 'SSL', 'inbox-ai' ); ?></option>
								<option value="tls" <?php selected( $inbound['encryption'], 'tls' ); ?>><?php esc_html_e( 'TLS', 'inbox-ai' ); ?></option>
								<option value="none" <?php selected( $inbound['encryption'], 'none' ); ?>><?php esc_html_e( 'None', 'inbox-ai' ); ?></option>
							</select>
						</div>
						<div class="inboxai-field">
							<label><?php esc_html_e( 'Mailbox folder', 'inbox-ai' ); ?></label>
							<input class="inboxai-field__input" data-field="inbound_mailbox" value="<?php echo esc_attr( $inbound['mailbox'] ); ?>" placeholder="INBOX">
						</div>
					</div>

					<div class="inboxai-field" style="margin-bottom:0;">
						<label><?php esc_html_e( 'Mailbox password', 'inbox-ai' ); ?></label>
						<input class="inboxai-field__input" type="text" data-field="inbound_password" value="<?php echo esc_attr( $inbound['password_masked'] ); ?>" placeholder="<?php esc_attr_e( 'App password or mailbox password', 'inbox-ai' ); ?>" style="font-family:var(--mono);">
						<div class="inboxai-field__hint"><?php esc_html_e( 'Encrypted at rest and never shown in full.', 'inbox-ai' ); ?></div>
					</div>

					<div style="margin-top:14px;">
						<button type="button" class="inboxai-btn--secondary" id="inbound-test-connection"><?php esc_html_e( 'Save settings above, then test connection', 'inbox-ai' ); ?></button>
					</div>
				</div>
			</div>

			<div style="display:flex;justify-content:flex-end;">
				<button class="inboxai-btn--primary" id="notifications-save-btn"><?php esc_html_e( 'Save Notification Settings', 'inbox-ai' ); ?></button>
			</div>

		</div>
	</div>
</section>
