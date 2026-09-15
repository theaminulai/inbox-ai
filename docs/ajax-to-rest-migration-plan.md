# AJAX → REST API Migration Plan

Inbox AI currently runs every admin-side request through `admin-ajax.php` (`wp_ajax_*` hooks). The plugin already reserves a REST namespace — `inbox-ai.php` defines `INBOXAI_API_NAMESPACE = 'inboxai/v1'` — but nothing registers a route on it yet. This document lists every existing AJAX endpoint and lays out how (and in what order) to move them to `register_rest_route()` under that namespace.

## Current AJAX inventory (27 endpoints, 3 controllers)

All three controllers extend `BaseAjaxController`, which supplies two things every action uses: `check( $capability, $nonce_action )` (runs `check_ajax_referer()` then `current_user_can()`, and short-circuits with `wp_send_json_error()` on failure) and a set of `post_*()` input helpers (`post_int`, `post_string`, `post_key`, `post_email`, `post_html`, `post_bool`, `post_json_array`, `post_page`, `post_per_page`).

### SettingsAjaxController — nonce `inboxai_settings`, capability `MANAGE_SETTINGS` on every action

| `wp_ajax_` action | Method | Notes |
|---|---|---|
| `inboxai_get_settings` | `get_settings()` | Read-only |
| `inboxai_save_settings` | `save_settings()` | Takes a JSON-encoded `values` blob (`post_json_array`) |
| `inboxai_test_connection` | `test_connection()` | Calls out to the configured AI provider |
| `inboxai_test_inbound_connection` | `test_inbound_connection()` | Calls out to the IMAP/inbound mailbox |
| `inboxai_test_slack` | `test_slack()` | Calls out to a Slack webhook |
| `inboxai_list_models` | `list_models()` | Calls out to the configured AI provider |
| `inboxai_flamingo_detect` | `flamingo_detect()` | Read-only detection |
| `inboxai_flamingo_import_batch` | `flamingo_import_batch()` | One batch of an in-progress import |
| `inboxai_flamingo_upload_csv` | `flamingo_upload_csv()` | **File upload** (`$_FILES`) |
| `inboxai_flamingo_import_csv_batch` | `flamingo_import_csv_batch()` | One batch of an in-progress import |
| `inboxai_native_csv_upload` | `native_csv_upload()` | **File upload** (`$_FILES`) |
| `inboxai_native_csv_import_batch` | `native_csv_import_batch()` | One batch of an in-progress import |
| `inboxai_add_category` | `add_category()` | Write |
| `inboxai_rename_category` | `rename_category()` | Write |
| `inboxai_delete_category` | `delete_category()` | Write |

### InboxAjaxController — nonce `inboxai_messages`

| `wp_ajax_` action | Method | Capability | Notes |
|---|---|---|---|
| `inboxai_list_messages` | `list_messages()` | `VIEW_MESSAGES` | Read-only, paginated + filtered |
| `inboxai_get_message` | `get_message()` | `VIEW_MESSAGES` | Returns pre-rendered HTML fragments alongside JSON (see below) |
| `inboxai_save_draft` | `save_draft()` | `EDIT_MESSAGES` | Write |
| `inboxai_send_reply` | `send_reply()` | `SEND_REPLIES` | Write, side effect: sends an email |
| `inboxai_mark_reviewed` | `mark_reviewed()` | `EDIT_MESSAGES` | Write |
| `inboxai_archive_message` | `archive_message()` | `EDIT_MESSAGES` | Write |
| `inboxai_delete_message` | `delete_message()` | `DELETE_MESSAGES` | Write (soft delete) |
| `inboxai_retry_analysis` | `retry_analysis()` | `EDIT_MESSAGES` | Returns pre-rendered HTML fragments too |
| `inboxai_bulk_action` | `bulk_action()` | `EDIT_MESSAGES` | Takes a JSON array of IDs (`post_json_array`) |

### ContactsAjaxController — nonce `inboxai_contacts`

| `wp_ajax_` action | Method | Capability |
|---|---|---|
| `inboxai_list_contacts` | `list_contacts()` | `VIEW_MESSAGES` |
| `inboxai_delete_contact` | `delete_contact()` | `DELETE_MESSAGES` |
| `inboxai_bulk_delete_contacts` | `bulk_delete_contacts()` | `DELETE_MESSAGES` |

## The one real design problem: HTML-fragment responses

`InboxAjaxController::get_message()` and `retry_analysis()` don't just return data — they return pre-rendered HTML via `Template::render_to_string()` for three partial templates (`inbox/detail-ai-body`, `inbox/detail-timeline`, `inbox/detail-mood-panel`) plus two badge strings from `Format`. That's a server-side-rendering pattern that doesn't map cleanly onto a JSON REST API, and it's the main reason a full rewrite isn't attractive: a "correct" REST version would return structured JSON and move that rendering into JS, which is a real frontend rewrite, not a mechanical route swap.

Recommendation: when these two endpoints are migrated, keep returning the rendered HTML fragments as string fields in the JSON response (`ai_card_html`, `timeline_html`, `mood_panel_html`, etc.), exactly as today. Don't try to "REST-ify" the response shape in the same pass as the transport change — that's a separate, much larger project with its own risk, and nothing requires doing it now.

## Mapping the shared patterns to REST

- `check( $capability, $nonce_action )` → a route's `permission_callback`, e.g. `fn() => current_user_can( Capabilities::VIEW_MESSAGES )`. The nonce itself is handled for free: any request to `/wp-json/` from an authenticated admin page already carries `X-WP-Nonce` (`wp_rest` action) if `wp_localize_script`/`wp_create_nonce( 'wp_rest' )` is wired up, so the per-controller nonce constants (`SETTINGS_NONCE_ACTION`, etc.) simply disappear — REST doesn't need a different nonce per page.
- `post_int()`, `post_string()`, `post_key()`, `post_email()`, `post_html()`, `post_bool()`, `post_json_array()` → each becomes an entry in a route's `args` schema (`sanitize_callback`, `validate_callback`, `type`), which WordPress runs automatically before the handler ever executes — this is a genuine improvement, not just a relocation, since invalid input is rejected before the handler runs instead of being silently defaulted.
- `post_page()` / `post_per_page()` → standard `page` / `per_page` query args on a `GET` list route (this also aligns with the WP REST convention already used by `/wp/v2/*` list endpoints).
- `wp_send_json_success( $data )` / `wp_send_json_error( $message, $status )` → `return new WP_REST_Response( $data, 200 )` / `return new WP_Error( 'inboxai_x', $message, array( 'status' => $status ) )`.
- File uploads (`flamingo_upload_csv`, `native_csv_upload`) → REST supports `multipart/form-data` and `$request->get_file_params()`, so this is possible, but WordPress's REST file-upload ergonomics are rougher than admin-ajax's `$_FILES` access. Leave these for last (see rollout order).

## Proposed route layout (`inboxai/v1`)

```
GET    /settings                       → get_settings
POST   /settings                       → save_settings
POST   /settings/test-connection       → test_connection
POST   /settings/test-inbound          → test_inbound_connection
POST   /settings/test-slack            → test_slack
GET    /settings/models                → list_models
GET    /settings/flamingo/detect       → flamingo_detect
POST   /settings/flamingo/import-batch → flamingo_import_batch
POST   /settings/flamingo/upload-csv   → flamingo_upload_csv        (multipart)
POST   /settings/flamingo/import-csv-batch → flamingo_import_csv_batch
POST   /settings/csv/upload            → native_csv_upload          (multipart)
POST   /settings/csv/import-batch      → native_csv_import_batch
POST   /settings/categories            → add_category
PUT    /settings/categories/{slug}     → rename_category
DELETE /settings/categories/{slug}     → delete_category

GET    /messages                       → list_messages
GET    /messages/{id}                  → get_message
POST   /messages/{id}/draft            → save_draft
POST   /messages/{id}/reply            → send_reply
POST   /messages/{id}/reviewed         → mark_reviewed
POST   /messages/{id}/archive          → archive_message
DELETE /messages/{id}                  → delete_message
POST   /messages/{id}/retry            → retry_analysis
POST   /messages/bulk                  → bulk_action

GET    /contacts                       → list_contacts
DELETE /contacts/{id}                  → delete_contact
POST   /contacts/bulk-delete           → bulk_delete_contacts
```

Every write route (`POST`/`PUT`/`DELETE`) keeps its existing capability check as the `permission_callback`; `id`/`slug` become URL path params validated via each route's `args` (e.g. `'id' => array('type' => 'integer', 'validate_callback' => fn($v) => MessageRepository::find($v) !== null)`), replacing today's `post_int( 'id' )` + manual "not found" check inside the method body.

## Rollout order (incremental, not a rewrite)

Doing all 27 at once isn't necessary and isn't recommended — the two systems can coexist indefinitely (nothing about registering `inboxai/v1` routes requires removing the `wp_ajax_*` ones), so this can move in small, low-risk batches whenever there's room in a release, without blocking anything else on the release plan.

1. **Read-only list/detail routes first**: `list_messages`, `get_message`, `list_contacts`, `get_settings`, `list_models`, `flamingo_detect`. No side effects, easiest to verify, and immediately useful if any JS ever needs to poll or refresh data.
2. **Simple writes with no file handling**: `save_draft`, `mark_reviewed`, `archive_message`, `delete_message`, `bulk_action`, `delete_contact`, `bulk_delete_contacts`, `add_category`, `rename_category`, `delete_category`, `save_settings`.
3. **External-call writes** (slower, need the same timeout/error handling already built in Release 3): `test_connection`, `test_inbound_connection`, `test_slack`, `send_reply`, `retry_analysis`.
4. **File uploads last**: `flamingo_upload_csv`, `native_csv_upload`, and their paired `*_import_batch`/`*_import_csv_batch` routes — REST's multipart handling needs its own testing pass, and there's no urgency since these run rarely (one-time imports).

Each batch is: register the new route(s) pointing at the *same* underlying logic the AJAX method already calls (extract shared logic into a private/shared method where the AJAX method currently does everything inline), update the relevant admin JS to call `wp.apiFetch` (or `fetch` with `X-WP-Nonce`) against the new route, verify in the browser, and only then optionally leave the old `wp_ajax_*` registration in place as a fallback or remove it — removal isn't required.

## What NOT to do

Don't rewrite `get_message()`/`retry_analysis()` to return structured JSON instead of HTML fragments as part of this migration — that's a frontend templating change, unrelated to the transport layer, and should be its own separate task if it's ever wanted.
