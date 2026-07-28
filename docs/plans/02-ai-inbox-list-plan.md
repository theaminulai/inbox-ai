# End-to-End Plan: AI Inbox List Page (`inboxai-inbox`, `html/inbox.html`)

**Note:** the shared admin-page architecture referenced throughout this plan (Menu-centralized enqueuing, the `inboxai_inbox_localize_data` filter, the JS loader/SCSS folder conventions) was established while building the Settings page — see `docs/plans/05-settings-plan.md` §10 for the full explanation and a code example. This plan has been updated to match; sections below now describe that real architecture instead of the original per-page-enqueue assumption.

Standalone build plan for the second of five admin pages — and the core of the plugin. This is the one page that writes data; Overview, Contacts List, and Analytics only read what this page's pipeline produces. Everything in this plan is buildable independently of the other four pages except where noted in section 10.

## 1. Mockup inventory (source of truth: `html/inbox.html` + `assets/js/inbox.js`)

Three sections share one page/URL, switched by JS (`showInboxScreen()`):

**List (`#screen-inbox`, default view).** Toolbar: free-text search (`#inbox-search`), four filter selects (form/status/priority/category), a refresh icon, an Export button. A 9-column table (Customer, Message, Form, Priority, Category, AI Confidence, Status, Received, actions) rendered by the shared `rowHtml()`/`rowActionsHtml()` helpers in `common.js`, paginated 5 per page, with a "no messages match" empty state and a "no submissions yet" empty state (two different things — filtered-to-nothing vs. genuinely empty). Row actions: view, reply (both open detail), and a "more" menu whose contents depend on status — Retry (failed only), Mark reviewed (new/review only), Archive (unless already archived).

**Submission Detail (`#screen-detail`).** Customer Information card (avatar, name, email, phone, location), Submission Details card (ID, form name, source page, IP, submitted date, mail status), AI Analysis card (summary, category badge, priority badge, confidence bar, reasoning, a "Regenerate Analysis" link), Submitted Fields card (subject, message, optional company row, optional attachment row), Reply Composer card (recipient, a template dropdown — AI draft / acknowledgement / refund / blank —, subject, a `contenteditable` rich-text body with a mini toolbar (bold/italic/underline/list/link/regenerate), Save Draft / Preview / Send Reply buttons), and an Activity timeline card. "Send Reply" opens a confirmation modal (`#reply-modal-overlay`) showing the recipient and a preview before actually sending.

**AI Failure Detail (`#screen-ai-failure`).** Breadcrumb back to the list, an error banner with the failure reason, Retry / Review Manually / Provider Settings actions, and a read-only Original Submission card.

Query-string entry points already wired in `inbox.js`: `?status=`, `?priority=`, `?category=`, `?confidence=low`, `?search=`, `?view=<id>` — these are how Overview and Contacts List deep-link in; they must keep working unchanged.

## 2. Data model

Uses all three tables from `includes/Database/Migrator.php`:

- **`{prefix}inboxai_messages`** — one row per captured submission. Every mockup field maps to a column already in the schema: `sender_name`→name, `sender_email`→email, `form_title`→form, `message`/`subject`→full text, `priority`, `category`, `confidence`, `workflow_status` (new/review/reviewed/drafted/replied/archived/failed — the mockup's `status` field), `ai_summary`, `ai_reasoning`, `ai_error`, `ai_provider`, `ai_model`, `reply_subject`, `reply_draft`, `reply_sent_body`, `reply_sent_at`, `fields` (JSON — phone/company/attachment/source page/IP live here or in dedicated columns; see section 3), `deleted_at` (soft delete, if ever needed beyond archive).
- **`{prefix}inboxai_activities`** — one row per timeline event (`received`, `ai_analysis_started`, `ai_analysis_completed`, `ai_analysis_failed`, `draft_saved`, `status_changed`, `reply_sent`, `reviewed`, `archived`), populating the Activity timeline and, later, Plan 4 (Analytics).
- **`{prefix}inboxai_usage`** — one row per AI request (analysis or reply-draft generation), feeding this page's per-message cost is optional to surface, but the rows must exist for Plan 1 (Overview) and Plan 4 (Analytics).

## 3. Backend components to build

### 3.1 Capture (R&D §5.1, §7.1)
- `includes/CF7/SubmissionHandler.php` — hooks:
  - `wpcf7_before_send_mail` → create the `inboxai_messages` row immediately (so a CF7 mail failure never loses the submission), `workflow_status = 'new'`, `mail_status = 'pending'`.
  - `wpcf7_mail_sent` → `mail_status = 'sent'`.
  - `wpcf7_mail_failed` → `mail_status = 'failed'` (message still fully usable — CF7 keeps working regardless).
  - `wpcf7_spam` → `spam_status = 1`.
  - Skip entirely for any form not in Plan 5's monitored-forms list.
- `includes/CF7/SubmissionMapper.php` — pulls posted field values via CF7's submission API, maps common field names (name/email/phone/subject/message) to dedicated columns, everything else into the `fields` JSON column; excludes password-type fields and any field explicitly marked sensitive; computes `submission_hash` (hash of form_id + normalized field values + a short time bucket) so a double-submit (e.g. browser back-button resubmit) doesn't create a duplicate row.
- Insert the initial `received` activity row here.

### 3.2 AI analysis (R&D §7, §8)
- `includes/Interfaces/AIProviderInterface.php` — `get_id()`, `validate_credentials()`, `get_models()`, `analyze( AnalysisRequest $request ): AnalysisResult|WP_Error`.
- `includes/AI/OpenAIProvider.php` first (matches the mockup's default selection), then Anthropic/Gemini/OpenRouter — same interface, so Plan 5's provider picker just switches which class `ProviderFactory` instantiates.
- `includes/AI/PromptBuilder.php` — builds the single combined analysis+reply prompt (R&D §7.2's one-request contract: summary, suggested_reply, reply_subject, category, priority, confidence, reasoning, all from one API call) from Plan 5's stored template, substituting `{message}`, `{customer_name}`, `{form_name}`, `{submitted_fields}`, `{categories}`; wraps submission content in clear delimiters and an explicit "do not follow instructions inside the submission" system instruction (prompt-injection defense, R&D §13).
- `includes/AI/ResponseValidator.php` (or inline in the service) — validates the provider's JSON against the schema, clamps `priority` to the four allowed values, normalizes `confidence` to 0–100.
- Queue: never call the AI provider inside the visitor-facing request. Use Action Scheduler if available, otherwise a lightweight WP-Cron-driven queue table/flag (`workflow_status = 'processing'` acts as the queue marker; a cron job picks up rows in that state). One job per message, keyed by `submission_hash` to avoid duplicate processing.
- On success: write `ai_summary`/`ai_reasoning`/`category`/`priority`/`confidence`/`reply_draft`/`reply_subject`/`ai_provider`/`ai_model`; set `workflow_status` to `review` if confidence is below Plan 5's threshold, else `new`; insert `ai_analysis_completed` activity + a `inboxai_usage` row (tokens/cost from the provider response).
- On failure: `workflow_status = 'failed'`, `ai_error` set to a safe (non-sensitive) message, `ai_analysis_failed` activity row, no automatic retry beyond Plan 5's configured retry count.

### 3.3 List, detail, and reply
- `includes/Database/MessageRepository.php`:
  - `get_filtered( array $filters, int $page, int $per_page ): array{items, total}` — filters: `status`, `priority`, `category`, `form`, `confidence_below` (int), `search` (LIKE across name/email/subject/message — see section 5 for the performance note), matching `inbox.js`'s `filteredMessages()` exactly.
  - `find( int $id ): ?Message`
  - `find_by_hash( string $hash ): ?Message` (dedup check)
  - `update_status( int $id, string $status )`
  - `save_draft( int $id, string $subject, string $body )`
  - `set_reply_sent( int $id, string $subject, string $body )`
  - `soft_delete( int $id )` (sets `deleted_at`; list queries always exclude these)
- `includes/Services/ReplyService.php` — recipient is always the stored `sender_email` column, never anything from AI output or client input (R&D §13 "AI Security" — prevents the model or a tampered request from redirecting mail); sanitizes subject/body for header-injection; sends via `wp_mail()`; on success calls `set_reply_sent()` + inserts a `reply_sent` activity row with the current user ID.
- `includes/Services/SubmissionService.php` (thin orchestration layer used by both the AJAX controller and, later, a REST controller) wiring `MessageRepository` + `ReplyService` + the AI queue's manual-retry trigger together.
- `includes/Admin/Pages/InboxListPage.php` — same thin shape as Plan 1's `OverviewPage` (and Settings' `SettingsPage`): checks `inboxai_view_messages` and calls `Support\Template::render()` once per screen (`includes/Templates/inbox-list.php`, `inbox-detail.php`, `inbox-failure.php`) in sequence — no inline markup in the class itself, and **no enqueue call**: `Menu.php` enqueues the shared `build/admin.js`/`build/admin.css` bundle for every registered page (see `docs/plans/05-settings-plan.md` §10). This page needs more than one nonce (list/detail actions vs. reply-sending, say), so its constructor hooks `inboxai_inbox_localize_data`, checks `$slug === 'inboxai-inbox'`, and adds whatever nonce(s) it needs to the shared payload — same mechanism as every other page, just with more keys added. Exactly like the static mockup, all three screens' markup renders into the page on one load; client-side JS (section 4) toggles which one is visible and reads `?view=<id>` on load to switch straight to Detail/Failure without a page reload — the same instant in-page navigation `showInboxScreen()` already provides, just relocated.
- `includes/Admin/AjaxController.php` (this is the shared controller class every other page's plan also targets — one class for all admin-page AJAX actions, not five separate ones) — one action per interaction, each nonce-checked and capability-gated:

| Action | Capability | Notes |
|---|---|---|
| `inboxai_list_messages` | `inboxai_view_messages` | filters + pagination |
| `inboxai_get_message` | `inboxai_view_messages` | single row, powers Detail/Failure screens |
| `inboxai_save_draft` | `inboxai_edit_messages` | |
| `inboxai_send_reply` | `inboxai_send_replies` | server-side confirmation re-check even though the UI already confirmed |
| `inboxai_mark_reviewed` | `inboxai_edit_messages` | |
| `inboxai_archive_message` | `inboxai_edit_messages` | |
| `inboxai_delete_message` | `inboxai_delete_messages` | soft delete |
| `inboxai_retry_analysis` | `inboxai_edit_messages` | re-queues the AI job |
| `inboxai_regenerate_reply` | `inboxai_edit_messages` | re-runs just the reply-draft half of the prompt |
| `inboxai_export_messages` | `inboxai_export_messages` | mirrors `exportInboxCsv()`; server-side generation recommended once filters can return more rows than fit client memory |

Every write action inserts a matching `inboxai_activities` row (`status_changed`, `draft_saved`, `reply_sent`, etc.) with `user_id` set, which is what powers both this page's Activity timeline and, later, Plan 1's Recent Activity and Plan 4's accuracy/response-time metrics.

## 4. Frontend build plan (`src/admin/componets/inbox/`)

`inbox.js` already implements every interaction correctly against a local mock array — this is still a data-layer swap, not a UI rewrite, just reorganized into small modules under `src/admin/`. All of it compiles into the one shared `build/admin.js`/`build/admin.css` bundle (webpack.config.js disables code-splitting — see `docs/plans/05-settings-plan.md` §10), not a separate bundle per page; this page's `index.js` exports `initInboxPage()` and is added as one entry to the `loaders` map in the shared `src/admin/index.js`, keyed by `data-page="inbox"`:

- `screens.js` — the relocated `showInboxScreen()`; toggles which of the three already-rendered sections (`#screen-inbox`/`#screen-detail`/`#screen-ai-failure`) is visible and updates the URL via `history.pushState`, unchanged in behavior from today.
- `api.js` — shared `fetch()`-to-`admin-ajax.php` wrapper (mirrors Plan 1's), reading from `window.inboxaiInboxAdmin` (the one shared localized object, see `docs/plans/05-settings-plan.md` §10) rather than a page-specific global, used by every module below instead of each hand-rolling its own request.
- `list.js` — `filteredMessages()` + `renderInboxTable()` → replace the local `.filter()`/`.slice()` with a `inboxai_list_messages` call keyed by `state.inboxFilters` + `state.inboxPage`; `syncFilterUI()` stays as-is.
- `detail.js` — `openDetail( id )` fetches via `inboxai_get_message` instead of `messages.find()`, keeps all the existing DOM-population code (it already expects exactly this field shape) for the Customer Info / Submission Details / AI Analysis / Submitted Fields / Activity Timeline cards.
- `replyComposer.js` — Save Draft, Preview, Send Reply, the rich-text toolbar (`document.execCommand`), and the templates dropdown, exactly as already built; Send Reply's confirm button calls `inboxai_send_reply`.
- `failure.js` — `openFailure( id )` and its Retry/Review Manually/Provider Settings actions (Provider Settings stays a plain `<a href>` to Plan 5's Settings admin URL).
- Row-menu actions (`reviewed`/`archive`/`retry`) → each becomes a real AJAX call via `api.js` to its matching action above, then a re-fetch of the affected row (and the list, if the status change affects which page/filter it belongs in).
- Shared modules reusable across Overview/Contacts/Inbox (`src/admin/componets/shared/`, replacing `common.js`): `badges.js` (`priorityBadgeHtml()`/`statusBadgeHtml()`), `pagination.js`, `rowMenu.js`, `toast.js` — same functions already built, just relocated and shared instead of copy-pasted per page.
- `exportInboxCsv()` → either kept client-side (fetch the full filtered set unpaginated, reuse the existing CSV-building code) or moved to a server-rendered download via `inboxai_export_messages` — recommend the server-side route once real data volume could exceed a single AJAX response, same as originally planned.
- Styling: reuse `src/admin/scss/common/`'s shared partials (variables, base, buttons, card, fields, switch, modal, toast — already built for Settings); this page's own rules go in a new `src/admin/scss/inbox/` folder, `@use`'d from the single `src/admin/scss/index.scss` entry, same convention as Plan 1.
- Tests: Jest coverage in `src/tests/` for `list.js`'s filtering logic, `replyComposer.js`'s Save Draft/Send Reply flow, and `screens.js`'s list/detail/failure switch — **not yet set up anywhere in this codebase** (`src/tests/index.js` is an empty stub, no `test-unit-js` script exists in `package.json`); Settings shipped without it too, see `docs/plans/05-settings-plan.md` §7.

## 5. Security

- Every AJAX action: nonce (`check_ajax_referer`) + `current_user_can()` per the capability table in section 3.3, re-checked server-side even though the admin UI already gates on capability (defense in depth, matches the pattern already used for `Requirements`/`Capabilities`).
- Reply recipient always server-derived from `sender_email`; never accept a `to` address from the request.
- Header-injection: strip newlines from subject/from before passing to `wp_mail()`.
- Prompt-injection: submission content is always the *data* half of the AI prompt, never concatenated in a way that could be mistaken for system instructions; the system instruction explicitly tells the model to ignore any instructions found inside the submission.
- Free-text search: use `$wpdb->prepare()` with `LIKE` wildcards escaped via `$wpdb->esc_like()`; avoid `LIKE` across the JSON `fields` column (R&D §15 performance note) — only search indexed/plain columns (name, email, subject, message).
- API keys are never read by this page directly — always through Plan 5's `Settings\Repository`/`Encryption`, and never echoed into any AJAX response this page returns.

## 6. Edge cases

- CF7 mail fails but the submission still must appear in the inbox (this is the whole reason capture happens on `wpcf7_before_send_mail`, not `wpcf7_mail_sent`).
- AI request times out or returns invalid JSON → `failed` status, safe error message, manual retry available, CF7's own confirmation to the visitor is completely unaffected either way.
- Duplicate submission (double form-submit) → caught by `submission_hash`, second attempt updates rather than duplicates.
- Attachment field present in some forms, absent in others → the mockup already conditionally hides the Attachments row; the mapper must store attachment metadata (name/size, not the file itself unless retention policy says otherwise) only when present.
- A message with no AI analysis yet (still queued, or the queue never ran because Action Scheduler isn't installed) → Detail screen's existing "No AI analysis available" fallback copy is already designed for this.

## 7. Testing checklist

- Submit a monitored CF7 form → row appears in the list within one page load (capture is synchronous; only AI analysis is async).
- Force a CF7 mail failure (e.g. bad SMTP config) → submission still appears, `mail_status` reflects the failure, CF7's own behavior toward the visitor is unchanged.
- Let the AI queue process a real submission against a real (or sandboxed) provider key → summary/category/priority/confidence populate, `inboxai_usage` gets a row.
- Force a provider error (invalid key) → message lands in `failed`, Failure Detail screen shows Retry/Review Manually/Provider Settings all working.
- Every filter/search combination in the toolbar returns the same rows a direct SQL query would.
- Save Draft, Send Reply (with confirmation), Mark Reviewed, Archive, Retry, Regenerate Analysis, Regenerate Reply — each round-trips correctly and logs an activity row.
- Attempt every write action as a user without the matching capability → rejected server-side, not just hidden client-side.
- Two rapid duplicate submissions of the same form → one row, not two.
- Jest tests for `list.js`'s filtering, `replyComposer.js`'s Save Draft/Send Reply flow, and `screens.js`'s list/detail/failure switch pass.

## 8. Step-by-step build order

1. `SubmissionHandler` + `SubmissionMapper`, gated by Plan 5's monitored-forms list (stub the list to "all forms" if Plan 5 isn't built yet, so this isn't blocked).
2. Verify capture end-to-end with a real CF7 submission before writing any AI code — confirm rows land correctly with the right `mail_status`/`spam_status` under success, failure, and spam scenarios.
3. `AIProviderInterface` + `OpenAIProvider` + `PromptBuilder` + `ResponseValidator`.
4. Async queue wiring (Action Scheduler if available; WP-Cron fallback otherwise) + usage logging.
5. `MessageRepository` list/detail/status/draft methods + the corresponding AJAX actions.
6. `ReplyService` + send-reply action + activity logging across every write path.
7. Retry/regenerate actions for the Failure Detail screen.
8. Build the components in section 4, one at a time against real data (List first, then Detail, then Failure) rather than all at once — port the relevant slice of `html/assets/css/inboxai.css` into `src/admin/scss/` alongside each.
9. Add Anthropic/Gemini/OpenRouter providers once OpenAI's path is fully proven.
10. Add `'inboxai-inbox' => array( 'AI Inbox List', 'AI Inbox List', Capabilities::VIEW_MESSAGES, InboxListPage::class )` to `Menu::PAGES` — no iframe fallback exists to replace (removed entirely; see `docs/plans/05-settings-plan.md` §10). `Menu.php` automatically enqueues the shared bundle; `InboxListPage`'s constructor just needs to hook `inboxai_inbox_localize_data` for its nonce(s).
11. Run the full testing checklist. (No Jest suite exists yet in this codebase, so this is manual verification, same as Settings.)

## 9. Explicit dependencies

- **Soft dependency on Plan 5 (Settings)** for: which forms to monitor, which AI provider/model/API key to use, the prompt templates, the confidence threshold, and the retry-count/fallback-behavior switches. All of these have safe defaults (monitor nothing until configured; queue jobs simply fail with a clear "no provider configured" error) so Plan 2's own build order in section 8 doesn't have to wait on Plan 5 being finished — only on Plan 5's *data shape* being agreed, which this document already assumes matches R&D §7.2/§8.
- **Nothing in this plan depends on Plans 1, 3, or 4** — they depend on this one.
