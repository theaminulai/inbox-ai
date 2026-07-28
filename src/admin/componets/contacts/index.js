/**
 * Contacts List page module.
 *
 * Lazily loaded by `src/admin/index.js` once it sees `data-page="contacts"`
 * on the page shell (`includes/Templates/contacts-list.php`). Unlike the AI
 * Inbox List, there's only ever one screen here (no list/detail split), so
 * this just wires up that one screen — see `componets/inbox/index.js` for
 * the analogous two-screen case.
 */

import { initListScreen } from './list.js';
import { initRowMenuGlobalClose } from '../shared/rowMenu.js';

export function initContactsPage() {
	initRowMenuGlobalClose();

	initListScreen();
}
