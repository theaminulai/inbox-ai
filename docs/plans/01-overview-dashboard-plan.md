# End-to-End Plan: Overview Page (`inboxai-overview`, `html/dashboard.html`)

**Note:** the shared admin-page architecture referenced throughout this plan (Menu-centralized enqueuing, the `inboxai_localize_data` filter, the JS loader/SCSS folder conventions) was established while building the Settings page — see `docs/plans/05-settings-plan.md` §10 for the full explanation and a code example. This plan has been updated to match; sections below now describe that real architecture instead of the original per-page-enqueue assumption.

Standalone build plan for the first of five admin pages. This page is read-only — it never writes data, only summarizes what AI Inbox List (Plan 2) has captured. If Plan 2 hasn't been built yet, every section of this page renders its already-designed empty state instead of failing.

## 1. Mockup inventory (source of truth: `html/dashboard.html` + `assets/js/dashboard.js`)

Top nav: Dashboard / AI Inbox / Contacts / Analytics / Settings (static links — already real, no work needed here).

Page header: title "Inbox AI", subtitle, a "Last 30 days" date-range control, an "All forms" form-filter control, a refresh icon button (`#dash-refresh-btn`), a primary "View AI Inbox" button linking to the Inbox page.

Three mutually-exclusive body states, toggled by JS today (`checkEmptyState()` / `doRefresh()`):
- `#dash-skeleton` — shimmer placeholders shown for ~900ms after a refresh.
- `#dashboard-empty` — shown when no forms are monitored; two CTAs ("Select Forms" → Settings/General, "Configure AI Provider" → Settings/AI Provider).
- `#dashboard-populated` — the real content, five parts:
  1. **Summary row**, five cards: New Messages (value + sparkline + "View new messages" → `inbox.html?status=new`), Needs Review (value + "review messages" link → `?status=review`), Urgent Messages (value + mini bar + link → `?priority=urgent`), Replies Sent (value + sparkline + link → `?status=replied`), AI Usage (dollar value + request/token count + link → Settings/Usage).
  2. **Submission Overview** card: Daily/Weekly/Monthly toggle (`#chart-toggle`), a 3-line SVG area/line chart (new/reviewed/replied series) with a legend and date-axis labels, all currently driven by the hardcoded `chartDatasets` object in `dashboard.js`.
  3. **Priority Distribution** card: four rows (urgent/high/normal/low), each a horizontal bar + count + percentage, linking to `?priority=<value>`.
  4. **Recent Messages** table (`#dashboard-table-body`, 6 rows via the shared `rowHtml()` helper from `common.js`) with a "View all messages" footer link.
  5. Right column: **Attention Required** (4 clickable rows: urgent count, failed-analysis count, low-confidence count, drafted-awaiting-approval count, each linking into Inbox with a filter), **AI Processing Status** (circular progress ring, processing-now/completed-today/failed-today/last-run rows, a "Retry failed items" button), **Categories** (7 rows: Sales/Support/Billing/Partnership/Feedback/Spam/Other, each linking to `?category=<value>`), **Recent Activity** timeline (6 items), **AI Provider Status** (provider/model/connection-pill/last-checked + Test Connection + Manage Settings buttons).

Current JS (`dashboard.js`): `chartDatasets`, `renderDashboardTable()`, `checkEmptyState()`, the chart-toggle click handler, `doRefresh()`, the retry-queue click handler, `testConnection()` (shared with Settings via `common.js`), and row-menu delegation (view/reply → redirect to `inbox.html?view=<id>`; more → reviewed/archive/retry against the local mock array).

## 2. Data model this page reads (writes belong to Plan 2)

All from tables Plan 2 owns (`{prefix}inboxai_messages`, `{prefix}inboxai_activities`, `{prefix}inboxai_usage`) plus Settings' stored "monitored forms" list (Plan 5) to decide empty vs. populated state. This page adds no new tables and no new columns.

## 3. Backend components to build

- `includes/Database/MessageRepository.php` (shared with Plan 2, extended here) — read-only aggregate methods:
  - `count_new()`, `count_needs_review()`, `count_urgent_open()`, `count_replied( $period )`, `count_low_confidence( $threshold = 70 )`
  - `get_priority_distribution( $period )` → `[priority => count]`
  - `get_category_distribution( $period )` → `[category => count]`
  - `get_recent( $limit = 6 )` → rows shaped exactly like the mock `messages` objects (`name`, `initials`, `color`, `email`, `form`, `preview`, `priority`, `category`, `confidence`, `status`, `received`) so `rowHtml()` in `common.js` needs zero changes.
  - `get_submission_trend( 'daily'|'weekly'|'monthly' )` → `{ axis: [...], nw: 'x,y x,y...', rv: '...', rp: '...' }`, matching `chartDatasets`' exact shape so the existing SVG-drawing code is untouched.
  - `get_failed_today()`, `get_completed_today()`, `get_processing_count()`, `get_last_run_time()` for the AI Processing Status widget (these depend on Plan 2's async queue existing — if it doesn't exist yet, this widget can legitimately show zeros/"—" rather than being blocked).
- `includes/Database/ActivityRepository.php` — `get_recent( $limit = 6 )` for the Recent Activity timeline.
- `includes/Database/UsageRepository.php` — `get_period_totals( $period )` → requests/tokens/estimated cost, for the AI Usage summary card.
- `includes/Admin/Pages/OverviewPage.php` — assembles the repository methods above into one view-model array (or leaves that to the AJAX action only — see below) and is the only class the page renderer talks to. `render()` checks `inboxai_view_messages` and calls `Support\Template::render( 'overview', $view_model )` — no inline markup in the class itself, and **no enqueue call of any kind**: `includes/Admin/Menu.php` enqueues the one shared `build/admin.js`/`build/admin.css` bundle for every registered page (see `docs/plans/05-settings-plan.md` §10). Instead, `OverviewPage`'s constructor hooks the shared `inboxai_localize_data` filter, checking `$slug === 'inboxai-overview'` before adding its own `inboxai_overview` nonce to the shared `window.inboxaiAdmin` payload — same pattern as `SettingsPage::localize_data()`.
- `includes/Templates/overview.php` — the actual HTML for the page (summary cards, chart container, tables, right column), ported near-verbatim from `html/dashboard.html`'s populated/empty/skeleton markup. Plain PHP + `esc_html()`/`esc_attr()` for anything server-rendered on first paint; the enqueued JS then fills in/updates values via AJAX, same relationship the static mockup already has between its HTML and `dashboard.js`.
- `includes/Admin/Ajax/OverviewAjaxController.php` — **not one shared `AjaxController` class across every plan, as this line originally said.** By the time the Contacts List page (Plan 3) was built, the single shared controller had grown large enough to be split into one class per admin page under `includes/Admin/Ajax/` (`SettingsAjaxController`, `InboxAjaxController`, `ContactsAjaxController` today), each extending a shared `BaseAjaxController` that holds just the nonce + `current_user_can()` check every action opens with. This page should follow that same convention with its own `OverviewAjaxController` rather than adding to any existing one. Add action `inboxai_get_overview` (nonce `inboxai_overview`, capability `inboxai_view_messages`), returns the view-model data as JSON for the client-side JS to consume. A `period`/`granularity` request param selects daily/weekly/monthly for the chart.

## 4. Frontend build plan (`src/admin/componets/overview/`)

Same vanilla JS/CSS/HTML approach as the static mockup — plain `fetch()` calls to `admin-ajax.php`, no framework — just reorganized as small, single-purpose modules under `src/admin/` instead of one large `assets/js/dashboard.js`. All of it compiles into the one shared `build/admin.js`/`build/admin.css` bundle (webpack.config.js disables code-splitting — see `docs/plans/05-settings-plan.md` §10), enqueued once for every plugin page by `Menu.php`; there is no separate per-page bundle. Breakdown mirrors section 1's five parts:

- `index.js` — exports `initOverviewPage()`; added as one entry to the `loaders` map in the shared `src/admin/index.js`, keyed by `data-page="overview"` (set on `#main` by `includes/Templates/overview.php`), same convention as `initSettingsPage()`. Wires up the initial fetch and re-render calls; owns the skeleton/empty/populated state switch (the same class-toggling `checkEmptyState()` already does, just moved here).
- `api.js` — a small wrapper around `fetch( window.inboxaiAdmin.ajaxUrl, { method: 'POST', body: new URLSearchParams({ action: 'inboxai_get_overview', period, nonce: window.inboxaiAdmin.nonce }) } )`, returning parsed JSON; every other module calls through this rather than hitting `fetch()` directly. `window.inboxaiAdmin` is the one shared localized object every page gets (see `docs/plans/05-settings-plan.md` §10) — this page's `nonce` value on it comes from `OverviewPage`'s `inboxai_localize_data` filter hook, not a page-specific global.
- `summaryCards.js` — renders/updates the five summary cards from the fetched payload; "View ..." links stay plain `<a href>`s already in the PHP-rendered markup (real cross-page navigation, not client routing), so this module only needs to fill in values, not build links.
- `chart.js` — the existing inline-SVG coordinate-generation approach from `dashboard.js`'s chart-toggle handler, ported as-is (just relocated and, if useful, split into a small reusable `drawLineChart()` helper shared with Plan 4's Analytics chart); the Daily/Weekly/Monthly toggle re-requests via `api.js` and redraws.
- `priorityDistribution.js`, `recentMessagesTable.js`, `attentionRequired.js`, `aiProcessingStatus.js`, `categories.js`, `recentActivity.js`, `aiProviderStatus.js` — one small module each, matching section 1's breakdown, each exporting a `render(container, data)` function fed by the same fetched payload; `recentMessagesTable.js` reuses the shared `rowHtml()` helper (moved from `common.js` into a `src/admin/componets/shared/` module, see Plan 2) rather than duplicating row markup.
- Refresh button re-runs `api.js`'s fetch; the skeleton-then-reveal UX stays the same ~900ms-then-reveal pattern already built, now triggered by the fetch's real latency rather than a fixed timer once data is live.
- Row-menu actions (reviewed/archive/retry) call Plan 2's existing AJAX actions via the same `fetch()`-to-`admin-ajax.php` pattern, then re-run this page's own fetch — same "re-fetch on any mutation" behavior as originally planned.
- Styling: `src/admin/scss/common/` already has the shared partials (`_variables.scss`, `_base.scss`, `_buttons.scss`, `_card.scss`, `_fields.scss`, `_switch.scss`, `_modal.scss`, `_toast.scss`) from the Settings build — reuse them rather than duplicating. This page's own rules (summary cards, chart, priority/category bar lists, processing-status ring, etc.) go in a new `src/admin/scss/overview/` folder, one partial per widget, `@use`'d from the single `src/admin/scss/index.scss` entry (see `docs/plans/05-settings-plan.md` §10) — compiled by the same webpack/wp-scripts build into `build/admin.css`.
- Icons stay exactly as in the static mockup (inline SVG/markup already in `includes/Templates/overview.php`) — no icon library needed.
- Tests: one Jest test per non-trivial module under `src/tests/` (e.g. `summaryCards.test.js`, `chart.test.js` — testing the pure render/data-shaping functions, not DOM snapshots), run via `wp-scripts test-unit-js`.

## 5. Security

- `inboxai_get_overview` requires `inboxai_view_messages` and a valid nonce; read-only, so no state-changing risk, but still nonce-gated to avoid unauthenticated scraping of message counts.
- All values echoed into the PHP template (`includes/Templates/overview.php`) go through `esc_html()`/`esc_attr()`. For the AJAX-refreshed portions, the previous concern about `common.js`'s string-concatenation `rowHtml()`/badge helpers still applies exactly as originally planned: prefer `textContent` assignment or an explicit escaping helper over raw `innerHTML` string-building once real (attacker-influenced) `subject`/`preview`/`name`/AI-summary text flows through these modules instead of fixed mock strings.

## 6. Edge cases / empty states

- Zero forms monitored → `dashboard-empty`, both CTAs working (already real links to Settings).
- Forms monitored but zero submissions yet → treat as populated with all-zero cards, not the empty state (the empty state's copy is specifically about disabled forms, not "no data yet" — confirm this distinction stays clear, possibly needs a second, milder empty message for "monitored but nothing submitted yet").
- AI provider never configured → the AI Provider Status card and Test Connection should reflect "Not configured" rather than a fake "Connected" pill.
- Chart with fewer data points than the mockup's fixed axis arrays (e.g., plugin installed 3 days ago, "monthly" view requested) → `get_submission_trend()` must degrade gracefully to however many buckets actually have data.

## 7. Testing checklist

- Fresh install, no forms monitored → empty state, both CTAs navigate correctly.
- One form monitored, no submissions yet → populated view with all-zero/empty widgets, no PHP notices from empty aggregate queries.
- Real submissions present (seed via Plan 2) → every summary card, chart, and list matches what direct SQL against `inboxai_messages`/`inboxai_activities`/`inboxai_usage` shows for the same period.
- Refresh button round-trips through skeleton → populated with updated numbers.
- Chart toggle correctly re-requests/re-renders for daily/weekly/monthly.
- Row-menu retry/reviewed/archive actions update both this page's counts and the underlying row (spot-check against AI Inbox List, Plan 2).
- Capability check: a user without `inboxai_view_messages` gets a WordPress permission error, not a broken/blank page.
- Jest tests in `src/tests/` for `summaryCards.js`, `chart.js`, and the skeleton/empty/populated switch logic pass (`npm run lint:js` and the `test-unit-js` script wp-scripts provides).

## 8. Step-by-step build order

1. `MessageRepository`/`ActivityRepository`/`UsageRepository` aggregate methods (numbers 1–1 in section 3), tested directly against seeded rows before touching any UI.
2. `OverviewPage` view-model assembler.
3. `inboxai_get_overview` AJAX action.
4. Port `includes/Templates/overview.php`'s markup from `html/dashboard.html` verbatim, then build the `src/admin/componets/overview/` modules in section 4 one at a time (summary cards first, chart second, everything else third) against the real payload — port `html/assets/css/inboxai.css`'s relevant rules into `src/admin/scss/` alongside each.
5. Wire the empty-state flag to Plan 5's real monitored-forms setting once it exists (until then, hardcode `forms_enabled = true` so this page isn't blocked waiting on Plan 5).
6. Add `'inboxai-overview' => array( 'Overview', 'Overview', Capabilities::VIEW_MESSAGES, OverviewPage::class )` to `Menu::PAGES` — there's no iframe fallback to replace anymore (it was removed entirely; see `docs/plans/05-settings-plan.md` §10). `Menu.php` automatically enqueues the shared bundle on this new screen; `OverviewPage`'s constructor just needs to hook `inboxai_localize_data` for its nonce.
7. Run the full testing checklist above. (No Jest suite exists yet in this codebase — see the note in `docs/plans/05-settings-plan.md` §7 — so this is manual verification for now, same as Settings.)

## 9. Explicit dependencies

- **Hard dependency on Plan 2** for every non-zero number this page shows — buildable and demoable before Plan 2 exists (empty/zero states), but not meaningfully "done" until Plan 2 is capturing real messages.
- **Soft dependency on Plan 5** for the monitored-forms empty-state flag and the AI Provider Status card's real connection state — safe to stub both until Plan 5 lands.
