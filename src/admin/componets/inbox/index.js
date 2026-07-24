/**
 * AI Inbox List page module.
 *
 * Lazily loaded by `src/admin/index.js` once it sees `data-page="inbox"` on
 * the page shell (`includes/Templates/inbox.php`). List and detail are two
 * separate, fully server-rendered page loads now (see
 * `InboxListPage::render()`) rather than one page with three client-toggled
 * screens, so there's no screen-switching logic left here — this just wires
 * up whichever one screen actually rendered. `initListScreen()`/
 * `initDetailScreen()` each already guard on their own screen's root element
 * existing, so it's safe to call both unconditionally.
 */

import { initListScreen } from './list.js';
import { initDetailScreen } from './detail.js';
import { initModalClose } from '../shared/modal.js';
import { initRowMenuGlobalClose } from '../shared/rowMenu.js';

export function initInboxPage() {
	initModalClose( document );
	initRowMenuGlobalClose();

	initListScreen();
	initDetailScreen();
}
