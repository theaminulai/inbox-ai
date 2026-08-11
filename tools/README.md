# Testing & exploring the real system

This is the practical companion to `docs/dev-tools.md` (which documents
`seed-demo-data.php` in depth) and `CLAUDE.md` (which documents the code).
This file is about the other half of the job: actually poking the *running*
plugin on this machine — clicking through wp-admin, watching what an AJAX
call actually returns, checking what landed in the database — instead of
only reading source and assuming it works.

Read this before telling anyone (a person or an AI agent picking up this
project cold) that a feature "works." Code review is not the same as
verification.

**There is no `wp` command-line tool set up in this environment.** This
plugin vendors the WP-CLI *framework* as a PHP library
(`vendor/wp-cli/wp-cli/`), but that's only used internally by the
`make-pot` npm script — it is not the same thing as having `wp` installed
as a shell command, and it isn't one here. Every technique below works
through the browser or phpMyAdmin instead. If a real WP-CLI install ever
does get added to this machine, `wp eval`/`wp eval-file`/`wp option get`
become shortcuts for some of this — but don't assume they exist.

## Explore the whole plugin first, before testing any one feature

Everything below this section is organized per-feature — useful once you
already know what you're looking for. Start here instead if the goal is
just to see what this plugin actually is and does, end to end, before
zooming into any one part of it.

1. **Read `CLAUDE.md`** — five minutes, and it tells you what's real. Only
   three admin pages actually exist and are registered (`Admin\Menu::PAGES`):
   AI Inbox, Contacts, Settings. `docs/plans/*.md` also describes an
   Overview/Dashboard, an Analytics page, and a Campaigns page in detail —
   none of them have any corresponding code. Don't go looking for them in
   wp-admin; they're not there.
2. **Log into wp-admin and seed demo data** (see the next section) so
   every screen below actually has something on it instead of an empty
   state.
3. **Walk all three admin pages in order, not just one:**
   - **AI Inbox** (`admin.php?page=inboxai-inbox`) — the core screen. Try
     the filters (form, status, priority, category, confidence, date
     range) and the search box first, then open one submission. Its detail
     screen is where most of the plugin's depth actually lives: the
     Conversation thread (original submission plus every reply, oldest
     first), a sidebar in this order — Customer, Submission details, Quick
     actions, Customer Mood, Activity Timeline — and an AI Analysis card
     always pinned at the bottom regardless of how many replies came in
     above it. Try "Mark reviewed," "Archive," and (on a seeded row)
     "Retry" on a failed one.
   - **Contacts** (`admin.php?page=inboxai-contacts`) — looks like its own
     data source but isn't: it's computed live from `wp_inboxai_messages`
     grouped by `sender_email`, not a separately maintained table (the
     `wp_inboxai_contacts` table exists but nothing reads or writes it —
     don't expect anything to show up there directly).
   - **Settings** (`admin.php?page=inboxai-settings`) — click through all
     seven tabs once, even the ones you're not currently changing: AI
     Provider, General, Prompts, Usage & Billing, Notifications, Import &
     Migration, Integrations. Seeing what already exists on each tab
     before editing one avoids re-adding a field/card that's already
     there under a different name.
4. **Browse the full database, not just the one table/option relevant to
   whatever you were just doing.** In phpMyAdmin (`ai-inbox` database):
   list every `wp_inboxai_*` table (`messages`, `activities`, `usage`,
   the dead `contacts`) and filter `wp_options` by `option_name LIKE
   'inboxai_%'` to see every setting this plugin persists, all at once —
   the per-feature table further down only lists what each one *means*.
5. **Open DevTools → Network tab and just click around normally for a few
   minutes.** You'll see the plugin's real, complete `wp_ajax_inboxai_*`
   surface organically — every action name, what it's called with, what
   it returns — which is a faster way to learn the AJAX surface than
   grepping `add_action( 'wp_ajax_...'` across the codebase by hand.

Once you've actually seen all of that firsthand, `CLAUDE.md`'s
architecture notes (why WP-Cron is used the way it is, why Settings is
split into per-tab options, why Contacts has no real table, etc.) will
make sense as explanations of things you've already looked at, not
abstract claims to take on faith.

## This environment, specifically

- Local WAMP install. WordPress root is this plugin's grandparent's
  grandparent folder — the same folder `wp-config.php` lives in. Under
  WAMP's default `www` document root, that's normally reachable at
  `http://localhost/wp-plugin/cf7-ai-inbox/`.
- Database: `ai-inbox` (MySQL, `root`/no password, `localhost`) — table
  prefix `wp_`. Browse it directly at `http://localhost/phpmyadmin/` — this
  is the primary way to inspect stored data in this environment, not an
  optional extra.
- `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY`, and `SAVEQUERIES` are all
  **on** (see `wp-config.php`). Every PHP notice/warning/error — including
  ones a `try/catch` swallowed from the user's view — lands in
  `wp-content/debug.log`. Check that file before assuming a silent failure
  has no trace.
- The plugin's own admin pages, once logged into wp-admin:
  - AI Inbox: `wp-admin/admin.php?page=inboxai-inbox`
  - Contacts: `wp-admin/admin.php?page=inboxai-contacts`
  - Settings: `wp-admin/admin.php?page=inboxai-settings&tab=<tab-key>`
    (`ai-settings`, `general-settings`, `prompts`, `usage`,
    `notifications`, `flamingo`, `integrations`)

## Get something to click through: seed demo data

An empty inbox tells you nothing about whether the list, filters, or detail
screen actually work. Run `tools/seed-demo-data.php` first — see
`docs/dev-tools.md` for the full explanation of what it creates. In this
environment, running it means visiting the file directly in the browser
while logged into wp-admin as an administrator:

```
http://localhost/wp-plugin/cf7-ai-inbox/wp-content/plugins/inbox-ai/tools/seed-demo-data.php?confirm=yes
```

Safe to run repeatedly — every run adds another 100 rows. It does **not**
touch anything under Settings (API keys, Slack, CRM, Inbound Email) — those
still need real values entered by hand, since they're credentials, not
inbox content.

## Testing the Import & Migration wizard specifically

There are two other CSV files in this folder, and they exist for a
different purpose than `seed-demo-data.php`: they're fixtures for
exercising the actual Settings → Import & Migration wizard (the
`flamingo.php` tab template / `flamingoImportTab.js`) end to end — file
upload, column detection, the "Run AI analysis" option, batch progress,
and re-upload dedup — rather than inserting rows directly like the seeder
does.

- **`inbox-ai-demo-50.csv`** is shaped like a real **Flamingo export**
  (`your-name`, `your-email`, `your-subject`, `your-message`, a trailing
  `Date` column — arbitrary CF7-style field names, not this plugin's own
  columns). Use it to test the **Flamingo** path:
  1. Settings → Import & Migration.
  2. Step 1 "Type": choose **Flamingo** → Next.
  3. Step 2 "Source": choose **Upload a CSV export** → Choose File → pick
     `inbox-ai-demo-50.csv` → wait for the green "detected N messages" line
     → Next.
  4. Step 3 "Options": optionally toggle "Run AI analysis on imported
     messages" (needs a real API key saved on the AI Provider tab if
     enabled) → Next.
  5. Step 4 "Review & Import": confirm the row count looks right (50) →
     **Start Import**.
  6. Step 5 "Complete" → **View AI Inbox** to confirm the rows actually
     landed there.

- **`inbox-ai-native-demo-50.csv`** is shaped for this **plugin's own
  columns** directly (`sender_name`, `sender_email`, `source_category`,
  `category`, `priority`, `confidence`, `workflow_status`, `created_at`,
  …) — useful for loading rows with a specific priority/category/status
  already set, without needing a real AI call. Use it to test the **Inbox
  AI CSV** path: same wizard, but Step 1 choose **Inbox AI CSV** instead,
  which skips straight to a single upload panel in Step 2 (no
  live-vs-CSV sub-choice). The exact recognized column list is documented
  in the wizard's own "Notes" card at the bottom of that tab — check there
  first if a column doesn't seem to be picked up.

Both paths are safe to re-run with the same file — already-imported rows
are skipped by content hash, not duplicated (see `CLAUDE.md` → Migration/
import). If a file uploads but "detected N messages" never appears, that's
an AJAX failure — see the Network tab / `debug.log` guidance below before
assuming the CSV itself is malformed.

The "Use live Flamingo data" option in Step 2 only applies if the actual
Flamingo plugin is installed and active on this site — if it isn't, that
option is disabled and the CSV upload is selected automatically, which is
expected, not a bug.

## Watching what's actually happening, not what the UI claims

A green toast only proves the JS ran `showToast('...', 'success')` — it
does not prove the AJAX call it followed actually succeeded server-side, or
that the thing it claims to have done actually happened. Cross-check with
at least one of these before calling something confirmed:

- **Browser DevTools → Network tab.** Every Settings/Inbox action goes
  through `admin-ajax.php` with an `action` param matching the
  `wp_ajax_inboxai_*` hook name (see the relevant `Ajax\*Controller::init()`).
  Click the request, check the actual JSON response body — `success`/`data`
  on a pass, `success: false` + `data.message` on a failure. This is the
  single most useful tab open while testing anything in this plugin.
- **`wp-content/debug.log`.** Tail it while you click around (or just
  refresh-and-reread it after each action — there's no live-tail tool set
  up here). A PHP fatal, warning, or uncaught exception inside an AJAX
  handler still returns *some* HTTP response (often a blank one, or a
  malformed JSON body that shows up in the Network tab as a parse
  failure) — the log is where the real reason lives.
- **phpMyAdmin** (`http://localhost/phpmyadmin/`, database `ai-inbox`) —
  the only reliable way to confirm a save actually persisted, versus the UI
  just optimistically re-rendering. See the table/option names below.

## Where things are actually stored

Every Settings tab is its own `wp_options` row (see `CLAUDE.md` → Settings).
Current option names, browsable directly in phpMyAdmin's `wp_options`
table (filter `option_name LIKE 'inboxai_%'`):

| Tab / feature | Option name(s) |
|---|---|
| AI Provider | `inboxai_settings_provider`, `inboxai_api_key` (encrypted) |
| General | `inboxai_settings_general` |
| Prompts | `inboxai_settings_prompts` |
| Notifications | `inboxai_settings_notifications` |
| Inbound Email Replies | `inboxai_settings_inbound`, `inboxai_inbound_password` (encrypted), `inboxai_inbound_cursor` |
| Slack Integration | `inboxai_settings_slack` |
| CRM Data Collection | `inboxai_settings_crm`, `inboxai_crm_api_key` (encrypted) |
| Schema version | `inboxai_db_version` |
| Encryption key | `inboxai_encryption_key` |

Encrypted values (`Security\Encryption`) are unreadable in the database by
design — don't expect to eyeball a real API key or password there. Use the
masked value shown in the UI, or add a temporary `error_log()` right after
the relevant `get_*()` call if you truly need to confirm the plaintext
during local debugging (never commit that).

Message/activity/usage data lives in `wp_inboxai_messages`,
`wp_inboxai_activities`, `wp_inboxai_usage` (see `Database\Migrator`).
`wp_inboxai_contacts` exists but is dead — nothing reads or writes it; don't
expect seeded or imported data to show up there.

## Deep-diving one feature at a time

The rest of this doc, for when you already know which specific part you're
verifying:

**CF7 submission capture.** The single most common "nothing happened" cause:
the form isn't in Settings → General → Monitored Forms. If a submission
doesn't create a row at all (not even a `failed` one), check that first,
before assuming the capture hook is broken.

**AI analysis pipeline.** Needs a real API key saved on the AI Provider tab
— there is no mock/offline mode. A queued analysis fires almost immediately
(a non-blocking loopback request to `wp-cron.php`, not a wait for the next
real cron tick — see `CLAUDE.md`), so if a submission sits at `new` with no
AI summary for more than a few seconds, something failed silently: check
`debug.log` and the message's own Activity Timeline for an
`ai_analysis_failed` entry. The "Retry" button in the UI calls
`AnalysisQueue::process()` synchronously, which is a faster test loop than
waiting on a fresh submission each time.

**Inbound Email Replies.** Can't be faked with local data — it needs a real
IMAP-reachable mailbox. See `wiki/Inbound-Email-Replies-Setup-Guide.md` for
setup and the common failure modes (cert hostname mismatch, WP-Cron vs. a
real system cron, plus-addressing not supported by some hosts). Always
click "Save settings above, then test connection" after any change —
that's a real, synchronous IMAP connection attempt, not a guess.

**Slack Integration.** Don't test against a real team channel by default —
get a disposable, safe endpoint from `https://webhook.site` (it hands you a
unique URL instantly and shows every request that hits it, no account
needed). Paste that as the webhook URL on Settings → Integrations, turn the
switch on, save, then click "Send test message" — you should see the hit
appear on webhook.site immediately (or in a real Slack channel, if you used
a real Incoming Webhook URL from `api.slack.com/messaging/webhooks`). That
button (`SlackIntegrationService::send_test()`) is a *blocking* call, so a
failure shows up as a red toast with Slack's actual HTTP response, not a
silent no-op — this alone is enough to confirm the webhook itself works.

The automatic path (`SlackIntegrationService::notify_urgent()`) only fires
when a submission's AI-assigned priority comes back `urgent`, which is slow
and non-deterministic to wait for naturally, and can't be triggered from
seeded/imported data (those write rows directly, bypassing
`AnalysisQueue` entirely). To exercise that specific code path directly —
without WP-CLI — use `tools/test-slack-notify.php`, a browser-runnable dev
tool built the same way as `seed-demo-data.php`:

```
http://localhost/wp-plugin/cf7-ai-inbox/wp-content/plugins/inbox-ai/tools/test-slack-notify.php?confirm=yes
```

It calls `notify_urgent()` with fake data through the exact same settings
gate the real pipeline uses, so it also doubles as a check that the
"enabled" switch and saved webhook URL are actually being read correctly.

**CRM Data Collection.** UI scaffold only — there is no live sync to
trigger (deliberately; see `Settings\CrmRepository`'s docblock). The entire
test surface is: pick a provider, enter an API key, save, reload the page,
and confirm the provider selection and masked key both persisted (check
`wp_options` rows `inboxai_settings_crm`/`inboxai_crm_api_key` in
phpMyAdmin if the UI alone doesn't convince you). Don't go looking for
anything beyond that — there is nothing else to verify yet.

**Any new/edited Settings field.** If a field doesn't save, the two most
likely causes: the input's `data-field="x"` name doesn't match the key the
PHP `save_*()` method actually reads from `$_POST['values']`, or the field
lives inside a `.inboxai-screen` section that isn't the one the Save button
you clicked is scoped to (`collectFields()` only reads fields inside the
container it's given — see `add-settings-field` skill for the full
five-layer pattern).

## Front-end build

If you edited anything under `src/`, run `npm run build` before testing in
the browser — nothing there loads at runtime until compiled into `build/`.
A change that "does nothing" in wp-admin after a JS edit is, more often
than not, just an unbuilt bundle, not a bug.

## Before assuming something is broken

1. Did you run `npm run build` after the last `src/` edit?
2. Does `wp-content/debug.log` show a PHP error at the time you tested?
3. Does the Network tab show the AJAX request firing at all, and what does
   its actual response body say?
4. Is the feature's own switch/toggle actually on *and saved* — reload the
   page and check in phpMyAdmin, don't trust the UI's in-memory state?
5. For anything credential-based (AI provider, Inbound Email, Slack, CRM):
   is a real, valid credential actually saved — not just a masked
   placeholder still sitting in the field?
