/**
 * Settings page — Usage & Billing tab.
 *
 * Mostly read-only and server-rendered (see
 * includes/Templates/settings/usage.php) on first paint — the one
 * interactive piece is the date-range `<select>` in the page header, which
 * re-fetches the KPI figures and cost breakdown via `cf7ai_get_settings`
 * (see AjaxController::get_settings()) and re-renders them in place, no
 * full page reload.
 */

import { cf7aiAjax } from '../shared/api.js';

/**
 * Matches the colors `includes/Templates/settings/usage.php` cycles
 * through for each breakdown bar, in the same order.
 */
const BREAKDOWN_COLORS = [
	'#3A5CF6',
	'#8A7EF0',
	'#1F9254',
	'#DA8A2E',
	'#D93B3B',
];

function formatNumber( n ) {
	return Number( n || 0 ).toLocaleString();
}

function formatMoney( n ) {
	return Number( n || 0 ).toFixed( 2 );
}

function prettifyLabel( label ) {
	return label
		.replace( /_/g, ' ' )
		.replace( /\b\w/g, ( char ) => char.toUpperCase() );
}

function renderTotals( totals, periodLabel ) {
	const requestsEl = document.getElementById( 'usage-kpi-requests' );
	const tokensEl = document.getElementById( 'usage-kpi-tokens' );
	const tokensSubEl = document.getElementById( 'usage-kpi-tokens-sub' );
	const costEl = document.getElementById( 'usage-kpi-cost' );
	const costSubEl = document.getElementById( 'usage-kpi-cost-sub' );

	if ( ! totals ) {
		return;
	}

	if ( requestsEl ) {
		requestsEl.textContent = formatNumber( totals.total_requests );
	}

	if ( tokensEl ) {
		tokensEl.textContent = formatNumber(
			totals.prompt_tokens + totals.completion_tokens
		);
	}

	if ( tokensSubEl ) {
		tokensSubEl.textContent = `${ formatNumber(
			totals.prompt_tokens
		) } in · ${ formatNumber( totals.completion_tokens ) } out`;
	}

	if ( costEl ) {
		costEl.textContent = `$${ formatMoney( totals.estimated_cost ) }`;
	}

	if ( costSubEl && periodLabel ) {
		costSubEl.textContent = `${ periodLabel }, all providers`;
	}
}

function renderBreakdown( breakdown ) {
	const body = document.getElementById( 'usage-breakdown-body' );

	if ( ! body ) {
		return;
	}

	const entries = Object.entries( breakdown || {} );

	if ( 0 === entries.length ) {
		body.innerHTML =
			'<p style="color:var(--text-tertiary);font-size:13px;">No AI usage recorded yet. This fills in once the AI Inbox List page starts analyzing submissions.</p>';
		return;
	}

	const total = entries.reduce( ( sum, [ , cost ] ) => sum + cost, 0 );

	body.innerHTML = entries
		.map( ( [ label, cost ], index ) => {
			const pct = total > 0 ? Math.round( ( cost / total ) * 100 ) : 0;
			const color = BREAKDOWN_COLORS[ index % BREAKDOWN_COLORS.length ];

			return (
				'<div class="cf7-ai-inbox-usage-bar__row">' +
				`<span class="cf7-ai-inbox-usage-bar__label">${ prettifyLabel(
					label
				) }</span>` +
				'<div class="cf7-ai-inbox-usage-bar__track">' +
				`<div class="cf7-ai-inbox-usage-bar__fill" style="width:${ pct }%;background:${ color };"></div>` +
				'</div>' +
				`<span class="cf7-ai-inbox-usage-bar__value">$${ formatMoney(
					cost
				) }</span>` +
				'</div>'
			);
		} )
		.join( '' );
}

export function initUsageBillingTab() {
	const select = document.getElementById( 'usage-period-select' );

	if ( ! select ) {
		return;
	}

	select.addEventListener( 'change', () => {
		const periodLabel =
			select.options[ select.selectedIndex ]?.textContent || '';

		select.disabled = true;

		cf7aiAjax( 'cf7ai_get_settings', {
			tab: 'usage',
			period: select.value,
		} )
			.then( ( data ) => {
				if ( data && data.usage ) {
					renderTotals( data.usage.totals, periodLabel );
					renderBreakdown( data.usage.breakdown );
				}
			} )
			.catch( () => {
				// Left as-is on failure — the previously rendered figures
				// stay visible rather than being replaced with an error
				// state, since this is a read-only dashboard.
			} )
			.finally( () => {
				select.disabled = false;
			} );
	} );
}
