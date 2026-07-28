/**
 * Thin wrappers around the shared `inboxaiAjax()` helper for every
 * `wp_ajax_inboxai_*` action {@see \InboxAI\Admin\AjaxController} still
 * calls from the client. The list and detail screens are server-rendered
 * now (see `InboxListPage::render()`), so this is only the handful of
 * actions that are genuinely background/interactive work: `list.js`'s CSV
 * export (still fetches a large, unpaginated slice via `inboxai_list_messages`
 * rather than a dedicated export endpoint) and every row/composer action
 * that changes a message's state (mark reviewed, archive, delete, retry,
 * save draft, send reply) from `list.js`/`detail.js`/`replyComposer.js`.
 * `inboxai_get_message` has no wrapper here anymore — nothing on the client
 * fetches a single message's data over AJAX now that the detail screen's
 * initial render already has it.
 */

import { inboxaiAjax } from '../shared/api.js';

/**
 * @param {Object} filters  `status`, `priority`, `category`, `form`,
 *                           `confidence_below`, `search`.
 * @param {number} page
 * @param {number} perPage
 * @return {Promise<{items:Array<Object>, total:number}>}
 */
export function listMessages( filters, page, perPage ) {
	return inboxaiAjax( 'inboxai_list_messages', {
		...filters,
		page,
		per_page: perPage,
	} );
}

/**
 * @param {number} id
 * @param {string} subject
 * @param {string} body
 * @return {Promise<Object>}
 */
export function saveDraft( id, subject, body ) {
	return inboxaiAjax( 'inboxai_save_draft', { id, subject, body } );
}

/**
 * @param {number} id
 * @param {string} subject
 * @param {string} body
 * @return {Promise<Object>}
 */
export function sendReply( id, subject, body ) {
	return inboxaiAjax( 'inboxai_send_reply', { id, subject, body } );
}

/**
 * @param {number} id
 * @return {Promise<Object>}
 */
export function markReviewed( id ) {
	return inboxaiAjax( 'inboxai_mark_reviewed', { id } );
}

/**
 * @param {number} id
 * @return {Promise<Object>}
 */
export function archiveMessage( id ) {
	return inboxaiAjax( 'inboxai_archive_message', { id } );
}

/**
 * @param {number} id
 * @return {Promise<Object>}
 */
export function deleteMessage( id ) {
	return inboxaiAjax( 'inboxai_delete_message', { id } );
}

/**
 * @param {number} id
 * @return {Promise<Object>}
 */
export function retryAnalysis( id ) {
	return inboxaiAjax( 'inboxai_retry_analysis', { id } );
}
