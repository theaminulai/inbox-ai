/**
 * Settings page tab switching — the relocated `showSettingsTab()`.
 *
 * All six subtabs are rendered server-side into the same page load (see
 * includes/Templates/settings.php), so switching between them is a pure
 * client-side visibility toggle with no fetch involved.
 */

const TABS = [
	'ai-settings',
	'general-settings',
	'prompts',
	'usage',
	'notifications',
	'flamingo',
];

/**
 * @param {string} name
 * @return {string|null}
 */
export function getQueryParam( name ) {
	return new URLSearchParams( window.location.search ).get( name );
}

/**
 * Shows one tab's `.inboxai-screen` section and hides the rest,
 * updates the subnav's active state, and syncs `?tab=` in the URL.
 *
 * @param {string} key One of the six tab keys.
 */
export function showSettingsTab( key ) {
	if ( -1 === TABS.indexOf( key ) ) {
		key = 'ai-settings';
	}

	document
		.querySelectorAll( '.inboxai-screen' )
		.forEach( ( s ) => s.classList.remove( 'inboxai-is-active' ) );

	const el = document.getElementById( 'screen-' + key );

	if ( el ) {
		el.classList.add( 'inboxai-is-active' );
	}

	document.querySelectorAll( '[data-subnav]' ).forEach( ( a ) => {
		a.classList.toggle(
			'inboxai-is-active',
			a.dataset.subnav === key
		);
	} );

	const main = document.getElementById( 'main' );

	if ( main && main.scrollTo ) {
		main.scrollTo( { top: 0, behavior: 'instant' } );
	}

	if ( window.history && history.replaceState ) {
		const url = new URL( window.location.href );
		url.searchParams.set( 'tab', key );
		history.replaceState( null, '', url.toString() );
	}
}

/**
 * Wires every `[data-subnav]` link to switch tabs on click.
 */
export function initTabs() {
	document.addEventListener( 'click', ( e ) => {
		const subnavEl = e.target.closest( '[data-subnav]' );

		if ( subnavEl ) {
			e.preventDefault();
			showSettingsTab( subnavEl.dataset.subnav );
		}
	} );
}
