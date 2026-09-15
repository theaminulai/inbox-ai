# Integrations

**Where to find it:** Contact → Settings → **Integrations** tab.

This tab connects Inbox AI to other tools your team already uses. It currently holds two unrelated cards — Slack and CRM — kept separate because they work very differently from each other.

## Slack Integration

If your team uses Slack:

1. Turn on **Send a Slack message for urgent submissions**.
2. Paste a valid webhook URL (it must start with `https://`) into the **Slack channel webhook URL** field. You can get this URL from your Slack workspace's app/integration settings — search Slack's own help documentation for "incoming webhooks" if you haven't set one up before.
3. Click **Send test message** to confirm it actually posts to your channel.
4. Click **Save Integration Settings**.

If the webhook URL field is left empty or isn't a valid HTTPS link, Slack messages won't be sent even with the switch on. As with email notifications, this only fires for **Urgent**-priority submissions.

## CRM Data Collection

Choose a **CRM provider** (currently HubSpot or Mailchimp) and paste in your **API key**. Your key is encrypted before it's stored and is never shown back to you in full, the same way your AI provider's API key is handled.

Automatic syncing to your CRM isn't available yet — this card only saves your connection details securely so nothing needs re-entering once that feature ships. Saving a provider and key here doesn't send anything to that CRM today.

## Saving

Click **Save Integration Settings** at the bottom of the page after making changes on either card.

---

[Guide index](README.md) | [← Back to Notifications](06-settings-notifications.md) | [Next: Import & Migration →](07-settings-import-migration.md)
