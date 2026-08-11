/**
 * Settings page — Integrations tab.
 *
 * One combined "Save Integration Settings" button persists both cards
 * (Slack Integration and CRM Data Collection) in a single request — see
 * `SettingsAjaxController::save_settings()`'s `'integrations'` case, which
 * calls `Settings\SlackRepository::save()` and `Settings\CrmRepository::save()`
 * — two entirely separate classes/options, so this tab's save can never
 * touch the Notifications tab's own `notify_*` toggles.
 */

import { inboxaiAjax } from '../shared/api.js';
import { showToast } from '../shared/toast.js';
import { collectFields } from '../shared/fields.js';

export function initIntegrationsTab() {
	const screen = document.getElementById( 'screen-integrations' );

	if ( ! screen ) {
		return;
	}

	const saveBtn = document.getElementById( 'integrations-save-btn' );

	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', () => {
			const values = collectFields( screen );

			inboxaiAjax( 'inboxai_save_settings', {
				tab: 'integrations',
				values: JSON.stringify( values ),
			} )
				.then( () =>
					showToast( 'Integration settings saved', 'success' )
				)
				.catch( ( err ) => showToast( err.message, 'error' ) );
		} );
	}

	// Tests whatever's currently typed into the webhook field, saved or
	// not — unlike Inbound Email's Test Connection (which only ever tests
	// the last-saved settings), a Slack webhook URL is a single self-
	// contained value with nothing else to save first, so testing it live
	// is safe. See `SettingsAjaxController::test_slack()`.
	const slackTestBtn = document.getElementById( 'slack-test-btn' );
	const slackWebhookInput = document.getElementById( 'slack-webhook-url' );

	if ( slackTestBtn && slackWebhookInput ) {
		slackTestBtn.addEventListener( 'click', () => {
			const original = slackTestBtn.textContent;
			slackTestBtn.disabled = true;
			slackTestBtn.textContent = 'Testing…';

			inboxaiAjax( 'inboxai_test_slack', {
				webhook_url: slackWebhookInput.value,
			} )
				.then( ( data ) => showToast( data.message, 'success' ) )
				.catch( ( err ) => showToast( err.message, 'error' ) )
				.finally( () => {
					slackTestBtn.disabled = false;
					slackTestBtn.textContent = original;
				} );
		} );
	}
}
