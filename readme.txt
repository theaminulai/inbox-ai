=== Inbox AI ===
Contributors: theaminuldev
Tags: contact form 7, ai, inbox, openai, anthropic
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.5.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

AI-powered review inbox for Contact Form 7: AI drafts summaries, replies, and priority — you review and send, nothing is automatic.

== Description ==

Inbox AI adds a unified review inbox on top of your Contact Form 7 forms, without modifying Contact Form 7 itself. It combines:

* Persistent local storage of every submission to your chosen forms.
* An AI-drafted summary, suggested reply, category, priority, and confidence score for each submission.
* A searchable, filterable inbox and a per-submission review screen.
* A manual, confirmed **Send Reply** action — the AI drafts, you decide. Nothing is ever sent automatically.
* Bring-your-own-key support for OpenAI, Anthropic, and Google Gemini.

= Current status =

The plugin is under active development. Built and working today:

* AI Inbox: a searchable, filterable list of every captured submission (by form, status, priority, category, and date range), with a per-submission detail screen, AI summary/category/priority/confidence, an AI-drafted reply composer, retry-on-failure, and CSV export.
* Settings: AI Provider connection (OpenAI, Anthropic, Google), Monitored Forms, Automatic Processing rules, customizable AI prompts, a Usage & Billing dashboard, Notifications (email + Slack), and a Flamingo import wizard for migrating older submissions.

A full step-by-step user guide is included with the plugin under `docs-platform/`.

== Installation ==

1. Install and activate Contact Form 7 (required).
2. Upload and activate Inbox AI.
3. Go to **Contact → Settings** to connect an AI provider and choose which forms to monitor, then check **Contact → AI Inbox**. See the included user guide (`docs-platform/`) for full setup steps.

== Frequently Asked Questions ==

= Is this ready to use? =

Yes. AI Inbox (list, filters, submission detail, AI-drafted replies) and Settings (AI Provider, General, Prompts, Usage & Billing, Notifications, Import & Migration) are built and working. A Campaigns (bulk email) feature is still in development and not yet available.

= Will this modify Contact Form 7? =

No. It's designed to integrate only through Contact Form 7's public hooks and will never edit CF7 core files or its outgoing mail.

= Will replies ever be sent automatically? =

No. The AI drafts a summary and reply, and a human reviews and explicitly confirms before anything is sent to a visitor.

= Where will my API key be stored? =

Your AI provider API key is stored encrypted in the WordPress options table and is never exposed in any frontend page, script, or REST response — only a masked version (e.g. `sk-••••••7f2A`) is ever displayed after saving.

== External services ==

This plugin connects to a third-party AI provider — OpenAI, Anthropic, or Google Gemini, whichever one the site administrator chooses and configures with their own API key in Settings → AI Provider — so it can generate a summary, suggested category, priority, and a suggested reply for each Contact Form 7 submission the administrator chooses to analyze.

What data is sent, and when: each time a monitored form is submitted (or an administrator clicks "Retry"/"Regenerate"), the submission's text content (message body, subject, and the customer's name, to the extent the administrator's own prompt template references them) is sent to the selected provider's API, along with the site's configured prompt instructions. No data is sent to any provider until an AI provider is connected and at least one form is turned on under Monitored Forms. The administrator's API key is sent with each request (as required by the provider's own API) and is otherwise stored only on the site, encrypted, and never transmitted anywhere else.

Only one provider is contacted per request — whichever one is currently selected in Settings → AI Provider. The plugin never uses a shared or proxy key; the site owner must supply and pay for their own API key directly with the provider.

Provider terms of service and privacy policy:
* OpenAI — https://openai.com/policies/business-terms/ (Business Terms, which govern API use) and https://openai.com/policies/privacy-policy/
* Anthropic — https://www.anthropic.com/legal/commercial-terms (Commercial Terms of Service, which govern API use) and https://www.anthropic.com/legal/privacy
* Google Gemini — https://ai.google.dev/gemini-api/terms (Gemini API Additional Terms of Service) and https://policies.google.com/privacy

== Screenshots ==

1. AI Inbox — the filterable, searchable list of Contact Form 7 submissions.
2. Submission detail — AI summary, category, priority, confidence, and the AI-drafted reply composer.
3. Settings — AI Provider connection screen.
4. Settings — General tab, Monitored Forms and Automatic Processing.

== Changelog ==

= Inbox AI/v0.6.0 - 2026-07-28 =

**Changed**
* Shortened the plugin name to "Inbox AI" (was "InboxAI for Contact Form 7").
* Finalized the slug/text domain as `inbox-ai` (was `inboxai-for-contact-form-7`).
* Confirmed with WordPress.org that the slug could still change before the first SVN push.
* Kept the internal PHP namespace (`InboxAI\`) unchanged.
* Kept the code-level hook/option/capability prefix (`inboxai_`) unchanged.
**Added**
* Added the plugin icon (`icon-128x128.png`) to `.wordpress-org/`.
* Added the plugin banner (`banner-772x250.png`) to `.wordpress-org/`.

= Inbox AI/v0.5.0 - 2026-07-27 =

**Changed**
* Renamed the plugin from "CF7 AI Inbox" to "InboxAI for Contact Form 7".
* Set the new slug/text domain to `inboxai-for-contact-form-7`.
* Made the rename to avoid implying affiliation with the Contact Form 7 project.
* Corrected the OpenAI Terms of Service link to the Business Terms (the terms that actually govern API use).
* Corrected the Anthropic Terms of Service link to the Commercial Terms of Service.
* Corrected the Google Gemini Terms of Service link to the Gemini API Additional Terms.
* Rewrote the "Current status" section, which had been left describing an early pre-release state.
* Rewrote the "Screenshots" section to match the AI Inbox and Settings screens that were actually built.
**Added**
* Added a "Source Code" section to the readme.
* Documented where the human-readable source for the compiled admin assets is maintained.

= Inbox AI/v0.4.0 - 2026-07-25 =

**Added**
* Added a real "Received" date-range filter to the AI Inbox List.
* Filter options cover 7/30/90 days, this month, and 1/2/3/5 years.
* Added the same period filter to the Usage & Billing tab.
* Replaced the previously decorative "Last 30 days" control on Usage & Billing with the working filter.
* Added a live "N submissions this month" count under each form in Monitored Forms.
* Added a full step-by-step user guide (`docs-platform/`).
* The guide covers setup, every Settings tab, the AI Inbox list, submission replies, and troubleshooting.
**Improved**
* Reorganized admin template files into per-page subfolders (`inbox/`, `settings/`) for maintainability.
* Removed duplicate CSS rules from the settings stylesheet.
* Removed conflicting CSS rules from the shared admin stylesheet.
**Fixed**
* Fixed the AI Provider Settings Model dropdown not updating when switching between OpenAI, Anthropic, and Google.

= Inbox AI/v0.3.0 - 2026-07-15 =
= 0.3.0 - 2026-07-15 =
**Added**
* Added AI Inbox with filters, search, sorting, pagination, and submission details.
* Added AI reply composer with draft, regenerate, retry analysis, and send workflow.
* Added background AI analysis queue with OpenAI, Anthropic, and Gemini support.
* Added per-form AI categories with real-time category management in the Contact Form 7 editor.
* Added spam auto-archive, validation, and normalization for AI results.
* Added failure screen with one-click retry for failed AI analysis.
* Added CSV export of the current filtered list.
**Improved**
* Improved inbox actions, permissions, and overall admin experience.
**Fixed**
* Fixed AI Categories UI rendering on the Contact Form 7 editor.
* Fixed admin stylesheet build issue.

= Inbox AI/v0.2.0 - 2026-07-10 =
**Added**
* Added a settings page to configure AI providers and plugin options.
* Added monitored forms management to choose which Contact Form 7 forms are analyzed.
* Added customizable AI analysis prompts for better response quality.
* Added email notification settings for inbox activity.
* Added usage tracking, spending limits, and secure API key management.
* Added Flamingo import to migrate existing Contact Form 7 submissions.

= Inbox AI/v0.1.0 - 2026-07-01 =
**Added**
* Initial release.
* Added plugin setup, requirements checks, and Contact Form 7 dependency.
* Added database schema and upgrade routines.
* Added user capabilities and uninstall cleanup.

== Upgrade Notice ==

= 0.1.0 =
Initial foundation release — no user-facing features yet.
