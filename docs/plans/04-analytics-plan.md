# End-to-End Plan: Analytics Page (`cf7ai-analytics`, `html/analytics.html`)

**Note:** the shared admin-page architecture referenced throughout this plan (Menu-centralized enqueuing, the `cf7ai_inbox_localize_data` filter, the JS loader/SCSS folder conventions) was established while building the Settings page — see `docs/plans/05-settings-plan.md` §10 for the full explanation and a code example. This plan has been updated to match; sections below now describe that real architecture instead of the original per-page-enqueue assumption.

Standalone build plan for the fourth of five admin pages, and the least-built one on both ends today: the backend needs two new activity-event types beyond what Plan 2 already logs, and the front end (`assets/js/analytics.js`) is currently an empty placeholder rather than a rewire target.

## 1. Mockup inventory (source of truth: `html/analytics.html`)

Page header: title, subtitle, a "Last 90 days" date-range control (present in the markup, currently has no click handler anywhere — this page is where it needs one). Four KPI cards: Avg. first response time, Reply rate, AI accuracy (self-reported), Avg. AI confidence. Two-column content grid:

- Left: **Submissions by Category** (SVG bar chart, 7 categories), **Response Time Trend** (SVG line chart over months).
- Right: **Top Performing Forms** (usage-bar rows, one per form, "% replied"), **Confidence Distribution** (three bands: High ≥70%, Medium, Low <40%, each a bar + count + percentage).

Every number on this page is currently hardcoded directly into the HTML (unlike the other four pages, there isn't even mock JS data driving it) — this plan effectively designs the data layer and the rendering code from scratch, using the mockup only as the exact target shape/values to match structurally.

## 2. Data model — two new activity event types

Everything here reads from `{prefix}cf7ai_messages` and `{prefix}cf7ai_activities`, both owned by Plan 2's schema — no new tables. Two additions to what Plan 2 logs are needed specifically for this page's KPIs:

- A `received` event (timestamp = capture time) and a `replied` event (timestamp = send time) — Plan 2's plan already includes logging these for its own Activity timeline, so this is confirmation, not new work, that both timestamps are queryable per message for "avg. first response time" (`AVG( replied.created_at - received.created_at )`).
- A `category_overridden` / `priority_overridden` event, logged whenever an admin edits a message's `category` or `priority` to a value different from what the AI originally set (Plan 2's edit endpoints need one extra check-and-log step: compare the incoming value to the current value before saving, and only write the AI's original value into `event_data` if the values actually differ). "AI accuracy (self-reported)" = `1 − ( overridden_count / analyzed_count )` over the period.

## 3. Backend components to build

- `includes/Database/AnalyticsRepository.php` — one method per KPI/chart, each scoped to a `$period` (start/end dates derived from the date-range control):
  - `avg_first_response_time( $period )` — average of `replied.created_at − received.created_at` across messages with both events.
  - `reply_rate( $period )` — `COUNT(workflow_status = 'replied') / COUNT(*)`.
  - `ai_accuracy( $period )` — `1 − (overridden events / analyzed messages)` per the event types above.
  - `avg_confidence( $period )` — `AVG(confidence)` across analyzed messages.
  - `category_distribution( $period )` — `GROUP BY category`, matching the bar chart's 7 categories.
  - `response_time_trend( $period, $granularity )` — average response time bucketed by week/month, shaped for the existing line-chart SVG approach (an `axis` array + a `points` polyline string, same pattern as Plan 1's `get_submission_trend()`).
  - `top_forms( $period )` — per-`form_title` reply rate, ordered descending, for the usage-bar list.
  - `confidence_distribution( $period )` — counts in the High/Medium/Low bands.
- Caching: every one of the above is a full-table aggregate, more expensive than Plan 1's simpler counts. Wrap each in a WordPress transient keyed by `md5( method name + period + granularity )`, TTL ~15 minutes, invalidated early by Plan 2's write paths calling `delete_transient()` (or just letting the TTL expire — for an admin-only analytics page, a 15-minute-stale number is an acceptable tradeoff against invalidating on every single message write).
- `includes/Admin/Pages/AnalyticsPage.php` — same thin shape as the other four page classes: capability check (`cf7ai_view_analytics`, not `cf7ai_view_messages` — see section 5), assemble the view-model, call `Support\Template::render( 'analytics', $view_model )` with markup in `includes/Templates/analytics.php`. **No enqueue call**: `Menu.php` enqueues the shared `build/admin.js`/`build/admin.css` bundle for every registered page; this class's constructor instead hooks the shared `cf7ai_inbox_localize_data` filter, checking `$slug === 'cf7ai-analytics'`, to add its own nonce — see `docs/plans/05-settings-plan.md` §10.
- `includes/Admin/AjaxController.php` (shared with Plans 1, 2, 3, and 5 — one controller class for every admin-page AJAX action) — `cf7ai_get_analytics` (nonce `cf7ai_analytics`, capability `cf7ai_view_analytics` — note this is a *different* capability than the other pages use, already defined in `Capabilities.php` and already grantable to a role that shouldn't see raw message content but should see aggregate performance).

## 4. Frontend build plan (`src/admin/componets/analytics/` — net-new, not a rewire)

This page has no working JS today (`assets/js/analytics.js` is a placeholder), so there's no legacy logic to port — it's built directly as vanilla JS modules against `cf7ai_get_analytics`. All of it compiles into the one shared `build/admin.js`/`build/admin.css` bundle, not a separate bundle per page (webpack.config.js disables code-splitting — see `docs/plans/05-settings-plan.md` §10):

- `api.js` — shared `fetch()`-to-`admin-ajax.php` wrapper (same as the other four plans), reading from `window.cf7aiInboxAdmin` rather than a page-specific global.
- `index.js` — exports `initAnalyticsPage()`, added as one entry to the `loaders` map in the shared `src/admin/index.js`, keyed by `data-page="analytics"`. Owns the date-range control's state (30/90/365 days, or a custom range) and re-runs the fetch/redraw on change.
- `kpiCards.js` — populates the four KPI cards, including the delta sub-text (e.g. "▼18% faster than last period"), which requires the endpoint to also return the *previous* period's numbers — every `AnalyticsRepository` method in section 3 should accept a comparison-period flag, or the endpoint computes both in one call.
- `categoryChart.js` and `responseTimeTrend.js` — the Submissions by Category bars and Response Time Trend line, drawn with the same inline-SVG coordinate-generation approach as Plan 1's chart (share a small `drawBarChart()`/`drawLineChart()` helper via `src/admin/componets/shared/charts.js` rather than duplicating the coordinate math in both pages).
- `topFormsList.js` and `confidenceDistribution.js` — the two right-column bar lists, populated from the same payload.
- The "Last 90 days" control is a real `<select>`/dropdown wired to `index.js`'s period state, re-fetching on change — this is the one piece of genuinely new UI behavior this page needs beyond what's already drawn in the mockup.
- Styling: reuse `src/admin/scss/common/`'s shared partials; this page's own rules go in a new `src/admin/scss/analytics/` folder, `@use`'d from `src/admin/scss/index.scss`. Tests: same Jest approach as the other four plans in principle — **not actually set up anywhere yet** (`src/tests/index.js` is an empty stub, no `test-unit-js` script exists); Settings shipped without it too.

## 5. Security

- `cf7ai_get_analytics` requires `cf7ai_view_analytics` specifically (not `cf7ai_view_messages`) — this is intentional per the capability system's design (R&D §13): a role that should see performance metrics but not individual customer messages is a real, supported use case, and this is the one page where that distinction actually matters.
- All aggregate-only, read-only, no message content is ever returned by this endpoint (category/priority/counts only) — this page could even be safely exposed to a broader set of users than AI Inbox List, precisely because it never returns `sender_email`, `message`, or `ai_summary`.
- Date-range input sanitized/validated (whitelist of allowed granularities, numeric bounds on custom day counts) before hitting any SQL.

## 6. Edge cases

- No messages yet, or no replies yet → every KPI legitimately divides by zero; each `AnalyticsRepository` method must guard for `COUNT(*) = 0` and return null/"—" rather than a PHP warning or `NaN` reaching the front end.
- No overridden categories/priorities yet → "AI accuracy" is 100% by the formula above, which is correct but should probably be labeled distinctly from "100% based on N reviewed" vs. "no data yet" (0 analyzed messages) — the endpoint should distinguish "accuracy unknown" from "accuracy is literally 100%."
- A period with data in some buckets but not others (e.g. plugin installed mid-month) → `response_time_trend()` must return whatever buckets have data rather than a fixed-length array assuming a full period, same note as Plan 1's chart.

## 7. Testing checklist

- Seed a range of messages with varied `received`/`replied` timestamps, confidences, categories, and a few manual category/priority overrides → every KPI and chart matches a hand-computed value from the same seed data.
- Zero-data fresh install → no PHP errors, sensible "no data yet" rendering instead of blank/broken charts.
- Date-range control correctly changes every widget on the page, not just one.
- A user with `cf7ai_view_analytics` but not `cf7ai_view_messages` can load this page; a user with neither cannot.
- Transient caching: confirm a cached response is served within the TTL window and a fresh one after it expires or after a manual `delete_transient()`.
- Jest tests for `kpiCards.js`, `categoryChart.js`, and `responseTimeTrend.js` pass, including the zero-data "no data yet" rendering path.

## 8. Step-by-step build order

1. Confirm Plan 2 is logging `received`/`replied` activity events (should already be true from Plan 2's own plan) and add the `category_overridden`/`priority_overridden` logging to Plan 2's edit endpoints.
2. `AnalyticsRepository` methods, one at a time, each verified directly against seeded SQL data before any UI exists.
3. Transient caching wrapper.
4. `AnalyticsPage` view-model assembler + `cf7ai_get_analytics` action.
5. Port `includes/Templates/analytics.php`'s markup from `html/analytics.html`, then build `src/admin/componets/analytics/` from scratch per section 4 — KPI cards first (simplest), then the two charts, then the two bar lists, then the date-range control last.
6. Add `'cf7ai-analytics' => array( 'Analytics', 'Analytics', Capabilities::VIEW_ANALYTICS, AnalyticsPage::class )` to `Menu::PAGES` — no iframe fallback exists to replace (removed entirely; see `docs/plans/05-settings-plan.md` §10). `Menu.php` automatically enqueues the shared bundle; `AnalyticsPage`'s constructor just needs to hook `cf7ai_inbox_localize_data` for its nonce.
7. Run the full testing checklist. (No Jest suite exists yet in this codebase, so this is manual verification, same as Settings.)

## 9. Explicit dependencies

- **Hard dependency on Plan 2**, plus two small additions to Plan 2's own write paths (the override-logging check) that should be called out to whoever builds Plan 2, even if Plan 4 itself is built later.
- **No dependency on Plans 1, 3, or 5.**
