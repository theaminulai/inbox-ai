/**
 * Settings page — AI Provider tab.
 */

import { cf7aiAjax } from '../shared/api.js';
import { showToast } from '../shared/toast.js';
import { collectFields } from '../shared/fields.js';

function selectedProviderId( screen ) {
	const selected = screen.querySelector(
		'.cf7-ai-inbox-provider__option.cf7-ai-inbox-is-selected'
	);
	return selected ? selected.dataset.provider : 'openai';
}

function populateModels( select, models, keepValue ) {
	if ( ! select || ! Array.isArray( models ) ) {
		return;
	}

	select.innerHTML = '';

	models.forEach( ( id ) => {
		const option = document.createElement( 'option' );
		option.value = id;
		option.textContent = id;

		if ( id === keepValue ) {
			option.selected = true;
		}

		select.appendChild( option );
	} );
}

export function initAiProviderTab() {
	const screen = document.getElementById( 'screen-ai-settings' );

	if ( ! screen ) {
		return;
	}

	screen.addEventListener( 'click', ( e ) => {
		const option = e.target.closest( '.cf7-ai-inbox-provider__option' );

		if ( ! option ) {
			return;
		}

		screen
			.querySelectorAll( '.cf7-ai-inbox-provider__option' )
			.forEach( ( o ) => {
				o.classList.remove( 'cf7-ai-inbox-is-selected' );
				const radio = o.querySelector(
					'.cf7-ai-inbox-provider__radio'
				);

				if ( radio ) {
					radio.classList.remove( 'cf7-ai-inbox-is-checked' );
				}
			} );

		option.classList.add( 'cf7-ai-inbox-is-selected' );
		const radio = option.querySelector( '.cf7-ai-inbox-provider__radio' );

		if ( radio ) {
			radio.classList.add( 'cf7-ai-inbox-is-checked' );
		}
	} );

	const apiKeyInput = screen.querySelector( '[data-field="api_key"]' );
	const modelSelect = screen.querySelector( '[data-field="model"]' );

	const testBtn = document.getElementById( 'settings-test-connection' );

	if ( testBtn ) {
		testBtn.addEventListener( 'click', () => {
			const original = testBtn.textContent;
			testBtn.disabled = true;
			testBtn.textContent = 'Testing…';

			const provider = selectedProviderId( screen );
			const apiKey = apiKeyInput ? apiKeyInput.value : '';

			cf7aiAjax( 'cf7ai_test_connection', { provider, api_key: apiKey } )
				.then( () => {
					showToast( 'Connection successful', 'success' );

					const pill = document.getElementById(
						'settings-provider-pill'
					);

					if ( pill ) {
						pill.style.display = '';
					}

					return cf7aiAjax( 'cf7ai_list_models', {
						provider,
						api_key: apiKey,
					} );
				} )
				.then( ( data ) => {
					if ( data && data.models ) {
						populateModels(
							modelSelect,
							data.models,
							modelSelect ? modelSelect.value : null
						);
					}
				} )
				.catch( ( err ) => showToast( err.message, 'error' ) )
				.finally( () => {
					testBtn.disabled = false;
					testBtn.textContent = original;
				} );
		} );
	}

	const saveBtn = document.getElementById( 'settings-save-provider' );

	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', () => {
			const values = Object.assign(
				{ provider: selectedProviderId( screen ) },
				collectFields( screen )
			);

			cf7aiAjax( 'cf7ai_save_settings', {
				tab: 'ai-settings',
				values: JSON.stringify( values ),
			} )
				.then( ( data ) => {
					showToast( 'Provider settings saved', 'success' );

					if ( apiKeyInput && data.apiKeyMasked ) {
						apiKeyInput.value = data.apiKeyMasked;
					}
				} )
				.catch( ( err ) => showToast( err.message, 'error' ) );
		} );
	}
}
