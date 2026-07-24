/**
 * Submission Detail screen — Reply Composer card.
 *
 * Only rendered/wired at all when the current user holds `EDIT_MESSAGES`
 * (see `includes/Templates/inbox-detail.php`) — every function here already
 * guards on the composer's elements existing for that reason. The card's
 * fields (recipient, subject, draft body) are pre-filled by PHP at render
 * time now, not populated from an AJAX response — this file only reads the
 * message id/recipient email once, from `#screen-detail`'s own
 * `data-message-id`/`data-recipient-email` attributes, and wires up the
 * rich-text toolbar plus the save-draft/send-reply calls.
 */

import { saveDraft, sendReply } from './api.js';
import { openModal, closeModal } from '../shared/modal.js';
import { showToast } from '../shared/toast.js';

function el( id ) {
	return document.getElementById( id );
}

function applyFormat( cmd, value ) {
	const body = el( 'detail-reply-body' );

	if ( ! body ) {
		return;
	}

	body.focus();
	document.execCommand( cmd, false, value || null );
}

export function initReplyComposer() {
	const screen = el( 'screen-detail' );

	if ( ! screen ) {
		return;
	}

	const messageId = parseInt( screen.dataset.messageId, 10 );
	const recipientEmail = screen.dataset.recipientEmail || '';

	const saveBtn = el( 'detail-save-draft' );

	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', () => {
			const subject = el( 'detail-subject' ).value;
			const body = el( 'detail-reply-body' ).innerText;

			saveDraft( messageId, subject, body )
				.then( () => {
					const status = el( 'detail-draft-status' );

					if ( status ) {
						status.textContent = 'Draft saved just now';
					}

					showToast( 'Draft saved', 'success' );
				} )
				.catch( ( err ) => showToast( err.message, 'error' ) );
		} );
	}

	[
		[ 'fmt-bold', 'bold' ],
		[ 'fmt-italic', 'italic' ],
		[ 'fmt-underline', 'underline' ],
		[ 'fmt-list', 'insertUnorderedList' ],
	].forEach( ( [ id, cmd ] ) => {
		const btn = el( id );

		if ( ! btn ) {
			return;
		}

		btn.addEventListener( 'mousedown', ( e ) => e.preventDefault() );
		btn.addEventListener( 'click', () => applyFormat( cmd ) );
	} );

	const openReplyModalBtn = el( 'open-reply-modal' );

	if ( openReplyModalBtn ) {
		openReplyModalBtn.addEventListener( 'click', () => {
			const subject = el( 'detail-subject' ).value;
			const bodyText = el( 'detail-reply-body' ).innerText;

			el( 'modal-body-text' ).innerHTML =
				'This reply will be emailed to <b style="color:var(--text-primary);">' +
				recipientEmail +
				'</b> and the message status will change to <b style="color:var(--text-primary);">Replied</b>. This can\'t be undone.' +
				'<div class="cf7-ai-inbox-modal__preview" id="modal-preview-text"><b>Subject:</b> ' +
				subject +
				'<br><br>' +
				bodyText.slice( 0, 140 ) +
				'…</div>';

			openModal( 'reply-modal-overlay' );
		} );
	}

	const confirmSendBtn = el( 'modal-confirm-send' );

	if ( confirmSendBtn ) {
		confirmSendBtn.addEventListener( 'click', () => {
			const subject = el( 'detail-subject' ).value;
			const body = el( 'detail-reply-body' ).innerText;

			confirmSendBtn.disabled = true;

			sendReply( messageId, subject, body )
				.then( () => {
					closeModal( 'reply-modal-overlay' );
					window.location.reload();
				} )
				.catch( ( err ) => {
					closeModal( 'reply-modal-overlay' );
					showToast( err.message, 'error' );
					confirmSendBtn.disabled = false;
				} );
		} );
	}
}
