/**
 * AI Inbox List page — submission detail screen (`#screen-detail`).
 *
 * Everything shown here (the conversation thread — original message, AI
 * analysis or the "analysis failed" error item, any already-sent reply —
 * plus the sidebar's customer/submission/activity/quick-actions panels) is
 * rendered server-side by `includes/Templates/inbox/detail.php` — this is a
 * real page load per submission (`?page=inboxai-inbox&id=123`), not a
 * client-side screen populated from an AJAX call. This file wires up: the
 * thread-item/sidebar-panel collapse toggles and the composer's collapsed/
 * open toggle (pure client-side UI state, no server round trip); the
 * sidebar's Quick Actions and the failed-analysis card's "Mark Reviewed",
 * which reload the page on success since they change server-side state the
 * whole page needs to re-render; and the three retry/regenerate-analysis
 * triggers, which do NOT reload — see `wireRegeneratingAction()`'s own
 * docblock for why a reload doesn't actually work for those (the AI call
 * runs on a later WP-Cron request, not inline) and how the result gets
 * patched into the page in place once it's actually ready.
 */

import { retryAnalysis, markReviewed, archiveMessage, deleteMessage, getMessage } from './api.js';
import { showToast } from '../shared/toast.js';
import { initReplyComposer } from './replyComposer.js';

/**
 * How often to poll while waiting for a retried/regenerated analysis to
 * actually finish, and how long to keep trying before giving up — see
 * {@see wireRegeneratingAction}.
 */
const POLL_INTERVAL_MS = 2500;
const POLL_MAX_ATTEMPTS = 24; // ~60s — generous for a real AI provider round trip.

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

/**
 * Swaps the finished result into the page in place — no reload. Called once
 * {@see wireRegeneratingAction}'s polling sees the analysis has actually
 * finished. Every piece here is markup the server already rendered (via
 * {@see getMessage}'s `ai_card_html`/`timeline_html`/badges — see
 * `InboxAjaxController::get_message()`), not reconstructed in JS, so it's
 * guaranteed to look exactly like a real page load would.
 *
 * @param {Object}  data
 * @param {Object}  data.message
 * @param {string}  data.ai_card_html
 * @param {string}  data.timeline_html
 * @param {string}  data.priority_badge
 * @param {string}  data.status_badge
 * @param {string}  data.ai_time_ago
 * @param {boolean} updateComposer Also refresh the Reply Composer's
 *                                 "Drafted By"/Subject/Message fields from
 *                                 the new draft — only for the "Regenerate
 *                                 reply" button, since the other two
 *                                 triggers aren't about the reply draft and
 *                                 shouldn't overwrite whatever's already in
 *                                 the composer.
 *
 * @return {void}
 */
function applyRegeneratedResult( data, updateComposer ) {
	const aiBody = el( 'detail-ai-body' );

	if ( aiBody ) {
		aiBody.innerHTML = data.ai_card_html;
	}

	const timeline = el( 'detail-timeline' );

	if ( timeline ) {
		timeline.innerHTML = data.timeline_html;
	}

	const priorityBadge = el( 'detail-priority-badge' );

	if ( priorityBadge ) {
		priorityBadge.innerHTML = data.priority_badge;
	}

	const statusBadge = el( 'detail-status-badge' );

	if ( statusBadge ) {
		statusBadge.innerHTML = data.status_badge;
	}

	const aiTimestamp = el( 'detail-ai-timestamp' );

	if ( aiTimestamp ) {
		aiTimestamp.textContent = data.ai_time_ago;
	}

	if ( updateComposer ) {
		const providerInfo = el( 'detail-provider-info' );
		const subject = el( 'detail-subject' );
		const body = el( 'detail-reply-body' );
		const message = data.message;

		if ( providerInfo ) {
			providerInfo.value = message.ai_provider
				? message.ai_provider + ' · ' + message.ai_model
				: 'Not yet drafted';
		}

		if ( subject && message.reply_subject ) {
			subject.value = message.reply_subject;
		}

		if ( body && message.reply_draft ) {
			body.innerText = message.reply_draft;
		}
	}

	// `ai_card_html` just replaced the whole AI card, including whichever of
	// these buttons was in it — their old click listeners are gone with the
	// old elements. Re-wire whichever one now actually exists (success
	// renders "Regenerate analysis"; failure renders "Retry"/"Mark
	// Reviewed" instead); each wiring function already no-ops if its id
	// isn't present, so it's safe to call all three unconditionally.
	wireRegeneratingAction( 'detail-regenerate-analysis', 'Re-queuing AI analysis…', data.message.id, false, data.activities.length );
	wireRegeneratingAction( 'detail-retry-btn', 'Retrying analysis…', data.message.id, false, data.activities.length );
	wireReloadingAction( 'detail-manual-btn', () => markReviewed( data.message.id ) );
}

/**
 * Wires a retry/regenerate button to `retryAnalysis()` — but, unlike
 * {@see wireReloadingAction}, does NOT reload the page once that call
 * resolves, and does NOT reload the page once the analysis itself finishes
 * either. `inboxai_retry_analysis` only re-queues a WP-Cron job (see
 * {@see \InboxAI\AI\AnalysisQueue::enqueue()}) — the actual AI provider call
 * happens later, on a separate request — so this polls {@see getMessage}
 * every few seconds until a new activity timeline entry actually shows up
 * (`ai_analysis_completed` or `ai_analysis_failed` — re-queuing itself
 * already logs one `retry_requested` entry synchronously, so "done" means
 * the count has grown by two, not one) and then patches the finished result
 * into the page via {@see applyRegeneratedResult} instead of reloading.
 *
 * @param {string}  id
 * @param {string}  pendingMessage
 * @param {number}  messageId
 * @param {boolean} updateComposer         See {@see applyRegeneratedResult}.
 * @param {number}  baselineActivityCount
 *
 * @return {void}
 */
function wireRegeneratingAction( id, pendingMessage, messageId, updateComposer, baselineActivityCount ) {
	const btn = el( id );

	if ( ! btn ) {
		return;
	}

	// The composer's "Regenerate reply with AI" toolbar button is icon-only
	// (26x26px, no room for a text label) — swapping its label like the two
	// real text buttons would blow out the button and hide the icon
	// entirely. It gets a spinning-icon treatment instead (see
	// `inbox/_detail.scss`'s `.inboxai-is-loading`).
	const iconOnly = btn.classList.contains( 'inboxai-composer-toolbar__btn' );
	const originalLabel = btn.textContent;

	function setPending( pending ) {
		btn.disabled = pending;

		if ( iconOnly ) {
			btn.classList.toggle( 'inboxai-is-loading', pending );
		} else {
			btn.textContent = pending ? 'Analyzing…' : originalLabel;
		}
	}

	function poll( attempt ) {
		getMessage( messageId )
			.then( ( data ) => {
				if ( data.activities.length >= baselineActivityCount + 2 ) {
					setPending( false );
					applyRegeneratedResult( data, updateComposer );
					showToast(
						'failed' === data.message.workflow_status
							? 'Analysis failed — see the details below.'
							: 'Analysis updated.',
						'failed' === data.message.workflow_status ? 'error' : 'success'
					);
					return;
				}

				if ( attempt >= POLL_MAX_ATTEMPTS ) {
					showToast(
						"This is taking longer than usual — it's still running in the background. Check back in a moment.",
						'error'
					);
					setPending( false );
					return;
				}

				setTimeout( () => poll( attempt + 1 ), POLL_INTERVAL_MS );
			} )
			.catch( ( err ) => {
				showToast( err.message, 'error' );
				setPending( false );
			} );
	}

	btn.addEventListener( 'click', () => {
		setPending( true );
		showToast( pendingMessage );

		retryAnalysis( messageId )
			.then( () => setTimeout( () => poll( 1 ), POLL_INTERVAL_MS ) )
			.catch( ( err ) => {
				showToast( err.message, 'error' );
				setPending( false );
			} );
	} );
}

/**
 * Every `.inboxai-thread-item__head` click collapses/expands that one
 * thread item (see `inbox/_detail.scss`'s `.inboxai-is-collapsed`
 * modifier) — delegated from the thread container since items are a fixed,
 * server-rendered set (no items are ever added/removed client-side).
 *
 * @return {void}
 */
function initThreadToggles() {
	const thread = el( 'detail-thread' );

	if ( ! thread ) {
		return;
	}

	thread.addEventListener( 'click', ( e ) => {
		const head = e.target.closest( '[data-toggle-thread-item]' );

		if ( ! head ) {
			return;
		}

		head.closest( '.inboxai-thread-item' ).classList.toggle( 'inboxai-is-collapsed' );
	} );
}

/**
 * Every sidebar `.inboxai-detail-panel__head` click collapses/expands that
 * one panel — delegated from `#screen-detail` since the panels live in a
 * few different places (Customer/Submission details/Activity/Quick actions).
 *
 * @return {void}
 */
function initPanelToggles( screen ) {
	screen.addEventListener( 'click', ( e ) => {
		const head = e.target.closest( '[data-toggle-panel]' );

		if ( ! head ) {
			return;
		}

		head.closest( '.inboxai-detail-panel' ).classList.toggle( 'inboxai-is-collapsed' );
	} );
}

/**
 * The Reply composer's Gmail-style "Reply to X…" collapsed state — clicking
 * it swaps in the full composer (recipient/subject/message fields) and
 * focuses the message body. There's no matching "collapse" control: once
 * open, it stays open for the rest of the page view, same as Gmail's own
 * inline reply.
 *
 * @return {void}
 */
function initComposerToggle() {
	const composer = el( 'detail-composer' );
	const collapsed = el( 'detail-composer-collapsed' );

	if ( ! composer || ! collapsed ) {
		return;
	}

	collapsed.addEventListener( 'click', () => {
		composer.classList.add( 'inboxai-is-open' );

		const body = el( 'detail-reply-body' );

		if ( body ) {
			body.focus();
		}
	} );
}

export function initDetailScreen() {
	const screen = el( 'screen-detail' );

	if ( ! screen ) {
		return;
	}

	const messageId = parseInt( screen.dataset.messageId, 10 );
	const baselineActivityCount = parseInt( screen.dataset.activityCount, 10 ) || 0;

	wireRegeneratingAction( 'detail-regenerate-analysis', 'Re-queuing AI analysis…', messageId, false, baselineActivityCount );
	wireRegeneratingAction( 'detail-regenerate-reply', 'Re-queuing AI analysis to regenerate this reply…', messageId, true, baselineActivityCount );
	wireRegeneratingAction( 'detail-retry-btn', 'Retrying analysis…', messageId, false, baselineActivityCount );
	wireReloadingAction( 'detail-manual-btn', () => markReviewed( messageId ) );

	// Sidebar Quick Actions — the same per-row actions the AI Inbox List's
	// own row menu offers (see `componets/inbox/list.js`), reused here
	// rather than a separate code path.
	wireReloadingAction( 'detail-quick-reviewed', () => markReviewed( messageId ) );
	wireReloadingAction( 'detail-quick-archive', () => archiveMessage( messageId ) );

	const deleteBtn = el( 'detail-quick-delete' );

	if ( deleteBtn ) {
		deleteBtn.addEventListener( 'click', () => {
			// eslint-disable-next-line no-alert -- matches the AI Inbox
			// List's own single-row delete confirmation; this plugin has no
			// dedicated confirm modal outside the reply composer's.
			if ( ! window.confirm( 'Delete this submission? This cannot be undone from the Inbox.' ) ) {
				return;
			}

			deleteBtn.disabled = true;

			deleteMessage( messageId )
				.then( () => {
					// There's no detail screen left to reload once this row
					// is deleted — go back to the list instead.
					window.location.href = window.location.pathname + '?page=inboxai-inbox';
				} )
				.catch( ( err ) => {
					showToast( err.message, 'error' );
					deleteBtn.disabled = false;
				} );
		} );
	}

	initThreadToggles();
	initPanelToggles( screen );
	initComposerToggle();
	initReplyComposer();
}
