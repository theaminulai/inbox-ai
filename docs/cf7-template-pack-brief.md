# Inbox AI — CF7 Template Pack: Categories & Build Prompt

## 1. The 20 categories (50 templates, distributed across them)

Picked by real-world Contact Form 7 use case, not by form mechanics — "quiz" and "multi-step" are
build *patterns*, not categories on their own, so they show up as the pattern used inside several
categories below rather than owning a whole category apiece. Two categories are the exception
(#15, #16) since you specifically want quiz and multi-step well represented.

| # | Category | Suggested count | Typical pattern |
|---|---|---|---|
| 1 | General Contact / Inquiry | 3 | Standard |
| 2 | Quote & Estimate Request | 3 | Standard |
| 3 | Booking & Appointment Scheduling | 3 | Standard / Multi-step |
| 4 | Event Registration / RSVP | 3 | Standard / Multi-step |
| 5 | Customer Support / Help Desk Ticket | 3 | Standard |
| 6 | Feedback & Survey | 3 | Standard |
| 7 | Job Application / Career | 3 | Multi-step |
| 8 | Newsletter & Lead Capture | 2 | Standard |
| 9 | Real Estate Inquiry | 2 | Standard |
| 10 | Healthcare / Medical Intake | 2 | Multi-step |
| 11 | Education / Course Enrollment | 2 | Standard |
| 12 | Restaurant Reservation | 2 | Standard |
| 13 | E-commerce Product Inquiry & Returns | 2 | Standard |
| 14 | Donation / Nonprofit | 2 | Standard |
| 15 | Quiz / Lead Qualification | 4 | Quiz (conditional/scored) |
| 16 | Multi-Step Onboarding / Intake | 4 | Multi-step |
| 17 | Testimonial / Review Request | 2 | Standard |
| 18 | Partnership / Vendor Inquiry | 2 | Standard |
| 19 | Technical Support / Bug Report | 2 | Standard |
| 20 | Wedding / Event Planning Inquiry | 1 | Multi-step |

Total: 50. Adjust counts freely — this is a starting split, not a hard rule.

## 2. Build prompt (use this per template, or batch it per category)

```
You are designing one form template for "Inbox AI," a Contact Form 7 add-on that adds an AI
review inbox on top of CF7 submissions. This template will be offered inside CF7's own
"Add Contact Form" screen, inserted via a custom tag-generator button (the same toolbar row as
CF7's built-in text/email/quiz/file buttons) — it is NOT a standalone form plugin and must never
require anything beyond Contact Form 7 itself to function.

TEMPLATE TO BUILD:
- Category: {{CATEGORY}}
- Template name: {{TEMPLATE_NAME}}
- Pattern: {{Standard | Multi-step | Quiz/conditional}}
- One-line description (shown in the template picker, <100 characters): {{...}}

DELIVERABLES (all four, every time):

1. CF7 Form tab markup
   - Valid Contact Form 7 tag syntax only (no shortcodes CF7 doesn't support natively).
   - Every field has a visible <label>, sensible autocomplete attributes, and correct
     required/optional marking.
   - Field names are unique, lowercase, hyphenated, and namespaced to avoid collisions if two
     templates are inserted into forms on the same site (e.g. `your-name`, `event-date`, not `q1`).

2. Mail tab content
   - A working default notification email using CF7's own mail-tag syntax, referencing every
     field actually present in the Form tab. No field should be silently dropped from the email.

3. CSS (only if the template needs layout beyond CF7's default stacked fields — grids,
   multi-column rows, step indicators, quiz progress bar, etc.)
   - Scope every rule under a single template-specific class, e.g. `.inboxai-tpl-{{slug}}`, so it
     cannot leak into the site's theme or into other forms on the page.
   - No dependency on a CSS framework that isn't already bundled with Inbox AI.
   - Must remain usable (not necessarily pretty) with zero CSS if the theme strips custom styles.

4. JS (only for Multi-step or Quiz patterns — Standard pattern templates should need none)
   - Vanilla JS only, no external library dependency.
   - Multi-step: client-side step navigation (next/back, a visible progress indicator, and
     per-step validation before advancing) — CF7's own validation still runs normally on final
     submit, this JS is only for the step-gating UX.
   - Quiz: conditional field show/hide and/or scoring logic, computed entirely client-side.
   - Must degrade safely: if JS fails to load, all fields should still be reachable and
     submittable in a single scroll (no field may become permanently hidden with no JS fallback).
   - Respect prefers-reduced-motion for any step-transition animation.

5. Suggested default AI Categories (1–3, matching Inbox AI's existing per-form AI Categories
   feature — see the "AI Categories" box in the CF7 editor sidebar) so the template is
   pre-configured for AI triage the moment it's inserted, not just a bare form.
   Example: a "Quote & Estimate Request" template suggests the AI category "Quote Request";
   a "Technical Support" template suggests "Bug Report" and "Urgent".

CONSTRAINTS (apply to every template, no exceptions):
- Must render and submit correctly using only Contact Form 7 — no other plugin required.
- Must not touch Contact Form 7 core files; only CF7's own public tag-generator API.
- Accessible: proper label association, keyboard-navigable steps, visible focus states, no
  color-only error indication.
- Mobile-first: single column below 600px regardless of desktop layout.
- No placeholder lorem ipsum in the mail template — every notification email must be genuinely
  usable as-is.
- Keep copy in plain, natural English (this is user-facing field/label text, not marketing copy —
  no keyword stuffing here, that's a readme concern, not a template concern).

OUTPUT FORMAT:
Return the four deliverables as separate labeled code blocks (Form / Mail / CSS / JS), followed by
the suggested AI Categories as a plain comma-separated line. Omit the CSS or JS block entirely
(don't return an empty block) if the template doesn't need it.
```

## 3. Notes for you before handing this to a designer/AI

- Multi-step and Quiz templates are the ones that actually need engineering time (the JS step/quiz
  logic), not just design — budget those differently than the 34 Standard-pattern templates.
- If two templates in different categories end up needing near-identical JS (e.g. every Multi-step
  template needs the same step-navigation logic), that logic should be written once as a shared
  script Inbox AI loads, with each template only supplying its own step markup — don't duplicate
  the same JS 8 times across templates.
- Worth deciding now, not after 50 are built: do templates get versioned/updated after release
  (e.g. if CF7 changes tag syntax), and who owns keeping 50 templates working across future CF7
  versions? That maintenance question is bigger than the initial build.
