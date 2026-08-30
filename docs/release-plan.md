# Inbox AI — Release Plan

Draft schedule: 7 releases between **Aug 29** and **Sep 10, 2026**, each with at least 4 changelog lines, mixing bug fixes and new features. This is a working draft — reorder, merge, or swap any item.

> **On versioning:** `package.json` already includes an `update-version-and-changelog.js` script, and version numbers on disk have changed independently of manual edits during this project. This plan intentionally does not assign version numbers — each release below is implemented as a batch of code changes, and the existing versioning script is left to handle version bumps and changelog text.

## Release 1 — Notification reliability

**Fixes only — code already written, ready to ship first.**

* **Fix:** Urgent-submission email actually sends (was saved to Settings but never wired to an email).
* **Fix:** AI-analysis-failure email actually sends (was saved but never wired).
* **Fix:** Reply-draft-ready email actually sends (was saved but never wired).
* **Add:** Daily digest email at 9:00 AM site time — new / unread / urgent submission counts from the last 24 hours.

Target: Aug 29

## Release 2 — Date & time correctness

**Fixes only — a timezone bug plus two settings that quietly do nothing.**

* **Fix:** AI Inbox List date-range filter (7/30/90 days, this month, N years) — was comparing against UTC while submissions are timestamped in site-local time, throwing off results near any boundary.
* **Fix:** Usage & Billing period selector and cost-breakdown chart — same timezone bug, same fix.
* **Fix:** "Keep submissions for" (Data Retention) — build the real purge-by-age cron job; currently saved and never read.
* **Fix:** "Delete attachments after reply" — remove or clearly relabel; the plugin doesn't capture attachments at all yet, so this control has nothing to act on.

Target: Aug 31

## Release 3 — AI Provider reliability

**Fixes only — the "Fallback Behavior" card is currently decorative.**

* **Fix:** "Retry failed requests automatically" — implement real retry with exponential backoff (up to 3 attempts) instead of failing on the first error.
* **Fix:** "Fall back to manual review on repeated failure" — after retries are exhausted, set Needs Review instead of Failed when this is on.
* **Fix:** "Send email alert on provider outage" — wire to a real email, distinct from the analysis-failure email added in Release 1.
* **Fix:** "Request timeout" dropdown — actually control the HTTP timeout per provider (all three currently hardcode 45 seconds regardless of this setting).

Target: Sep 2

## Release 4 — CRM sync, part 1 (HubSpot)

**New feature — turns "CRM Data Collection" from a scaffold into a real integration.**

* **Add:** Push a new submission to HubSpot as a contact/deal when CRM sync is enabled.
* **Add:** Manual "Sync now" button on the submission detail screen.
* **Add:** Sync status indicator (synced / failed / pending) shown per submission.
* **Add:** CRM sync attempts logged to the submission's activity timeline.

Target: Sep 4

## Release 5 — CRM sync, part 2 + usage budget

**New features — Mailchimp completes the CRM card; a budget cap completes Usage & Billing.**

* **Add:** Push a new submission to Mailchimp as a subscriber when CRM sync is enabled.
* **Add:** Monthly budget field on Usage & Billing (currently shows "Not configured yet").
* **Add:** Warning email when estimated spend nears the configured budget.
* **Add:** Optional auto-pause of AI analysis once the budget is exceeded.

Target: Sep 6

## Release 6 — Material Design refresh, part 1

**Visual only — re-does the Material 3 pass from earlier; those file changes didn't persist.**

* **Add:** Material 3 design-token layer (color roles, elevation scale, shape scale, motion) added alongside existing tokens.
* **Add:** Buttons and cards restyled to Material 3 (filled/outlined/icon buttons, elevated cards).
* **Add:** Switches and form fields restyled to Material 3 (outlined text fields, grow-on-select switch).
* **Add:** Badges and status pills aligned to the Material 3 chip shape.

Target: Sep 8

## Release 7 — Material Design refresh, part 2 + QA pass

**Visual, plus a final regression pass before the 10th.**

* **Add:** Modal/dialog and toast/snackbar restyled to Material 3.
* **Add:** Visual sweep of Settings sub-pages (Prompts, Usage, Import & Migration).
* **Add:** Visual sweep of AI Inbox list/detail and Contacts screens.
* **Fix:** Regression pass across all 7 releases — click through every tab once more before calling it done.

Target: Sep 10

---

An interactive, checkbox-tracked version of this same plan is also available as a Cowork artifact (`inbox-ai-release-plan`) for checking items off as they ship.

To start a batch, just say which release to implement (e.g. "do release 1").
