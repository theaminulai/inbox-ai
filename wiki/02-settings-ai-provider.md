# 2. AI Provider Settings

**Where to find it:** Contact → Settings → **AI Provider** tab (this is the tab that opens by default).

This is where you tell Inbox AI which AI service to use for analyzing submissions and drafting replies. Nothing here is optional — the plugin can't summarize or categorize anything until a provider is connected.

## Step by step: connecting a provider

1. Under **Choose a provider**, click the card for the service you have an account with: **OpenAI**, **Anthropic**, or **Google**. The card you click gets a filled-in radio circle to show it's selected, and the section below updates to show that provider's name.
2. In the **Configuration** section, paste your **API key** into the API key field. This key comes from your account on the provider's own website (for example, your OpenAI or Anthropic account dashboard).
   * Your key is encrypted before it's stored and is never shown in full again — once saved, you'll only see a masked version like `sk-••••••••7f2A`.
3. Choose a **Model** from the dropdown. Each provider offers a few options (for example, OpenAI's list includes GPT-4.1 Mini, GPT-4.1, and GPT-4o). If you're not sure which to pick, the first option in the list is a sensible, cost-effective default.
4. Choose a **Request timeout** — how many seconds the plugin should wait for the AI to respond before giving up (30, 60, or 90 seconds). 30 seconds is fine for most cases.
5. Click **Test Connection**. This checks that your API key actually works, without saving anything yet.
6. Once the test succeeds, click **Save Changes**.

A green **Connected** badge appears next to the Configuration heading once a working key has been saved.

## Switching providers later

You can come back and click a different provider card at any time. The Model dropdown will refresh to show that provider's own models, and the "Configuration" heading updates to match — so you'll always be entering a key for the provider you actually see selected on screen.

## Fallback Behavior

Below the provider configuration is a **Fallback Behavior** card with three switches that control what happens when something goes wrong:

* **Retry failed requests automatically** — if an AI request fails, the plugin will automatically try again (up to 3 attempts, waiting a little longer between each try) before giving up.
* **Fall back to manual review on repeated failure** — if every retry still fails, the submission is marked **Needs Review** instead of **Failed**, so it doesn't get lost — you'll just need to handle that one manually.
* **Send email alert on provider outage** — notifies site administrators by email if the AI provider becomes completely unreachable, so you find out about a wider problem quickly.

Click a switch to turn it on (blue) or off (gray). These settings are saved as part of this tab — click **Save Changes** after adjusting them.
