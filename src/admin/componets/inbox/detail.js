/**
 * AI Inbox List page — submission detail screen (`#screen-detail`).
 *
 * Everything shown here (customer info, AI analysis or the "analysis
 * failed" error card, submitted fields, reply composer values, activity
 * timeline) is rendered server-side by `includes/Templates/inbox-detail.php`
 * — this is a real page load per submission (`?page=inboxai-inbox&id=123`),
 * not a client-side screen populated from an AJAX call. This file only
 * wires up the buttons that trigger a background action and then reload the
 * page so the server-rendered content reflects the result: retry analysis,
 * regenerate analysis/reply, and (on the failed-analysis error card) mark
 * reviewed.
 */

import { retryAnalysis, markReviewed } from './api.js';
import { showToast } from '../shared/toast.js';
import { initReplyComposer } from './replyComposer.js';

function el( id ) {
	return document.getElementById( id );
}

/**
 * Wires one button to an AJAX call that reloads the page on success —
 * shared by every action on this screen that changes server-side state the
 * page needs to re-render (as opposed to the reply composer's save-draft,
 * which doesn't need a reload since the composer's own fields already match
 * what was just saved).
 *
 * @param {string}          id
 * @param {() => Promise}   action
 * @param {string}          [pendingMessage]
 */
function wireReloadingAction( id, action, pendingMessage ) {
	const btn = el( id );

	if ( ! btn ) {
		return;
	}

	btn.addEventListener( 'click', () => {
		btn.disabled = true;

		if ( pendingMessage ) {
			showToast( pendingMessage );
		}

		action()
			.then( () => window.location.reload() )
			.catch( ( err ) => {
				showToast( err.message, 'error' );
				btn.disabled = false;
			} );
	} );
}

export function initDetailScreen() {
	const screen = el( 'screen-detail' );

	if ( ! screen ) {
		return;
	}

	const messageId = parseInt( screen.dataset.messageId, 10 );

	wireReloadingAction( 'detail-regenerate-analysis', () => retryAnalysis( messageId ), 'Re-queuing AI analysis…' );
	wireReloadingAction( 'detail-regenerate-reply', () => retryAnalysis( messageId ), 'Re-queuing AI analysis to regenerate this reply…' );
	wireReloadingAction( 'detail-retry-btn', () => retryAnalysis( messageId ), 'Retrying analysis…' );
	wireReloadingAction( 'detail-manual-btn', () => markReviewed( messageId ) );

	initReplyComposer();
}
