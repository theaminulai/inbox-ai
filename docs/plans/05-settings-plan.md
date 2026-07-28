# End-to-End Plan: Settings Page (`inboxai-settings`, `html/settings.html`)

**Status: built and complete.** Sections 1–2 and 5–8 below are kept as-written (they describe the mockup and the security/edge-case/testing surface, which didn't change). Sections 3, 4, and 9 have been updated to describe the architecture as actually built — in particular, asset loading and per-page localized data moved out of `SettingsPage` and into a shared mechanism in `includes/Admin/Menu.php` that every later page (Plans 1–4) must follow too, and there is no longer an iframe/static-mockup fallback in `Menu.php` for pages that aren't built yet: `Menu::PAGES` only ever lists pages with a real page class. See section 10 for the shared architecture summary.

Standalone build plan for the fifth of five admin pages — and, despite being last in the menu, the one every other page depends on. Nothing in Plans 1–4 can do anything real without at least the AI Provider and General tabs built here first.

## 1. Mockup inventory (source of truth: `html/settings.html` + `assets/js/settings.js`)

Six subtabs share one page/URL, switched by JS (`showSettingsTab()`, with a `?tab=` query-string deep link already wired so Plan 1/2's "Configure AI Provider"/"Provider Settings" links land on the right tab):

**AI Provider.** A three-way provider picker (OpenAI/Anthropic/Google, radio-style cards), an API key field (password input, masked), a model dropdown, a request-timeout dropdown, Test Connection + Save Changes buttons, and a Fallback Behavior card (three switches: auto-retry failed requests, fall back to manual review on repeated failure, email alert on provider outage).

**General.** Monitored Forms (a switch per form — currently four hardcoded form names), Automatic Processing (three switches: analyze automatically, auto-draft high-confidence replies, auto-archive detected spam — plus a confidence-threshold range slider), Data Retention (a "keep submissions for" dropdown + a "delete attachments after reply" switch).

**Prompts.** Analysis Prompt card (variable chips + a large textarea, pre-filled with a working default template), Reply Draft Prompt card (same pattern, smaller), Reply Tone dropdown, Reset to Defaults + Save Prompts buttons.

**Usage & Billing.** Four KPI cards (total requests, tokens used, estimated cost, monthly budget/% used), a Requests Over Time bar chart, a Cost by Request Type breakdown (analysis / reply drafts / regenerations).

**Notifications.** Email Notifications (four switches: urgent messages, daily digest, analysis failure, draft ready), Slack Integration (a switch + a webhook URL field), Save Notification Settings button.

**Import & Migration.** A four-step wizard (Upload → Map Fields → Import → Complete): file picker (.json/.csv) with a simulated parse/detect step, a field-mapping screen (four toggles: inbound messages, attachments, forwarded/reply history, run-AI-on-import), a review-and-import screen with cost/time estimates and a progress bar (behind a confirmation modal), and a completion screen.

Current JS (`settings.js`): tab switching, provider-card selection, the generic switch-toggle handler (shared across every subtab that uses `.inboxai-switch`), `testConnection()` (shared with Overview via `common.js`), three save-button handlers that currently just show a toast, and the full four-step Flamingo wizard state machine including a simulated progress bar.

## 2. Data model

No new custom table — everything here is WordPress options plus one existing table Plan 2 owns:

- `includes/Settings/Repository.php` — a single options-backed store (either one serialized option or several discrete `wp_options` rows; recommend several discrete options so partial reads/writes don't require deserializing everything) covering: `provider` (openai/anthropic/google), `model`, `request_timeout`, fallback-behavior switches, monitored form IDs (array), automatic-processing switches + confidence threshold, retention period + attachment-deletion switch, both prompt templates + reply tone, notification switches + Slack webhook URL.
- API key: stored encrypted, separately from the rest (see section 3) — never part of a plain options blob.
- Usage & Billing tab reads `{prefix}inboxai_usage`, owned by Plan 2 — no new schema, this tab is a thin view.
- Import & Migration ultimately writes into `{prefix}inboxai_messages`/`inboxai_activities` (owned by Plan 2) via the importer described in section 3.

## 3. Backend components to build

### 3.1 Core settings
- `includes/Settings/Repository.php` — typed getters/setters for every field in section 2, with sane defaults matching the mockup's pre-filled values (so a fresh install's Settings page looks/behaves like the mockup out of the box, minus a real API key).
- `includes/Security/Encryption.php` — symmetric encryption (e.g. `sodium_crypto_secretbox` if available, OpenSSL fallback) keyed off a site-specific secret (WordPress salts or a generated-and-stored key); `encrypt( $plain ): string`, `decrypt( $cipher ): ?string`; the API key is the only field this touches. Lives alongside `Capabilities.php` in `includes/Security/`, matching the folder R&D §9's tree already reserves for this class.
- `includes/Admin/AjaxController.php` (shared with Plans 1–4 — one controller class for every admin-page AJAX action, not a page-specific class) — handles the six save actions (one per tab, or one generic `inboxai_save_settings` action with a `tab` param) plus the read/test/import actions detailed in 3.2 and 3.7 below:

| Action | Capability | Notes |
|---|---|---|
| `inboxai_get_settings` | `inboxai_manage_settings` | reads current values for the active tab (or all six, fetched once) |
| `inboxai_save_settings` | `inboxai_manage_settings` | persists one tab's fields; nonce-checked, sanitizes every field (checkbox → bool, select → whitelist check, textarea → `wp_kses_post()` or plain `sanitize_textarea_field()` since prompts are plain text fed to an API, not rendered as HTML, range slider → `absint()` clamped 0–100) |
| `inboxai_test_connection` | `inboxai_manage_settings` | AI Provider tab only, see 3.2 |
| `inboxai_list_models` | `inboxai_manage_settings` | AI Provider tab only, see 3.2 |
| `inboxai_flamingo_detect` | `inboxai_manage_settings` | Import & Migration step 1, see 3.7 |
| `inboxai_flamingo_import_batch` | `inboxai_manage_settings` | Import & Migration step 3, called repeatedly, see 3.7 |
- `includes/Admin/Pages/SettingsPage.php` — capability check (`inboxai_manage_settings`) in `render()`, plus a constructor that hooks the shared `inboxai_inbox_localize_data` filter (see section 10 — **not** its own `wp_enqueue_*`/`wp_localize_script()` calls; `Menu.php` owns all of that now, for every page). Assembles the current settings into one view model covering all six tabs, then calls `Support\Template::render( 'settings', $view_model )`, where `includes/Templates/settings.php` is a shared shell that in turn calls `Template::render()` once per tab (`settings-ai-provider.php`, `settings-general.php`, `settings-prompts.php`, `settings-usage.php`, `settings-notifications.php`, `settings-flamingo.php`) — all six render into the DOM on one page load (not per `?tab=`, since client-side switching needs all six present at once), ported near-verbatim from `html/settings.html`'s six sections. Client-side JS (section 4) toggles which one is visible via the same `showSettingsTab()`/`?tab=` pattern already built.

### 3.2 AI Provider tab specifically
- `includes/Interfaces/AIProviderInterface.php` + `includes/AI/ProviderFactory.php` (shared with Plan 2 — this tab is the *configuration* half, Plan 2's queue is the *usage* half of the same provider classes).
- Two of the shared `AjaxController`'s actions (see the table in 3.1) are specific to this tab:
  - `inboxai_test_connection` — instantiates the selected provider with the *submitted* (not-yet-saved) API key, calls `validate_credentials()`, returns success/failure — matches the mockup's Test Connection button exactly.
  - `inboxai_list_models` — calls `get_models()` on the selected provider, returns the live model list to populate the dropdown (mockup currently shows a static list; this is what makes it live).
- Both require `inboxai_manage_settings` and never log the submitted API key anywhere (not in debug logs, not in the activity table).

### 3.3 General tab specifically
- Monitored Forms must read real Contact Form 7 forms (`WPCF7_ContactForm::find()`), not the mockup's four hardcoded names — each form gets a switch bound to whether its ID is in the stored monitored-forms array. This list is what Plan 2's `SubmissionHandler` checks before capturing, and what Plan 1's empty-state check reads.

### 3.4 Prompts tab specifically
- Templates saved as plain strings; `includes/AI/PromptBuilder.php` (owned by Plan 2, read here) is what actually substitutes the `{variable}` placeholders at analysis time — this tab only persists the template text and validates that at minimum the required variables are present before saving (warn, don't hard-block, in case a template intentionally omits one).

### 3.5 Usage & Billing tab specifically
- No new backend — reuses Plan 1's `UsageRepository::get_period_totals()` and a per-request-type breakdown (`GROUP BY` a `request_type` distinction between analysis vs. reply-draft vs. regeneration — requires `inboxai_usage` rows to record which kind of request they were, a detail Plan 2's usage-logging step should include as a column value or as part of `request_status`/a new lightweight column, whichever is more natural at that point in Plan 2's build).

### 3.6 Notifications tab specifically
- Toggle checks wired into Plan 2's activity-logging points: on `received` → conditionally urgent-priority email; on `ai_analysis_failed` → conditionally email; on `ai_analysis_completed` with a drafted reply → conditionally "draft ready" email. Daily digest → a WP-Cron job querying the last 24h of activity, sent once/day if enabled.
- Slack: a simple `wp_remote_post()` to the stored webhook URL when the toggle is on and a matching event fires (urgent submissions, per the mockup's copy).

### 3.7 Import & Migration tab specifically
- `includes/Migration/FlamingoImporter.php` (R&D §10) — detects Flamingo's `flamingo_inbound` custom post type, maps fields per R&D §10.2 (`_subject`→subject, `_from_name`/`_from_email`→sender fields, `_fields`→the `fields` JSON column, `_akismet`/`_recaptcha`→spam metadata, channel taxonomy→`channel`, etc.), imports in batches via a chunked AJAX loop (mirrors the wizard's existing progress bar exactly — each AJAX call imports N rows and returns a running count), never deletes or modifies Flamingo's own posts, records the source Flamingo post ID on each imported `inboxai_messages` row for traceability, and refuses to double-import an already-migrated post (check for that stored source-ID reference first).
- Shared `AjaxController` actions for this tab (see 3.1's table): `inboxai_flamingo_detect` (step 1 — count what's available), `inboxai_flamingo_import_batch` (step 3, called repeatedly with an offset until done).

## 4. Frontend build plan (`src/admin/componets/settings/`)

Reorganizes `settings.js` into small, single-purpose modules — same tab switching, same behavior, just relocated and split. All of it ships inside the one shared `build/admin.js`/`build/admin.css` bundle (webpack.config.js has `splitChunks`/`runtimeChunk` disabled, so every page's JS and CSS compile into that single pair of files, not one bundle per page) — `src/admin/index.js` is the one entry point enqueued (by `Menu.php`, see section 10) on every plugin admin screen; it reads `data-page` off `#main` and dynamically imports the matching page module, calling that module's exported `init<Page>Page()` directly. For this page that's `initSettingsPage()` in `src/admin/componets/settings/index.js`.

- `api.js` — shared `fetch()`-to-`admin-ajax.php` wrapper (same as the other four plans), reading its bootstrap data from `window.inboxaiInboxAdmin` (the one shared localized object every page gets — see section 10), not a page-specific global.
- `tabs.js` — the relocated `showSettingsTab()`; toggles which of the six already-rendered sections is visible, updates the `[data-subnav]` active state, and syncs `?tab=` via `history.replaceState` — unchanged in behavior from today, which is what keeps Plan 1/2's "Configure AI Provider"/"Provider Settings" cross-page links (plain `<a href>`s ending in `?tab=...`) landing on the right subtab.
- `aiProviderTab.js` — provider-card selection, Test Connection, Load Models (the model `<select>`'s static `<option>`s replaced with ones built from `inboxai_list_models`'s response), Save Changes.
- `generalTab.js` — Monitored Forms list now rendered from real CF7 forms (server-side in `settings-general.php`) rather than four hardcoded switch rows; Automatic Processing + Data Retention save handlers.
- `promptsTab.js` — Save/Reset-to-Defaults, the latter fetching a `defaults` payload from the backend rather than keeping a hardcoded copy in JS.
- `usageBillingTab.js` — the four KPI cards + Requests Over Time chart (same inline-SVG/shared `drawBarChart()` helper as Plan 4) + Cost by Request Type breakdown, read-only, same data-fetching pattern as Plan 1.
- `notificationsTab.js` — Save Notification Settings.
- `flamingoImportTab.js` — the existing four-step wizard state machine (`goFlamingoStep()`, `resetFlamingoWizard()`), with the simulated `setTimeout()`-based file-parse and import-progress code replaced by real calls to `inboxai_flamingo_detect` and repeated `inboxai_flamingo_import_batch` calls, keeping the exact same wizard UI/progress-bar behavior already built; the confirmation modal reuses the shared `modal.js` also used by Plan 2's Send Reply modal.
- The generic switch-toggle handler (shared across every tab using `.inboxai-switch`) stays exactly as-is for the toggle *interaction*, relocated into `src/admin/componets/shared/switch.js`; each tab's Save button now sends the current state of every switch on that tab to `inboxai_save_settings` instead of just toasting.
- On page load (or per-tab-switch), `inboxai_get_settings` fetches the current saved settings for whichever tab is active and populates the form fields (today they're hardcoded into the HTML) — or all six tabs' values are fetched once and cached, whichever proves simpler once built.
- Styling: `src/admin/scss/common/` holds partials shared by every page (`_variables.scss`, `_base.scss`, `_buttons.scss`, `_card.scss`, `_fields.scss`, `_switch.scss`, `_modal.scss`, `_toast.scss`); `src/admin/scss/settings/` holds this page's own (`_layout.scss`, `_provider.scss`, `_prompts.scss`, `_kpi.scss`, `_usage-bar.scss`, `_wizard.scss`). Both are pulled into the single `src/admin/scss/index.scss` entry via `@use`, compiled into `build/admin.css`/`build/admin-rtl.css` by the same webpack build as the JS. `src/tests/` exists but no Jest suite was actually written for this page — the testing checklist below was run manually; a later pass could add real Jest coverage if this build order needs revisiting.

## 5. Security

- Every save/test/import action: nonce + `current_user_can( 'inboxai_manage_settings' )`, re-checked server-side.
- API key: encrypted at rest via `Security\Encryption`, never returned by any AJAX/REST response in decrypted form (the field should come back masked, e.g. `sk-••••••7f2A`, exactly like the mockup already shows, and a save request should only overwrite the stored key if a *new* value was actually entered — not re-encrypt the masked placeholder as if it were a real key).
- Test Connection/Load Models use the submitted (not-yet-saved) key for that one request only — never persisted unless Save Changes is also clicked.
- Slack webhook URL validated as a well-formed HTTPS URL before storing or POSTing to it.
- Flamingo import: read-only against Flamingo's own tables/posts; the importer must never delete or alter Flamingo data, matching R&D §10.1's explicit instruction not to treat Flamingo's internal classes as a stable dependency — read via public `WP_Query`/post meta only, not Flamingo's internal APIs.
- Prompt templates are stored as plain text and only ever used as an LLM prompt input, never rendered as HTML anywhere in wp-admin — so `sanitize_textarea_field()` (which strips tags) is safe and appropriate, no `wp_kses_post()` needed.

## 6. Edge cases

- No API key entered yet → Test Connection/Load Models return a clear "enter an API key first" state rather than a confusing provider error.
- Switching provider after already selecting a model → model dropdown must clear/refetch, not silently keep an incompatible model string.
- Zero CF7 forms exist on the site yet → Monitored Forms tab shows an empty/explanatory state instead of a blank list.
- Confidence threshold slider at extreme ends (0 or 100) → Plan 2's queue logic must handle "everything is low-confidence" and "nothing is" without dividing by zero anywhere downstream (Plan 4's confidence-distribution KPI, for instance).
- Flamingo not installed/active → the Import & Migration tab should say so plainly rather than offering a wizard that will fail at step 1's detection.
- Re-running an import after some rows were already migrated → must skip already-imported Flamingo posts (per the source-ID check in section 3.7), not duplicate them.

## 7. Testing checklist

- Save each of the six tabs independently; reload the page; confirm every field reflects exactly what was saved (including switches, dropdowns, and the range slider).
- API key round-trip: save a real key, reload, confirm the field shows a masked placeholder (never the real value) and that a *re-save without touching the key field* doesn't corrupt the stored encrypted value.
- Test Connection succeeds with a valid key and fails clearly with an invalid one, for at least the first provider implemented.
- Load Models populates real model names.
- Monitored Forms reflects real CF7 forms and correctly gates Plan 2's capture (toggle a form off, submit it, confirm no row is created).
- Prompts Save persists correctly and Plan 2's `PromptBuilder` picks up the new template on the next analysis.
- Notifications: trigger each event type (urgent submission, analysis failure, draft ready) with each switch on and off, confirm email/Slack fires only when enabled.
- Flamingo import: run the full four-step wizard against a real (or sample) Flamingo dataset, confirm imported rows appear correctly in AI Inbox List with correct field mapping, confirm re-running doesn't duplicate, confirm Flamingo's own data is untouched.
- Every action rejected server-side for a user without `inboxai_manage_settings`.
- ~~Jest tests for `aiProviderTab.js`'s Test Connection/Load Models flow, the shared `switch.js`, and `tabs.js`'s six-tab switch pass.~~ Not done — no Jest suite exists yet (`src/tests/index.js` is an empty stub, and there's no `test-unit-js` script in `package.json`). Everything above was verified manually instead.

## 8. Step-by-step build order

1. `Settings\Repository` + `Security\Encryption`, with the section 2 defaults — get this right first since every other tab and every other plan's "safe default" behavior depends on it existing.
2. AI Provider tab: `ProviderFactory` + `OpenAIProvider` (shared start with Plan 2) + Test Connection + Load Models + Save — get one working provider fully wired before adding the other three.
3. General tab: real CF7 form list + persistence — this immediately unblocks Plan 2's capture gating and Plan 1's empty-state check, so prioritize it right after the provider tab.
4. Prompts tab: persistence (Plan 2's `PromptBuilder` can read the stored template as soon as this exists, even before Plan 2's queue is fully built).
5. Usage & Billing tab: once Plan 2 is logging real `inboxai_usage` rows, wire this tab's read-only view.
6. Notifications tab: persistence first, then wire the actual triggers into Plan 2's activity-logging points.
7. Import & Migration: `FlamingoImporter` + the batched wizard endpoints — reasonable to build last since it's the least time-critical of the six tabs.
8. Port the six subtab templates from `html/settings.html`, then build the six tab modules in section 4 against real data, in the same order as steps 2–7 above (provider → general → prompts → usage → notifications → import), reusing `switch.js`/`modal.js` across tabs rather than duplicating them.
9. Add `'inboxai-settings' => array( 'Settings', 'Inbox AI Settings', Capabilities::MANAGE_SETTINGS, SettingsPage::class )` to `Menu::PAGES` — there's no iframe step anymore; `Menu.php` automatically enqueues the shared `build/admin.js`/`admin.css` bundle on this new screen and applies the `inboxai_inbox_localize_data` filter right before its one `wp_localize_script()` call, and `SettingsPage`'s constructor hooks that filter (checking `$slug === 'inboxai-settings'`) to add its nonce — see section 10. The `?tab=` query arg still drives which subtab `tabs.js` shows on first paint, read server-side in `SettingsPage::render()` so a post-save redirect lands back on the tab that was just saved.
10. Run the full testing checklist (manually — see the note in section 7 about Jest not being set up).

## 9. Explicit dependencies

- **Depends on nothing** — this is the foundational plan; it can and should be built first.
- **Blocks Plan 2** (AI Provider + General tabs specifically) and has soft read-only relationships with Plan 1 (empty-state flag, provider status) and Plan 4 (none directly, beyond sharing `inboxai_usage`).

## 10. Shared admin-page architecture (established here — every later page follows this)

Building this page settled several things that aren't Settings-specific; Plans 1–4 should follow all of them rather than re-deriving their own:

- **One page class per page, registered in `Menu::PAGES`.** Each entry is `[ menu title, page title, capability, PageClass::class ]`. `Menu::register_menu()` does `add_submenu_page( ..., array( new $page_class(), 'render' ) )` for every row. There is no fallback for pages without a real class — a page simply isn't added to `Menu::PAGES` (and doesn't appear in the menu at all) until its page class exists. The old per-page iframe preview of the static `html/*.html` mockups (and its shared `includes/Templates/admin-preview.php` template) has been removed entirely, not just for Settings.
- **`Menu.php` owns all enqueuing, once.** `Menu::enqueue_assets()`, hooked to `admin_enqueue_scripts`, checks the current hook suffix against the hook suffixes `register_menu()` captured from `add_submenu_page()`'s return value, and bails immediately on every screen that isn't one of this plugin's own. On a match, it enqueues one shared script + style handle (`inboxai-inbox-admin`) pointing at `build/admin.js` / `build/admin.css`, using `build/admin.asset.php` (written by the wp-scripts/webpack build) for the real dependency array and a content-hash version string, and adds `wp_style_add_data( ..., 'rtl', 'replace' )` so `build/admin-rtl.css` swaps in automatically for RTL locales. **No page class enqueues anything itself.**
- **Page-specific localized data goes through one filter, not `wp_localize_script()` per page.** `Menu::enqueue_assets()` builds `array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) )`, runs it through `apply_filters( 'inboxai_inbox_localize_data', $data, $slug )`, and calls `wp_localize_script()` exactly once with the result, as `window.inboxaiInboxAdmin`. Multiple `wp_localize_script()` calls on the same handle don't merge — only the last one survives — so a page that needs its own nonce or other bootstrap data hooks this one shared filter in its constructor and checks `$slug` before touching `$data`, exactly like `SettingsPage::localize_data()`:
  ```php
  public function __construct() {
      add_filter( 'inboxai_inbox_localize_data', array( $this, 'localize_data' ), 10, 2 );
  }

  public function localize_data( array $data, string $slug ): array {
      if ( 'inboxai-settings' !== $slug ) {
          return $data;
      }
      $data['nonce'] = wp_create_nonce( AjaxController::SETTINGS_NONCE_ACTION );
      return $data;
  }
  ```
- **One shared JS entry, one shared bundle.** `src/admin/index.js` is the only enqueued script. It reads `data-page` off the page's `#main` wrapper and dynamically `import()`s that page's own module, calling its exported `init<Page>Page()` directly (not a `DOMContentLoaded` listener inside the module — by the time a dynamic `import()` resolves, `DOMContentLoaded` may already have fired). `webpack.config.js` disables `splitChunks`/`runtimeChunk`, so this still all compiles into the single `build/admin.js`/`build/admin.css` pair — there's no per-page bundle to enqueue, and no reason for a page class to enqueue its own script. Each new page adds one entry to the `loaders` map in `src/admin/index.js` and one `src/admin/componets/<page>/index.js` exporting its own `init<Page>Page()`.
- **SCSS: `common/` for shared partials, one same-named folder per page for the rest.** `src/admin/scss/common/` holds partials every page uses (`_variables.scss`, `_base.scss`, `_buttons.scss`, `_card.scss`, `_fields.scss`, `_switch.scss`, `_modal.scss`, `_toast.scss`); each page gets its own lowercase folder next to it (`src/admin/scss/settings/`, and later `overview/`, `inbox/`, `contacts/`, `analytics/`) for partials only that page uses. All of it is pulled into the one `src/admin/scss/index.scss` entry via `@use`.
- **`AjaxController` stays one shared class.** Already the plan for every page (section 3.1's table) — confirmed correct as built, no changes needed here.
