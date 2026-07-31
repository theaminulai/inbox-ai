/**
 * Shared "Bulk actions" bar wiring — the checkbox column + select-all +
 * live "N selected" count + Apply button behavior is identical on the AI
 * Inbox List and Contacts List (see `componets/inbox/list.js` and
 * `componets/contacts/list.js`); only three things actually differ per page:
 * what identifies a row (`data-id` vs. `data-email`), which actions exist
 * and which of them need a confirmation prompt, and the AJAX call that
 * actually applies the chosen action. Those three are passed in; everything
 * else (select-all syncing, the live count, reading the bar's own adjacent
 * `<select>`, the empty-selection/empty-action guards, reload-on-success)
 * lives here once instead of being copy-pasted per page.
 */

import { showToast } from './toast.js';

/**
 * Wires one screen's checkbox column + "Bulk actions" bar.
 *
 * @param {Object}   options
 * @param {string}   options.tableBodyId    Id of the element containing
 *                                           every row checkbox
 *                                           (`.inboxai-bulk-checkbox`).
 * @param {string}   options.selectAllId    Id of the header "select all"
 *                                           checkbox.
 * @param {string}   options.itemAttr       The `data-*` attribute each row
 *                                           checkbox carries its identifier
 *                                           under, camelCased for
 *                                           `dataset` — `'id'` for
 *                                           `data-id`, `'email'` for
 *                                           `data-email`.
 * @param {(raw:string)=>*} [options.parseItem] Converts a checkbox's raw
 *                                           `dataset` string into the value
 *                                           `options.apply()` expects (e.g.
 *                                           `(v) => parseInt(v, 10)` for
 *                                           numeric ids). Defaults to
 *                                           passing the string through
 *                                           unchanged — the Contacts List's
 *                                           emails need no conversion.
 * @param {string}   options.noun           Singular noun for toast/confirm
 *                                           copy (e.g. `'submission'`,
 *                                           `'contact'`) — a trailing `s`
 *                                           is appended whenever a count
 *                                           isn't exactly `1`.
 * @param {(count:number, action:string)=>(string|null)} [options.confirmMessage]
 *                                           Returns the `window.confirm()`
 *                                           prompt for a given action and
 *                                           selection count, or a falsy
 *                                           value to apply that action
 *                                           without confirming (e.g. the AI
 *                                           Inbox List only confirms its
 *                                           `delete` action, not
 *                                           `reviewed`/`archive`).
 * @param {(items:Array<*>, action:string)=>Promise<{updated:number}>} options.apply
 *                                           Called with every selected
 *                                           item's identifier and the
 *                                           chosen action; must resolve the
 *                                           same `{updated}` shape every
 *                                           bulk AJAX action already
 *                                           returns.
 *
 * @return {void}
 */
export function initBulkActions( {
	tableBodyId,
	selectAllId,
	itemAttr,
	parseItem = ( value ) => value,
	noun,
	confirmMessage,
	apply,
} ) {
	const tableBody = document.getElementById( tableBodyId );
	const selectAll = document.getElementById( selectAllId );

	if ( ! tableBody || ! selectAll ) {
		return;
	}

	const checkboxes = () =>
		Array.from( tableBody.querySelectorAll( '.inboxai-bulk-checkbox' ) );

	const selectedItems = () =>
		checkboxes()
			.filter( ( cb ) => cb.checked )
			.map( ( cb ) => parseItem( cb.dataset[ itemAttr ] ) );

	function updateCount() {
		const all = checkboxes();
		const count = selectedItems().length;

		document.querySelectorAll( '[data-bulk-count]' ).forEach( ( el ) => {
			el.textContent = count > 0 ? count + ' selected' : '';
		} );

		selectAll.checked = all.length > 0 && count === all.length;
		selectAll.indeterminate = count > 0 && count < all.length;
	}

	selectAll.addEventListener( 'change', () => {
		checkboxes().forEach( ( cb ) => {
			cb.checked = selectAll.checked;
		} );
		updateCount();
	} );

	tableBody.addEventListener( 'change', ( e ) => {
		if ( e.target.classList.contains( 'inboxai-bulk-checkbox' ) ) {
			updateCount();
		}
	} );

	document.querySelectorAll( '.inboxai-bulk-apply' ).forEach( ( btn ) => {
		btn.addEventListener( 'click', () => {
			const bar = btn.closest( '.inboxai-bulk-bar' );
			const select = bar && bar.querySelector( '.inboxai-bulk-select' );
			const action = select ? select.value : '';
			const items = selectedItems();

			if ( ! action ) {
				showToast( 'Choose a bulk action first', 'error' );
				return;
			}

			if ( 0 === items.length ) {
				showToast( 'Select at least one ' + noun + ' first', 'error' );
				return;
			}

			const prompt = confirmMessage ? confirmMessage( items.length, action ) : null;

			// eslint-disable-next-line no-alert -- matches the single-row
			// action confirmations elsewhere on these pages; neither page has
			// a dedicated confirm modal.
			if ( prompt && ! window.confirm( prompt ) ) {
				return;
			}

			apply( items, action )
				.then( ( { updated } ) => {
					showToast(
						updated + ' ' + noun + ( 1 === updated ? '' : 's' ) + ' updated',
						'success'
					);
					window.location.reload();
				} )
				.catch( ( err ) => showToast( err.message, 'error' ) );
		} );
	} );
}
