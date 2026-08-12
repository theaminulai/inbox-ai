<?php
/**
 * Settings page — AI Provider tab.
 *
 * @var string  $active_tab     Currently visible tab key.
 * @var array   $provider       {@see \InboxAI\Settings\Repository::get_provider()}.
 * @var string  $api_key_masked Masked API key display value, or ''.
 * @var bool    $has_api_key    Whether a key is currently stored.
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The 'models' lists here are the same fallback ids each provider class
// returns from get_models() when a live "list models" API call hasn't
// been made yet (see OpenAIProvider::get_models(),
// AnthropicProvider::get_models(), GeminiProvider::get_models()) — kept
// here too so the Model dropdown shows the right provider's models
// immediately on switching cards, before "Test Connection" replaces them
// with the real, live list for that key.
$inboxai_providers = array(
	'openai'    => array(
		'label'  => 'OpenAI',
		'sub'    => 'GPT-4.1, GPT-4.1 Mini, GPT-4o',
		'bg'     => '#EAF3FF',
		'fg'     => '#10A37F',
		'letter' => 'AI',
		'models' => array( 'gpt-4.1-mini', 'gpt-4.1', 'gpt-4o' ),
	),
	'anthropic' => array(
		'label'  => 'Anthropic',
		'sub'    => 'Claude Sonnet, Claude Haiku',
		'bg'     => '#FBEFE9',
		'fg'     => '#D97757',
		'letter' => 'AN',
		'models' => array( 'claude-sonnet-4-5', 'claude-haiku-4-5' ),
	),
	'google'    => array(
		'label'  => 'Google',
		'sub'    => 'Gemini 2.5 Flash, Gemini 2.5 Pro',
		'bg'     => '#EEF3FF',
		'fg'     => '#4285F4',
		'letter' => 'G',
		'models' => array( 'gemini-2.5-flash', 'gemini-2.5-pro' ),
	),
);

$inboxai_selected_provider = $provider['provider'];
$inboxai_provider_label    = $inboxai_providers[ $inboxai_selected_provider ]['label'] ?? 'Provider';

?>
<section class="inboxai-screen<?php echo 'ai-settings' === $active_tab ? ' inboxai-is-active' : ''; ?>" id="screen-ai-settings">
	<div class="inboxai-page-header">
		<div>
			<h1><?php esc_html_e( 'AI Provider Settings', 'inbox-ai' ); ?></h1>
			<p><?php esc_html_e( 'Connect and configure the AI provider used to analyze submissions.', 'inbox-ai' ); ?></p>
		</div>
	</div>
	<div class="inboxai-settings__shell">
		<?php \InboxAI\Support\Template::render( 'settings/partials/subnav', array( 'active_tab' => $active_tab ) ); ?>
		<div class="inboxai-stack">

			<div class="inboxai-card">
				<div class="inboxai-card__header"><h2><?php esc_html_e( 'Choose a provider', 'inbox-ai' ); ?></h2></div>
				<div class="inboxai-card__body">
					<?php foreach ( $inboxai_providers as $inboxai_id => $inboxai_meta ) : ?>
						<div
							class="inboxai-provider__option<?php echo $inboxai_id === $inboxai_selected_provider ? ' inboxai-is-selected' : ''; ?>"
							data-provider="<?php echo esc_attr( $inboxai_id ); ?>"
							data-provider-label="<?php echo esc_attr( $inboxai_meta['label'] ); ?>"
							data-models="<?php echo esc_attr( wp_json_encode( $inboxai_meta['models'] ) ); ?>"
						>
							<div class="inboxai-provider__logo" style="background:<?php echo esc_attr( $inboxai_meta['bg'] ); ?>;color:<?php echo esc_attr( $inboxai_meta['fg'] ); ?>;"><?php echo esc_html( $inboxai_meta['letter'] ); ?></div>
							<div>
								<div style="font-weight:700;font-size:13.5px;"><?php echo esc_html( $inboxai_meta['label'] ); ?></div>
								<div style="font-size:12px;color:var(--text-tertiary);"><?php echo esc_html( $inboxai_meta['sub'] ); ?></div>
							</div>
							<div class="inboxai-provider__radio<?php echo $inboxai_id === $inboxai_selected_provider ? ' inboxai-is-checked' : ''; ?>"></div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="inboxai-card">
				<div class="inboxai-card__header">
					<h2><span id="settings-provider-config-label"><?php echo esc_html( $inboxai_provider_label ); ?></span> <?php esc_html_e( 'Configuration', 'inbox-ai' ); ?></h2>
					<span class="inboxai-connected-pill" id="settings-provider-pill" style="<?php echo $has_api_key ? '' : 'display:none;'; ?>">
						<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>
						<?php esc_html_e( 'Connected', 'inbox-ai' ); ?>
					</span>
				</div>
				<div class="inboxai-card__body">
					<div class="inboxai-field">
						<label><?php esc_html_e( 'API key', 'inbox-ai' ); ?></label>
						<input
							class="inboxai-field__input"
							type="text"
							data-field="api_key"
							value="<?php echo esc_attr( $api_key_masked ); ?>"
							placeholder="<?php esc_attr_e( 'sk-…', 'inbox-ai' ); ?>"
							style="font-family:var(--mono);"
						>
						<div class="inboxai-field__hint"><?php esc_html_e( 'Your key is encrypted at rest and never shown in full. It is not exposed in logs or error messages.', 'inbox-ai' ); ?></div>
					</div>
					<div class="inboxai-field-row">
						<div class="inboxai-field" style="margin-bottom:0;">
							<label><?php esc_html_e( 'Model', 'inbox-ai' ); ?></label>
							<select class="inboxai-field__input" data-field="model">
								<option value="<?php echo esc_attr( $provider['model'] ); ?>" selected><?php echo esc_html( $provider['model'] ); ?></option>
							</select>
						</div>
						<div class="inboxai-field" style="margin-bottom:0;">
							<label><?php esc_html_e( 'Request timeout', 'inbox-ai' ); ?></label>
							<select class="inboxai-field__input" data-field="request_timeout">
								<?php foreach ( array( 30, 60, 90 ) as $inboxai_seconds ) : ?>
									<option value="<?php echo esc_attr( (string) $inboxai_seconds ); ?>" <?php selected( $provider['request_timeout'], $inboxai_seconds ); ?>>
										<?php
										printf(
											/* translators: %d: number of seconds. */
											esc_html__( '%d seconds', 'inbox-ai' ),
											absint( $inboxai_seconds )
										);
										?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div style="display:flex;gap:10px;margin-top:18px;">
						<button class="inboxai-btn--secondary" id="settings-test-connection"><?php esc_html_e( 'Test Connection', 'inbox-ai' ); ?></button>
						<button class="inboxai-btn--primary" id="settings-save-provider"><?php esc_html_e( 'Save Changes', 'inbox-ai' ); ?></button>
					</div>
				</div>
			</div>

			<div class="inboxai-card">
				<div class="inboxai-card__header"><h2><?php esc_html_e( 'Fallback Behavior', 'inbox-ai' ); ?></h2></div>
				<div class="inboxai-card__body">
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Retry failed requests automatically', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Up to 3 attempts with exponential backoff', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $provider['auto_retry'] ? ' inboxai-is-on' : ''; ?>" data-field="auto_retry"></div>
					</div>
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Fall back to manual review on repeated failure', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Marks the submission as Needs Review instead of Failed', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $provider['fallback_manual_review'] ? ' inboxai-is-on' : ''; ?>" data-field="fallback_manual_review"></div>
					</div>
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Send email alert on provider outage', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Notifies site admins if the provider is unreachable', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $provider['email_alert_outage'] ? ' inboxai-is-on' : ''; ?>" data-field="email_alert_outage"></div>
					</div>
				</div>
			</div>

		</div>
	</div>
</section>
