/**
 * Settings page — Prompts tab.
 */

import { cf7aiAjax } from '../shared/api.js';
import { showToast } from '../shared/toast.js';
import { collectFields, populateFields } from '../shared/fields.js';

export function initPromptsTab() {
	const screen = document.getElementById( 'screen-prompts' );

	if ( ! screen ) {
		return;
	}

	const saveBtn = document.getElementById( 'prompts-save-btn' );

	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', () => {
			const values = collectFields( screen );

			cf7aiAjax( 'cf7ai_save_settings', { tab: 'prompts', values: JSON.stringify( values ) } )
				.then( () => showToast( 'Prompts saved', 'success' ) )
				.catch( ( err ) => showToast( err.message, 'error' ) );
		} );
	}

	const resetBtn = document.getElementById( 'prompts-reset-btn' );

	if ( resetBtn ) {
		resetBtn.addEventListener( 'click', () => {
			cf7aiAjax( 'cf7ai_save_settings', { tab: 'prompts', values: JSON.stringify( { reset: true } ) } )
				.then( ( data ) => {
					populateFields( screen, data && data.defaults ? data.defaults : {} );
					showToast( 'Prompts reset to defaults', 'success' );
				} )
				.catch( ( err ) => showToast( err.message, 'error' ) );
		} );
	}
}
