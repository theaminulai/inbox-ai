/**
 * Toast notifications, ported from the static mockup's `common.js`.
 *
 * Sets the message via `textContent` rather than string-concatenated
 * `innerHTML` so real (server- or attacker-influenced) text is never
 * treated as markup.
 */

const ICONS = {
	success:
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>',
	error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>',
	info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>',
};

/**
 * @param {string} message Toast text.
 * @param {string} [type]  'success' | 'error' | anything else for the neutral style.
 */
export function showToast( message, type ) {
	const container = document.getElementById( 'toast-container' );

	if ( ! container ) {
		return;
	}

	const el = document.createElement( 'div' );
	el.className =
		'inboxai-toast' +
		( type && ICONS[ type ] ? ' inboxai-toast--' + type : '' );

	const icon = document.createElement( 'span' );
	icon.innerHTML = ICONS[ type ] || ICONS.info;

	const text = document.createElement( 'span' );
	text.textContent = message;

	el.appendChild( icon.firstChild );
	el.appendChild( text );
	container.appendChild( el );

	requestAnimationFrame( () =>
		el.classList.add( 'inboxai-is-visible' )
	);

	setTimeout( () => {
		el.classList.remove( 'inboxai-is-visible' );
		setTimeout( () => el.remove(), 250 );
	}, 3000 );
}
