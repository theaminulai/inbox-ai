# 1. Getting Started

## What you need before you start

* **Contact Form 7** installed and active. Inbox AI works alongside it and never modifies your existing forms.
* WordPress 6.7 or newer.
* A hosting account running PHP 8.1 or newer (ask your host if you're not sure — most modern hosting already meets this).
* An account with at least one AI provider: **OpenAI**, **Anthropic**, or **Google (Gemini)**. You'll need an API key from whichever one you choose — see [step 2 of the checklist](#first-time-setup-checklist) below.

## Installing and activating

1. Upload the Inbox AI plugin the same way you would any other WordPress plugin (via **Plugins → Add New → Upload Plugin**, or by placing the folder in `wp-content/plugins/` if you're installing manually).
2. Go to **Plugins** in your WordPress admin and click **Activate** next to Inbox AI.
3. Look for **Contact** in your admin sidebar (this is Contact Form 7's own menu). You should now see two new entries under it: **AI Inbox** and **Settings**.

If you don't see these two entries, double-check that Contact Form 7 itself is active — Inbox AI won't add its menu items without it.

## First-time setup checklist

Follow these five steps in order the first time you set the plugin up:

1. **Connect an AI provider.** Go to **Contact → Settings** and open the **AI Provider** tab. Pick OpenAI, Anthropic, or Google, paste in your API key, and click **Test Connection**. Full instructions: [AI Provider Settings](02-settings-ai-provider.md).
2. **Choose which forms to monitor.** Still in Settings, open the **General** tab and turn on the Contact Form 7 forms you want Inbox AI to watch. Nothing is captured from a form until you turn it on here. Full instructions: [General Settings](03-settings-general.md).
3. **(Optional) Review the AI's instructions.** The **Prompts** tab lets you adjust exactly what the AI is told to do when summarizing a message or drafting a reply. The defaults work well for most sites, so you can skip this and come back later. Full instructions: [Prompts](04-settings-prompts.md).
4. **(Optional) Turn on notifications.** The **Notifications** tab can email you (or post to Slack) when something urgent comes in. Full instructions: [Notifications](06-settings-notifications.md).
5. **Submit a test message.** Fill out one of the forms you just turned on monitoring for, then go to **Contact → AI Inbox**. Your test submission should appear there within a few moments, followed shortly by an AI-written summary, category, and priority once analysis finishes.

Once that test submission shows up correctly, you're fully set up. Head to [The AI Inbox List](08-ai-inbox-list.md) to learn how to work with incoming messages day to day.

---

[Guide index](README.md) | [Next: AI Provider Settings →](02-settings-ai-provider.md)
