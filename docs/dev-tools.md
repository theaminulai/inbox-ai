# Developer tools

Internal scripts for local development and testing. None of this ships with
the plugin — everything under `tools/` is dev-only and must be deleted (or
excluded from the build) before packaging for the WordPress.org SVN/zip.

## `tools/seed-demo-data.php` — demo data seeder

Populates the AI Inbox, Contacts, and Usage & Billing screens with 100
realistic fake submissions, so you have something to click through without
waiting for real Contact Form 7 traffic.

### What it creates

- 100 rows in `wp_inboxai_messages`, spread across a pool of 25 recurring
  sender names/emails (so the Contacts List has senders with more than one
  message to group).
- `created_at` timestamps randomized across the last 90 days (not all
  "just now"), so the date-range filters on the AI Inbox and Usage & Billing
  tabs have something real to filter.
- A realistic workflow-status mix: roughly 45% `new`, 20% `reviewed`,
  25% `replied`, 5% `archived`, 5% `failed` — matching what a real inbox
  looks like rather than every row sitting at `new`.
- Category, priority, confidence score, and AI provider/model per row
  (rotating across OpenAI, Anthropic, and Gemini), plus a matching
  `ai_summary`/`ai_reasoning`/`reply_draft`.
- Matching rows in `wp_inboxai_activities` (`received`, `ai_analysis_completed`
  or `ai_analysis_failed`, `reply_sent`) so the Submission Detail screen's
  Activity Timeline isn't empty either.
- Real Contact Form 7 forms if any exist on the site (via `get_posts()` on
  `wpcf7_contact_form`); falls back to a placeholder `form_id`/title if none
  are found yet.

It does this by calling the plugin's own `MessageRepository::insert()`,
`MessageRepository::update_analysis()`, `MessageRepository::set_reply_sent()`,
`MessageRepository::mark_failed()`, and `ActivityRepository::log()` — the
same methods the real submission/AI-analysis flow uses — rather than writing
raw `INSERT` statements, so the seeded rows can't drift out of sync with
whatever the schema actually expects. The one exception is backdating
`created_at`: none of those methods expose a way to set a custom timestamp
(intentionally — real submissions should always use "now"), so the script
does a direct `$wpdb->update()` for that one column after each insert.

### How to run it

**Option 1 — WP-CLI** (if installed):

```
wp eval-file wp-content/plugins/cf7-ai-inbox/tools/seed-demo-data.php
```

**Option 2 — browser**, while logged into wp-admin as an administrator, visit
the file directly with a confirmation flag:

```
http://localhost/<your-site-path>/wp-content/plugins/cf7-ai-inbox/tools/seed-demo-data.php?confirm=yes
```

The `?confirm=yes` is required on purpose — without it, or without being
logged in as an admin, the script refuses to run. That's the only safety gate
here; there's no CSRF nonce, so don't leave this file reachable on anything
but a local/dev environment.

Safe to run more than once — each run just adds another 100 rows on top of
whatever's already there. There's no dedup against previous seed runs.

### Customizing it

Everything worth tweaking is declared as a plain variable near the top of the
file, not buried in logic:

- `$total` — how many rows to create (default 100).
- `$categories` / `$priorities` / `$providers` — the pools random rows are
  drawn from.
- The `$roll <= N` thresholds control the workflow-status distribution — shift
  those if you want more/fewer `replied` or `failed` rows for a specific test.

### Before release

Delete `tools/seed-demo-data.php` (and this doc, or move it out of the
packaged `docs/` if that folder ships) before cutting the SVN/zip for
WordPress.org. It has no place in front of end users and was never meant to.
