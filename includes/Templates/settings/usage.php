<?php
/**
 * Settings page — Usage & Billing tab.
 *
 * Entirely read-only; reuses {@see \CF7AIInbox\Database\UsageRepository}
 * against the `cf7ai_usage` table the AI Inbox List page's analysis queue
 * will eventually write to. Renders real (currently likely all-zero)
 * figures rather than the mockup's fixed sample numbers.
 *
 * @var string $active_tab      Currently visible tab key.
 * @var array  $usage_totals    {@see \CF7AIInbox\Database\UsageRepository::get_period_totals()}.
 * @var array  $usage_breakdown {@see \CF7AIInbox\Database\UsageRepository::get_cost_breakdown()}.
 *
 * @package CF7AIInbox\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cf7ai_breakdown_colors = array( '#3A5CF6', '#8A7EF0', '#1F9254', '#DA8A2E', '#D93B3B' );
$cf7ai_breakdown_total  = array_sum( $usage_breakdown );

// Matches AjaxController::USAGE_PERIODS — the page always loads with
// 30_days server-rendered; switching this select re-fetches the figures
// below via `cf7ai_get_settings` (see usageBillingTab.js) without a full
// page reload.
$cf7ai_period_labels = array(
	'7_days'     => __( 'Last 7 days', 'cf7-ai-inbox' ),
	'30_days'    => __( 'Last 30 days', 'cf7-ai-inbox' ),
	'90_days'    => __( 'Last 90 days', 'cf7-ai-inbox' ),
	'this_month' => __( 'This month', 'cf7-ai-inbox' ),
	'1_year'     => __( 'Last 1 year', 'cf7-ai-inbox' ),
	'2_years'    => __( 'Last 2 years', 'cf7-ai-inbox' ),
	'3_years'    => __( 'Last 3 years', 'cf7-ai-inbox' ),
	'5_years'    => __( 'Last 5 years', 'cf7-ai-inbox' ),
);

?>
<section class="cf7-ai-inbox-screen<?php echo 'usage' === $active_tab ? ' cf7-ai-inbox-is-active' : ''; ?>" id="screen-usage">
	<div class="cf7-ai-inbox-page-header">
		<div>
			<h1><?php esc_html_e( 'Usage Dashboard', 'cf7-ai-inbox' ); ?></h1>
			<p><?php esc_html_e( 'Track AI requests, tokens, and estimated cost across providers.', 'cf7-ai-inbox' ); ?></p>
		</div>
		<div class="cf7-ai-inbox-page-header__controls">
			<select class="cf7-ai-inbox-control" id="usage-period-select">
				<?php foreach ( $cf7ai_period_labels as $cf7ai_period_value => $cf7ai_period_label ) : ?>
					<option value="<?php echo esc_attr( $cf7ai_period_value ); ?>" <?php selected( '30_days', $cf7ai_period_value ); ?>><?php echo esc_html( $cf7ai_period_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>
	<div class="cf7-ai-inbox-settings__shell">
		<div class="cf7-ai-inbox-settings__tabs" id="settings-tabs-4">
			<a href="#" data-subnav="ai-settings" class="<?php echo 'ai-settings' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'AI Provider', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="general-settings" class="<?php echo 'general-settings' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'General', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="prompts" class="<?php echo 'prompts' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Prompts', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="usage" class="<?php echo 'usage' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Usage & Billing', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="notifications" class="<?php echo 'notifications' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Notifications', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="flamingo" class="<?php echo 'flamingo' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Import & Migration', 'cf7-ai-inbox' ); ?></a>
		</div>
		<div class="cf7-ai-inbox-stack">

			<div class="cf7-ai-inbox-kpi__strip" style="margin-bottom:0;">
				<div class="cf7-ai-inbox-kpi__card">
					<div class="cf7-ai-inbox-kpi__label"><?php esc_html_e( 'Total requests', 'cf7-ai-inbox' ); ?></div>
					<div class="cf7-ai-inbox-kpi__value" id="usage-kpi-requests"><?php echo esc_html( number_format_i18n( $usage_totals['total_requests'] ) ); ?></div>
					<div class="cf7-ai-inbox-kpi__sub"><?php esc_html_e( 'Analysis + reply generation', 'cf7-ai-inbox' ); ?></div>
				</div>
				<div class="cf7-ai-inbox-kpi__card">
					<div class="cf7-ai-inbox-kpi__label"><?php esc_html_e( 'Tokens used', 'cf7-ai-inbox' ); ?></div>
					<div class="cf7-ai-inbox-kpi__value" id="usage-kpi-tokens"><?php echo esc_html( number_format_i18n( $usage_totals['prompt_tokens'] + $usage_totals['completion_tokens'] ) ); ?></div>
					<div class="cf7-ai-inbox-kpi__sub" id="usage-kpi-tokens-sub">
						<?php
						printf(
							/* translators: 1: prompt tokens, 2: completion tokens */
							esc_html__( '%1$s in · %2$s out', 'cf7-ai-inbox' ),
							esc_html( number_format_i18n( $usage_totals['prompt_tokens'] ) ),
							esc_html( number_format_i18n( $usage_totals['completion_tokens'] ) )
						);
						?>
					</div>
				</div>
				<div class="cf7-ai-inbox-kpi__card">
					<div class="cf7-ai-inbox-kpi__label"><?php esc_html_e( 'Estimated cost', 'cf7-ai-inbox' ); ?></div>
					<div class="cf7-ai-inbox-kpi__value" id="usage-kpi-cost">$<?php echo esc_html( number_format_i18n( $usage_totals['estimated_cost'], 2 ) ); ?></div>
					<div class="cf7-ai-inbox-kpi__sub" id="usage-kpi-cost-sub">
						<?php
						printf(
							/* translators: %s: selected date-range label, e.g. "Last 30 days" */
							esc_html__( '%s, all providers', 'cf7-ai-inbox' ),
							esc_html( $cf7ai_period_labels['30_days'] )
						);
						?>
					</div>
				</div>
				<div class="cf7-ai-inbox-kpi__card">
					<div class="cf7-ai-inbox-kpi__label"><?php esc_html_e( 'Monthly budget', 'cf7-ai-inbox' ); ?></div>
					<div class="cf7-ai-inbox-kpi__value">—</div>
					<div class="cf7-ai-inbox-kpi__sub"><?php esc_html_e( 'Not configured yet', 'cf7-ai-inbox' ); ?></div>
				</div>
			</div>

			<div class="cf7-ai-inbox-card">
				<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Cost by Request Type', 'cf7-ai-inbox' ); ?></h2></div>
				<div class="cf7-ai-inbox-card__body" id="usage-breakdown-body">
					<?php if ( array() === $usage_breakdown ) : ?>
						<p style="color:var(--text-tertiary);font-size:13px;"><?php esc_html_e( 'No AI usage recorded yet. This fills in once the AI Inbox List page starts analyzing submissions.', 'cf7-ai-inbox' ); ?></p>
					<?php else : ?>
						<?php $cf7ai_color_index = 0; ?>
						<?php foreach ( $usage_breakdown as $cf7ai_label => $cf7ai_cost ) : ?>
							<?php
							$cf7ai_pct   = $cf7ai_breakdown_total > 0 ? round( ( $cf7ai_cost / $cf7ai_breakdown_total ) * 100 ) : 0;
							$cf7ai_color = $cf7ai_breakdown_colors[ $cf7ai_color_index % count( $cf7ai_breakdown_colors ) ];
							++$cf7ai_color_index;
							?>
							<div class="cf7-ai-inbox-usage-bar__row">
								<span class="cf7-ai-inbox-usage-bar__label"><?php echo esc_html( ucwords( str_replace( '_', ' ', $cf7ai_label ) ) ); ?></span>
								<div class="cf7-ai-inbox-usage-bar__track">
									<div class="cf7-ai-inbox-usage-bar__fill" style="width:<?php echo esc_attr( (string) $cf7ai_pct ); ?>%;background:<?php echo esc_attr( $cf7ai_color ); ?>;"></div>
								</div>
								<span class="cf7-ai-inbox-usage-bar__value">$<?php echo esc_html( number_format_i18n( $cf7ai_cost, 2 ) ); ?></span>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

		</div>
	</div>
</section>
