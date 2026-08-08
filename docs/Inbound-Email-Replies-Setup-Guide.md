# Inbound Email Replies — Setup Guide

This feature lets Inbox AI capture a customer's reply when they hit "Reply" in their own email client (Gmail, Outlook, Apple Mail, or anything else) after receiving an AI-generated response, and bring that reply back into the submission's Activity timeline and Conversation thread automatically.

## How it works, in one paragraph

Every reply Inbox AI sends already goes out with a special `Reply-To` address (a tracking marker added to your real mailbox address). When a customer replies, their message lands in that same real mailbox. Inbox AI checks that mailbox on a schedule you control, using the standard IMAP protocol, and matches each new message back to the right submission. Nothing about this requires DNS changes, a new email account, or a third-party service — it uses the mailbox you already have.

## Requirements

- A real mailbox you can log into via IMAP (any host that gives you IMAP access works: cPanel/hosting email, Google Workspace, Microsoft 365, Zoho, etc.)
- The mailbox's IMAP host, port, and encryption type (your host or email provider's docs will have these — see the common examples below)
- Your host's PHP must have the `imap` extension enabled. If it isn't, the Settings page will show a warning and tell you plainly — ask your hosting provider to enable it if you see that message.

## Step-by-step setup

1. Go to **Inbox AI → Settings → Notifications** in your WordPress admin.
2. Scroll to the **Inbound Email Replies** card.
3. If you see a yellow warning saying PHP's `imap` extension isn't available, stop here and ask your host to enable it first — the rest of the setup will save fine, but checking won't actually run until it's on.
4. Fill in the fields:
   - **Check every** — how often Inbox AI checks the mailbox: 1, 2, 5, 10, 15, 30, or 60 minutes. 10 minutes is a reasonable default. See the note on WP-Cron below before picking 1 or 2 minutes.
   - **Mailbox address** — the real mailbox your replies are already sent from (e.g. `hello@yourbusiness.com`). This must match the address your outgoing mail actually uses.
   - **IMAP host** — see the table below for common providers, or ask your host.
   - **Port** — usually `993` for SSL or `143` for TLS/none.
   - **Encryption** — SSL, TLS, or None, matching your host's requirement.
   - **Mailbox folder** — usually `INBOX`; only change this if your host uses a different name for the main folder.
   - **Mailbox password** — the mailbox's real password, or an app-specific password if your provider requires one (Gmail and Microsoft 365 usually do). This is encrypted before it's stored and is never shown back to you in full.
5. Turn on the **Check for replies** switch.
6. Click **Save Notification Settings**.
7. Click **Save settings above, then test connection**. You should see a message like "Checked just now — 0 new message(s), 0 matched to a submission." That confirms the connection itself works, even with an empty inbox.

## Common provider settings

| Provider | IMAP host | Port | Encryption | Notes |
|---|---|---|---|---|
| cPanel / most hosting email (e.g. your own domain via your web host) | `imap.yourdomain.com` or `mail.yourdomain.com` | 993 | SSL | Check your host's webmail/email setup page for the exact hostname |
| Google Workspace / Gmail | `imap.gmail.com` | 993 | SSL | Requires a Google **App Password**, not your normal password (needs 2-Step Verification turned on first) |
| Microsoft 365 / Outlook | `outlook.office365.com` | 993 | SSL | Some tenants require an app password or have basic auth disabled by an admin — ask your Microsoft 365 admin if the connection test fails |
| Zoho Mail | `imap.zoho.com` | 993 | SSL | — |

## Worked examples

Pick whichever matches how your email is actually set up, and type these exact values in (swapping in your own domain/address/password). These are the three most common setups.

### Example 1 — Business email through your web host (cPanel, Hostinger, Bluehost, SiteGround, etc.)

Most WordPress sites use this: an email address at your own domain, created inside your hosting control panel (e.g. `hello@yourbusiness.com`), not routed through Google or Microsoft.

- **Check every:** `10 minutes`
- **Mailbox address:** `hello@yourbusiness.com`
- **IMAP host:** `mail.yourbusiness.com` (some hosts use `imap.yourbusiness.com` instead — check your host's webmail setup page, usually under "Email Accounts → Connect Devices" or similar)
- **Port:** `993`
- **Encryption:** `SSL`
- **Mailbox folder:** `INBOX`
- **Mailbox password:** the same password you use to log into that mailbox's webmail

### Example 2 — Business email on Google Workspace (Gmail for your domain)

Your email address looks like `hello@yourbusiness.com` but you log into it at gmail.com or mail.google.com, not your host's webmail.

- **Check every:** `10 minutes`
- **Mailbox address:** `hello@yourbusiness.com`
- **IMAP host:** `imap.gmail.com`
- **Port:** `993`
- **Encryption:** `SSL`
- **Mailbox folder:** `INBOX`
- **Mailbox password:** a Google **App Password**, not your normal Gmail password. Generate one at myaccount.google.com → Security → 2-Step Verification (must be turned on first) → App Passwords.

### Example 3 — Business email on Microsoft 365 / Outlook

Your email address looks like `hello@yourbusiness.com` but you log into it at outlook.com or office.com.

- **Check every:** `10 minutes`
- **Mailbox address:** `hello@yourbusiness.com`
- **IMAP host:** `outlook.office365.com`
- **Port:** `993`
- **Encryption:** `SSL`
- **Mailbox folder:** `INBOX`
- **Mailbox password:** your normal password usually works, but if the connection test fails, ask whoever manages your Microsoft 365 admin account to create an app password for you, or to confirm IMAP/basic authentication is allowed on the account (some organizations disable it by default for security).

In every example above, once "Check every," "Mailbox address," "IMAP host," "Port," "Encryption," and "Mailbox password" are filled in and the **Check for replies** switch is on, click **Save Notification Settings**, then **Save settings above, then test connection** to confirm it actually connects.

## Testing it for real

1. Send a reply to a customer from the AI Inbox (any test submission works).
2. From a different email account you control, reply to that email as if you were the customer.
3. Wait for the interval you set in "Check every" (or click "Save settings above, then test connection" again to check immediately rather than waiting).
4. Open that submission in the AI Inbox — the customer's reply should now appear in the Conversation thread and the Activity timeline, and the submission's status should move to "Review."

## A note on the "Check every" interval and WP-Cron

WordPress doesn't run background tasks on its own — checks only actually happen when something triggers `wp-cron.php`, which is normally a site visit, or a real system cron job if you've set one up on your host. Setting "Check every" to 1 minute only means Inbox AI will consider a check due after 1 minute — it still won't run any sooner than your site's own cron actually fires. If you want checks to genuinely happen every 1–2 minutes, make sure you have a real system cron job hitting `wp-cron.php` at least that often (ask your host how to set this up, or see your control panel's cron job settings).

## Troubleshooting

- **"PHP's imap extension is not available on this server"** — contact your hosting provider and ask them to enable the `imap` PHP extension. Nothing else will work until this is on.
- **"Could not connect: ..."** — double-check host, port, and encryption. A mismatched port/encryption pair (e.g. port 993 with encryption set to "None") is the most common cause.

- **"Could not connect: Certificate failure for mail.yourdomain.com: hostname mismatch: /CN=some-other-name.yourhost.com"** — a common shared-hosting quirk, not a mistake in your settings.

  **What's happening:** Many hosts (cPanel, Hostinger, and similar) put your mailbox on a shared mail server. That server's SSL certificate is issued for its own auto-generated hostname (the part after `/CN=` in the error — something like `204-197-172-218.cprapid.com`), not for `mail.yourdomain.com`. IMAP checks that the hostname you connected to matches the certificate's name, and here they don't match, so the connection is refused.

  **How to fix it:**
  1. Copy the hostname shown after `/CN=` in the error message — that's the real, certificate-matching hostname for your mail server.
  2. Go to **Inbox AI → Settings → Notifications → Inbound Email Replies**, and replace the **IMAP host** field with that hostname instead of `mail.yourdomain.com`.
  3. Save, then click **Save settings above, then test connection** again.

  If the error message doesn't show a `/CN=` hostname, check your hosting control panel's email setup page (often under "Email Accounts → Connect Devices" or "SSL/TLS Status") for the correct secure IMAP hostname, or ask your host's support directly — this is a common question they'll recognize right away.

- **Connects fine, but a customer's reply never shows up** — confirm the customer replied to the actual email (not a forward, and not a new message to your address), and that their reply landed in the folder set in "Mailbox folder" (usually INBOX, not Spam/Junk).
- **Test says "0 matched to a submission" even after a real reply arrived** — the message may have been marked as read by another mail client (e.g. checked in a webmail tab) before Inbox AI's check ran; only unread messages are scanned, and each one is marked read as it's processed either way. Confirm the reply is still unread in webmail before the next check runs.
