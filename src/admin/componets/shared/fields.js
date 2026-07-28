/**
 * Reads/writes every `[data-field]` control under a container into a plain
 * object keyed by field name — used by every Settings tab's Save button
 * instead of each tab hand-rolling its own field collection.
 */

/**
 * @param {HTMLElement} root Container to search within (e.g. one `.inboxai-screen`).
 * @return {Object<string, *>} field name => value.
 */
export function collectFields( root ) {
	const values = {};

	root.querySelectorAll( '[data-field]' ).forEach( ( el ) => {
		const field = el.dataset.field;

		if ( el.classList.contains( 'inboxai-switch' ) ) {
			values[ field ] = el.classList.contains( 'inboxai-is-on' );
		} else if ( 'checkbox' === el.type ) {
			values[ field ] = el.checked;
		} else if ( 'range' === el.type || 'number' === el.type ) {
			values[ field ] = parseInt( el.value, 10 ) || 0;
		} else if ( el.multiple ) {
			values[ field ] = Array.from( el.selectedOptions ).map(
				( o ) => o.value
			);
		} else {
			values[ field ] = el.value;
		}
	} );

	return values;
}

/**
 * The inverse of {@see collectFields} — writes a plain object's values back
 * onto the matching `[data-field]` controls (used by "Reset to defaults").
 *
 * @param {HTMLElement}       root Container to search within.
 * @param {Object<string, *>} data field name => value.
 */
export function populateFields( root, data ) {
	Object.keys( data || {} ).forEach( ( field ) => {
		const el = root.querySelector( '[data-field="' + field + '"]' );

		if ( ! el ) {
			return;
		}

		if ( el.classList.contains( 'inboxai-switch' ) ) {
			el.classList.toggle( 'inboxai-is-on', !! data[ field ] );
		} else {
			el.value = data[ field ];
		}
	} );
}
