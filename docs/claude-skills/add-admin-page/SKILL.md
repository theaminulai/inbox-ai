---
name: "add-admin-page"
description: "Add a brand-new admin page to Inbox AI (e.g. the planned Overview, Analytics, or Campaigns pages, or anything new). Use whenever a request needs a new top-level or submenu screen under the plugin, not just a new field on an existing Settings tab."
---

Only three admin pages are currently built: AI Inbox List, Contacts List, Settings (`Admin\Menu::PAGES`). `docs/plans/*.md` describes Overview, Analytics, and Campaigns pages in detail, but none of them have any code yet — building one means creating every layer below from scratch, following the AI Inbox List page as the reference implementation (`Admin\Pages\InboxListPage.php`, `Admin\Ajax\InboxAjaxController.php`, `includes/Templates/inbox/*.php`, `src/admin/componets/inbox/*.js`).

1. **Register the page** — add an entry to `Admin\Menu::PAGES` (`slug => [menu_title, page_title, capability, page_class]`). Pages are CF7 submenus, not a separate top-level menu. Pick or add a `Security\Capabilities` constant for gating (e.g. the already-defined-but-unused `VIEW_ANALYTICS` is exactly for the Analytics page).

2. **Page class** (`Admin\Pages\XPage.php`) — a `render()` method that calls `Support\Template::render( 'x/x', $view_model )`. If the page needs its own AJAX nonce, hook `inboxai_localize_data` in the **constructor** (not `init()`), checking the passed `$slug` before adding data — this is the only way any page adds to the shared localized JS object; there's exactly one `wp_localize_script()` call in the whole plugin (inside `Menu::enqueue_assets()`).

3. **AJAX controller** (`Admin\Ajax\XAjaxController.php`) — extend `BaseAjaxController`. Every action method starts with `$this->check( Capability, nonce_action )`, then reads input via `post_int()`/`post_string()`/etc., never raw `$_POST`. One controller per page — don't add actions to an existing controller for a different page, and don't resurrect a single monolithic controller (that was already tried and deliberately split apart).

4. **Templates** (`includes/Templates/x/*.php`) — plain PHP, no templating engine; `Template::render()` `extract()`s the view model into local scope, so escape at output time yourself. For any AJAX action that needs to return updated HTML (not just JSON data), render the same template server-side via `Template::render_to_string()` so the initial page load and the AJAX update use identical markup — don't hand-build equivalent HTML in JS.

5. **List views with real data** — do pagination/filtering in SQL (a new `XRepository` method, or additions to an existing repository), never by loading a full table into PHP/JS and slicing client-side. The AI Inbox List and Contacts List both render fully server-side on first load; JS only adds interactivity on top.

6. **Frontend** — no new webpack entry point. Add `src/admin/componets/x/*.js` and `src/admin/scss/x/*.scss` following the existing per-page folder convention, import/`@use` them from the shared `src/admin/index.js`/`index.scss` — everything still compiles into the one `build/admin/admin.{js,css}` bundle (code-splitting is intentionally disabled). Run `npm run build` after.

Before building Overview or Analytics specifically, check their plan docs for repository methods that don't exist yet (e.g. Overview needs aggregate methods like message counts by status/priority that `MessageRepository` doesn't currently have) — add those to the relevant repository rather than querying `$wpdb` directly from the page/AJAX layer.
