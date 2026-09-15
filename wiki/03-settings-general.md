# 3. General Settings

**Where to find it:** Contact → Settings → **General** tab.

This tab controls which forms feed into the AI Inbox and how new submissions are automatically handled.

## Monitored Forms

Every Contact Form 7 form on your site is listed here with a switch next to it.

* **Turn a switch on** to start capturing that form's submissions into the AI Inbox.
* **Turn it off** to stop — existing submissions from that form stay in your inbox, but new ones won't be added.
* Under each form's name, a small line tells you how many submissions it has received **this calendar month** (for example, "3 submissions this month"), so you can see at a glance which forms are active.

If you don't see any forms listed here, you haven't created a Contact Form 7 form yet — create one first, then come back.

## Automatic Processing

These three switches control what happens the moment a new submission comes in:

* **Analyze new submissions automatically** — when on, the AI immediately summarizes, categorizes, and scores every new submission. Turn this off if you'd rather trigger analysis manually later (not recommended for most sites).
* **Auto-draft replies for high-confidence messages** — when on, the AI also writes a suggested reply for messages it's confident about. Important: a draft is never sent on its own — a person always has to review and click Send.
* **Auto-archive detected spam** — when on, messages the AI is at least 95% sure are spam are automatically archived, keeping them out of your main list.

Below the switches is a **Confidence threshold for "Needs Review"** slider (0–100%). Any submission the AI analyzes with confidence *below* this number gets flagged as **Needs Review** instead of being treated as fully processed — a signal to double-check it yourself before relying on the AI's summary or category.

## Manage Categories

Add, rename, or delete the categories the AI can assign to a submission (these are the same categories you'll see in the AI Inbox's Category filter and on each submission).

* Type a name into the **New category name** field and click **Add category**.
* Click the pencil icon next to an existing category to rename it — this updates it everywhere it's already been used, on every form.
* Click the trash icon to delete a category. The "Used by N form(s)" line under each name tells you how widely it's used before you delete it.

Categories you add here are available to every monitored form; there's nothing per-form to configure separately.

## Data Retention

* **Keep submissions for** — choose how long to keep submission records: Forever, 24 months, 12 months, or 6 months. Anything older than your chosen period is permanently deleted by a daily background check — there's nothing to click to trigger this, it just runs. Choosing **Forever** turns this off entirely.

## Saving your changes

Click **Save Changes** at the bottom of the page after adjusting anything on this tab. Changes to Monitored Forms take effect immediately when you toggle them (you'll see a confirmation message), but the other settings need the Save Changes button.

---

[← Previous: AI Provider Settings](02-settings-ai-provider.md) | [Guide index](README.md) | [Next: Prompts →](04-settings-prompts.md)
