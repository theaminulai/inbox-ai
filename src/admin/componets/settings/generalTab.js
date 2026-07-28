/**
 * Settings page — General tab.
 */

import { inboxaiAjax } from '../shared/api.js';
import { showToast } from '../shared/toast.js';
import { collectFields } from '../shared/fields.js';

export function initGeneralTab() {
	const screen = document.getElementById( 'screen-general-settings' );

	if ( ! screen ) {
		return;
	}

	const saveBtn = document.getElementById( 'general-settings-save-btn' );

	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', () => {
			const values = collectFields( screen );

			const monitoredForms = [];
			screen.querySelectorAll( '[data-form-toggle]' ).forEach( ( el ) => {
				if ( el.classList.contains( 'inboxai-is-on' ) ) {
					monitoredForms.push( parseInt( el.dataset.formId, 10 ) );
				}
			} );
			values.monitored_forms = monitoredForms;

			inboxaiAjax( 'inboxai_save_settings', {
				tab: 'general-settings',
				values: JSON.stringify( values ),
			} )
				.then( () => showToast( 'General settings saved', 'success' ) )
				.catch( ( err ) => showToast( err.message, 'error' ) );
		} );
	}
}
