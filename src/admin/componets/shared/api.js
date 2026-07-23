/**
 * Shared `admin-ajax.php` wrapper used by every page's JS modules.
 *
 * Native ES module — no bundler required to run. `wp-scripts` remains
 * available for an optional later bundling/minification pass.
 */

/**
 * Calls one `wp_ajax_*` action and resolves with its `data` payload.
 *
 * @param {string} action   The registered `wp_ajax_{action}` name.
 * @param {Object} data     Extra POST fields. Values that are objects/arrays
 *                          must already be JSON-stringified by the caller
 *                          (this function does not do it for you, since some
 *                          actions need a specific field JSON-encoded and
 *                          others plain).
 * @param {Object} settings Localized bootstrap data — defaults to the
 *                          shared `cf7aiInboxAdmin` global every admin page
 *                          gets from `wp_localize_script()` (enqueued once,
 *                          in {@see \CF7AIInbox\Admin\Menu::enqueue_assets()});
 *                          each page's own nonce is merged in via that
 *                          page's `cf7ai_inbox_localize_{$slug}` filter.
 * @return {Promise<Object>} Resolves with the response's `data` object,
 *                            rejects with an Error on failure or a non-2xx
 *                            response.
 */
export function cf7aiAjax( action, data = {}, settings = window.cf7aiInboxAdmin ) {
	if ( ! settings || ! settings.ajaxUrl ) {
		return Promise.reject( new Error( 'CF7 AI Inbox: missing localized settings.' ) );
	}

	const body = new URLSearchParams( { action, nonce: settings.nonce } );

	Object.keys( data ).forEach( ( key ) => {
		if ( undefined !== data[ key ] && null !== data[ key ] ) {
			body.append( key, data[ key ] );
		}
	} );

	return fetch( settings.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		body,
	} )
		.then( ( response ) => response.json() )
		.then( ( json ) => {
			if ( ! json || ! json.success ) {
				const message = json && json.data && json.data.message ? json.data.message : 'Request failed.';
				throw new Error( message );
			}

			return json.data || {};
		} );
}
