/**
 * Minimal show/hide modal helper, ported from the static mockup's generic
 * `[data-close-modal]` wiring.
 */

/**
 * Wires every `[data-close-modal]` element under `root` to hide the modal
 * overlay it names.
 *
 * @param {Document|HTMLElement} [root]
 */
export function initModalClose( root = document ) {
	root.querySelectorAll( '[data-close-modal]' ).forEach( ( el ) => {
		el.addEventListener( 'click', () => closeModal( el.dataset.closeModal ) );
	} );
}

/**
 * @param {string} id Overlay element id.
 */
export function openModal( id ) {
	const overlay = document.getElementById( id );

	if ( overlay ) {
		overlay.style.display = 'flex';
	}
}

/**
 * @param {string} id Overlay element id.
 */
export function closeModal( id ) {
	const overlay = document.getElementById( id );

	if ( overlay ) {
		overlay.style.display = 'none';
	}
}
