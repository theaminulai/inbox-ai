<?php
/**
 * Settings page — AI Provider tab.
 *
 * @var string  $active_tab     Currently visible tab key.
 * @var array   $provider       {@see \CF7AIInbox\Settings\Repository::get_provider()}.
 * @var string  $api_key_masked Masked API key display value, or ''.
 * @var bool    $has_api_key    Whether a key is currently stored.
 *
 * @package CF7AIInbox\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cf7ai_providers = array(
	'openai'    => array(
		'label'  => 'OpenAI',
		'sub'    => 'GPT-4.1, GPT-4.1 Mini, GPT-4o',
		'bg'     => '#EAF3FF',
		'fg'     => '#10A37F',
		'letter' => 'AI',
	),
	'anthropic' => array(
		'label'  => 'Anthropic',
		'sub'    => 'Claude Sonnet, Claude Haiku',
		'bg'     => '#FBEFE9',
		'fg'     => '#D97757',
		'letter' => 'AN',
	),
	'google'    => array(
		'label'  => 'Google',
		'sub'    => 'Gemini 2.5 Flash, Gemini 2.5 Pro',
		'bg'     => '#EEF3FF',
		'fg'     => '#4285F4',
		'letter' => 'G',
	),
);

$cf7ai_selected_provider = $provider['provider'];
$cf7ai_provider_label    = $cf7ai_providers[ $cf7ai_selected_provider ]['label'] ?? 'Provider';

?>
<section class="cf7-ai-inbox-screen<?php echo 'ai-settings' === $active_tab ? ' cf7-ai-inbox-is-active' : ''; ?>" id="screen-ai-settings">
	<div class="cf7-ai-inbox-page-header">
		<div>
			<h1><?php esc_html_e( 'AI Provider Settings', 'cf7-ai-inbox' ); ?></h1>
			<p><?php esc_html_e( 'Connect and configure the AI provider used to analyze submissions.', 'cf7-ai-inbox' ); ?></p>
		</div>
	</div>
	<div class="cf7-ai-inbox-settings__shell">
		<div class="cf7-ai-inbox-settings__tabs" id="settings-tabs">
			<a href="#" data-subnav="ai-settings" class="<?php echo 'ai-settings' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'AI Provider', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="general-settings" class="<?php echo 'general-settings' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'General', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="prompts" class="<?php echo 'prompts' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Prompts', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="usage" class="<?php echo 'usage' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Usage & Billing', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="notifications" class="<?php echo 'notifications' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Notifications', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="flamingo" class="<?php echo 'flamingo' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Import & Migration', 'cf7-ai-inbox' ); ?></a>
		</div>
		<div class="cf7-ai-inbox-stack">

			<div class="cf7-ai-inbox-card">
				<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Choose a provider', 'cf7-ai-inbox' ); ?></h2></div>
				<div class="cf7-ai-inbox-card__body">
					<?php foreach ( $cf7ai_providers as $cf7ai_id => $cf7ai_meta ) : ?>
						<div
							class="cf7-ai-inbox-provider__option<?php echo $cf7ai_id === $cf7ai_selected_provider ? ' cf7-ai-inbox-is-selected' : ''; ?>"
							data-provider="<?php echo esc_attr( $cf7ai_id ); ?>"
						>
							<div class="cf7-ai-inbox-provider__logo" style="background:<?php echo esc_attr( $cf7ai_meta['bg'] ); ?>;color:<?php echo esc_attr( $cf7ai_meta['fg'] ); ?>;"><?php echo esc_html( $cf7ai_meta['letter'] ); ?></div>
							<div>
								<div style="font-weight:700;font-size:13.5px;"><?php echo esc_html( $cf7ai_meta['label'] ); ?></div>
								<div style="font-size:12px;color:var(--text-tertiary);"><?php echo esc_html( $cf7ai_meta['sub'] ); ?></div>
							</div>
							<div class="cf7-ai-inbox-provider__radio<?php echo $cf7ai_id === $cf7ai_selected_provider ? ' cf7-ai-inbox-is-checked' : ''; ?>"></div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="cf7-ai-inbox-card">
				<div class="cf7-ai-inbox-card__header">
					<h2><?php echo esc_html( $cf7ai_provider_label ); ?> <?php esc_html_e( 'Configuration', 'cf7-ai-inbox' ); ?></h2>
					<span class="cf7-ai-inbox-connected-pill" id="settings-provider-pill" style="<?php echo $has_api_key ? '' : 'display:none;'; ?>">
						<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>
						<?php esc_html_e( 'Connected', 'cf7-ai-inbox' ); ?>
					</span>
				</div>
				<div class="cf7-ai-inbox-card__body">
					<div class="cf7-ai-inbox-field">
						<label><?php esc_html_e( 'API key', 'cf7-ai-inbox' ); ?></label>
						<input
							class="cf7-ai-inbox-field__input"
							type="password"
							data-field="api_key"
							value="<?php echo esc_attr( $api_key_masked ); ?>"
							placeholder="<?php esc_attr_e( 'sk-…', 'cf7-ai-inbox' ); ?>"
							style="font-family:var(--mono);"
						>
						<div class="cf7-ai-inbox-field__hint"><?php esc_html_e( 'Your key is encrypted at rest and never shown in full. It is not exposed in logs or error messages.', 'cf7-ai-inbox' ); ?></div>
					</div>
					<div class="cf7-ai-inbox-field-row">
						<div class="cf7-ai-inbox-field" style="margin-bottom:0;">
							<label><?php esc_html_e( 'Model', 'cf7-ai-inbox' ); ?></label>
							<select class="cf7-ai-inbox-field__input" data-field="model">
								<option value="<?php echo esc_attr( $provider['model'] ); ?>" selected><?php echo esc_html( $provider['model'] ); ?></option>
							</select>
						</div>
						<div class="cf7-ai-inbox-field" style="margin-bottom:0;">
							<label><?php esc_html_e( 'Request timeout', 'cf7-ai-inbox' ); ?></label>
							<select class="cf7-ai-inbox-field__input" data-field="request_timeout">
								<?php foreach ( array( 30, 60, 90 ) as $cf7ai_seconds ) : ?>
									<option value="<?php echo esc_attr( (string) $cf7ai_seconds ); ?>" <?php selected( $provider['request_timeout'], $cf7ai_seconds ); ?>>
										<?php
										printf(
											/* translators: %d: number of seconds. */
											esc_html__( '%d seconds', 'cf7-ai-inbox' ),
											absint( $cf7ai_seconds )
										);
										?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div style="display:flex;gap:10px;margin-top:18px;">
						<button class="cf7-ai-inbox-btn--secondary" id="settings-test-connection"><?php esc_html_e( 'Test Connection', 'cf7-ai-inbox' ); ?></button>
						<button class="cf7-ai-inbox-btn--primary" id="settings-save-provider"><?php esc_html_e( 'Save Changes', 'cf7-ai-inbox' ); ?></button>
					</div>
				</div>
			</div>

			<div class="cf7-ai-inbox-card">
				<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Fallback Behavior', 'cf7-ai-inbox' ); ?></h2></div>
				<div class="cf7-ai-inbox-card__body">
					<div class="cf7-ai-inbox-switch-row">
						<div>
							<div class="cf7-ai-inbox-switch-row__text"><?php esc_html_e( 'Retry failed requests automatically', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-switch-row__sub"><?php esc_html_e( 'Up to 3 attempts with exponential backoff', 'cf7-ai-inbox' ); ?></div>
						</div>
						<div class="cf7-ai-inbox-switch<?php echo $provider['auto_retry'] ? ' cf7-ai-inbox-is-on' : ''; ?>" data-field="auto_retry"></div>
					</div>
					<div class="cf7-ai-inbox-switch-row">
						<div>
							<div class="cf7-ai-inbox-switch-row__text"><?php esc_html_e( 'Fall back to manual review on repeated failure', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-switch-row__sub"><?php esc_html_e( 'Marks the submission as Needs Review instead of Failed', 'cf7-ai-inbox' ); ?></div>
						</div>
						<div class="cf7-ai-inbox-switch<?php echo $provider['fallback_manual_review'] ? ' cf7-ai-inbox-is-on' : ''; ?>" data-field="fallback_manual_review"></div>
					</div>
					<div class="cf7-ai-inbox-switch-row">
						<div>
							<div class="cf7-ai-inbox-switch-row__text"><?php esc_html_e( 'Send email alert on provider outage', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-switch-row__sub"><?php esc_html_e( 'Notifies site admins if the provider is unreachable', 'cf7-ai-inbox' ); ?></div>
						</div>
						<div class="cf7-ai-inbox-switch<?php echo $provider['email_alert_outage'] ? ' cf7-ai-inbox-is-on' : ''; ?>" data-field="email_alert_outage"></div>
					</div>
				</div>
			</div>

		</div>
	</div>
</section>
