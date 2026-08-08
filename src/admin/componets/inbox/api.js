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
 * `getMessage()` fetches one message's current data/timeline on demand; it's
 * no longer needed to poll for a retry/regenerate result, since
 * `retryAnalysis()` now runs that AI call synchronously and returns the
 * finished result directly (see `InboxAjaxController::retry_analysis()`'s
 * own docblock).
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

/**
 * One message's current data, including its activity timeline (see
 * {@see \InboxAI\Admin\Ajax\InboxAjaxController::get_message()}). Not
 * currently called from this screen — kept as a general-purpose fetch for
 * future use.
 *
 * @param {number} id
 * @return {Promise<{message:Object, activities:Array<Object>}>}
 */
export function getMessage( id ) {
	return inboxaiAjax( 'inboxai_get_message', { id } );
}

/**
 * @param {Array<number>} ids
 * @param {string}        action `reviewed`, `archive`, or `delete`.
 * @return {Promise<Object>}
 */
export function bulkAction( ids, action ) {
	return inboxaiAjax( 'inboxai_bulk_action', {
		ids: JSON.stringify( ids ),
		bulk_action: action,
	} );
}
