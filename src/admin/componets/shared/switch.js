/**
 * The `.inboxai-switch` toggle control, shared across every tab.
 */

/**
 * Delegates click handling for every switch under `root`, toggling its
 * on/off class. Safe to call once globally (event delegation, not
 * per-element listeners).
 *
 * @param {Document|HTMLElement} [root]
 */
export function initSwitches( root = document ) {
	root.addEventListener( 'click', ( e ) => {
		const el = e.target.closest( '.inboxai-switch' );

		if ( ! el ) {
			return;
		}

		el.classList.toggle( 'inboxai-is-on' );
		el.dispatchEvent(
			new CustomEvent( 'inboxai:switch-toggled', { bubbles: true } )
		);
	} );
}

/**
 * @param {HTMLElement} el
 * @return {boolean}
 */
export function isSwitchOn( el ) {
	return !! el && el.classList.contains( 'inboxai-is-on' );
}

/**
 * @param {HTMLElement} el
 * @param {boolean}     on
 */
export function setSwitch( el, on ) {
	if ( el ) {
		el.classList.toggle( 'inboxai-is-on', !! on );
	}
}
