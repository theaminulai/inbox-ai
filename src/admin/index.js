/**
 * Shared entry point for every Inbox AI admin page.
 *
 * Enqueued as a native ES module (`type="module"`) on each of the plugin's
 * five admin pages — see includes/Admin/Pages/*.php. Reads the page shell's
 * `data-page` attribute (set on the `#main` wrapper by each page's template,
 * e.g. includes/Templates/settings.php) and lazily loads that one page's
 * module via dynamic `import()`, so each page only downloads its own code
 * without needing a bundler to split it that way.
 */
import '../admin/scss/index.scss';

import { initSettingsPage } from './componets/settings/index.js';
import { initInboxPage } from './componets/inbox/index.js';

function boot() {
	const page = document.getElementById( 'main' )?.dataset.page;

	switch ( page ) {
		case 'settings':
			initSettingsPage();
			break;

		case 'inbox':
			initInboxPage();
			break;
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
