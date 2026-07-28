<?php
/**
 * Settings page — Usage & Billing tab.
 *
 * Entirely read-only; reuses {@see \InboxAI\Database\UsageRepository}
 * against the `inboxai_usage` table the AI Inbox List page's analysis queue
 * will eventually write to. Renders real (currently likely all-zero)
 * figures rather than the mockup's fixed sample numbers.
 *
 * @var string $active_tab      Currently visible tab key.
 * @var array  $usage_totals    {@see \InboxAI\Database\UsageRepository::get_period_totals()}.
 * @var array  $usage_breakdown {@see \InboxAI\Database\UsageRepository::get_cost_breakdown()}.
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$inboxai_breakdown_colors = array( '#3A5CF6', '#8A7EF0', '#1F9254', '#DA8A2E', '#D93B3B' );
$inboxai_breakdown_total  = array_sum( $usage_breakdown );

// Matches AjaxController::USAGE_PERIODS — the page always loads with
// 30_days server-rendered; switching this select re-fetches the figures
// below via `inboxai_get_settings` (see usageBillingTab.js) without a full
// page reload.
$inboxai_period_labels = array(
	'7_days'     => __( 'Last 7 days', 'inbox-ai' ),
	'30_days'    => __( 'Last 30 days', 'inbox-ai' ),
	'90_days'    => __( 'Last 90 days', 'inbox-ai' ),
	'this_month' => __( 'This month', 'inbox-ai' ),
	'1_year'     => __( 'Last 1 year', 'inbox-ai' ),
	'2_years'    => __( 'Last 2 years', 'inbox-ai' ),
	'3_years'    => __( 'Last 3 years', 'inbox-ai' ),
	'5_years'    => __( 'Last 5 years', 'inbox-ai' ),
);

?>
<section class="inboxai-screen<?php echo 'usage' === $active_tab ? ' inboxai-is-active' : ''; ?>" id="screen-usage">
	<div class="inboxai-page-header">
		<div>
			<h1><?php esc_html_e( 'Usage Dashboard', 'inbox-ai' ); ?></h1>
			<p><?php esc_html_e( 'Track AI requests, tokens, and estimated cost across providers.', 'inbox-ai' ); ?></p>
		</div>
		<div class="inboxai-page-header__controls">
			<select class="inboxai-control" id="usage-period-select">
				<?php foreach ( $inboxai_period_labels as $inboxai_period_value => $inboxai_period_label ) : ?>
					<option value="<?php echo esc_attr( $inboxai_period_value ); ?>" <?php selected( '30_days', $inboxai_period_value ); ?>><?php echo esc_html( $inboxai_period_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>
	<div class="inboxai-settings__shell">
		<div class="inboxai-settings__tabs" id="settings-tabs-4">
			<a href="#" data-subnav="ai-settings" class="<?php echo 'ai-settings' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'AI Provider', 'inbox-ai' ); ?></a>
			<a href="#" data-subnav="general-settings" class="<?php echo 'general-settings' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'General', 'inbox-ai' ); ?></a>
			<a href="#" data-subnav="prompts" class="<?php echo 'prompts' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'Prompts', 'inbox-ai' ); ?></a>
			<a href="#" data-subnav="usage" class="<?php echo 'usage' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'Usage & Billing', 'inbox-ai' ); ?></a>
			<a href="#" data-subnav="notifications" class="<?php echo 'notifications' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'Notifications', 'inbox-ai' ); ?></a>
			<a href="#" data-subnav="flamingo" class="<?php echo 'flamingo' === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php esc_html_e( 'Import & Migration', 'inbox-ai' ); ?></a>
		</div>
		<div class="inboxai-stack">

			<div class="inboxai-kpi__strip" style="margin-bottom:0;">
				<div class="inboxai-kpi__card">
					<div class="inboxai-kpi__label"><?php esc_html_e( 'Total requests', 'inbox-ai' ); ?></div>
					<div class="inboxai-kpi__value" id="usage-kpi-requests"><?php echo esc_html( number_format_i18n( $usage_totals['total_requests'] ) ); ?></div>
					<div class="inboxai-kpi__sub"><?php esc_html_e( 'Analysis + reply generation', 'inbox-ai' ); ?></div>
				</div>
				<div class="inboxai-kpi__card">
					<div class="inboxai-kpi__label"><?php esc_html_e( 'Tokens used', 'inbox-ai' ); ?></div>
					<div class="inboxai-kpi__value" id="usage-kpi-tokens"><?php echo esc_html( number_format_i18n( $usage_totals['prompt_tokens'] + $usage_totals['completion_tokens'] ) ); ?></div>
					<div class="inboxai-kpi__sub" id="usage-kpi-tokens-sub">
						<?php
						printf(
							/* translators: 1: prompt tokens, 2: completion tokens */
							esc_html__( '%1$s in · %2$s out', 'inbox-ai' ),
							esc_html( number_format_i18n( $usage_totals['prompt_tokens'] ) ),
							esc_html( number_format_i18n( $usage_totals['completion_tokens'] ) )
						);
						?>
					</div>
				</div>
				<div class="inboxai-kpi__card">
					<div class="inboxai-kpi__label"><?php esc_html_e( 'Estimated cost', 'inbox-ai' ); ?></div>
					<div class="inboxai-kpi__value" id="usage-kpi-cost">$<?php echo esc_html( number_format_i18n( $usage_totals['estimated_cost'], 2 ) ); ?></div>
					<div class="inboxai-kpi__sub" id="usage-kpi-cost-sub">
						<?php
						printf(
							/* translators: %s: selected date-range label, e.g. "Last 30 days" */
							esc_html__( '%s, all providers', 'inbox-ai' ),
							esc_html( $inboxai_period_labels['30_days'] )
						);
						?>
					</div>
				</div>
				<div class="inboxai-kpi__card">
					<div class="inboxai-kpi__label"><?php esc_html_e( 'Monthly budget', 'inbox-ai' ); ?></div>
					<div class="inboxai-kpi__value">—</div>
					<div class="inboxai-kpi__sub"><?php esc_html_e( 'Not configured yet', 'inbox-ai' ); ?></div>
				</div>
			</div>

			<div class="inboxai-card">
				<div class="inboxai-card__header"><h2><?php esc_html_e( 'Cost by Request Type', 'inbox-ai' ); ?></h2></div>
				<div class="inboxai-card__body" id="usage-breakdown-body">
					<?php if ( array() === $usage_breakdown ) : ?>
						<p style="color:var(--text-tertiary);font-size:13px;"><?php esc_html_e( 'No AI usage recorded yet. This fills in once the AI Inbox List page starts analyzing submissions.', 'inbox-ai' ); ?></p>
					<?php else : ?>
						<?php $inboxai_color_index = 0; ?>
						<?php foreach ( $usage_breakdown as $inboxai_label => $inboxai_cost ) : ?>
							<?php
							$inboxai_pct   = $inboxai_breakdown_total > 0 ? round( ( $inboxai_cost / $inboxai_breakdown_total ) * 100 ) : 0;
							$inboxai_color = $inboxai_breakdown_colors[ $inboxai_color_index % count( $inboxai_breakdown_colors ) ];
							++$inboxai_color_index;
							?>
							<div class="inboxai-usage-bar__row">
								<span class="inboxai-usage-bar__label"><?php echo esc_html( ucwords( str_replace( '_', ' ', $inboxai_label ) ) ); ?></span>
								<div class="inboxai-usage-bar__track">
									<div class="inboxai-usage-bar__fill" style="width:<?php echo esc_attr( (string) $inboxai_pct ); ?>%;background:<?php echo esc_attr( $inboxai_color ); ?>;"></div>
								</div>
								<span class="inboxai-usage-bar__value">$<?php echo esc_html( number_format_i18n( $inboxai_cost, 2 ) ); ?></span>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

		</div>
	</div>
</section>
