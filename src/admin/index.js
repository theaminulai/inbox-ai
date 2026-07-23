/**
 * Shared entry point for every CF7 AI Inbox admin page.
 *
 * Enqueued as a native ES module (`type="module"`) on each of the plugin's
 * five admin pages — see includes/Admin/Pages/*.php. Reads the page shell's
 * `data-page` attribute (set on the `#main` wrapper by each page's template,
 * e.g. includes/Templates/settings.php) and lazily loads that one page's
 * module via dynamic `import()`, so each page only downloads its own code
 * without needing a bundler to split it that way.
 */
import '../admin/scss/index.scss';

const loaders = {
	settings: () => import( './componets/settings/index.js' ).then( ( m ) => m.initSettingsPage() ),
};

function boot() {
	const root = document.getElementById( 'main' );
	const page = root ? root.dataset.page : '';

	if ( page && loaders[ page ] ) {
		loaders[ page ]();
	}
}

// Module scripts run deferred, so DOMContentLoaded may already have fired
// by the time this executes — check readyState instead of assuming either way.
if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
