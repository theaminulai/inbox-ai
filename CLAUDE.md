# Inbox AI

WordPress plugin (Contact Form 7 add-on): captures CF7 submissions, runs them through an AI provider (OpenAI/Anthropic/Google) for triage and reply drafting, gives them an inbox/CRM-style admin UI, and threads a customer's email replies back onto the right submission. Published on WordPress.org — see the `wordpress-org-compliance` skill before touching anything that affects the shipped package.

Run `npm run build` after editing anything under `src/` — nothing there loads at runtime until compiled into `build/`.

## What actually exists vs. what's only planned

Only three admin pages are built and registered: **AI Inbox List** (`inboxai-inbox`), **Contacts List** (`inboxai-contacts`), **Settings** (`inboxai-settings`) — see `Admin\Menu::PAGES`. `docs/plans/*.md` also describes an **Overview/Dashboard** page, an **Analytics** page, and a **Campaigns** page — none of these have any corresponding code (no page class, no AJAX controller, no template, no JS folder). Campaigns' own plan doc says so explicitly ("mockup complete, backend not started") and needs two tables that don't exist yet. Don't assume any of these three exist just because a plan doc describes them in detail — check `Menu::PAGES` and `includes/Admin/Pages/` first. If you're asked to build one of them, see the `add-admin-page` skill.

The `inboxai_contacts` table is created by `Database\Migrator` but **nothing reads or writes it** — the real Contacts List is derived live from `inboxai_messages` grouped by `sender_email` (`MessageRepository::get_contacts()`). Don't add code that assumes this table is populated; a real Contacts-import path existed at one point and was reverted at the project owner's request.

## Settings

One `wp_options` row per Settings tab, not one big blob — see `includes/Settings/Repository.php`. Every tab follows the same shape: `get_x()` returns `wp_parse_args($stored, $typed_defaults)`, `save_x(array $data)` whitelists/sanitizes every key against the current value (never trusts `$data` directly). Secrets (API keys, mailbox passwords) never live in these arrays — they get their own encrypted `wp_options` row via `Security\Encryption`, with a masked getter (`get_masked_x()`, returns `•` bullets) and a save guard that refuses to store a string containing `\u{2022}` (a resubmitted masked display value must never overwrite the real secret).

Any Settings input with `data-field="x"` is read/written automatically by `src/admin/componets/shared/fields.js`'s `collectFields()`/`populateFields()` (text/number/select/switch/checkbox/multi-select) — no per-field JS needed for a plain field. For the full five-layer pattern of adding a new field or tab, see the `add-settings-field` skill.

## CF7 submission capture

`CF7\SubmissionHandler` hooks `wpcf7_before_send_mail` (the actual capture point — chosen over `wpcf7_mail_sent` so a submission is captured even if CF7's own mail sending fails), plus `wpcf7_mail_sent`/`wpcf7_mail_failed` (update `mail_status` on the already-captured row only, never insert) and `wpcf7_spam` (captures spam submissions on a separate path that skips AI analysis entirely). Every path is gated by `SettingsRepository::is_form_monitored( $form_id )` — a form not in the "Monitored Forms" list is silently ignored, no row created. Dedup is a 30-second time-bucket hash (`SubmissionMapper::compute_hash()`), distinct from the CSV importers' own per-row content hash (see Migration below).

`CategoryTaxonomy` has **two independent category columns** on a message — don't conflate them: `source_category` is the CF7 form's own admin-assigned taxonomy term, captured once at submission time and never touched again; `category` is the AI's own re-computable classification, rewritten on every (re)analysis. List/filter UI filters against `source_category`, not `category`.

Field mapping (`SubmissionMapper::map()`) is entirely heuristic since CF7 forms are free-form: first `textarea` field → message (else longest field), first field named like `/subject/i` → subject (else a generated default), visitor name/company guessed by field-name regex in `PromptBuilder`.

## AI analysis pipeline

Pure WP-Cron, no Action Scheduler. `AI\AnalysisQueue::enqueue()` schedules a single event then immediately fires a non-blocking loopback request to `wp-cron.php` — deliberately bypassing the `DISABLE_WP_CRON` check WordPress core's own `spawn_cron()` respects, so analysis starts in ~1s even when a production host has moved cron to a real system cron with a coarse interval. `AnalysisQueue::process()` is a plain callable, safe to invoke directly — `InboxAjaxController::retry_analysis()` does exactly that (synchronous, not re-queued) since an admin clicking Retry is already on the screen waiting.

Providers implement `Interfaces\AIProviderInterface` and are registered in `ProviderFactory::PROVIDERS` (`openai`/`anthropic`/`google` → class map) — that's the only two-step wiring a 4th provider needs. `analyze()` is one generic method used for both the analysis call and the reply-draft call, differing only in the prompt text `PromptBuilder` builds.

`ResponseValidator` strips markdown code fences before `json_decode()`, clamps confidence to 0-100, normalizes priority to `urgent|high|normal|low` (defaulting `normal`), and — important — never lets the AI invent a category the admin didn't create: when a form has a fixed category list, a non-matching AI answer normalizes to empty rather than being stored, and `AnalysisQueue::process()` only overwrites the stored `category` when the result is non-empty (a failed-to-match retry never blanks out a previous successful category).

## Database

Four tables via `Database\Migrator` (`inboxai_messages`, `inboxai_activities`, `inboxai_usage`, `inboxai_contacts` — the last one is dead, see above). `Migrator::SCHEMA_VERSION` lives in option `inboxai_db_version`; `maybe_migrate()` is the safety net called every `plugins_loaded` from `Plugin::init()` (cheap no-op once current), re-running the additive-only `dbDelta()` install whenever the stored version is behind — this is what catches an in-place upgrade that skips deactivate/reactivate. `drop_tables()` only ever runs from `Uninstaller`, never on plain deactivation.

Table names are always `$wpdb->prefix . <class constant>` (never user input) — every actual *value* still goes through `$wpdb->prepare()`. `MessageRepository::decode_row()` JSON-decodes the `fields`/`meta` LONGTEXT columns on every read.

## Admin pages / AJAX

Admin pages are hardcoded in `Admin\Menu::PAGES` (`slug => [menu_title, page_title, capability, page_class]`), registered as CF7 submenus (not a separate top-level menu). **One shared asset bundle for every admin page** — `Menu::enqueue_assets()` is the only place `wp_enqueue_script()`/`wp_enqueue_style()` is called; individual page classes never enqueue their own assets. Page classes add data to the shared localized JS object by hooking the `inboxai_localize_data` filter in their **constructor** (checking the passed `$slug` first) — there's exactly one `wp_localize_script()` call in the whole plugin.

Every AJAX handler extends `Ajax\BaseAjaxController` and starts with `$this->check( Capability, nonce_action )` (nonce + capability, sends 403 and halts on failure), then reads input via `post_int()`/`post_string()`/`post_key()`/`post_json_array()`/etc. — never raw `$_POST`. One controller per admin page (`SettingsAjaxController`, `InboxAjaxController`, `ContactsAjaxController`) — a single monolithic `AjaxController` existed early on and was deliberately split once unwieldy; follow the per-page split for any new page. `Support\Template::render_to_string()` (the `ob_start()` variant of `Template::render()`) is how the same PHP template backs both the initial server render and an AJAX fragment swap-in — don't hand-build equivalent HTML in JS.

## Migration/import

Three independent import paths sharing one Settings wizard tab, zero code overlap: `FlamingoImporter` (reads a live, currently-installed Flamingo plugin's posts via public WP APIs only), `FlamingoCsvImporter` (Flamingo's own CSV export format, for migrating without Flamingo installed), `InboxCsvImporter` (this plugin's own native CSV column shape). All follow a "stage then batch" pattern: `stage()` parses the file once into a transient keyed by a random token, then `import_batch( $token, $offset, 25, $run_ai )` is called repeatedly until `done: true`. Each computes its own deterministic per-row content hash so re-uploading the same file twice is a safe no-op.

`FlamingoImporter` has a fixed, non-obvious bug-avoidance baked in: it never queries `post_status => 'any'`, because `WP_Query` silently excludes `exclude_from_search` statuses under `'any'` — which includes Flamingo's own spam status — so it queries the exact status list `wp_count_posts()` returns instead, keeping the "detected N messages" count and the actual import count consistent.

## Security

Seven capability constants in `Security\Capabilities` (`VIEW_MESSAGES`, `EDIT_MESSAGES`, `DELETE_MESSAGES` — stricter, separately checked on top of `EDIT_MESSAGES` for delete actions —, `SEND_REPLIES`, `MANAGE_SETTINGS`, plus `VIEW_ANALYTICS`/`EXPORT_MESSAGES` which are defined but not wired to anything yet). Granted to Administrator only on activation, stripped only on uninstall (never on deactivation).

`Security\Encryption` prefers libsodium (`sodium_crypto_secretbox`), falls back to OpenSSL AES-256-CBC, stores a self-generated 32-byte key in its own `wp_options` row (`inboxai_encryption_key`) — deliberately not derived from `wp-config.php`'s `AUTH_KEY`/salts, so rotating those can never make stored secrets undecryptable. Any new encrypted-secret field should reuse this class rather than rolling new crypto.

## Plugin lifecycle

`Plugin::init()` (hooked `plugins_loaded` priority 11, after CF7's own default-priority hooks) never fatals on unmet requirements — it stays active and dormant, showing an admin notice instead. Two groups of hooks: `is_admin()`-gated (`Menu`, `AjaxController`), and **unconditional** (`SubmissionHandler`, `AnalysisQueue`, `InboundMailChecker`, `CategoryTaxonomy`) — the unconditional group exists because a visitor's front-end form submit and a WP-Cron request are never `is_admin()`. Any new always-on feature (background checks, capture hooks) belongs in the unconditional group; anything admin-screen-only belongs in the gated group.

`Requirements::are_met()` deliberately does zero `__()` calls so it's safe to call from `plugins_loaded`; `Requirements::get_errors()` does call `__()` and is only ever invoked from inside the `admin_notices` callback (well after `init`) to avoid WP 6.7+'s "translation loading triggered too early" notice — any new requirement-check error message must follow this same split.

## Front-end build

`@wordpress/scripts`. Two webpack entries: `src/admin/index.js` → `build/admin/admin.{js,css}` (the one shared bundle `Menu::enqueue_assets()` loads on every plugin page — code-splitting is deliberately disabled, everything compiles into this one bundle even though `src/admin/componets/` is organized per-page), and `src/cf7/category-metabox.js` → `build/cf7/category.js` (a standalone script, only enqueued on CF7's own edit-form screen, never bundled into `admin.js`). Note: `componets` (missing the second "n") is the actual, consistent spelling used throughout every JS import path in this codebase — not a typo to fix.

## Reference implementations, not descriptions

When adding something that follows an existing pattern, model it on the closest real example rather than re-deriving the shape from scratch:
- New Settings tab/field end-to-end → `add-settings-field` skill, referencing the Inbound Email Replies feature.
- New admin page (Overview/Analytics/Campaigns, or anything new) → `add-admin-page` skill, referencing the AI Inbox List page.
- New encrypted secret → `Repository::get_inbound_password()`/`save_inbound_password()`.
- New AI provider → implement `AIProviderInterface`, register in `ProviderFactory::PROVIDERS`, model `analyze()` on `OpenAIProvider`.
- New always-on background feature → `Mail/InboundMailChecker.php`'s docblock explains the WP-Cron reasoning in full.
- WordPress.org release/compliance checklist → `wordpress-org-compliance` skill.
