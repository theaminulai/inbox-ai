---
name: "add-settings-field"
description: "Add a new field, group of fields, or whole new settings tab/card to the Inbox AI Settings page end-to-end. Use whenever a request touches the plugin's Settings screen — a new toggle, a new credential, a new card, a new tab."
---

Every settings feature in this plugin — AI Provider, General, Prompts, Notifications, Inbound Email Replies — follows the same shape across five layers. Adding a new one means touching all five, in this order:

1. **`includes/Settings/Repository.php`** — add `get_x()` (returns `wp_parse_args($stored, $defaults)` from its own `wp_options` row) and `save_x(array $data)` (whitelists/sanitizes every key, falling back to the current value for anything missing or invalid). If the field is a secret (password, API key, token), give it its own encrypted option with `get_x_password()`/`has_x_password()`/`get_masked_x_password()`/`save_x_password()` — copy `get_inbound_password()`/`save_inbound_password()` exactly, including the masked-value save guard.

2. **`includes/Admin/Pages/SettingsPage.php`** — add the new `get_x()` result (plus any masked/derived values a template needs) to `build_view_model()`.

3. **`includes/Templates/settings/settings.php`** — add the same key to `$inboxai_settings_vars` so it reaches the tab template.

4. **`includes/Templates/settings/<tab>.php`** — new markup goes inside an `.inboxai-card`, fields as `.inboxai-field` (or paired in an `.inboxai-field-row` for a 50/50 layout), each input carrying `data-field="x"` — that's what makes `fields.js` pick it up automatically with no per-field JS. Match an existing card's structure rather than inventing new markup patterns; the Inbound Email Replies card is the most complete reference (switch row, paired field-row, secret field with hint text, conditional warning notice).

5. **`includes/Admin/Ajax/SettingsAjaxController.php`** — add `SettingsRepository::save_x($values);` inside the relevant `case` of `save_settings()`'s switch (each `case` corresponds to one tab, not one field — most new fields join an existing case). If the feature needs a live "test" button, add a dedicated `wp_ajax_inboxai_test_x_connection` action following `test_inbound_connection()`'s pattern: always test the *saved* setting, never unsaved form values, since the thing being tested (a cron check, a mail send) always reads from the repository itself.

If it's JS-only behavior beyond plain field read/write (a picker that auto-fills other fields, a test-connection button, conditional show/hide), add it to that tab's own file under `src/admin/componets/settings/` — then run `npm run build`.

Don't create a new settings tab unless the feature is genuinely unrelated to all six existing ones — most new features belong as a new card inside Notifications or General, matching how Inbound Email Replies was added to Notifications rather than getting its own tab.
