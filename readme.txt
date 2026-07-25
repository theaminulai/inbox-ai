=== CF7 AI Inbox ===
Contributors: theaminulai
Tags: contact form 7, ai, inbox, openai, anthropic
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.4.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

AI-powered review inbox for Contact Form 7: AI drafts summaries, replies, and priority — you review and send, nothing is automatic.

== Description ==

CF7 AI Inbox will add a unified review inbox to your Contact Form 7 forms, without modifying Contact Form 7 itself. When finished, it will combine:

* Persistent local storage of every submission to your chosen forms.
* An AI-drafted summary, suggested reply, category, priority, and confidence score for each submission.
* A searchable, filterable inbox and a per-submission review screen.
* A manual, confirmed **Send Reply** action — the AI drafts, you decide. Nothing is ever sent automatically.
* Bring-your-own-key support for OpenAI, Anthropic, Google Gemini, and OpenRouter.

= Current status =

This is an early, foundation-only release. The pieces in place so far:

* Plugin bootstrap with a PSR-4 autoloader.
* Requirements checks (PHP 8.1+, WordPress 6.7+, Contact Form 7 active), surfaced as admin notices.
* The custom database schema (messages, activities, usage) created on activation.
* A custom capability system, granted to Administrators on activation.

**Submission capture, the AI analysis layer, and the AI Inbox admin screens are not built yet.** Activating this version adds no visible admin screen and processes no submissions. See the plugin's `docs/CF7_AI_Inbox_RnD.md` for the full development plan.

== Installation ==

1. Install and activate Contact Form 7 (required).
2. Upload and activate CF7 AI Inbox.
3. There is nothing to configure yet — later releases will add a Settings and AI Inbox screen under **Contact**.

== Frequently Asked Questions ==

= Is this ready to use? =

Not yet. This release only lays the foundation (database tables, capabilities, requirements checks). It does not capture submissions, generate AI analysis, or show an inbox screen.

= Will this modify Contact Form 7? =

No. It's designed to integrate only through Contact Form 7's public hooks and will never edit CF7 core files or its outgoing mail.

= Will replies ever be sent automatically? =

No. The plan is the same as for any AI-assisted inbox: the AI drafts a summary and reply, and a human reviews and explicitly confirms before anything is sent to a visitor.

= Where will my API key be stored? =

Once AI provider support is added, keys will be stored encrypted in the WordPress options table and never exposed in any frontend page, script, or REST response.

== External services ==

This plugin does not currently connect to any external service — no submission data is sent anywhere yet, because submission capture and AI provider integration are not implemented in this release. Once AI provider support (OpenAI, Anthropic, Google Gemini, OpenRouter) is added in a future version, this section will be updated to disclose exactly what is sent, when, and under which provider terms.

== Screenshots ==

None yet — there is no admin UI in this release.

== Changelog ==

= CF7AI Inbox/v0.4.0 - 2026-07-25 =
**Added**
* Added a real "Received" date-range filter to the AI Inbox List (7/30/90 days, this month, 1/2/3/5 years) — previously missing from the live page despite being present in the design.
* Added the same period filter to the Usage & Billing tab, replacing the previously decorative "Last 30 days" control.
* Added a live "N submissions this month" count under each form in Monitored Forms.
* Added a full step-by-step user guide (`docs-platform/`) covering setup, every Settings tab, the AI Inbox list, submission replies, and troubleshooting.
**Improved**
* Reorganized admin template files into per-page subfolders (`inbox/`, `settings/`) for maintainability.
* Removed duplicate/conflicting CSS rules across the settings and shared admin stylesheets.
**Fixed**
* Fixed the AI Provider Settings Model dropdown not updating when switching between OpenAI, Anthropic, and Google.

= CF7AI Inbox/v0.3.0 - 2026-07-15 =
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

= CF7AI Inbox/v0.2.0 - 2026-07-10 =
**Added**
* Added a settings page to configure AI providers and plugin options.
* Added monitored forms management to choose which Contact Form 7 forms are analyzed.
* Added customizable AI analysis prompts for better response quality.
* Added email notification settings for inbox activity.
* Added usage tracking, spending limits, and secure API key management.
* Added Flamingo import to migrate existing Contact Form 7 submissions.

= CF7AI Inbox/v0.1.0 - 2026-07-01 =
**Added**
* Initial release.
* Added plugin setup, requirements checks, and Contact Form 7 dependency.
* Added database schema and upgrade routines.
* Added user capabilities and uninstall cleanup.

== Upgrade Notice ==

= 0.1.0 =
Initial foundation release — no user-facing features yet.
