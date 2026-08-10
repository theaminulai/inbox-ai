/**
 * Settings page — Notifications tab.
 */

import { inboxaiAjax } from '../shared/api.js';
import { showToast } from '../shared/toast.js';
import { collectFields } from '../shared/fields.js';

/**
 * Shows or hides the "Connected" pill in the Inbound Email Replies card
 * header — same green pill/checkmark as the AI Provider card's own
 * `#settings-provider-pill`, see `notifications.php`'s `#inbound-connected-pill`.
 * Toggled on the *real* Test Connection outcome here (unlike the AI Provider
 * pill, which a failed test leaves alone) because a mailbox that just failed
 * to connect isn't "Connected" anymore — leaving a stale green pill up would
 * contradict the error toast shown at the same time.
 *
 * @param {boolean} connected
 */
function setConnectedPill( connected ) {
	const pill = document.getElementById( 'inbound-connected-pill' );

	if ( pill ) {
		pill.style.display = connected ? '' : 'none';
	}
}

export function initNotificationsTab() {
	const screen = document.getElementById( 'screen-notifications' );

	if ( ! screen ) {
		return;
	}

	const saveBtn = document.getElementById( 'notifications-save-btn' );

	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', () => {
			const values = collectFields( screen );

			inboxaiAjax( 'inboxai_save_settings', {
				tab: 'notifications',
				values: JSON.stringify( values ),
			} )
				.then( () =>
					showToast( 'Notification settings saved', 'success' )
				)
				.catch( ( err ) => showToast( err.message, 'error' ) );
		} );
	}

	// Always tests the currently *saved* Inbound Email settings, not
	// whatever's typed into the fields right now — see
	// `SettingsAjaxController::test_inbound_connection()`'s own docblock for
	// why. The button label says as much, so this isn't a surprise.
	const testInboundBtn = document.getElementById( 'inbound-test-connection' );

	if ( testInboundBtn ) {
		testInboundBtn.addEventListener( 'click', () => {
			const original = testInboundBtn.textContent;
			testInboundBtn.disabled = true;
			testInboundBtn.textContent = 'Testing…';

			inboxaiAjax( 'inboxai_test_inbound_connection', {} )
				.then( ( data ) => {
					showToast( data.message, 'success' );
					setConnectedPill( true );
				} )
				.catch( ( err ) => {
					// Unlike a save/list request failing, this is the actual
					// connection outcome (bad certificate, wrong password,
					// unreachable host, etc.) — see
					// `SettingsAjaxController::test_inbound_connection()`'s own
					// docblock. Shown the same way a failure would be, not as
					// a false "success" the way this used to always report.
					showToast( err.message, 'error' );
					setConnectedPill( false );
				} )
				.finally( () => {
					testInboundBtn.disabled = false;
					testInboundBtn.textContent = original;
				} );
		} );
	}
}
