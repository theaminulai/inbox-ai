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
}
