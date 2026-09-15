# 6. Notifications

**Where to find it:** Contact → Settings → **Notifications** tab.

Use this tab to decide how you (or your team) hear about activity in the AI Inbox, instead of having to check it constantly.

## Email Notifications

Five independent switches:

* **Notify me on urgent messages** — sends an email the moment a submission is scored as Urgent priority.
* **Daily summary digest** — sends one email each morning at 9:00 AM summarizing the previous day's activity.
* **Notify on AI analysis failure** — sends an email whenever a submission's AI analysis couldn't complete.
* **Notify when a reply draft is ready** — sends an email when a new AI-drafted reply is waiting for your approval.
* **Notify me when a customer replies** — sends an email as soon as a customer's reply is pulled in by Inbound Email Replies (see below).

Turn on whichever combination makes sense for you — they work independently of each other.

## Inbound Email Replies

This card lets Inbox AI pick up a customer's reply when they hit "Reply" in their own email client, and bring it back into that submission's conversation thread automatically — instead of it just landing in your regular mailbox and going unnoticed by the plugin.

Setting this up involves a few technical fields (an IMAP host, port, and mailbox password), so it has its own dedicated walkthrough with common hosting examples and troubleshooting: see the [Inbound Email Replies Setup Guide](Inbound-Email-Replies-Setup-Guide.md).

If your host's PHP doesn't have the `imap` extension enabled, this card shows a yellow warning saying so — the rest of the tab still saves fine, but checking won't run until your host turns it on.

## Looking for Slack or a CRM?

Slack notifications and CRM connection details moved to their own **Integrations** tab — see [Integrations](settings-integrations.md).

## Saving

Click **Save Notification Settings** at the bottom of the page after making changes — nothing on this tab takes effect until you save.

---

[← Previous: Usage & Billing](05-settings-usage-billing.md) | [Guide index](README.md) | [Next: Import & Migration →](07-settings-import-migration.md)
