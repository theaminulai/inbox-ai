/**
 * The `.cf7-ai-inbox-switch` toggle control, shared across every tab.
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
		const el = e.target.closest( '.cf7-ai-inbox-switch' );

		if ( ! el ) {
			return;
		}

		el.classList.toggle( 'cf7-ai-inbox-is-on' );
		el.dispatchEvent( new CustomEvent( 'cf7ai:switch-toggled', { bubbles: true } ) );
	} );
}

/**
 * @param {HTMLElement} el
 * @return {boolean}
 */
export function isSwitchOn( el ) {
	return !! el && el.classList.contains( 'cf7-ai-inbox-is-on' );
}

/**
 * @param {HTMLElement} el
 * @param {boolean}     on
 */
export function setSwitch( el, on ) {
	if ( el ) {
		el.classList.toggle( 'cf7-ai-inbox-is-on', !! on );
	}
}
