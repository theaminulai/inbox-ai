=== Inbox AI ===
Contributors: theaminuldev
Tags: contact form 7, AI, inbox, database, submissions
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.3
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Turn Contact Form 7 submissions into an AI-powered inbox: AI drafts the summary, database, category, priority, and reply — you review and send.

== Description ==

**AI-Powered Inbox for [Every Contact Form 7](https://wordpress.org/plugins/contact-form-7/) Submission**

**Inbox AI** uses AI to turn your Contact Form 7 submissions into something your team can actually work from — without modifying Contact Form 7 itself. Every submission is analyzed by your chosen AI provider and returned with a plain-language AI summary, an AI-suggested category and priority, a confidence score, and a ready-to-edit AI draft reply.

If you've ever dug through email notifications trying to figure out which submissions actually need a reply, Inbox AI's AI layer solves exactly that problem — reading, categorizing, and prioritizing every message automatically, then handing you a focused, triage-ready queue instead of a plain list of messages.

= Manage Contact Form 7 Submissions with AI: =

* **Unified AI Inbox:** Every Contact Form 7 submission lands in one searchable inbox, instead of getting buried across scattered email notifications.

* **AI-Generated Summaries:** Each submission is read and summarized by your chosen AI provider, so you know what it's about without opening the full message.

* **Smart Category & Priority Suggestions:** The AI suggests a category and a priority level (Urgent, High, Normal, Low) for every submission, along with a confidence score for each suggestion.

* **Ready-to-Edit Draft Replies:** Inbox AI drafts a reply for you to review and edit — never sent automatically, always in your control.

* **Inbound Email Replies:** When a customer replies to your emailed response, Inbox AI picks it up automatically — no matter what email platform they use — and threads it straight into the original submission's conversation. The AI re-analyzes the full conversation and drafts a follow-up reply for you to review.

* **Customer Mood Tracking:** Every message in a conversation, from the original submission through each reply, is read for tone — positive, neutral, frustrated, or angry — with a short AI explanation, so an unhappy customer never slips by unnoticed.

* **Powerful Search & Filtering:** Search, filter, sort, and tag submissions by form, status, priority, category, or date, so nothing important gets lost.

* **One-Click CSV Export:** Export the current filtered view to CSV for reporting, backup, or import into another database or CRM.

* **Bring Your Own AI Provider:** Connect OpenAI, Anthropic (Claude), or Google Gemini with your own API key — you choose the provider and control the cost.

* **Zero Core Modification:** Inbox AI reads from Contact Form 7's public hooks only. It never edits Contact Form 7 core files or its mail handling.

= Why Inbox AI? =

Contact Form 7 is great at collecting submissions — but it doesn't help you manage them. Inbox AI closes that gap: instead of a plain list of messages, every submission arrives already summarized, categorized, and prioritized, with a draft reply waiting for your review.

= How Contact Form 7 (CF7) Inbox AI Works =

1. A visitor submits one of your monitored **Contact Form 7** forms.
2. Inbox AI stores the submission locally in your WordPress database.
3. Your connected AI provider (OpenAI, Anthropic, or Google Gemini) analyzes it in the background and returns a summary, category, priority, confidence score, suggested mood, and a suggested reply.
4. You review everything in the AI Inbox screen and, when you're happy with it, send the reply yourself. Nothing is ever sent to a visitor automatically.
5. If the customer replies to that email, Inbox AI's optional inbound mail check picks it up automatically, threads it into the same conversation, and re-analyzes it so you always have a fresh summary and a new draft reply.

= Who It's For =

* Small businesses and agencies using Contact Form 7 who want a real support inbox instead of scattered email notifications.
* Support and sales teams that want an AI customer support layer for day-to-day customer inquiry management, triaging contact form submissions by priority and category automatically.
* Anyone already using Flamingo to store Contact Form 7 submissions who wants AI categorization and an AI-drafted reply on top of that same data.

= AI Features (OpenAI, Claude, or Google Gemini) =

* Every AI-drafted reply is exactly that — a draft. It's an AI email assistant for drafting replies, not an autoresponder; nothing sends without your review.
* If AI analysis fails for a submission, Inbox AI shows a clear failure state and lets you retry with one click instead of leaving a silent gap.
* A customer's reply — from any email platform, not just ones with special integrations — is picked up automatically, re-analyzed with the full conversation in view, and gets its own suggested follow-up reply.
* Each message in a conversation gets a suggested mood (positive, neutral, frustrated, angry) with a short one-line reason, shown in a Customer Mood panel on the Submission Detail screen.

= Contact Form 7 (CF7) Inbox & Submission Management =

* Filter by form, status, priority, category, AI confidence, and date range, with full-text search across sender name, email, subject, and message.
* A per-submission detail screen shows the full AI analysis alongside an activity timeline — who did what, and when — closer to a support ticket than a plain message list.
* Manual "Mark reviewed," "Archive," and "Delete" actions, each gated behind its own WordPress capability.
* Unread submissions and fresh customer replies are visually flagged in the list (tinted row background, bold name, and a dot — blue for a new submission, amber for a reply, so you can tell them apart without opening either one), with a count badge on the "AI Inbox" menu item — cleared automatically as soon as you open that submission.

= Not Just a Contact Form 7 (CF7) Database Plugin =

Inbox AI stores every submission to a monitored Contact Form 7 form directly in your own WordPress database, the same way a dedicated Contact Form 7 database plugin would — so you don't need to run one just to keep a local copy of your entries.

It isn't a full replacement for a dedicated database add-on, though. Inbox AI doesn't currently offer multisite-wide storage, capture or store form attachments, support generic CSV import with custom field mapping, or let you rename field labels — if your site depends on any of those specific features, a dedicated Contact Form 7 database plugin running alongside Inbox AI still makes sense.

= Contact Form 7 (CF7) Contacts =

* Every sender automatically grouped into a single Contacts row — a lightweight contact form CRM view built directly from your existing submissions, no separate import needed.
* Search plus category and priority filters, matching the main inbox.
* One click from a contact straight to every message from that sender.
* CSV export and an archive-based delete, so removing a contact never destroys the underlying message history.

= Notifications =

* Email and Slack notifications so your team knows when a new submission — or one flagged urgent by the AI — needs attention, keeping AI customer service response times fast.
* Optional Inbound Email Replies: point Inbox AI at a mailbox (IMAP) and it checks for customer replies on a schedule you choose — every 1, 2, 5, 10, 15, 30, or 60 minutes.
* An email alert as soon as a customer reply comes in (on by default), with a preview of the reply and a direct link to the submission — so you always know a reply has arrived without keeping the AI Inbox tab open.

= Import & Migration =

* A guided import wizard for existing Flamingo submissions, so switching from Flamingo to Inbox AI doesn't mean losing your submission history.
* A CSV upload path for a Flamingo export, for sites that can't run the direct import wizard.

= Privacy & Security =

* Your AI provider API key is encrypted in the database and only ever shown masked (e.g. `sk-••••••7f2A`) after saving.
* Custom WordPress capabilities control who can view, edit, reply to, delete, or export messages — no blanket admin-only access.
* No data reaches any AI provider until you've connected one and explicitly turned on at least one monitored form.

= Performance =

* AI analysis runs as a background AI workflow via WP-Cron — a visitor's form submission is never delayed waiting on an AI response.

== Installation ==

1. Install and activate Contact Form 7 (required — Inbox AI is a Contact Form 7 add-on, not a standalone form builder).
2. Upload and activate Inbox AI.
3. Go to **Contact → Settings** to connect an AI provider (OpenAI, Anthropic, or Google Gemini) and choose which forms to monitor.
4. Open **Contact → AI Inbox** to see submissions arrive, get analyzed, and become ready to review.

A full step-by-step user guide is included with the plugin under `wiki/`.

== Frequently Asked Questions ==

= Does this work with Contact Form 7 (CF7)? =

Yes — Contact Form 7 is a required dependency, and Inbox AI is built specifically as a contact form inbox layer for it. Submissions are captured through Contact Form 7's own public hooks; Inbox AI never modifies Contact Form 7 core files or its mail handling.

= Can I import my existing Flamingo submissions? =

Yes. Settings → Import & Migration includes a guided wizard for Flamingo's stored submissions, plus a CSV upload path for exports from Flamingo or another Contact Form 7 database/entries plugin.

= Will replies ever be sent automatically? =

No. The AI drafts a summary and a suggested reply; a human always reviews and explicitly clicks Send before anything reaches a visitor.

= Which AI providers are supported? =

OpenAI, Anthropic (Claude), and Google Gemini. You choose one in Settings → AI Provider and supply your own API key.

= Where is my API key stored, and is it secure? =

Encrypted in your WordPress database. It's never exposed in any frontend page, script, or REST response — only a masked version is ever displayed after saving.

= Does this plugin store my Contact Form 7 (CF7) submissions? =

Yes. Every submission to a monitored form is stored locally in your own WordPress database (not sent to a third party for storage) — the same local-storage approach as a typical Contact Form 7 db/database add-on, with AI analysis layered on top.

= Can I export my Contact Form 7 (CF7) submissions or contacts? =

Yes. Both the AI Inbox and the Contacts page support CSV export of the current filtered view.

= Does this modify Contact Form 7 (CF7) or its emails? =

No. Inbox AI only reads from Contact Form 7's public hooks; it never edits Contact Form 7 core files or interferes with Contact Form 7's own outgoing mail.

= Can I control who sees or replies to messages? =

Yes. Inbox AI defines its own capabilities (view, edit, delete, reply, export, manage settings, view analytics) so you can grant a support agent narrower access than a full administrator.

= Can I disable AI analysis? =

Yes. AI analysis only runs for forms listed under Settings → General → Monitored Forms. Remove a form from that list (or leave it out to begin with) and Inbox AI stops sending its submissions to any AI provider — the submission is still stored locally, just without AI analysis.

= Does Inbox AI capture a customer's reply to my email? =

Yes, if you turn on Inbound Email Replies under Settings → Notifications and point it at a mailbox over IMAP. It works with any email platform the customer replies from — there's no special integration needed on their end. Inbox AI checks that mailbox on a schedule you choose (every 1, 2, 5, 10, 15, 30, or 60 minutes), threads a matching reply into the original submission's conversation, and triggers a fresh AI re-analysis with a new suggested reply. Checking never marks anything in the mailbox as read, so it's safe to point at a mailbox you also use as your real inbox.

= Does Inbox AI track a customer's mood? =

Yes. Every message in a conversation — the original submission and each new reply — is read by the AI for tone (positive, neutral, frustrated, or angry) with a short one-line reason, shown in the Customer Mood panel on the Submission Detail screen. Regenerating or retrying analysis on a message that already has a mood never changes it — each mood is a one-time read of that specific message.

= How much does OpenAI, Anthropic, or Gemini cost? =

That's set entirely by the provider, not by Inbox AI — see each provider's own published pricing (for example OpenAI's or Anthropic's API pricing pages) since rates vary by model and change over time. Inbox AI itself doesn't charge anything or mark up provider costs; you pay the provider directly for what your usage consumes, and the Usage & Billing tab tracks your estimated spend inside WordPress so there are no surprises.

= Is this ready to use? =

Yes. AI Inbox, Contacts, and Settings (AI Provider, General, Prompts, Usage & Billing, Notifications, Import & Migration) are built and working. An Overview dashboard, Analytics, and Campaigns (bulk email) are still in development.

== External services ==

This plugin sends submission data to a third-party AI provider — OpenAI, Anthropic, or Google Gemini — chosen and connected with the administrator's own API key in Settings → AI Provider, to generate a summary, category, priority, and suggested reply.

What's sent, and when: on each monitored submission (or a "Retry"/"Regenerate" click), the message, subject, and sender name (as referenced by the prompt template) are sent to the selected provider's API along with the configured prompt and the administrator's own API key. If Inbound Email Replies is turned on, a genuine new customer reply is also sent for re-analysis — including the conversation so far (the original message plus any prior replies) — so the AI can draft an in-context follow-up. Nothing is sent until a provider is connected and at least one form is monitored. Only one provider is contacted per request — never a shared or proxy key; the site owner pays the provider directly.

* **OpenAI** — [Terms of Use](https://openai.com/policies/terms-of-use/), [Privacy Policy](https://openai.com/policies/privacy-policy/).
* **Anthropic** — [Commercial Terms of Service](https://www.anthropic.com/legal/commercial-terms), [Privacy Policy](https://www.anthropic.com/legal/privacy).
* **Google Gemini** — [Additional Terms of Service](https://ai.google.dev/gemini-api/terms), [Privacy Policy](https://policies.google.com/privacy).

= My Contributions =

I actively contribute to the following WordPress plugins:

* [Gutenberg](https://wordpress.org/plugins/gutenberg/) – The WordPress block editor, developed by the WordPress team.
* [AI](https://wordpress.org/plugins/ai/) – Brings AI-powered features like content summarization, alt text generation, and title suggestions directly into the WordPress Block Editor.
* [ElementsKit](https://wordpress.org/plugins/elementskit-lite/) – Advanced widgets, header/footer builder, and mega menu builder for Elementor.
* [MetForm](https://wordpress.org/plugins/metform/) – Super flexible and easy-to-use form builder.
* [ShopEngine](https://wordpress.org/plugins/shopengine/) – Your complete WooCommerce solution, built for Elementor.
* [GutenKit](https://wordpress.org/plugins/gutenkit-blocks-addon/) – Build websites 10x Faster with ZERO coding in the Gutenberg Block Editor.
* [PopupKit](https://wordpress.org/plugins/popup-builder-block/) – Build exceptional popup for diverse needs within the WordPress block editor.
* [TableKit](https://wordpress.org/plugins/table-builder-block/) – Make fully-customizable multipurpose table & generate data table within Gutenberg block editor.

== Source Code ==

Compiled assets (`build/admin/admin.js`, `build/admin/admin.css`, `build/cf7/category.js`, etc.) are built from human-readable source via `npm run build` (webpack, through `@wordpress/scripts`).

The uncompiled source is public at https://github.com/theaminulai/inbox-ai — the canonical, always-up-to-date source for every compiled file shipped in this plugin.

== Screenshots ==

1. AI Inbox — the filterable, searchable list of Contact Form 7 submissions.
2. Submission detail — AI summary, category, priority, confidence, and the AI-drafted reply composer.
3. Contacts — every sender grouped into one row, with search and filters.
4. Settings — AI Provider connection screen.
5. Settings — Notifications tab, where you can configure email notifications.

== Changelog ==

= Inbox AI for Contact Form 7/v1.1.3 - 2026-08-30 =
* ***Fixed***
	* * Fixed: AI Inbox List date-range filter (7/30/90 days, this month, N years) — was comparing against UTC while submissions are timestamped in site-local time, throwing off results near any boundary.
	* * Fixed: Usage & Billing period selector and cost-breakdown chart — same timezone bug, same fix.
	* * Fixed: "Keep submissions for" (Data Retention) — build the real purge-by-age cron job; currently saved and never read.
	* * Fixed: "Delete attachments after reply" — remove or clearly relabel; the plugin doesn't capture attachments at all yet, so this control has nothing to act on.

= Inbox AI for Contact Form 7/v1.1.2 - 2026-08-15 =
* ***Fixed***
	* Fixed the "Notify me on urgent messages" setting so the site admin now receives an email whenever a submission or customer reply is marked as Urgent.
	* Fixed the "Notify on AI analysis failure" setting so the site admin now receives an email when AI analysis fails for a submission.
	* Fixed the "Notify when a reply draft is ready" setting so the site admin now receives an email when AI finishes generating a reply draft.
	* Fixed the "Daily summary digest" feature so the digest is now properly scheduled and sent at 9:00 AM, including new, unread, and urgent submissions from the previous 24 hours.

= Inbox AI for Contact Form 7/v1.1.1 - 2026-08-12 =
* ***Added***
    * Added a new Integrations tab in Settings, separate from Notifications.
    * Added a CRM Data Collection card (HubSpot, Mailchimp, and more) that saves your provider and API key ahead of automatic syncing in a future release.
    * Added a "Send test message" button to confirm a Slack webhook URL works right away, without waiting for an urgent submission.
* ***Changed***
    * Moved the Slack Integration card from Settings → Notifications to the new Settings → Integrations tab.
* ***Fixed***
    * Fixed the Slack Integration switch not actually sending anything — urgent submissions now post a real message to the configured webhook.
    * Fixed the Slack toggle silently resetting to off whenever Notification settings were saved.

= Inbox AI for Contact Form 7/v1.1.0 - 2026-08-10 =
* ***Added***
    * Added customer reply email notifications, sent to the site admin with a preview of the reply and a link to the submission. On by default in Settings → Notifications.
    * Added an unread count badge to the AI Inbox menu.
    * Added unread indicators to AI Inbox submissions — a tinted row, bold name, and a dot, colored blue for a new submission and amber for a customer reply.
* ***Improved***
	* Improved unread status tracking and indicators for new submissions and customer replies.
* ***Fixed***
    * Fixed inbound email replies incorrectly marking unrelated mailbox messages as read.
    * Fixed mailbox monitoring to only process new replies from the connection point onward.

= Inbox AI for Contact Form 7/v1.0.1 - 2026-08-08 =
* ***Improved***
    * Linked "Contact Form 7" in the Description to the official Contact Form 7 plugin page for easier navigation.
    * Rewrote the Description section to lead with the AI-powered analysis, rather than the inbox and search functionality.
    * Reordered and reformatted the Key Features list for better scannability.

= Inbox AI for Contact Form 7/v1.0.0 - 2026-08-08 =
* ***Added***
    * Added Inbound Email Replies: an optional IMAP mailbox check that captures a customer's reply to your emailed response, from any email platform, and threads it into the original conversation.
    * Added a configurable check interval for Inbound Email Replies (1, 2, 5, 10, 15, 30, or 60 minutes), replacing the old fixed 10-minute interval.
    * Added automatic AI re-analysis and a new suggested follow-up reply whenever a customer reply comes in, using the full conversation for context.
    * Added Customer Mood tracking: a suggested mood (positive, neutral, frustrated, or angry) with a short AI reason for the original submission and every reply, shown in a new Customer Mood panel.
    * Added a step-by-step Inbound Email Replies setup guide.
* ***Improved***
    * The AI Analysis card now always appears last in the conversation thread, no matter how many replies follow it.
    * Submission Detail sidebar reordered to Customer, Submission details, Quick actions, Customer Mood, Activity.
    * Customer Mood history now uses the same timeline design as the Activity panel, with a short description under each entry.
    * Regenerating or retrying analysis no longer changes an already-recorded mood or adds a duplicate history entry.
    * "Check every" and "Mailbox address" fields now sit side by side in Settings.
* ***Fixed***
    * The AI-drafted reply no longer greets the site owner's own name instead of the customer's.

= Inbox AI for Contact Form 7/v0.10.2 - 2026-08-02 =
* ***Fixed***
    * Fixed PHPCS ignore placement in category save.
    * Fixed generated inbox-ai translation template POT.

= Inbox AI for Contact Form 7/v0.10.1 - 2026-08-02 =
* ***Fixed***
    * Fixed a guideline violation flagged by the WordPress.org Plugins Team: no publicly documented, human-readable source was linked for the plugin's compiled JavaScript. Added a "Source Code" section to this readme linking to the public GitHub repository the compiled files are built from.
    * Fixed a translation string in the activity log that passed a variable to `__()` instead of a literal string, which prevented translators from picking it up.

= Inbox AI for Contact Form 7/v0.10.0 - 2026-08-2 =
* ***Added***
    * Added a source category, captured once from the form's own category assignment at submission time, that stays fixed even when the AI's own category is regenerated.
    * Added a "Source category" field to the Submission Detail page.
    * Added a native CSV import format, built for this plugin's own data, as an alternative to importing a Flamingo CSV export.
    * Added a Manage Categories card to Settings → General for adding, renaming, and deleting AI categories.
    * Added a separate "AI Categories" box on the Contact Form 7 editor screen (previously nested inside the Status box).
* ***Improved***
    * Improved the AI Inbox detail page to show the source category, AI category, and AI confidence in a single row for easier scanning.
    * The category column and filter on the AI Inbox and Contacts pages now use the source category instead of the AI-generated category.
    * The AI now always suggests a category for a submission, even for forms with no categories configured.
    * Merged the Flamingo and native CSV import flows into a single guided wizard, with a first step to choose which one to use.
* ***Fixed***
    * Fixed the AI-generated category being cleared whenever analysis was regenerated.
    * Fixed misaligned spacing in the Manage Categories edit row.

= Inbox AI for Contact Form 7/v0.9.0 - 2026-07-31 =
* ***Improved***
    * Bulk actions on the AI Inbox page now support "Mark Reviewed" and "Archive" in addition to "Delete."
    * Bulk actions on the Contacts page now support "Mark Reviewed" and "Archive" in addition to "Delete."
    * Pagination on the AI Inbox/Contacts page now shows the total number of submissions and the current page number.
* ***Fixed***
    * Fixed CSS design issues on the Settings page for small screens.
    * Fixed CSS design issues with select fields on the AI Inbox page for small screens.
    * Fixed CSS design issues with filters on the AI Inbox page for small screens.
    * Fixed CSS design issues with filters on the Contacts page for small screens.

= Inbox AI for Contact Form 7/v0.8.0 - 2026-07-30 =
* ***Improved***
    * Split the shared AJAX handler into one controller per page.
* ***Fixed***
    * Fixed Tested up to and Stable tag drifting out of sync with the plugin version.
    * Fixed Tags Issues: the readme.txt tags were not updated to match the plugin header tags.

= Inbox AI for Contact Form 7/v0.7.0 - 2026-07-28 =
* ***Added***
    * Added the Contacts List page, grouped by sender.
    * Added search, category, and priority filters, plus CSV export.
    * Added a "Delete contact" action that archives that sender's messages.
* ***Improved***
    * Split the shared AJAX handler into one controller per page.
    * Consolidated repeated field-reading code into shared helpers.
    * Reorganized Contacts templates into their own folder.
* ***Fixed***
    * Fixed the version-bump script crashing on a first release.

= Inbox AI for Contact Form 7/v0.6.0 - 2026-07-28 =
* ***Added***
    * Added the plugin icon (`icon-128x128.png`) to `.wordpress-org/`.
    * Added the plugin banner (`banner-772x250.png`) to `.wordpress-org/`.
* ***Changed***
    * Shortened the plugin name to "Inbox AI" (was "InboxAI for Contact Form 7").
    * Finalized the slug/text domain as `inbox-ai` (was `inboxai-for-contact-form-7`).
    * Confirmed with WordPress.org that the slug could still change before the first SVN push.
    * Kept the internal PHP namespace (`InboxAI\`) unchanged.
    * Kept the code-level hook/option/capability prefix (`inboxai_`) unchanged.

= Inbox AI for Contact Form 7/v0.5.0 - 2026-07-27 =
* ***Added***
    * Added a "Source Code" section to the readme.
    * Documented where the human-readable source for the compiled admin assets is maintained.
* ***Changed***
    * Renamed the plugin from "CF7 AI Inbox" to "InboxAI for Contact Form 7".
    * Set the new slug/text domain to `inboxai-for-contact-form-7`.
    * Made the rename to avoid implying affiliation with the Contact Form 7 project.
    * Corrected the OpenAI Terms of Service link to the Business Terms (the terms that actually govern API use).
    * Corrected the Anthropic Terms of Service link to the Commercial Terms of Service.
    * Corrected the Google Gemini Terms of Service link to the Gemini API Additional Terms.
    * Rewrote the "Current status" section, which had been left describing an early pre-release state.
    * Rewrote the "Screenshots" section to match the AI Inbox and Settings screens that were actually built.

= Inbox AI for Contact Form 7/v0.4.0 - 2026-07-25 =
* ***Added***
    * Added a real "Received" date-range filter to the AI Inbox List.
    * Filter options cover 7/30/90 days, this month, and 1/2/3/5 years.
    * Added the same period filter to the Usage & Billing tab.
    * Replaced the previously decorative "Last 30 days" control on Usage & Billing with the working filter.
    * Added a live "N submissions this month" count under each form in Monitored Forms.
    * Added a full step-by-step user guide (`docs-platform/`), covering setup, every Settings tab, the AI Inbox list, submission replies, and troubleshooting.
* ***Improved***
    * Reorganized admin template files into per-page subfolders (`inbox/`, `settings/`) for maintainability.
    * Removed duplicate CSS rules from the settings stylesheet.
    * Removed conflicting CSS rules from the shared admin stylesheet.
* ***Fixed***
    * Fixed the AI Provider Settings Model dropdown not updating when switching between OpenAI, Anthropic, and Google.

= Inbox AI for Contact Form 7/v0.3.0 - 2026-07-15 =
* ***Added***
    * Added AI Inbox with filters, search, sorting, pagination, and submission details.
    * Added AI reply composer with draft, regenerate, retry analysis, and send workflow.
    * Added background AI analysis queue with OpenAI, Anthropic, and Gemini support.
    * Added per-form AI categories with real-time category management in the Contact Form 7 editor.
    * Added spam auto-archive, validation, and normalization for AI results.
    * Added failure screen with one-click retry for failed AI analysis.
    * Added CSV export of the current filtered list.
* ***Improved***
    * Improved inbox actions, permissions, and overall admin experience.
* ***Fixed***
    * Fixed AI Categories UI rendering on the Contact Form 7 editor.
    * Fixed admin stylesheet build issue.

= Inbox AI for Contact Form 7/v0.2.0 - 2026-07-10 =
* ***Added***
    * Added a settings page to configure AI providers and plugin options.
    * Added monitored forms management to choose which Contact Form 7 forms are analyzed.
    * Added customizable AI analysis prompts for better response quality.
    * Added email notification settings for inbox activity.
    * Added usage tracking, spending limits, and secure API key management.
    * Added Flamingo import to migrate existing Contact Form 7 submissions.

= Inbox AI for Contact Form 7/v0.1.0 - 2026-07-01 =
* ***Added***
    * Initial release.
    * Added plugin setup, requirements checks, and Contact Form 7 dependency.
    * Added database schema and upgrade routines.
    * Added user capabilities and uninstall cleanup.

== Upgrade Notice ==

= 0.7.0 =
Adds the Contacts page (senders grouped, filterable, exportable). Internal-only refactor otherwise — no action needed.

= 0.1.0 =
Initial foundation release — no user-facing features yet.
