/**
 * Settings page module.
 *
 * Lazily loaded by `src/admin/index.js` (the one shared entry point
 * enqueued on every admin page) once it sees `data-page="settings"` on the
 * page shell. Exporting an explicit `initSettingsPage()` — rather than this
 * module self-running on `DOMContentLoaded` — matters because by the time a
 * dynamic `import()` resolves, `DOMContentLoaded` may already have fired;
 * the loader calls this directly once the DOM is confirmed ready instead.
 */

import { initTabs, showSettingsTab, getQueryParam } from './tabs.js';
import { initAiProviderTab } from './aiProviderTab.js';
import { initGeneralTab } from './generalTab.js';
import { initPromptsTab } from './promptsTab.js';
import { initUsageBillingTab } from './usageBillingTab.js';
import { initNotificationsTab } from './notificationsTab.js';
import { initFlamingoImportTab } from './flamingoImportTab.js';
import { initIntegrationsTab } from './integrationsTab.js';
import { initCategoriesManager } from './categoriesManager.js';
import { initSwitches } from '../shared/switch.js';
import { initModalClose } from '../shared/modal.js';

export function initSettingsPage() {
	initSwitches( document );
	initModalClose( document );
	initTabs();
	initAiProviderTab();
	initGeneralTab();
	initPromptsTab();
	initUsageBillingTab();
	initNotificationsTab();
	initFlamingoImportTab();
	initIntegrationsTab();
	initCategoriesManager();

	showSettingsTab( getQueryParam( 'tab' ) || 'ai-settings' );
}
