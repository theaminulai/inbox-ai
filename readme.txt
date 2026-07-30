=== Inbox AI ===
Contributors: theaminuldev
Tags: contact form 7, ai, inbox, openai, flamingo
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.8.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Turn Contact Form 7 submissions into an AI-powered inbox: AI drafts the summary, database, category, priority, and reply — you review and send.

== Description ==

**Inbox AI** adds a unified, searchable **Contact Form 7** **(CF7)** inbox on top of your existing forms — without modifying Contact Form 7 itself. Every submission is stored, analyzed by your chosen AI provider, and turned into something your team can actually work from: a plain-language AI email summary, a suggested category and priority, a confidence score, and a ready-to-edit draft reply.

If you've ever dug through email notifications trying to figure out which contact form submissions actually need a reply, Inbox AI is built for exactly that problem. Think of it as a Contact Form 7 AI layer: a lightweight support inbox, contact form management tool, and lead management layer that turns what your forms already collect into a focused, triage-ready queue.

= How it works =

1. A visitor submits one of your monitored **Contact Form 7** forms.
2. Inbox AI stores the submission locally in your WordPress database.
3. Your connected AI provider (OpenAI, Anthropic, or Google Gemini) analyzes it in the background and returns a summary, category, priority, confidence score, and a suggested reply.
4. You review everything in the AI Inbox screen and, when you're happy with it, send the reply yourself. Nothing is ever sent to a visitor automatically.

= Who it's for =

* Small businesses and agencies using Contact Form 7 who want a real support inbox instead of scattered email notifications.
* Support and sales teams that want an AI customer support layer for day-to-day customer inquiry management, triaging contact form submissions by priority and category automatically.
* Anyone already using Flamingo to store Contact Form 7 submissions who wants AI categorization and an AI-drafted reply on top of that same data.

= AI Features =

* AI-generated summary for every submission, so you don't have to read the full message to know what it's about.
* AI prioritization and category suggestions (Urgent, High, Normal, Low), with a confidence score for each AI analysis.
* AI-drafted reply you can edit before sending — an AI email assistant for drafting replies, not an autoresponder.
* Bring your own API key: OpenAI, Anthropic (Claude), or Google Gemini. You choose the provider and control the cost.
* Automatic retry for any submission whose AI analysis failed, with a clear failure state instead of a silent gap.

= Inbox & Submission Management =

* A searchable, filterable AI inbox: filter by form, status, priority, category, AI confidence, and date range.
* Full-text search across sender name, email, subject, and message.
* A per-submission detail screen with the full AI analysis and an activity timeline (who did what, and when) — closer to a support ticket assistant than a plain message list.
* CSV export of the current filtered view, for reporting or import into another contact form database or CRM.
* Manual "Mark reviewed," "Archive," and "Delete" actions, each gated behind its own WordPress capability.

= Not just a Contact Form 7 database plugin =

Inbox AI stores every submission to a monitored Contact Form 7 form directly in your own WordPress database, the same way a dedicated **Contact Form 7 database** plugin would — and lets you search, filter, and export those contact form entries to CSV without running anything else alongside it. Where it goes further is the AI layer on top of that stored data: a plain-language summary, a suggested category and priority, and a draft reply for every submission, so you're not just archiving messages, you're actually working through them.

It isn't a full replacement for a dedicated database add-on, though. Inbox AI doesn't currently offer multisite-wide storage, capture or store form attachments, support generic CSV import with custom field mapping, or let you rename field labels — if your site depends on any of those specific features, a dedicated Contact Form 7 database plugin running alongside Inbox AI still makes sense.

= Contacts =

* Every sender automatically grouped into a single Contacts row — a lightweight contact form CRM view built directly from your existing submissions, no separate import needed.
* Search plus category and priority filters, matching the main inbox.
* One click from a contact straight to every message from that sender.
* CSV export and an archive-based delete, so removing a contact never destroys the underlying message history.

= Notifications =

* Email and Slack notifications so your team knows when a new submission — or one flagged urgent by the AI — needs attention, keeping AI customer service response times fast.

= Import & Migration =

* A guided import wizard for existing Flamingo submissions, so switching from Flamingo to Inbox AI doesn't mean losing your submission history.
* A CSV upload path for a Flamingo export, for sites that can't run the direct import wizard.

= Privacy & Security =

* Your AI provider API key is encrypted in the database and only ever shown masked (e.g. `sk-••••••7f2A`) after saving.
* Custom WordPress capabilities control who can view, edit, reply to, delete, or export messages — no blanket admin-only access.
* No data reaches any AI provider until you've connected one and explicitly turned on at least one monitored form.

= Performance =

* AI analysis runs as a background AI workflow via WP-Cron — a visitor's form submission is never delayed waiting on an AI response.

= Developer Friendly =

* Built to WordPress coding standards, with its own custom capabilities (filterable via `inboxai_capabilities`) instead of overloading core roles.
* Integrates with Contact Form 7 (wpcf7) exclusively through its own public hooks — `wpcf7_before_send_mail`, `wpcf7_mail_sent`, and `wpcf7_mail_failed` for capturing submissions, plus `wpcf7_admin_misc_pub_section`/`wpcf7_after_save` for per-form AI categories — so Contact Form 7 core files and its own outgoing mail are never touched.
* Lifecycle action hooks (e.g. `inboxai_loaded`) for developers who want to extend the plugin.

== Installation ==

1. Install and activate Contact Form 7 (required — Inbox AI is a Contact Form 7 add-on, not a standalone form builder).
2. Upload and activate Inbox AI.
3. Go to **Contact → Settings** to connect an AI provider (OpenAI, Anthropic, or Google Gemini) and choose which forms to monitor.
4. Open **Contact → AI Inbox** to see submissions arrive, get analyzed, and become ready to review.

A full step-by-step user guide is included with the plugin under `docs-platform/`.

== Frequently Asked Questions ==

= Does this work with Contact Form 7? =

Yes — Contact Form 7 is a required dependency, and Inbox AI is built specifically as a contact form inbox layer for it. Submissions are captured through Contact Form 7's own public hooks; Inbox AI never modifies Contact Form 7 core files or its mail handling.

= Can I import my existing Flamingo submissions? =

Yes. Settings → Import & Migration includes a guided wizard for Flamingo's stored submissions, plus a CSV upload path for exports from Flamingo or another Contact Form 7 database/entries plugin.

= Will replies ever be sent automatically? =

No. The AI drafts a summary and a suggested reply; a human always reviews and explicitly clicks Send before anything reaches a visitor.

= Which AI providers are supported? =

OpenAI, Anthropic (Claude), and Google Gemini. You choose one in Settings → AI Provider and supply your own API key.

= Where is my API key stored, and is it secure? =

Encrypted in your WordPress database. It's never exposed in any frontend page, script, or REST response — only a masked version is ever displayed after saving.

= Does this plugin store my form submissions? =

Yes. Every submission to a monitored form is stored locally in your own WordPress database (not sent to a third party for storage) — the same local-storage approach as a typical Contact Form 7 db/database add-on, with AI analysis layered on top.

= Can I export my submissions or contacts? =

Yes. Both the AI Inbox and the Contacts page support CSV export of the current filtered view.

= Does this modify Contact Form 7 or its emails? =

No. Inbox AI only reads from Contact Form 7's public hooks; it never edits Contact Form 7 core files or interferes with Contact Form 7's own outgoing mail.

= Can I control who sees or replies to messages? =

Yes. Inbox AI defines its own capabilities (view, edit, delete, reply, export, manage settings, view analytics) so you can grant a support agent narrower access than a full administrator.

= Can I disable AI analysis? =

Yes. AI analysis only runs for forms listed under Settings → General → Monitored Forms. Remove a form from that list (or leave it out to begin with) and Inbox AI stops sending its submissions to any AI provider — the submission is still stored locally, just without AI analysis.

= How much does OpenAI, Anthropic, or Gemini cost? =

That's set entirely by the provider, not by Inbox AI — see each provider's own published pricing (for example OpenAI's or Anthropic's API pricing pages) since rates vary by model and change over time. Inbox AI itself doesn't charge anything or mark up provider costs; you pay the provider directly for what your usage consumes, and the Usage & Billing tab tracks your estimated spend inside WordPress so there are no surprises.

= Is this ready to use? =

Yes. AI Inbox, Contacts, and Settings (AI Provider, General, Prompts, Usage & Billing, Notifications, Import & Migration) are built and working. An Overview dashboard, Analytics, and Campaigns (bulk email) are still in development.

== External services ==

This plugin connects to a third-party AI provider — OpenAI, Anthropic, or Google Gemini, whichever one the site administrator chooses and configures with their own API key in Settings → AI Provider — so it can generate a summary, suggested category, priority, and a suggested reply for each Contact Form 7 submission the administrator chooses to analyze.

What data is sent, and when: each time a monitored form is submitted (or an administrator clicks "Retry"/"Regenerate"), the submission's text content (message body, subject, and the customer's name, to the extent the administrator's own prompt template references them) is sent to the selected provider's API, along with the site's configured prompt instructions. No data is sent to any provider until an AI provider is connected and at least one form is turned on under Monitored Forms. The administrator's API key is sent with each request (as required by the provider's own API) and is otherwise stored only on the site, encrypted, and never transmitted anywhere else.

Only one provider is contacted per request — whichever one is currently selected in Settings → AI Provider. The plugin never uses a shared or proxy key; the site owner must supply and pay for their own API key directly with the provider.

* **OpenAI** — used when you select OpenAI as your provider. [Terms of Use](https://openai.com/policies/terms-of-use/), [Privacy Policy](https://openai.com/policies/privacy-policy/).
* **Anthropic** — used when you select Anthropic as your provider. [Commercial Terms of Service](https://www.anthropic.com/legal/commercial-terms), [Privacy Policy](https://www.anthropic.com/legal/privacy).
* **Google Gemini** — used when you select Google Gemini as your provider. [Gemini API Additional Terms of Service](https://ai.google.dev/gemini-api/terms), [Google Privacy Policy](https://policies.google.com/privacy).

== Screenshots ==

1. AI Inbox — the filterable, searchable list of Contact Form 7 submissions.
2. Submission detail — AI summary, category, priority, confidence, and the AI-drafted reply composer.
3. Contacts — every sender grouped into one row, with search and filters.
4. Settings — AI Provider connection screen.
5. Settings — General tab, Monitored Forms and Automatic Processing.

== Changelog ==
= Inbox AI/v0.8.0 - 2026-07-30 =
*Fixed*

* Fixed Tested up to and Stable tag drifting out of sync with the plugin version.
* Fixed Tags Issues: the readme.txt tags were not updated to match the plugin header tags.

*Improved*

* Split the shared AJAX handler into one controller per page.

= Inbox AI/v0.7.0 - 2026-07-28 =
*Added*

* Added the Contacts List page, grouped by sender.
* Added search, category, and priority filters, plus CSV export.
* Added a "Delete contact" action that archives that sender's messages.

*Improved*

* Split the shared AJAX handler into one controller per page.
* Consolidated repeated field-reading code into shared helpers.
* Reorganized Contacts templates into their own folder.

*Fixed*

* Fixed the version-bump script crashing on a first release.

= Inbox AI/v0.6.0 - 2026-07-28 =
*Changed*

* Shortened the plugin name to "Inbox AI" (was "InboxAI for Contact Form 7").
* Finalized the slug/text domain as `inbox-ai` (was `inboxai-for-contact-form-7`).
* Confirmed with WordPress.org that the slug could still change before the first SVN push.
* Kept the internal PHP namespace (`InboxAI\`) unchanged.
* Kept the code-level hook/option/capability prefix (`inboxai_`) unchanged.

*Added*

* Added the plugin icon (`icon-128x128.png`) to `.wordpress-org/`.
* Added the plugin banner (`banner-772x250.png`) to `.wordpress-org/`.

= Inbox AI/v0.5.0 - 2026-07-27 =
*Changed*

* Renamed the plugin from "CF7 AI Inbox" to "InboxAI for Contact Form 7".
* Set the new slug/text domain to `inboxai-for-contact-form-7`.
* Made the rename to avoid implying affiliation with the Contact Form 7 project.
* Corrected the OpenAI Terms of Service link to the Business Terms (the terms that actually govern API use).
* Corrected the Anthropic Terms of Service link to the Commercial Terms of Service.
* Corrected the Google Gemini Terms of Service link to the Gemini API Additional Terms.
* Rewrote the "Current status" section, which had been left describing an early pre-release state.
* Rewrote the "Screenshots" section to match the AI Inbox and Settings screens that were actually built.

*Added*

* Added a "Source Code" section to the readme.
* Documented where the human-readable source for the compiled admin assets is maintained.

= Inbox AI/v0.4.0 - 2026-07-25 =
*Added*

* Added a real "Received" date-range filter to the AI Inbox List.
* Filter options cover 7/30/90 days, this month, and 1/2/3/5 years.
* Added the same period filter to the Usage & Billing tab.
* Replaced the previously decorative "Last 30 days" control on Usage & Billing with the working filter.
* Added a live "N submissions this month" count under each form in Monitored Forms.
* Added a full step-by-step user guide (`docs-platform/`).
* The guide covers setup, every Settings tab, the AI Inbox list, submission replies, and troubleshooting.

*Improved*

* Reorganized admin template files into per-page subfolders (`inbox/`, `settings/`) for maintainability.
* Removed duplicate CSS rules from the settings stylesheet.
* Removed conflicting CSS rules from the shared admin stylesheet.

*Fixed*

* Fixed the AI Provider Settings Model dropdown not updating when switching between OpenAI, Anthropic, and Google.

= Inbox AI/v0.3.0 - 2026-07-15 =
*Added*

* Added AI Inbox with filters, search, sorting, pagination, and submission details.
* Added AI reply composer with draft, regenerate, retry analysis, and send workflow.
* Added background AI analysis queue with OpenAI, Anthropic, and Gemini support.
* Added per-form AI categories with real-time category management in the Contact Form 7 editor.
* Added spam auto-archive, validation, and normalization for AI results.
* Added failure screen with one-click retry for failed AI analysis.
* Added CSV export of the current filtered list.

*Improved*

* Improved inbox actions, permissions, and overall admin experience.

*Fixed*

* Fixed AI Categories UI rendering on the Contact Form 7 editor.
* Fixed admin stylesheet build issue.

= Inbox AI/v0.2.0 - 2026-07-10 =
*Added*

* Added a settings page to configure AI providers and plugin options.
* Added monitored forms management to choose which Contact Form 7 forms are analyzed.
* Added customizable AI analysis prompts for better response quality.
* Added email notification settings for inbox activity.
* Added usage tracking, spending limits, and secure API key management.
* Added Flamingo import to migrate existing Contact Form 7 submissions.

= Inbox AI/v0.1.0 - 2026-07-01 =
*Added*

* Initial release.
* Added plugin setup, requirements checks, and Contact Form 7 dependency.
* Added database schema and upgrade routines.
* Added user capabilities and uninstall cleanup.

== Upgrade Notice ==

= 0.7.0 =
Adds the Contacts page (senders grouped, filterable, exportable). Internal-only refactor otherwise — no action needed.

= 0.1.0 =
Initial foundation release — no user-facing features yet.
