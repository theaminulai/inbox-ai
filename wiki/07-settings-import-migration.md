# 7. Import & Migration

**Where to find it:** Contact → Settings → **Import & Migration** tab.

This is a 5-step wizard that brings older submissions into Inbox AI as new copies — nothing it reads from is ever changed or deleted. It's safe to run more than once; anything already imported is skipped, not duplicated.

## Step 1: Type

Choose what you're importing:

* **Flamingo** — if you were previously using the **Flamingo** plugin to store Contact Form 7 submissions, this brings that history in (either from Flamingo's own live data on this site, or a CSV it exported).
* **Inbox AI CSV** — upload a CSV file shaped for this plugin's own columns directly. This is mainly useful for loading test/demo data, or migrating from something other than Flamingo.

Click **Next** once you've picked one.

## Step 2: Source

What you see here depends on your choice in Step 1.

**If you chose Flamingo:**

* **Use live Flamingo data** — reads directly from Flamingo's own data already stored on your site. Only available (not grayed out) if Flamingo is currently active.
* **Upload a CSV export** — choose this if you have a CSV exported from Flamingo's own "Export" button, then select the file with **Choose File**.

Click **Check for Flamingo Data** (for live data) — choosing a CSV file triggers the same check automatically. Once it shows a green confirmation, click **Next**.

**If you chose Inbox AI CSV:**

Click **Choose File** and select your CSV. It must include at least a `sender_email` and a `message` column (see "Recognized CSV columns" below for the full list). Once it's checked successfully, click **Next**.

## Step 3: Options

Decide whether to **run AI analysis on imported messages**. This is optional — importing without it just brings the messages in as-is, and you can always run analysis on them later in smaller batches (useful if you'd rather control AI costs). Click **Next** when ready.

## Step 4: Review & Import

You'll see a summary of exactly how many rows are about to be imported. Click **Start Import** to begin. A progress bar shows how far along the import is — this can take a little while for a large batch, so it's fine to leave the tab open until it finishes.

## Step 5: Complete

Once done, you'll see a summary of what was imported. From here you can:

* Click **Import Another Batch** to run the wizard again (for example, if new Flamingo messages have arrived since your last import).
* Click **View AI Inbox** to go straight to your imported messages.

## Recognized CSV columns (Inbox AI CSV path)

Any order, case-insensitive. Only `sender_email` and `message` are required — everything else is optional:

```
sender_name, sender_email, phone, company, form_title, source_category,
subject, message, category, priority, confidence, workflow_status, created_at
```

`source_category` is the fixed, form-defined category (never changed by AI regenerate); `category` is the AI's own classification — provide it directly in the file, or leave it blank and turn on "Run AI analysis" in Step 3 to have it generated for real.

## Good to know

Your original Flamingo entries are left completely untouched — this process only ever creates new copies inside Inbox AI. Re-uploading the same file is also safe: rows already imported before are skipped, not imported twice.

---

[← Previous: Notifications](06-settings-notifications.md) | [Guide index](README.md) | [Next: The AI Inbox List →](08-ai-inbox-list.md)
