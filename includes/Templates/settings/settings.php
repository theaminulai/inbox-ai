<?php
/**
 * Settings page shell.
 *
 * All six subtabs render into this one page load (matching how
 * `html/settings.html` ships all six sections in one file) so the existing
 * `showSettingsTab()`-style client-side switch (now `tabs.js`) can toggle
 * between them instantly, with no server round trip. Each tab keeps its own
 * copy of the `.inboxai-settings__tabs` subnav (it doubles as the left
 * column of that tab's two-column CSS grid layout, not just navigation) —
 * only the page-level modal and toast container are shared, matching the
 * mockup exactly.
 *
 * Expects, via {@see \InboxAI\Support\Template::render()}, everything
 * {@see \InboxAI\Admin\Pages\SettingsPage::build_view_model()} builds,
 * plus:
 *
 * @var string $active_tab Which of the six tabs should be visible on first
 *                          paint (`ai-settings`, `general-settings`,
 *                          `prompts`, `usage`, `notifications`, `flamingo`).
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Re-collected explicitly (rather than via get_defined_vars()) so the
// six nested Template::render() calls below only ever receive the exact
// view-model keys this page defines, not whatever else happens to be in
// this template's local scope.
$inboxai_settings_vars = array(
	'active_tab'      => $active_tab,
	'provider'        => $provider,
	'api_key_masked'  => $api_key_masked,
	'has_api_key'     => $has_api_key,
	'general'         => $general,
	'cf7_forms'       => $cf7_forms,
	'categories'      => $categories,
	'prompts'         => $prompts,
	'notifications'   => $notifications,
	'usage_totals'    => $usage_totals,
	'usage_breakdown' => $usage_breakdown,
	'flamingo_active' => $flamingo_active,
);

?>
<div class="wrap inboxai-wrap">
	<div class="inboxai-main" id="main" data-page="settings">

		<?php
		\InboxAI\Support\Template::render( 'settings/ai-provider', $inboxai_settings_vars );
		\InboxAI\Support\Template::render( 'settings/general', $inboxai_settings_vars );
		\InboxAI\Support\Template::render( 'settings/prompts', $inboxai_settings_vars );
		\InboxAI\Support\Template::render( 'settings/usage', $inboxai_settings_vars );
		\InboxAI\Support\Template::render( 'settings/notifications', $inboxai_settings_vars );
		\InboxAI\Support\Template::render( 'settings/flamingo', $inboxai_settings_vars );
		?>

	</div>
</div>

<div class="inboxai-modal" id="import-modal-overlay" style="display:none;">
	<div class="inboxai-modal__box">
		<div class="inboxai-modal__header">
			<h3><?php esc_html_e( 'Start import?', 'inbox-ai' ); ?></h3>
			<div class="inboxai-btn--icon" data-close-modal="import-modal-overlay" style="width:26px;height:26px;">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
			</div>
		</div>
		<div class="inboxai-modal__body" id="flamingo-modal-body">
			<?php
			/*
			 * Static fallback text only — `flamingoImportTab.js` always
			 * overwrites this right before opening the modal, with copy
			 * matching whichever import type (Flamingo or Inbox AI CSV)
			 * Step 1 of the wizard chose. One shared modal for both paths,
			 * not two.
			 */
			esc_html_e( 'This will import the selected data into AI Inbox, and optionally run AI analysis on each row.', 'inbox-ai' );
			?>
		</div>
		<div class="inboxai-modal__footer">
			<button class="inboxai-btn--secondary" data-close-modal="import-modal-overlay"><?php esc_html_e( 'Cancel', 'inbox-ai' ); ?></button>
			<button class="inboxai-btn--primary" id="modal-confirm-import">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:13px;height:13px;"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
				<?php esc_html_e( 'Confirm & Import', 'inbox-ai' ); ?>
			</button>
		</div>
	</div>
</div>

<div id="toast-container"></div>
