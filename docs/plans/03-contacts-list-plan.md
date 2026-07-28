# End-to-End Plan: Contacts List Page (`inboxai-contacts`, `html/contacts.html`)

**Note:** the shared admin-page architecture referenced throughout this plan (Menu-centralized enqueuing, the `inboxai_inbox_localize_data` filter, the JS loader/SCSS folder conventions) was established while building the Settings page — see `docs/plans/05-settings-plan.md` §10 for the full explanation and a code example. This plan has been updated to match; sections below now describe that real architecture instead of the original per-page-enqueue assumption.

Standalone build plan for the third of five admin pages. This page has no data of its own — it's a grouped view over the same `inboxai_messages` rows Plan 2 (AI Inbox List) captures, aggregated by sender email.

## 1. Mockup inventory (source of truth: `html/contacts.html` + `assets/js/contacts.js`)

Page header: title, subtitle, an Export button. One card: a toolbar (free-text search + category filter + priority filter), an 8-column table (Contact, Email, Category, Priority, Messages, Replied, Last Contact, actions), paginated 5 per page, with a "no contacts match your filters" empty state. Row actions live in a "more" menu: "View messages" (jumps to AI Inbox List filtered by that email) and "Delete contact".

Current JS (`contacts.js`): `contactsFromMessages()` groups the mock `messages` array by `email`, taking the most recent message's name/initials/color/category/priority/received and computing `count`/`replied`; `filteredContacts()` applies search/category/priority on top of that; deletion is currently just an in-memory `Set` of hidden emails (`state.deletedContacts`) — this is the one behavior that needs a real decision before going further (section 3).

## 2. Data model

No new table. Every field comes from `{prefix}inboxai_messages`, grouped by `sender_email`:

```sql
SELECT
  sender_email,
  -- most-recent-row values, via a join or a window function
  ANY_VALUE(sender_name)  AS name,
  COUNT(*)                AS message_count,
  SUM(workflow_status = 'replied') AS replied_count,
  MAX(created_at)         AS last_contact
FROM {prefix}inboxai_messages
WHERE deleted_at IS NULL
GROUP BY sender_email
```

("Category" and "priority" shown per contact are the most recent message's values, matching the mockup's derivation exactly — not a separate aggregate.)

## 3. Design decision: what "Delete contact" means

There is deliberately no dedicated `inboxai_contacts` table (R&D §6 marks it optional for v1, and this plan follows that call). That means "delete" can't mean "remove a contact record" — there isn't one. Two real options:

- **(a) Archive-by-email** — set `workflow_status = 'archived'` on every message from that sender. Reuses Plan 2's existing archive logic and capability (`inboxai_delete_messages`), no new destructive code path.
- **(b) No delete for v1** — ship only "View messages," drop the delete action from this page's menu until there's a real reason to add one.

**Recommendation: (a).** It gives the button real, sensible behavior without inventing new deletion semantics or a new capability, and it's reversible (an admin can still find and un-archive individual messages from AI Inbox List). This plan proceeds with (a); flag to the project owner if (b) is preferred instead — it only removes one menu item and one endpoint, everything else in this plan is unaffected.

## 4. Backend components to build

- `includes/Database/MessageRepository.php` (shared with Plan 2, extended here):
  - `get_contacts( array $filters, int $page, int $per_page ): array{items, total}` — the aggregate query above, with `category`/`priority`/`search` (name or email) filters applied identically to `contacts.js`'s `filteredContacts()`.
  - `archive_by_email( string $email ): int` (returns rows affected) — powers the "Delete contact" decision above.
- `includes/Admin/AjaxController.php` (shared with Plans 1, 2, and 4 — one controller class for every admin-page AJAX action) — three new actions:

| Action | Capability | Notes |
|---|---|---|
| `inboxai_list_contacts` | `inboxai_view_messages` | filters + pagination |
| `inboxai_delete_contact` | `inboxai_delete_messages` | calls `archive_by_email()` per section 3 |
| `inboxai_export_contacts` | `inboxai_export_messages` | mirrors `exportContactsCsv()` |

- No AI, no queue, no new activity event types beyond what Plan 2 already logs (an `archive_by_email` action can reuse Plan 2's existing per-message `status_changed` activity insert, looped, or a single summary event — either is fine since this isn't surfaced anywhere granular).
- `includes/Admin/Pages/ContactsListPage.php` — same thin shape as Plans 1 and 2: capability check, assemble the contacts view model, call `Support\Template::render( 'contacts-list', $view_model )` — markup in `includes/Templates/contacts-list.php`, ported near-verbatim from `html/contacts.html`. **No enqueue call**: `Menu.php` enqueues the shared bundle for every registered page; this class's constructor instead hooks `inboxai_inbox_localize_data`, checking `$slug === 'inboxai-contacts'`, to add its own nonce — see `docs/plans/05-settings-plan.md` §10.

## 5. Frontend build plan (`src/admin/componets/contacts/`)

All of it compiles into the one shared `build/admin.js`/`build/admin.css` bundle, not a separate bundle per page (webpack.config.js disables code-splitting — see `docs/plans/05-settings-plan.md` §10); this page's `index.js` exports `initContactsPage()` and is added as one entry to the `loaders` map in the shared `src/admin/index.js`, keyed by `data-page="contacts"`.

- `api.js` — shared `fetch()`-to-`admin-ajax.php` wrapper (same as Plans 1 and 2), reading from `window.inboxaiInboxAdmin` rather than a page-specific global.
- `list.js` — replaces `contactsFromMessages()` + `filteredContacts()` + `renderContacts()`'s client-side grouping with a `inboxai_list_contacts` call keyed by `state.contactsFilters`/`state.contactsPage` — the grouping itself now happens in `get_contacts()` (section 4), not in the browser.
- Table rows reuse the shared `pagination.js`/`rowMenu.js`/badge modules from Plan 2's `src/admin/componets/shared/` rather than duplicating them; the two empty states ("no contacts match your filters" vs. "no contacts yet," section 7) toggle via the same class-based approach already used elsewhere.
- "View messages" row action stays a plain `<a href="inbox.html?search=...">`-equivalent link, now pointed at the real AI Inbox List admin URL with the same `search` query arg (`Menu::url( 'inboxai-inbox' ) . '&search=' . rawurlencode( $email )`), rendered server-side in `contacts-list.php`.
- `state.deletedContacts` (the client-side `Set`) goes away entirely — "Delete contact" calls `inboxai_delete_contact` via `api.js`, then re-fetches the current page of contacts.
- `exportContactsCsv()` → same client-vs-server tradeoff as Plan 2's export; recommend the same choice made there for consistency.
- Styling: reuse `src/admin/scss/common/`'s shared partials; this page's own rules go in a new `src/admin/scss/contacts/` folder, `@use`'d from `src/admin/scss/index.scss`. Tests: same Jest approach as Plans 1 and 2 in principle — **not actually set up anywhere yet** (`src/tests/index.js` is an empty stub, no `test-unit-js` script exists); Settings shipped without it too.

## 6. Security

- `inboxai_list_contacts` requires `inboxai_view_messages`, nonce-checked, read-only.
- `inboxai_delete_contact` requires `inboxai_delete_messages` (a stronger capability than viewing), nonce-checked — this is the one write path on this page and deserves the same defense-in-depth capability re-check as every write action in Plan 2.
- Search filter uses `$wpdb->prepare()` + `esc_like()`, same as Plan 2's message search.
- No AI, no external requests, no encrypted data on this page — smallest security surface of the five.

## 7. Edge cases

- Zero messages captured yet → contacts list is legitimately empty; this should read as "no contacts yet," distinct from "no contacts match your filters" (the mockup only has the filtered-empty copy — needs a second empty-state message for the true-zero case, or reuse of AI Inbox List's "no submissions yet" pattern).
- A sender who submitted once, then had that message archived via option (a) above → contact should disappear from the default list (matches the SQL's `deleted_at IS NULL` / could also exclude fully-archived contacts depending on whether "archived" should still count as a contact — recommend still showing them, since archiving isn't deletion, unless product direction says otherwise).
- Same email, different display name across submissions (e.g. a typo corrected on a later submit) → aggregate query's `ANY_VALUE`/most-recent-row choice needs to be deterministic (use the row with `MAX(created_at)`, not an arbitrary `ANY_VALUE`, to avoid flapping between page loads).

## 8. Testing checklist

- Multiple messages from the same email → collapse into one contact row with correct `message_count`/`replied_count`/`last_contact`.
- Search by partial name and by partial email both work.
- Category/priority filters match a direct SQL check against the most-recent-message values.
- "View messages" link lands on AI Inbox List pre-filtered to exactly that sender's messages.
- Delete contact archives every message from that sender (verify in AI Inbox List, not just that the contact disappears here) and is blocked for a user without `inboxai_delete_messages`.
- Pagination math matches the same 5-per-page behavior already proven in Plan 2.
- Jest tests for `list.js`'s grouping/filtering logic and the delete flow pass.

## 9. Step-by-step build order

1. Confirm Plan 2 is capturing real messages — this page has nothing to show otherwise and should not be built/tested against mock data.
2. `get_contacts()` aggregate query, tested directly against seeded rows for correctness (counts, most-recent-row selection, filters).
3. `inboxai_list_contacts` action.
4. Confirm the Delete decision (section 3) with the project owner if not already settled; implement `archive_by_email()` + `inboxai_delete_contact` accordingly.
5. `inboxai_export_contacts` action (or keep client-side, per section 5).
6. Build the modules in section 5 against real data, reusing Plan 2's shared modules (`pagination.js`, `rowMenu.js`, badges) rather than re-implementing them.
7. Add `'inboxai-contacts' => array( 'Contacts List', 'Contacts List', Capabilities::VIEW_MESSAGES, ContactsListPage::class )` to `Menu::PAGES` — no iframe fallback exists to replace (removed entirely; see `docs/plans/05-settings-plan.md` §10). `Menu.php` automatically enqueues the shared bundle; `ContactsListPage`'s constructor just needs to hook `inboxai_inbox_localize_data` for its nonce.
8. Run the full testing checklist. (No Jest suite exists yet in this codebase, so this is manual verification, same as Settings.)

## 10. Explicit dependencies

- **Hard dependency on Plan 2** — this page is purely derived data; it cannot be meaningfully built or tested until AI Inbox List is capturing real messages.
- **No dependency on Plans 1, 4, or 5.**
