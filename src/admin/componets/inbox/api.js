/**
 * Thin wrappers around the shared `cf7aiAjax()` helper for every
 * `wp_ajax_cf7ai_*` action {@see \CF7AIInbox\Admin\AjaxController} still
 * calls from the client. The list and detail screens are server-rendered
 * now (see `InboxListPage::render()`), so this is only the handful of
 * actions that are genuinely background/interactive work: `list.js`'s CSV
 * export (still fetches a large, unpaginated slice via `cf7ai_list_messages`
 * rather than a dedicated export endpoint) and every row/composer action
 * that changes a message's state (mark reviewed, archive, delete, retry,
 * save draft, send reply) from `list.js`/`detail.js`/`replyComposer.js`.
 * `cf7ai_get_message` has no wrapper here anymore — nothing on the client
 * fetches a single message's data over AJAX now that the detail screen's
 * initial render already has it.
 */

import { cf7aiAjax } from '../shared/api.js';

/**
 * @param {Object} filters  `status`, `priority`, `category`, `form`,
 *                           `confidence_below`, `search`.
 * @param {number} page
 * @param {number} perPage
 * @return {Promise<{items:Array<Object>, total:number}>}
 */
export function listMessages( filters, page, perPage ) {
	return cf7aiAjax( 'cf7ai_list_messages', {
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
	return cf7aiAjax( 'cf7ai_save_draft', { id, subject, body } );
}

/**
 * @param {number} id
 * @param {string} subject
 * @param {string} body
 * @return {Promise<Object>}
 */
export function sendReply( id, subject, body ) {
	return cf7aiAjax( 'cf7ai_send_reply', { id, subject, body } );
}

/**
 * @param {number} id
 * @return {Promise<Object>}
 */
export function markReviewed( id ) {
	return cf7aiAjax( 'cf7ai_mark_reviewed', { id } );
}

/**
 * @param {number} id
 * @return {Promise<Object>}
 */
export function archiveMessage( id ) {
	return cf7aiAjax( 'cf7ai_archive_message', { id } );
}

/**
 * @param {number} id
 * @return {Promise<Object>}
 */
export function deleteMessage( id ) {
	return cf7aiAjax( 'cf7ai_delete_message', { id } );
}

/**
 * @param {number} id
 * @return {Promise<Object>}
 */
export function retryAnalysis( id ) {
	return cf7aiAjax( 'cf7ai_retry_analysis', { id } );
}
