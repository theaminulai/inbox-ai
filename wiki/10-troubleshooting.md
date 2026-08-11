# 10. Troubleshooting

## "AI analysis could not be completed" on a submission

This means the plugin couldn't get a usable response from your AI provider for that specific message. It doesn't affect Contact Form 7 itself — your visitor's confirmation email still went out normally either way. To fix it:

1. Open the submission and click **Retry** — many failures are temporary (a brief connection hiccup, a rate limit) and succeed on a second attempt.
2. If it keeps failing, click **Provider Settings** from the same error card and confirm your AI Provider tab shows a green **Connected** badge. If it doesn't, re-check your API key and click **Test Connection** again.
3. If you just changed your API key or switched providers, make sure you clicked **Save Changes** afterward — testing a connection alone doesn't save it.
4. As a fallback, click **Mark Reviewed** to move on and handle the submission's reply yourself while you sort out the provider issue.

## No submissions are showing up in the AI Inbox

1. Go to **Settings → General** and confirm the form you submitted is actually toggled **on** under Monitored Forms. Only forms with their switch on are captured.
2. Confirm you're looking at the right date range — the dropdown in the AI Inbox's top-right corner defaults to showing everything, but if you've changed it, older or newer submissions outside that window won't appear. Switch it back to **All time** to check.
3. Check whether any filters (search box, Form/Status/Priority/Category) are active — an active filter can make the list look empty even when submissions exist. Click **Clear filters** if you see that option.

## Test Connection fails on the AI Provider tab

* Double-check the API key was copied in full and pasted without extra spaces.
* Confirm the key hasn't been revoked or expired on the provider's own website.
* Make sure you're testing the same provider card you meant to configure — clicking a different provider card resets which one you're currently editing.

## Slack notifications aren't arriving

* Confirm the **Send a Slack message for urgent submissions** switch is on in **Settings → Notifications**.
* Confirm the webhook URL field contains a real `https://` webhook URL from your Slack workspace, not a placeholder or a copy-paste mistake.
* Remember this notification only fires for **Urgent**-priority submissions — anything else won't trigger a Slack message even with everything configured correctly.

## Still stuck?

Check that both Contact Form 7 and Inbox AI are updated to their latest versions, since fixes and improvements ship regularly.

---

[← Previous: Viewing & Replying to a Submission](09-submission-detail-and-replies.md) | [Guide index](README.md)
