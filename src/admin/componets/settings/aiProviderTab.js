/**
 * Settings page — AI Provider tab.
 */

import { inboxaiAjax } from '../shared/api.js';
import { showToast } from '../shared/toast.js';
import { collectFields } from '../shared/fields.js';

function selectedProviderId( screen ) {
	const selected = screen.querySelector(
		'.inboxai-provider__option.inboxai-is-selected'
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

	const apiKeyInput = screen.querySelector( '[data-field="api_key"]' );
	const modelSelect = screen.querySelector( '[data-field="model"]' );

	// The provider/model actually saved and rendered on page load — used so
	// clicking back to that same card restores its real saved model instead
	// of resetting to that provider's first default option.
	const savedProvider = selectedProviderId( screen );
	const savedModel = modelSelect ? modelSelect.value : null;

	screen.addEventListener( 'click', ( e ) => {
		const option = e.target.closest( '.inboxai-provider__option' );

		if ( ! option ) {
			return;
		}

		screen
			.querySelectorAll( '.inboxai-provider__option' )
			.forEach( ( o ) => {
				o.classList.remove( 'inboxai-is-selected' );
				const radio = o.querySelector(
					'.inboxai-provider__radio'
				);

				if ( radio ) {
					radio.classList.remove( 'inboxai-is-checked' );
				}
			} );

		option.classList.add( 'inboxai-is-selected' );
		const radio = option.querySelector( '.inboxai-provider__radio' );

		if ( radio ) {
			radio.classList.add( 'inboxai-is-checked' );
		}

		// Keep the "<Provider> Configuration" card header in sync with
		// whichever card was just clicked — otherwise it keeps showing
		// whatever provider was active on page load, making it look like
		// Anthropic/Google can't actually be configured.
		const configLabel = screen.querySelector(
			'#settings-provider-config-label'
		);

		if ( configLabel && option.dataset.providerLabel ) {
			configLabel.textContent = option.dataset.providerLabel;
		}

		// Swap the Model dropdown to this provider's own models — left
		// alone it kept showing whatever model belonged to whichever
		// provider was active on page load (e.g. an OpenAI model id while
		// Anthropic is selected), which isn't a valid model for this
		// provider at all.
		if ( modelSelect && option.dataset.models ) {
			try {
				const models = JSON.parse( option.dataset.models );
				const isSavedProvider =
					option.dataset.provider === savedProvider;

				populateModels(
					modelSelect,
					models,
					isSavedProvider ? savedModel : null
				);
			} catch ( err ) {
				// Malformed data-models JSON — leave the dropdown as-is
				// rather than clearing it.
			}
		}
	} );

	const testBtn = document.getElementById( 'settings-test-connection' );

	if ( testBtn ) {
		testBtn.addEventListener( 'click', () => {
			const original = testBtn.textContent;
			testBtn.disabled = true;
			testBtn.textContent = 'Testing…';

			const provider = selectedProviderId( screen );
			const apiKey = apiKeyInput ? apiKeyInput.value : '';

			inboxaiAjax( 'inboxai_test_connection', { provider, api_key: apiKey } )
				.then( () => {
					showToast( 'Connection successful', 'success' );

					const pill = document.getElementById(
						'settings-provider-pill'
					);

					if ( pill ) {
						pill.style.display = '';
					}

					return inboxaiAjax( 'inboxai_list_models', {
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

			inboxaiAjax( 'inboxai_save_settings', {
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
