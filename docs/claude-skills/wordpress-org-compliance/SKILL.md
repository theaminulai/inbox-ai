---
name: "wordpress-org-compliance"
description: "Checklist and gotchas for keeping Inbox AI compliant with WordPress.org's Plugin Directory guidelines, and for preparing a release. Use before any version bump, before submitting/resubmitting to WordPress.org, or if a WordPress.org Plugins Team review email needs a fix."
---

Inbox AI is distributed through the WordPress.org Plugin Directory, which runs automated and human review. This plugin has already been closed once for guideline violations, so treat these as real constraints, not style preferences.

## i18n — the parser is stricter than PHP itself

Every `__()`, `_e()`, `_n()`, `_x()` call is statically parsed. The message argument **must be a literal string** — a variable there fails the parser even though the code runs fine locally (`WordPress.WP.I18n.NonSingularStringLiteralText`). If you need dynamic labels (e.g. mapping an event type to display text), write a literal-string `switch`/`match` instead of building a lookup array and passing the result to `__()`. Reference: `Support\Format::activity_event_label()`.

## Compiled assets need a human-readable source

Anything under `src/` (the pre-build SCSS/JS) is excluded from the shipped package by `.distignore`. WordPress.org requires a way to inspect the human-readable source of any compiled/minified JS in the package — this plugin satisfies that via the `== Source Code ==` section in `readme.txt` linking to the public GitHub repo. If that link ever goes stale or the repo goes private, the plugin is non-compliant again.

## PHPCS

Run the WordPress Coding Standards ruleset before release. Common flags seen in this codebase: unsanitized `$_POST`/`$_GET` access (`WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` — fix by sanitizing/unslashing at the point of access, not one line above it in a multi-line statement) and missing nonce verification on non-AJAX admin actions (`WordPress.Security.NonceVerification.Recommended`). A `phpcs:ignore` comment must sit directly above the flagged line, not above an earlier line in the same statement.

## readme.txt conventions already established in this file

- `=== Title ===` (h1) must match the `Plugin Name:` header in `inbox-ai.php` exactly.
- `== Section ==` (h2) names are reserved by the WordPress.org parser (Description, Installation, FAQ, Changelog, Screenshots, Upgrade Notice) — don't rename these.
- No h4 exists in the parser. Use `**bold**` for a pseudo-sub-heading, not `*italic*` and not a heading syntax.
- Changelog entries: `= Inbox AI for Contact Form 7/vX.X.X - YYYY-MM-DD =`, with `**Fixed**`/`**Added**`/`**Improved**`/`**Changed**` bold sub-labels underneath.
- `Stable tag:` in the readme header and `Version:` in `inbox-ai.php` must always match.
- Upgrade Notice entries stay version-only — don't add feature descriptions there.

## If a WordPress.org Plugins Team review email arrives

Replying is required, not optional — check their own submission guidelines page for the current wording, but silence on a flagged plugin risks it staying closed past the fix window. Draft a reply that: states each violation was fixed, references the specific commit/version, and asks for re-review. Keep a copy of what was actually fixed (this session's actual fixes: literal-string i18n in `Format.php`, and linking the GitHub source for compiled JS) in case the same reviewer asks for specifics.
