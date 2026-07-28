# 9. Viewing & Replying to a Submission

**Where to find it:** click any customer's name/email, or the eye/envelope icon, from [the AI Inbox list](08-ai-inbox-list.md).

This page shows everything about one submission and, for anything the AI could successfully analyze, lets you review and send a reply.

## Customer Information

Name, email, phone (if the form collected it), and company (if provided).

## Submission Details

Submission ID, which form was used, the page the form was submitted from, the visitor's IP address, exactly when it was submitted, and whether the confirmation email to your visitor sent successfully (**Mail Status**).

## AI Analysis

If analysis completed successfully, you'll see:

* **Summary** — a short plain-language recap of what the visitor is asking for.
* **Category** and **Priority** — the AI's classification.
* **Confidence** — a percentage and bar showing how confident the AI is in its own analysis.
* **AI Reasoning** — a brief explanation of why it reached that conclusion.
* A **Regenerate Analysis** link, if you'd like the AI to take another pass at it.

### If analysis failed instead

You'll see an error card explaining that analysis couldn't complete, along with:

* **Retry** — asks the AI to try again.
* **Mark Reviewed** — marks the submission as handled, without waiting on the AI.
* **Provider Settings** — a shortcut back to the AI Provider tab, in case the underlying issue is a connection problem (see [Troubleshooting](10-troubleshooting.md)).

There's no Reply Composer on a failed submission yet, since there's no AI draft to start from — you can still see everything the visitor submitted and handle it manually.

## Submitted Fields

The subject and full message text the visitor submitted, plus any other custom fields their form collected.

## Reply Composer

This only appears once AI analysis has completed. Here's how to send a reply:

1. The **Recipient** field always shows the real sender's email address and can't be edited — replies only ever go to whoever actually submitted the form.
2. Check the **Subject** line (pre-filled as "Re: " plus the original subject) and edit if needed.
3. Review the **Message** body. If the AI drafted a reply, it's already filled in here — read it over and edit anything you'd like to change using the small toolbar above it (bold, italic, underline, bulleted list). You can also click the refresh icon in that toolbar to have the AI **regenerate** the reply if you'd like a different draft.
4. Click **Save Draft** at any point to save your edits without sending.
5. When you're happy with it, click **Send Reply**. You'll see a confirmation step showing exactly what will be sent before it actually goes out — nothing is ever emailed without this explicit confirmation.

## Activity

A timeline at the bottom of the page logs everything that's happened to this submission — when it was received, when analysis completed (or failed), when a draft was saved, when a reply was sent, and so on — each with a "how long ago" timestamp.
