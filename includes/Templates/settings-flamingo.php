<?php
/**
 * Settings page — Import & Migration (Flamingo) tab.
 *
 * @var string $active_tab      Currently visible tab key.
 * @var bool   $flamingo_active Whether the Flamingo plugin is active on this site.
 *
 * @package CF7AIInbox\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<section class="cf7-ai-inbox-screen<?php echo 'flamingo' === $active_tab ? ' cf7-ai-inbox-is-active' : ''; ?>" id="screen-flamingo">
	<div class="cf7-ai-inbox-page-header">
		<div>
			<h1><?php esc_html_e( 'Flamingo Import', 'cf7-ai-inbox' ); ?></h1>
			<p><?php esc_html_e( 'Import data from Flamingo to CF7 AI Inbox.', 'cf7-ai-inbox' ); ?></p>
		</div>
	</div>
	<div class="cf7-ai-inbox-settings__shell">
		<div class="cf7-ai-inbox-settings__tabs" id="settings-tabs-6">
			<a href="#" data-subnav="ai-settings" class="<?php echo 'ai-settings' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'AI Provider', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="general-settings" class="<?php echo 'general-settings' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'General', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="prompts" class="<?php echo 'prompts' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Prompts', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="usage" class="<?php echo 'usage' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Usage & Billing', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="notifications" class="<?php echo 'notifications' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Notifications', 'cf7-ai-inbox' ); ?></a>
			<a href="#" data-subnav="flamingo" class="<?php echo 'flamingo' === $active_tab ? 'cf7-ai-inbox-is-active' : ''; ?>"><?php esc_html_e( 'Import & Migration', 'cf7-ai-inbox' ); ?></a>
		</div>

		<div>
			<div class="cf7-ai-inbox-wizard__steps">
				<div class="cf7-ai-inbox-wizard__step cf7-ai-inbox-is-active" data-wizard-step="1"><div class="cf7-ai-inbox-wizard__circle">1</div><span><?php esc_html_e( 'Source', 'cf7-ai-inbox' ); ?></span></div>
				<div class="cf7-ai-inbox-wizard__line"></div>
				<div class="cf7-ai-inbox-wizard__step" data-wizard-step="2"><div class="cf7-ai-inbox-wizard__circle">2</div><span><?php esc_html_e( 'Options', 'cf7-ai-inbox' ); ?></span></div>
				<div class="cf7-ai-inbox-wizard__line"></div>
				<div class="cf7-ai-inbox-wizard__step" data-wizard-step="3"><div class="cf7-ai-inbox-wizard__circle">3</div><span><?php esc_html_e( 'Import', 'cf7-ai-inbox' ); ?></span></div>
				<div class="cf7-ai-inbox-wizard__line"></div>
				<div class="cf7-ai-inbox-wizard__step" data-wizard-step="4"><div class="cf7-ai-inbox-wizard__circle">4</div><span><?php esc_html_e( 'Complete', 'cf7-ai-inbox' ); ?></span></div>
			</div>

			<!-- STEP 1: SOURCE -->
			<div class="cf7-ai-inbox-card" id="flamingo-panel-1">
				<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Choose a Source', 'cf7-ai-inbox' ); ?></h2><span class="cf7-ai-inbox-card__muted"><?php esc_html_e( 'Read this site\'s live Flamingo data, or upload a CSV exported from Flamingo', 'cf7-ai-inbox' ); ?></span></div>
				<div class="cf7-ai-inbox-card__body">
					<label class="cf7-ai-inbox-switch-row" style="cursor:<?php echo $flamingo_active ? 'pointer' : 'not-allowed'; ?>;">
						<div>
							<div class="cf7-ai-inbox-switch-row__text"><?php esc_html_e( 'Use live Flamingo data', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-switch-row__sub"><?php echo $flamingo_active ? esc_html__( 'Read directly from Flamingo\'s data on this site', 'cf7-ai-inbox' ) : esc_html__( 'Flamingo is not active on this site', 'cf7-ai-inbox' ); ?></div>
						</div>
						<input type="radio" name="flamingo-source" id="flamingo-source-live" value="live" <?php echo $flamingo_active ? 'checked' : 'disabled'; ?>>
					</label>
					<label class="cf7-ai-inbox-switch-row" style="border-bottom:none;cursor:pointer;">
						<div>
							<div class="cf7-ai-inbox-switch-row__text"><?php esc_html_e( 'Upload a CSV export', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-switch-row__sub"><?php esc_html_e( 'An Inbound Messages export from Flamingo\'s own Export button', 'cf7-ai-inbox' ); ?></div>
						</div>
						<input type="radio" name="flamingo-source" id="flamingo-source-csv" value="csv" <?php echo $flamingo_active ? '' : 'checked'; ?>>
					</label>

					<div id="flamingo-source-live-panel" style="<?php echo $flamingo_active ? '' : 'display:none;'; ?>text-align:center;padding:28px 0 8px;">
						<button class="cf7-ai-inbox-btn--secondary" id="flamingo-check-live-btn" type="button"><?php esc_html_e( 'Check for Flamingo Data', 'cf7-ai-inbox' ); ?></button>
						<div id="flamingo-detected-info" style="display:none;align-items:center;justify-content:center;gap:8px;background:var(--conf-good-soft);border:1px solid #BEE7CD;border-radius:8px;padding:10px 14px;font-size:12.5px;color:var(--conf-good);font-weight:600;margin:14px auto 0;max-width:420px;">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:13px;height:13px;flex-shrink:0;"><path d="M20 6L9 17l-5-5"/></svg>
							<span></span>
						</div>
					</div>

					<div id="flamingo-source-csv-panel" style="<?php echo $flamingo_active ? 'display:none;' : ''; ?>text-align:center;padding:28px 0 8px;">
						<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:center;">
							<label class="cf7-ai-inbox-btn--secondary" for="flamingo-file-input" style="cursor:pointer;margin:0;"><?php esc_html_e( 'Choose File', 'cf7-ai-inbox' ); ?></label>
							<input type="file" id="flamingo-file-input" accept=".csv" style="display:none;">
							<span id="flamingo-file-name" style="font-size:12.5px;color:var(--text-tertiary);"><?php esc_html_e( 'No file chosen', 'cf7-ai-inbox' ); ?></span>
						</div>
						<div id="flamingo-csv-detected-info" style="display:none;align-items:center;justify-content:center;gap:8px;background:var(--conf-good-soft);border:1px solid #BEE7CD;border-radius:8px;padding:10px 14px;font-size:12.5px;color:var(--conf-good);font-weight:600;margin:14px auto 0;max-width:420px;">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:13px;height:13px;flex-shrink:0;"><path d="M20 6L9 17l-5-5"/></svg>
							<span></span>
						</div>
					</div>
				</div>
				<div class="cf7-ai-inbox-modal__footer" style="justify-content:flex-end;background:transparent;">
					<button class="cf7-ai-inbox-btn--primary" id="flamingo-next-1" disabled><?php esc_html_e( 'Next', 'cf7-ai-inbox' ); ?></button>
				</div>
			</div>

			<!-- STEP 2: OPTIONS -->
			<div class="cf7-ai-inbox-card" id="flamingo-panel-2" style="display:none;">
				<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Import Options', 'cf7-ai-inbox' ); ?></h2><span class="cf7-ai-inbox-card__muted" id="flamingo-options-sub"><?php esc_html_e( 'What will be brought into AI Inbox', 'cf7-ai-inbox' ); ?></span></div>
				<div class="cf7-ai-inbox-card__body">
					<div class="cf7-ai-inbox-info-row" id="flamingo-options-summary-row"></div>
					<div class="cf7-ai-inbox-switch-row" style="border-bottom:none;"><div><div class="cf7-ai-inbox-switch-row__text"><?php esc_html_e( 'Run AI analysis on imported messages', 'cf7-ai-inbox' ); ?></div><div class="cf7-ai-inbox-switch-row__sub"><?php esc_html_e( 'Optional — can also be run later in smaller batches to manage cost', 'cf7-ai-inbox' ); ?></div></div><div class="cf7-ai-inbox-switch" id="flamingo-toggle-ai"></div></div>
				</div>
				<div class="cf7-ai-inbox-modal__footer" style="justify-content:space-between;background:transparent;">
					<button class="cf7-ai-inbox-btn--secondary" id="flamingo-back-2"><?php esc_html_e( 'Back', 'cf7-ai-inbox' ); ?></button>
					<button class="cf7-ai-inbox-btn--primary" id="flamingo-next-2"><?php esc_html_e( 'Next', 'cf7-ai-inbox' ); ?></button>
				</div>
			</div>

			<!-- STEP 3: IMPORT -->
			<div class="cf7-ai-inbox-card" id="flamingo-panel-3" style="display:none;">
				<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Review & Import', 'cf7-ai-inbox' ); ?></h2><span class="cf7-ai-inbox-card__muted"><?php esc_html_e( 'A summary is shown before anything is permanently imported', 'cf7-ai-inbox' ); ?></span></div>
				<div class="cf7-ai-inbox-card__body">
					<div class="cf7-ai-inbox-info-row"><span><?php esc_html_e( 'Source', 'cf7-ai-inbox' ); ?></span><b id="flamingo-summary-source"><?php esc_html_e( 'Flamingo', 'cf7-ai-inbox' ); ?></b></div>
					<div class="cf7-ai-inbox-info-row"><span><?php esc_html_e( 'Messages to import', 'cf7-ai-inbox' ); ?></span><b style="font-family:var(--mono);" id="flamingo-summary-messages">0</b></div>
					<div id="flamingo-progress-wrap" style="display:none;margin-top:14px;">
						<div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-secondary);margin-bottom:6px;"><span id="flamingo-progress-label"><?php esc_html_e( 'Importing…', 'cf7-ai-inbox' ); ?></span><span id="flamingo-progress-pct">0%</span></div>
						<div class="cf7-ai-inbox-confidence__track"><div class="cf7-ai-inbox-confidence__fill" id="flamingo-progress-fill" style="width:0%;background:var(--accent);"></div></div>
					</div>
				</div>
				<div class="cf7-ai-inbox-modal__footer" style="justify-content:space-between;background:transparent;">
					<button class="cf7-ai-inbox-btn--secondary" id="flamingo-back-3"><?php esc_html_e( 'Back', 'cf7-ai-inbox' ); ?></button>
					<button class="cf7-ai-inbox-btn--primary" id="flamingo-start-import-btn"><?php esc_html_e( 'Start Import', 'cf7-ai-inbox' ); ?></button>
				</div>
			</div>

			<!-- STEP 4: COMPLETE -->
			<div class="cf7-ai-inbox-card" id="flamingo-panel-4" style="display:none;">
				<div class="cf7-ai-inbox-card__body" style="text-align:center;padding:44px 24px;">
					<svg viewBox="0 0 24 24" fill="none" stroke="var(--conf-good)" stroke-width="1.6" style="width:48px;height:48px;"><circle cx="12" cy="12" r="10"/><path d="M8 12l3 3 5-6"/></svg>
					<h2 style="margin:14px 0 6px;font-size:16px;"><?php esc_html_e( 'Import Complete', 'cf7-ai-inbox' ); ?></h2>
					<p style="color:var(--text-secondary);font-size:13px;margin:0 0 22px;" id="flamingo-complete-summary"></p>
					<div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
						<button class="cf7-ai-inbox-btn--secondary" id="flamingo-restart-btn"><?php esc_html_e( 'Import Another Batch', 'cf7-ai-inbox' ); ?></button>
						<a class="cf7-ai-inbox-btn--primary" href="<?php echo esc_url( \CF7AIInbox\Admin\Menu::url( 'cf7ai-inbox' ) ); ?>"><?php esc_html_e( 'View AI Inbox', 'cf7-ai-inbox' ); ?></a>
					</div>
				</div>
			</div>

			<div class="cf7-ai-inbox-card" style="margin-top:16px;">
				<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Notes', 'cf7-ai-inbox' ); ?></h2></div>
				<div class="cf7-ai-inbox-card__body" style="font-size:12.5px;color:var(--text-secondary);line-height:1.7;"><?php esc_html_e( 'Original Flamingo entries are left untouched — this creates copies inside AI Inbox. You can re-run this import later for any new Flamingo messages received after today.', 'cf7-ai-inbox' ); ?></div>
			</div>
		</div>

	</div>
</section>
