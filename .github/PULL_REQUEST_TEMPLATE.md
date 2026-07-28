## What?
<!-- Link this PR to its associated issue. Use keywords: Closes, Fixes, or Resolves -->
Closes #<!-- ISSUE-NUMBER -->

<!-- Briefly describe what this PR does. -->

## Why?
<!-- Explain why this change is necessary:
- What problem does it solve?
- What bug or user need does it address?
- Reference any related issues or PRs
-->

## How?
<!-- Describe how this PR implements the solution:
- What approach did you take?
- Any key implementation details or architectural decisions?
-->

## Type of Change
<!-- Check all that apply -->
- [ ] Bug fix
- [ ] New feature
- [ ] Refactor / code quality
- [ ] Performance improvement
- [ ] Security fix
- [ ] Documentation update
- [ ] Build / tooling change


## Testing Instructions
<!-- Step-by-step instructions to verify this PR works correctly -->
1.
2.
3.

<!-- Example for an AI-analysis change:
1. Install the plugin on a clean WordPress + Contact Form 7 site with WP_DEBUG enabled
2. Connect an AI provider (OpenAI/Anthropic/Gemini) under Contact → Settings → AI Provider
3. Turn on a monitored form under Settings → General, then submit a test entry on the frontend
4. Confirm the submission appears in Contact → AI Inbox with a summary/category/priority once WP-Cron processes the queue
5. Open the submission, edit the AI-drafted reply, and send it — verify it never sends without explicit confirmation
6. Check the browser console and PHP error log for any errors
-->

## Checklist
- [ ] Code follows the Inbox AI coding standards (PHP 8.1+, `declare(strict_types=1)`, PSR-4 namespace `InboxAI\`)
- [ ] PHPCS passes with no errors (`composer cs`)
- [ ] Tested on a clean WordPress installation with `WP_DEBUG` set to `true`
- [ ] Plugin degrades gracefully (stays installed, shows an admin notice, adds no menu items) when Contact Form 7 is inactive or missing
- [ ] AI provider API keys remain encrypted at rest and are never exposed in any frontend page, script, or REST response (only a masked value, e.g. `sk-••••••7f2A`)
- [ ] Background AI analysis jobs use the existing WP-Cron-driven queue (`AnalysisQueue`) — no new Action Scheduler dependency introduced
- [ ] No reply or other outbound action is ever sent automatically — a human must explicitly confirm
- [ ] Text domain `inbox-ai` used in all translatable strings
- [ ] No `__()` calls inside constructors
