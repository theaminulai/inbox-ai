# Development Plans: Overview, AI Inbox List, Contacts List, Analytics, Settings

This document turns the five static mockups in `html/` (`dashboard.html`, `inbox.html`, `contacts.html`, `analytics.html`, `settings.html`) into five concrete build plans, grounded in the architecture already agreed in `docs/CF7_AI_Inbox_RnD.md` and in the Phase 1 code that already exists in `includes/`.

## 0. Baseline: what already exists

Before the five plans, here's the current state each of them builds on:

- **Database** (`includes/Database/Migrator.php`): three tables are already created via `dbDelta` — `{prefix}cf7ai_messages` (id, site_id, form_id, form_title, submission_hash, sender_name, sender_email, subject, message, fields, meta, channel, submission_status, workflow_status, mail_status, spam_status, priority, category, confidence, ai_summary, ai_reasoning, ai_error, ai_provider, ai_model, reply_subject, reply_draft, reply_sent_body, reply_sent_at, created_at, updated_at, deleted_at), `{prefix}cf7ai_activities` (id, message_id, user_id, event_type, event_data, created_at), and `{prefix}cf7ai_usage` (id, message_id, provider, model, prompt_tokens, completion_tokens, estimated_cost, request_status, created_at). No rows exist yet — nothing writes to these tables until Plan 2 (AI Inbox List) is built.
- **Capabilities** (`includes/Security/Capabilities.php`): `cf7ai_view_messages`, `cf7ai_edit_messages`, `cf7ai_delete_messages`, `cf7ai_send_replies`, `cf7ai_manage_settings`, `cf7ai_view_analytics`, `cf7ai_export_messages` — all granted to Administrator on activation.
- **Admin menu** (`includes/Admin/Menu.php`): only `cf7ai-settings` is registered so far — `Menu::PAGES` is a page-slug => `[ menu title, page title, capability, page-class ]` map, and a page is only added to it once it has a real, capability-gated PHP page class with its own `render()` method. There is **no iframe/static-mockup fallback anymore** — that was removed entirely once Settings shipped (see `docs/plans/05-settings-plan.md` §10), so a page simply doesn't appear in the menu until its own plan below is actually built. `Menu.php` also now centrally owns asset loading for every registered page: it enqueues one shared `build/admin.js`/`build/admin.css` bundle (built by `wp-scripts`/webpack from `src/admin/`) on `admin_enqueue_scripts`, gated to just this plugin's own screens, and applies a shared `cf7ai_inbox_localize_data` filter (passing the current page slug) right before its one `wp_localize_script()` call. Individual page classes never enqueue anything themselves — a page that needs its own AJAX nonce hooks that filter in its constructor and checks the slug, exactly like `SettingsPage::localize_data()`. Every plan below has been updated to describe this instead of the original per-page-enqueue-with-an-iframe-fallback assumption.
- **Not built yet**: CF7 submission capture, all AI provider code, the Settings repository, any REST/AJAX endpoints, and every repository class. All five plans depend on at least the Settings plan (Plan 5) and most depend on the AI Inbox List plan (Plan 2) for their data.

### Recommended build order

The five pages don't need to be built in the order they're listed in the menu. Actual data dependencies run:

**Settings (AI Provider + General tabs) → AI Inbox List (capture + AI analysis + reply) → Overview → Contacts List → Analytics.**

Settings has to exist first because nothing can call an AI provider or know which forms to watch without it. AI Inbox List has to exist before Overview, Contacts, or Analytics because all three are just different views over the same `cf7ai_messages` / `cf7ai_activities` / `cf7ai_usage` rows that only AI Inbox List's capture pipeline produces. Building Overview or Analytics first would mean staring at empty-state screens with nothing to develop against.

---

## 1. Overview page (`cf7ai-overview`, was "Dashboard" in the mockup)

### What the mockup already shows
Five summary cards (New Messages, Needs Review, Urgent Messages, Replies Sent, AI Usage) with sparklines and quick-filter links into AI Inbox List; a Submission Overview area chart with a daily/weekly/monthly toggle; a Priority Distribution bar list; a Recent Messages table (6 rows); an Attention Required list; an AI Processing Status widget; a Categories bar list; a Recent Activity timeline; and an AI Provider Status card with Test Connection.

### Backend work
- `includes/Database/MessageRepository.php` — add aggregate methods: `count_by_workflow_status()`, `count_by_priority()`, `count_low_confidence( $threshold = 70 )`, `get_recent( $limit = 6 )`, `get_submission_trend( $granularity )` (GROUP BY DATE/WEEK/MONTH over `created_at`, split by `workflow_status`).
- `includes/Database/ActivityRepository.php` — `get_recent( $limit = 6 )` for the Recent Activity timeline, reading `event_type`/`event_data`/`user_id` rows written by Plan 2 and Plan 5.
- `includes/Database/UsageRepository.php` — `get_period_totals()` (requests, tokens, estimated cost) for the AI Usage card.
- `includes/Admin/OverviewController.php` (or a `Pages\OverviewPage`) — assembles the above into the view model the existing `dashboard.js` markup expects (same field names it already renders: `preview`, `priority`, `confidence`, `status`, etc., so the front end barely has to change).
- An AJAX action (e.g. `cf7ai_get_overview`, nonce-protected, `current_user_can( 'cf7ai_view_messages' )`) that returns this payload as JSON, replacing the current mock `messages` array in `common.js`.

### Frontend work
`dashboard.js` keeps its rendering functions (`renderDashboardTable`, `checkEmptyState`, the chart-toggle handler) almost unchanged — only the data source moves from the hardcoded `messages` array to a `fetch()`/`wp.ajax` call on load and on the existing refresh button. The empty-state toggle (`dashboard-empty` vs `dashboard-populated`) becomes real once Plan 5's "Monitored Forms" setting exists: empty state shows only when zero forms are enabled.

### Steps
1. Build `MessageRepository`, `ActivityRepository`, `UsageRepository` read methods (write methods land with Plan 2).
2. Build the AJAX/REST overview endpoint and its capability/nonce checks.
3. Swap `dashboard.js`'s mock data for a real fetch; keep all existing DOM IDs and CSS classes.
4. Wire the real "Monitored Forms" state into the empty-state check.
5. Add `OverviewPage` to `Menu::PAGES` — there's no iframe to replace anymore (removed entirely; see the Admin menu note in section 0 and `docs/plans/05-settings-plan.md` §10). `Menu.php` automatically enqueues the one shared `build/admin.js`/`build/admin.css` bundle on this new screen; `OverviewPage` just needs to hook the shared `cf7ai_inbox_localize_data` filter for its own nonce.

---

## 2. AI Inbox List page (`cf7ai-inbox`)

This is the core of the plugin and unblocks the other four pages, so it's the biggest plan.

### What the mockup already shows
A filterable, searchable, paginated list (form/status/priority/category filters, free-text search) with per-row quick actions (view, reply, more → mark reviewed/archive/retry); a Submission Detail screen (customer info, submission details, AI analysis with regenerate, submitted fields, a rich-text Reply Composer with templates and Save Draft/Preview/Send Reply, and an activity timeline); and an AI Failure Detail screen (error message, Retry / Review Manually / Provider Settings). `inbox.js` already reads `status`, `priority`, `category`, `confidence`, `search`, and `view` from the query string, so cross-page deep links from Overview/Contacts keep working once this is real.

### Backend work — capture (R&D §5.1, §7.1)
- `includes/CF7/SubmissionHandler.php` — hooks `wpcf7_before_send_mail` (create the row before mail even attempts to send, so nothing is lost on mail failure), then updates it via `wpcf7_mail_sent`, `wpcf7_mail_failed`, and `wpcf7_spam`.
- `includes/CF7/SubmissionMapper.php` — maps CF7's posted data + form fields to the `cf7ai_messages` columns, excluding passwords/sensitive fields per R&D §5.1, and computing `submission_hash` to prevent duplicate rows on retried submits.
- Respect the "Monitored Forms" list from Plan 5's General settings — skip capture entirely for forms not enabled.

### Backend work — AI analysis (R&D §7, §8)
- `includes/Interfaces/AIProviderInterface.php` + `includes/AI/{OpenAIProvider,AnthropicProvider,GeminiProvider,OpenRouterProvider}.php`, each implementing `validate_credentials()`, `get_models()`, `analyze()` against the structured JSON contract in R&D §7.2 (summary, suggested_reply, reply_subject, category, priority, confidence, reasoning).
- `includes/AI/PromptBuilder.php` — builds the analysis prompt from the template Plan 5's Prompts tab stores, substituting `{message}`, `{customer_name}`, `{form_name}`, `{submitted_fields}`, `{categories}` and clearly delimiting submission content from system instructions (prompt-injection defense per R&D §13).
- Processing must be asynchronous (Action Scheduler, or WP-Cron + a `cf7ai_ai_queue` marker) — never block the visitor's form submission on an AI call. On success, write `ai_summary`/`ai_reasoning`/`category`/`priority`/`confidence`/`ai_provider`/`ai_model`, set `workflow_status` to `new` or `needs-review` (below the confidence threshold from Plan 5), and insert a `cf7ai_usage` row. On failure, set `workflow_status` to `failed`, store `ai_error`, and never retry automatically more than the configured limit.

### Backend work — list, detail, and reply
- `includes/Database/MessageRepository.php` — `get_filtered( $filters, $page, $per_page )` (mirrors `inbox.js`'s `filteredMessages()` filter set exactly: status, priority, category, form, confidence<70, free-text search across name/email/preview/form), `find( $id )`, `update_status( $id, $status )`, `save_draft( $id, $subject, $body )`, `mark_deleted( $id )` (soft delete via `deleted_at`).
- `includes/Services/ReplyService.php` — validates the recipient comes from the stored `sender_email` (never from AI output, per R&D §13 "AI Security"), sanitizes headers, sends via `wp_mail()`, then stamps `reply_sent_body`/`reply_sent_at` and flips `workflow_status` to `replied`.
- `includes/Admin/AjaxController.php` (or REST `includes/REST/MessagesController.php`) — one action/route per mockup interaction: list, get one, save draft, send reply (with confirmation client-side, per the existing modal), mark reviewed, archive, delete, retry analysis, regenerate analysis, regenerate reply, export CSV (mirrors the existing `exportInboxCsv()` so the button keeps working, or moves CSV generation server-side for the full unfiltered set).
- Every write action: nonce + `current_user_can()` (`cf7ai_edit_messages` / `cf7ai_send_replies` / `cf7ai_delete_messages` / `cf7ai_export_messages` as appropriate) + an `cf7ai_activities` row recording who did what and when, feeding the Activity timeline that's already in the mockup.

### Frontend work
`inbox.js` already has the full UI logic (filters, pagination, detail/failure screen switching, reply composer, rich-text toolbar, templates, modal). The rewrite is data-layer only: replace the `messages` array and its in-memory mutations with AJAX calls that hit the endpoints above, re-rendering from the response instead of mutating a local array. The `?view=<id>` deep link from Overview and `?search=<email>` deep link from Contacts keep working unchanged since they only touch `state.inboxFilters` / `openDetail()`.

### Steps
1. `SubmissionHandler` + `SubmissionMapper`, wired only for forms enabled in Settings — verify a real CF7 submission lands a row in `cf7ai_messages`.
2. Provider interface + at least one working provider (OpenAI first, matching the mockup's default), `PromptBuilder`, async queue, usage logging.
3. `MessageRepository` read/write methods + the list/detail/status/draft endpoints.
4. `ReplyService` + send-reply endpoint, with the existing confirmation modal wired to a real "are you sure" → AJAX call.
5. Retry/regenerate endpoints for the AI Failure Detail screen.
6. Rewire `inbox.js` from mock data to these endpoints; verify every existing interaction (filters, pagination, row menu, composer, modal) still works against real rows.
7. Add `InboxListPage` to `Menu::PAGES` — no iframe to replace (removed entirely; see section 0). `Menu.php` handles enqueuing automatically; hook `cf7ai_inbox_localize_data` for this page's own nonce(s).

---

## 3. Contacts List page (`cf7ai-contacts`)

### What the mockup already shows
A searchable, filterable (category, priority), paginated contact list derived by grouping messages by sender email, with message/replied counts and a "view messages" link into AI Inbox List filtered by that email; a delete action.

### Design decision to make before building
R&D §6 marks a dedicated `cf7ai_contacts` table as "optional for version 1" and recommends deriving contacts from `cf7ai_messages` at query time initially. This plan follows that recommendation: no new table yet. One open question to resolve before writing the delete endpoint — since there's no real contacts table, "Delete contact" (currently just a client-side `Set` of hidden emails in the mockup) needs a real meaning. The two options are (a) archive every message from that sender rather than truly deleting data, or (b) skip a destructive action entirely for v1 and only ship "View messages." Recommend (a), gated behind `cf7ai_delete_messages`, since it reuses Plan 2's existing archive logic instead of inventing new deletion semantics.

### Backend work
- `includes/Database/MessageRepository.php` — `get_contacts( $filters, $page, $per_page )`: a `GROUP BY sender_email` aggregate query (name/initials/color from the most recent message, `COUNT(*)`, `SUM(workflow_status = 'replied')`, `MAX(created_at)`), filtered by category/priority/search exactly like `contacts.js`'s `filteredContacts()`.
- Reuse Plan 2's archive-by-status update, applied to every message row matching a given `sender_email`, for the delete action.
- AJAX/REST endpoints: list contacts, delete (archive-all-by-email), export CSV (mirrors `exportContactsCsv()`).

### Frontend work
`contacts.js` already renders real `<a href="inbox.html?search=...">`-style links (now pointing at the real `cf7ai-inbox` admin URL instead of a static file) — only the data source changes from the client-side `contactsFromMessages()` derivation to a server-side aggregate call.

### Steps
1. Confirm Plan 2 is capturing real messages (Contacts List has nothing to show otherwise).
2. Build the `get_contacts()` aggregate query and list endpoint.
3. Decide and implement the delete semantics above.
4. Rewire `contacts.js` from the client-side derivation to the endpoint; update the "view messages" / delete links to real admin URLs (`Menu::url( 'cf7ai-inbox' )` plus the search query arg).
5. Add `ContactsListPage` to `Menu::PAGES` — no iframe to replace (removed entirely; see section 0). `Menu.php` handles enqueuing automatically; hook `cf7ai_inbox_localize_data` for this page's own nonce.

---

## 4. Analytics page (`cf7ai-analytics`)

### What the mockup already shows
Four KPI cards (avg. first response time, reply rate, AI accuracy self-reported, avg. AI confidence), a Submissions by Category bar chart, a Response Time Trend line chart, a Top Performing Forms bar list, and a Confidence Distribution breakdown. `analytics.js` is currently an empty placeholder — this is the least-built page on the front end as well as the back end.

### Backend work
- `includes/Database/ActivityRepository.php` needs two event types this plan introduces (beyond what Plan 2 already logs): a `received` event at capture time and a `replied` event at send time, both with timestamps — first-response time is `replied.created_at - received.created_at` per message, averaged.
- "AI accuracy (self-reported)" needs a way to know whether an admin changed the AI's category/priority before it stuck — add an `activities` event type like `category_overridden` / `priority_overridden`, logged whenever Plan 2's edit endpoint changes a value that differs from the original AI output. Accuracy = 1 − (overridden count / analyzed count).
- `includes/Database/AnalyticsRepository.php` — aggregate queries for: avg first-response time, reply rate (`replied` / total), AI accuracy (above), avg confidence, category breakdown, response-time trend bucketed by week/month, per-form reply-rate ("Top Performing Forms"), and confidence-band distribution (High ≥70 / Medium 40–69 / Low <40) — all matching the exact numbers already laid out in the mockup so the SVG chart-drawing code in the front end doesn't need to change shape.
- Since these are heavier aggregate queries than the other pages, cache results in a transient (e.g. 15 minutes) keyed by the selected date range, invalidated whenever Plan 2 writes a new message or activity row.
- One AJAX/REST endpoint returning the full payload for a given date range (mirrors the "Last 90 days" control already in the header, which currently has no handler).

### Frontend work
Since `analytics.js` starts empty, this is the one page needing new front-end code rather than a rewire: fetch on load (and on date-range change once that control gets wired up), then populate the KPI card values and redraw the existing inline SVGs (bars/lines) from real numbers using the same coordinate-generation approach already used for the mock chart in `dashboard.js`'s chart-toggle handler.

### Steps
1. Add `received`/`replied`/`*_overridden` activity logging to Plan 2's capture and edit endpoints.
2. Build `AnalyticsRepository` aggregate queries + transient caching.
3. Build the analytics endpoint and wire the "Last 90 days" control to it.
4. Write the missing `analytics.js` rendering logic against real data.
5. Add `AnalyticsPage` to `Menu::PAGES` — no iframe to replace (removed entirely; see section 0). `Menu.php` handles enqueuing automatically; hook `cf7ai_inbox_localize_data` for this page's own nonce.

---

## 5. Settings page (`cf7ai-settings`)

**Status: built and complete** — see `docs/plans/05-settings-plan.md` for the full, up-to-date plan (sections 3, 4, 9, and the new §10 there describe the architecture as actually built, including the shared enqueue/localize mechanism referenced in section 0 above). The summary below is left for quick reference but two details have been corrected: the encryption class is `includes/Security/Encryption.php` (not `includes/Helpers/Encryption.php`), and the last step no longer describes an iframe swap.

### What the mockup already shows
Six subtabs sharing one page (already switched client-side by `settings.js`, including the `?tab=` deep link used by Plan 1/2's "Configure AI Provider" and "Provider Settings" links): AI Provider (provider picker, API key, model, timeout, Test Connection, fallback-behavior switches), General (monitored forms, automatic-processing switches, confidence threshold, data retention), Prompts (analysis + reply-draft templates with variable chips), Usage & Billing (KPIs + charts — reads from Plan 2's `cf7ai_usage` table, so functionally it's a thin extension of Plan 2's work), Notifications (email + Slack toggles), and Import & Migration (the four-step Flamingo import wizard).

### Backend work
- `includes/Settings/Repository.php` — one options-backed repository for everything except the API key (provider, model, monitored form IDs, automatic-processing toggles, confidence threshold, retention period, prompt templates, notification toggles, Slack webhook URL) — matches the architecture tree in R&D §9.
- `includes/Security/Encryption.php` — encrypts the API key at rest (R&D §13, "API-Key Security"); never returned through REST/AJAX responses, only a masked placeholder.
- AI Provider tab: `ProviderFactory`/`AIService` (R&D §8) power "Test Connection" and a live "Load Models" call per provider — both AJAX actions gated by `cf7ai_manage_settings`, since they need the (unsaved) API key from the form before it's persisted.
- General tab: the monitored-forms list must be populated from real Contact Form 7 forms (`WPCF7_ContactForm::find()`), not the mockup's hardcoded four names — this is also what Plan 1 and Plan 2 both read to know which forms to watch/show.
- Prompts tab: template strings saved as-is; `PromptBuilder` (Plan 2) reads them at analysis time.
- Usage & Billing tab: reuses Plan 1's `UsageRepository` — no new backend beyond what Plan 1 already needs.
- Notifications tab: `wp_mail()` hooks triggered from Plan 2's capture/analysis-failure/reply-draft-ready events; Slack via a simple webhook POST when the toggle and URL are set.
- Import & Migration tab: `includes/Migration/FlamingoImporter.php` (R&D §10) — detects Flamingo's `flamingo_inbound` posts, maps them per the R&D §10.2 table, imports in batches via AJAX with a progress callback (mirrors the wizard's existing progress bar), never deletes Flamingo's own data, and records the Flamingo source post ID on each imported row for traceability.

### Frontend work
`settings.js` already has all six tabs' client-side behavior (switch toggles, provider selection, wizard steps, file parsing simulation). The rewrite replaces the simulated `setTimeout()` calls (Test Connection, Save Changes, Save Prompts, Save Notification Settings, the Flamingo wizard's file-parse/import simulation) with real AJAX calls to the endpoints above, and the "current settings" shown on page load come from `Settings\Repository` instead of being hardcoded in the HTML.

### Steps
1. `Settings\Repository` + `Encryption` for the API key.
2. AI Provider tab: real Test Connection / Load Models / Save, at least for OpenAI first (matches Plan 2's provider build order).
3. General tab: real form list + persistence — this unblocks Plan 2's capture gating and Plan 1's empty-state check.
4. Prompts tab: persistence, consumed by Plan 2's `PromptBuilder`.
5. Usage & Billing tab: read-only view over Plan 1's `UsageRepository`.
6. Notifications tab: persistence + the `wp_mail()`/Slack triggers.
7. Import & Migration: `FlamingoImporter` + batched AJAX wizard.
8. Add `SettingsPage` to `Menu::PAGES` (no iframe involved — see section 0); `Menu.php` enqueues the shared bundle automatically and applies the `cf7ai_inbox_localize_data` filter, which `SettingsPage` hooks for its nonce. The existing `?tab=` query arg drives which subtab renders server-side too (not just client-side), so a saved-settings redirect can land back on the right tab.

---

## Summary

| Page | Blocked by | Unblocks |
|---|---|---|
| Settings | — | Everything else |
| AI Inbox List | Settings (AI Provider + General) | Overview, Contacts List, Analytics |
| Overview | AI Inbox List | — |
| Contacts List | AI Inbox List | — |
| Analytics | AI Inbox List (plus new activity logging) | — |

Building in that order — Settings, then AI Inbox List, then Overview/Contacts/Analytics in any order — means every subsequent page is built and tested against real data instead of another empty-state screen.
