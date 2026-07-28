/**
 * Thin wrappers around the shared `inboxaiAjax()` helper for the two
 * `wp_ajax_inboxai_*` actions {@see \InboxAI\Admin\AjaxController} exposes
 * for the Contacts List screen. The list itself is server-rendered (see
 * `ContactsListPage::render()`), so `listContacts()` is only used by this
 * page's CSV export (a large, unpaginated slice, same as the AI Inbox
 * List's own export — see `componets/inbox/api.js`'s `listMessages()`).
 */

import { inboxaiAjax } from '../shared/api.js';

/**
 * @param {Object} filters `category`, `priority`, `search`.
 * @param {number} page
 * @param {number} perPage
 * @return {Promise<{items:Array<Object>, total:number}>}
 */
export function listContacts( filters, page, perPage ) {
	return inboxaiAjax( 'inboxai_list_contacts', {
		...filters,
		page,
		per_page: perPage,
	} );
}

/**
 * @param {string} email
 * @return {Promise<Object>}
 */
export function deleteContact( email ) {
	return inboxaiAjax( 'inboxai_delete_contact', { email } );
}
