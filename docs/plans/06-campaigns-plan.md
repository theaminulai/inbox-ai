# End-to-End Plan: Campaigns Page (`cf7ai-campaigns`, `html/campaign.html`)

**Status: mockup complete, backend not started.** Section 1 describes `html/campaign.html` + `html/assets/js/campaign.js`, extracted this session from the connected single-file mockup into the same static-page-per-feature structure Plans 1–5 already used as their own source of truth (`html/dashboard.html`, `inbox.html`, `contacts.html`, `analytics.html`, `settings.html`). Unlike Plans 1–5, Campaigns has no corresponding section in `docs/CF7_AI_Inbox_RnD.md` — it wasn't part of the original five-page admin UX plan (see `includes/Admin/Menu.php`'s docblock), so this document also has to make a few net-new design calls (capabilities, schema, email-provider dependency) that the other five plans could just cite from R&D.

Standalone build plan for what would be the sixth admin page. Sends bulk email to a segment of captured submissions — by category, form, priority, or status — which is a materially different, riskier action than Plan 2's one-to-one reply flow (one send action fans out to many real customers at once), so this plan treats capability-gating and consent/compliance more strictly than the other five.

## 1. Mockup inventory (source of truth: `html/campaign.html` + `html/assets/js/campaign.js`)

Two sections share one page/URL, switched by JS (`showCampaignScreen()` — same in-page-screen-switch pattern as `inbox.js`'s list/detail/failure split):

**Campaigns List (`#screen-campaigns`, default view).** Page header with a "New Campaign" button. Four KPI cards: Total campaigns, Emails sent, Avg. open rate, Daily send limit. A 7-column table (Campaign, Audience, Recipients, Status, Open Rate, Sent, actions), with a "no campaigns yet" empty state. Row actions live in a "more" menu: Duplicate, Delete.

**New Campaign Wizard (`#screen-campaign-new`).** Five steps:
1. **Audience** — a provider-card-style picker (All contacts / By category / By form / By priority / By status), a segment-value dropdown that appears for the four non-"all" options, and a live recipient count.
2. **Compose** — From name/email, Subject, a `contenteditable` rich-text body with a mini toolbar (bold/italic/underline/list/link via `document.execCommand`), an auto-appended unsubscribe footer (currently decorative copy only — see section 5).
3. **Sending settings** — a daily send-rate dropdown (500/1,000/3,000/5,000/no limit), an email-provider status card with a "Manage" link (now points at `settings.html?tab=email-settings`, a seventh Settings subtab added after this plan was first written — see section 9), a "send immediately" switch that reveals date/time fields when off, and a required "recipients have consented" switch for non-"all" audiences.
4. **Review & Send** — a read-only summary of every prior step plus an estimated-completion calculation, a body preview, a consent warning banner, and the Send button, which shows a simulated progress bar (`setInterval()` + `Math.random()`) once clicked.
5. **Complete** — confirmation copy, "Create Another" / "Back to Campaigns" actions.

Current JS (`campaign.js`): `computeAudience()`/`distinctValues()` filter the shared mock `messages` array (from `common.js`) exactly the way `contacts.js`'s `contactsFromMessages()` does; `campaigns` is a local in-memory array (2 seeded rows) with no persistence; open rates on the two seeded "sent" rows are hardcoded, and every newly-sent campaign in the wizard gets a `Math.round(35+Math.random()*40)` fake open rate — there is no real open-tracking mechanism in the mockup at all, which is the one piece of UI behavior (the "Open Rate" KPI/column) that has literally nothing real to port and must be designed from scratch (section 3).

## 2. Data model — two new tables, no new contacts table

Follows Plan 3 (Contacts)'s precedent: there's no dedicated `cf7ai_contacts` table, so audience membership is always derived live from `{prefix}cf7ai_messages` (owned by Plan 2), grouped by `sender_email`, exactly like `computeAudience()`'s client-side logic today. Two genuinely new tables are needed, since a campaign itself — and who it was actually sent to — isn't derivable from anything Plan 2 already stores:

```sql
CREATE TABLE {prefix}cf7ai_campaigns (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(255) NOT NULL,
  subject           VARCHAR(255) NOT NULL,
  body              LONGTEXT NOT NULL,
  from_name         VARCHAR(255) NOT NULL,
  from_email        VARCHAR(255) NOT NULL,
  audience_type     VARCHAR(20) NOT NULL,      -- all|category|form|priority|status
  audience_value    VARCHAR(255) NULL,
  recipient_count   INT UNSIGNED NOT NULL DEFAULT 0,
  send_rate         INT UNSIGNED NOT NULL DEFAULT 0,  -- 0 = no limit
  status            VARCHAR(20) NOT NULL,       -- draft|scheduled|sending|sent
  scheduled_at      DATETIME NULL,
  sent_at           DATETIME NULL,
  created_by        BIGINT UNSIGNED NOT NULL,
  created_at        DATETIME NOT NULL,
  updated_at        DATETIME NOT NULL
);

CREATE TABLE {prefix}cf7ai_campaign_recipients (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id   BIGINT UNSIGNED NOT NULL,
  email         VARCHAR(255) NOT NULL,
  send_token    CHAR(64) NOT NULL UNIQUE,   -- long random value, powers open tracking
  sent_at       DATETIME NULL,
  opened_at     DATETIME NULL,
  UNIQUE KEY campaign_email (campaign_id, email)
);
```

Both added to `includes/Database/Migrator.php` alongside the three tables Plan 2 already owns. `audience_value` is stored as a plain label string at send time (not a live reference to a category/form/priority/status), matching Plan 3 §7's same call for its own `most-recent-row` fields — if a form is later renamed or deleted, historical campaigns keep showing what it was called when sent.

Email-provider connection details (provider key, encrypted API key, default daily rate) are **not** part of either table above — they're account-level configuration, not campaign data, and belong in Settings' existing options-backed store (section 9).

## 3. Backend components to build

- Two new capabilities in `includes/Security/Capabilities.php`, mirroring the existing `VIEW_MESSAGES`/`SEND_REPLIES` split (viewing is safe; bulk-sending is the one genuinely risky action on this page and deserves a narrower grant than viewing does):
  ```php
  public const VIEW_CAMPAIGNS = 'cf7ai_view_campaigns';
  public const SEND_CAMPAIGNS = 'cf7ai_send_campaigns';
  ```
  Both added to `Capabilities::all()` and granted to Administrator on activation, same as every existing capability.
- `includes/Database/CampaignRepository.php` — `get_campaigns( $page, $per_page ): array{items, total}`, `find( int $id )`, `insert( array $data ): int`, `update_status( int $id, string $status )`, `duplicate( int $id ): int`, `delete( int $id )`, `record_recipients( int $id, array $emails ): void` (bulk-inserts `cf7ai_campaign_recipients` rows with fresh `send_token`s), `record_open( string $token ): bool`, `open_rate( int $id ): ?float`.
- `includes/Database/MessageRepository.php` extended (shared with Plans 2–4) with `distinct_values( string $field ): array` (category/form/priority/status — powers the wizard's segment-value dropdown, replacing `distinctValues()`'s client-side `messages.forEach()`) and an `audience_contacts( string $type, ?string $value ): array` method whose SQL shape matches Plan 3 §2's `GROUP BY sender_email` aggregate exactly, with an extra `WHERE category = %s` (or `form_title`/`priority`/`workflow_status`) clause for the four non-"all" types.
- `includes/Interfaces/EmailProviderInterface.php` + `includes/Email/ProviderFactory.php`, one concrete provider first (`SendGridProvider`, matching the mockup's default selection) — `validate_credentials()`, `send( string $to, string $subject, string $body, array $headers ): bool|WP_Error`. Deliberately mirrors the shape of Plan 5 §3.2's `AIProviderInterface`/`ProviderFactory` pattern, but this is a separate interface/factory pair — sending bulk email has nothing to do with the AI provider used for analysis.
- `includes/Services/CampaignSendService.php` — resolves the audience via `audience_contacts()`, chunks it by the campaign's `send_rate`, and queues sends using the **same** Action-Scheduler/WP-Cron queue infrastructure Plan 2 built for AI analysis jobs (section 3.2 there) rather than standing up a second queue mechanism. Per recipient: generates a `send_token`, embeds a 1×1 tracking pixel pointed at `cf7ai_track_open&t=<token>` in the sent body, calls the active `EmailProviderInterface::send()`, and records the `sent_at` timestamp. Re-resolves the audience fresh at the start of each day's batch (not once at wizard-submit time) — see section 6 for why. Updates `cf7ai_campaigns.status`/`sent_at` as batches complete (`sending` while multi-day batching is in progress, `sent` once the last batch finishes).
- `includes/Admin/Pages/CampaignsPage.php` — same thin shape as the other five page classes: capability check (`cf7ai_view_campaigns` in `render()`, re-checked per-action at `cf7ai_send_campaigns` for writes), assembles a view model (current campaigns page + the four `distinct_values()` lists the wizard's segment dropdown needs), calls `Support\Template::render( 'campaigns/campaigns', $view_model )`, which in turn renders `campaigns/list.php` and `campaigns/wizard.php` — following the `inbox/`/`settings/` subfolder convention this session's Templates reorg already established for the other two multi-screen pages. **No enqueue call**: `Menu.php` enqueues the shared bundle for every registered page; this class's constructor hooks `cf7ai_inbox_localize_data`, checks `$slug === 'cf7ai-campaigns'`, and adds its nonce(s) — same mechanism as every other page (`docs/plans/05-settings-plan.md` §10).
- `includes/Admin/AjaxController.php` (shared with every other page — one controller class, not a per-page one) — new actions:

| Action | Capability | Notes |
|---|---|---|
| `cf7ai_list_campaigns` | `VIEW_CAMPAIGNS` | list + pagination |
| `cf7ai_get_audience_count` | `VIEW_CAMPAIGNS` | wizard Step 1's live count, replaces client-side `computeAudience().length` |
| `cf7ai_create_campaign` | `SEND_CAMPAIGNS` | draft / schedule / send-now, per the wizard's final step |
| `cf7ai_duplicate_campaign` | `SEND_CAMPAIGNS` | |
| `cf7ai_delete_campaign` | `SEND_CAMPAIGNS` | |
| `cf7ai_track_open` | *(none — see section 5)* | hit by an `<img>` tag inside a delivered email, can't carry a nonce |

## 4. Frontend build plan (`src/admin/componets/campaigns/`)

Same shared-bundle/loader-map/scss-folder convention as every other page (`docs/plans/05-settings-plan.md` §10) — `campaign.js` already implements every wizard interaction correctly against local mock data, so most of this is a data-layer swap rather than a UI rewrite, same character as Plan 2's `inbox.js` → `src/admin/componets/inbox/` port:

- `api.js` — shared `fetch()`-to-`admin-ajax.php` wrapper (same as every other plan), reading from `window.cf7aiInboxAdmin`.
- `list.js` — replaces the local `campaigns` mock array + `renderCampaigns()`'s client-side rendering with a `cf7ai_list_campaigns` call.
- `audience.js` — `computeAudience()`/`distinctValues()`/`updateCampaignSegmentOptions()`/`updateCampaignAudienceCount()` replaced by a `cf7ai_get_audience_count` call keyed on the selected type/value — the aggregate now happens in `MessageRepository::audience_contacts()`, not in the browser over the full mock `messages` array.
- `wizard.js` — `resetCampaignWizard()`/`goCampaignStep()`/`fillCampaignReview()`/`updateCampaignProviderDisplay()` kept essentially as-built (pure UI state machine, no data dependency beyond the audience count above); the Send button now calls `cf7ai_create_campaign` and, for an immediate send, polls `cf7ai_list_campaigns` (or a small dedicated status action) to animate the real progress bar instead of the mockup's `setInterval()` + `Math.random()` simulation.
- `richText.js` — `applyCampaignFormat()` unchanged, still `document.execCommand`-based.
- Row-menu actions (Duplicate/Delete) reuse the shared `rowMenu.js` module already relocated out of `common.js` for Plan 2, extended with the `campaign` kind this session's mockup pass already added to `html/assets/js/common.js`'s `openRowMenu()`.
- Styling: `src/admin/scss/campaigns/` folder, `@use`'d from `src/admin/scss/index.scss`. Almost no new component CSS is needed — the wizard/provider-option/info-row/modal/switch-row partials already exist in `src/admin/scss/common/` and `src/admin/scss/settings/_wizard.scss` (built for the Flamingo import wizard and AI Provider tab) and are directly reusable here. The two rules this session's mockup pass *did* add to `html/assets/css/cf7-ai-inbox.css` (`.cf7-ai-inbox-grid-table--campaigns`'s column widths, and the `--draft`/`--scheduled`/`--sending`/`--sent` status-badge colors) still need porting into `src/admin/scss/common/`, the same way every other mockup rule gets ported per page.
- Tests: Jest coverage for `audience.js`'s count logic and `wizard.js`'s five-step state machine — **not set up anywhere in this codebase yet** (`src/tests/index.js` is an empty stub), consistent with every other plan's note on this; verify manually per section 7 instead.

## 5. Security

- `cf7ai_view_campaigns` gates the list/KPIs/audience-count preview (read-only); `cf7ai_send_campaigns` gates every write action (create/duplicate/delete) — re-checked server-side per action, not just hidden client-side.
- The server re-validates, on `cf7ai_create_campaign`, that a non-"all" audience has its consent switch confirmed — never trust the client-side check alone (the mockup's own `campaign-consent-warning` logic is a UX nicety, not a security boundary).
- `cf7ai_track_open` is necessarily unauthenticated (an emailed `<img>` tag can't carry a WordPress nonce). Its `send_token` must be a long, unguessable random value 1:1 with a single `cf7ai_campaign_recipients` row — never a sequential ID — and the handler only ever writes an `opened_at` timestamp; it never reads back or returns any campaign/recipient data, so a forged/guessed token can at most mark one open, never leak anything.
- Email-provider API key: encrypted at rest via the same `Security\Encryption` class Plan 5 built for the AI provider key (`docs/plans/05-settings-plan.md` §3.1), never returned in decrypted form to any AJAX response.
- **Unsubscribe/suppression is a hard requirement before this ships**, not optional polish — the mockup's auto-appended footer text ("You're receiving this because you contacted us... Unsubscribe.") is currently decorative copy with no real link or backend behind it. Real bulk sending to non-transactional segments (by category/form/priority/status, as opposed to "all") without a working unsubscribe/suppression list is both a deliverability risk (provider ToS) and a compliance risk (CAN-SPAM/GDPR, depending on the site's audience). This needs its own small design pass — a `cf7ai_campaign_suppressions` table or an `unsubscribed` flag on messages/contacts — before section 8's build order reaches "enable non-'all' audiences in production."

## 6. Edge cases

- Zero contacts match the selected audience → the mockup already disables "Next" client-side; the server must reject `cf7ai_create_campaign` the same way even if the request is forged.
- Audience membership can shift between the wizard's Step 1 count and the actual send, especially for a scheduled campaign or one large enough to batch across multiple days — `CampaignSendService` re-resolves the audience fresh per batch (section 3), so the Step 1/Review numbers are always an estimate, not a locked recipient list; Review step copy should say so.
- Daily send-rate smaller than audience size → multi-day batching (`status = 'sending'`), already modeled by the mockup's `days > 1` branch. The queue must resume correctly after a missed cron tick or a site outage mid-campaign, not silently drop the remaining recipients.
- Same email-provider account used for other outbound mail on the site (transactional email, another plugin) → the daily send-rate this feature enforces should be provider-account-aware in spirit, not purely this plugin's own counter, to avoid tripping the provider's own reputation/throttling systems from the outside.
- A campaign's `audience_type` was "category"/"form"/etc. and that category/form is later renamed or deleted → the stored `audience_value` label (section 2) keeps the campaign's history accurate without needing the original reference to still exist.
- Repeat opens of the same tracking pixel (an email client re-fetching images, or a recipient opening the email twice) → `record_open()` should only ever set `opened_at` once (first open wins), so the KPI stays a true unique-open rate rather than inflating on refetch.

## 7. Testing checklist

- Each of the four non-"all" audience types returns the same contact count a direct SQL query against seeded `cf7ai_messages` rows would, matching `computeAudience()`'s existing client-side logic value-for-value.
- Full wizard round-trip (Audience → Compose → Sending → Review → Send) for both "send now" and "schedule for later" produces a real `cf7ai_campaigns` row with the correct status, recipient rows, and tokens.
- Daily send-rate batching actually spans multiple days for an audience larger than the configured rate, and resumes correctly after a simulated missed cron run.
- Open tracking: opening the pixel records `opened_at` exactly once per recipient even on repeat fetches; a campaign's displayed Open Rate matches `COUNT(opened_at IS NOT NULL) / COUNT(*)` on its recipients.
- Duplicate/Delete row actions work and are rejected server-side for a user with `cf7ai_view_campaigns` but not `cf7ai_send_campaigns`.
- `cf7ai_track_open` with an invalid/guessed token silently no-ops (no error message, no data returned) rather than leaking whether a token exists.
- Consent-switch bypass attempt (forged request with a non-"all" audience and no consent flag) is rejected server-side even though the client-side wizard would have blocked it first.

## 8. Step-by-step build order

1. Two new capabilities in `Capabilities.php` + grant to Administrator on activation.
2. `Migrator.php`: add `cf7ai_campaigns` + `cf7ai_campaign_recipients` tables.
3. Email Provider connection: extend Settings' options-backed store (or add a seventh Settings subtab) with provider key + encrypted API key + Test Connection, following Plan 5 §3.1/3.2's AI-Provider-tab pattern exactly — this blocks any real send, so do it early (see section 9).
4. `MessageRepository::distinct_values()` / `audience_contacts()`, verified directly against seeded data before any UI exists.
5. `CampaignRepository` CRUD methods.
6. `EmailProviderInterface` + `SendGridProvider` — prove a single real email actually delivers before building any batching logic on top of it.
7. `CampaignSendService`: single-recipient send path first, then the daily-rate batching/multi-day queue logic (reusing Plan 2's queue infrastructure), then open-pixel tracking last.
8. `CampaignsPage` + the shared `AjaxController` actions from section 3's table.
9. Port `html/campaign.html`'s two screens into `includes/Templates/campaigns/list.php` + `campaigns/wizard.php`, then build `src/admin/componets/campaigns/` from section 4 against real data — list screen first, then the wizard's five steps in the order they already appear.
10. Add `'cf7ai-campaigns' => array( 'Campaigns', 'Campaigns', Capabilities::VIEW_CAMPAIGNS, CampaignsPage::class )` to `Menu::PAGES`.
11. Build the unsubscribe/suppression mechanism (section 5) — required before enabling any non-"all" audience type in production, even if "all contacts" ships earlier.
12. Run the full testing checklist. (No Jest suite exists yet in this codebase, so this is manual verification, same as every other plan.)

## 9. Explicit dependencies

- **Hard dependency on Plan 2** (AI Inbox List) — audience segmentation reads `cf7ai_messages` exactly the way Plan 3 (Contacts) does; cannot be meaningfully built or tested without real captured submissions.
- **Hard dependency on a new Email Provider connection** — no existing plan covers the *real backend* for this. The static mockup side is now done: `html/settings.html` has a seventh subtab (`#screen-email-settings`, `data-subnav="email-settings"`) with the provider picker (SendGrid/Postmark/SES/Mailgun), API key field, Test Connection, and Sending Defaults card, wired in `html/assets/js/settings.js`, and the wizard's "Manage" link in `html/campaign.html` now points at `settings.html?tab=email-settings`. What's still missing is everything in section 3 — `EmailProviderInterface`/`ProviderFactory`, the real settings-repository fields, encryption, and the actual `cf7ai_test_connection`-equivalent AJAX action. Recommend building the real backend as a small addendum to Plan 5 (Settings) rather than a new standalone plan, since it's shaped exactly like Plan 5 §3.2's AI-Provider-tab pattern.
- **Soft dependency on Plan 3's precedent** (no dedicated contacts table; audience is always derived live from `cf7ai_messages`) — this plan follows that same call for consistency, per section 2.
- **No dependency on Plans 1 or 4.**
