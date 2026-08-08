/**
 * Settings page — Notifications tab.
 */

import { inboxaiAjax } from '../shared/api.js';
import { showToast } from '../shared/toast.js';
import { collectFields } from '../shared/fields.js';

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

					const lastCheck = document.getElementById( 'inbound-last-check' );

					if ( lastCheck ) {
						lastCheck.textContent = data.message + ' (just now)';
					}
				} )
				.catch( ( err ) => showToast( err.message, 'error' ) )
				.finally( () => {
					testInboundBtn.disabled = false;
					testInboundBtn.textContent = original;
				} );
		} );
	}
}
