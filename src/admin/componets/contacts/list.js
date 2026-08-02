/**
 * Contacts List page screen (`#screen-contacts` equivalent — this page has
 * only one view, so there's no `data-view` to switch on, unlike the AI
 * Inbox List).
 *
 * The table, filters' current values, and pagination are all rendered
 * server-side (see `includes/Templates/contacts-list.php` and
 * `ContactsListPage::render()`) — this file only adds interactivity on top
 * of that already-rendered HTML: auto-submitting the filter form, the row
 * "more actions" menu, "Delete contact", and CSV export. Same division of
 * responsibility as `componets/inbox/list.js`.
 */

import { deleteContact, listContacts, bulkDeleteContacts } from './api.js';
import { openRowMenu, closeRowMenu } from '../shared/rowMenu.js';
import { showToast } from '../shared/toast.js';
import { downloadCsv } from '../shared/csv.js';
import { initBulkActions } from '../shared/bulkActions.js';

// Read once from `#main`'s `data-can-delete` attribute (see
// `includes/Templates/contacts-list.php`) — same pattern as
// `componets/inbox/list.js`'s `canDelete`: the row-menu's "Delete contact"
// item is only ever built for users who actually hold `DELETE_MESSAGES`.
const inboxaiMainEl = document.getElementById( 'main' );
const canDelete = !! inboxaiMainEl && '1' === inboxaiMainEl.dataset.canDelete;

/**
 * @return {Array<{action:string,label:string,icon:string,danger?:boolean}>}
 */
function rowMenuItems() {
	if ( ! canDelete ) {
		return [];
	}

	return [
		{
			action: 'delete',
			label: 'Delete contact',
			icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0-1 14a2 2 0 01-2 2H7a2 2 0 01-2-2L4 6"/><path d="M10 11v6M14 11v6"/></svg>',
			danger: true,
		},
	];
}

/**
 * Reads the screen's current filters straight from the URL's query string
 * (the filter form is a plain GET form, so the URL is always the single
 * source of truth), for the CSV export request — same pattern as
 * `componets/inbox/list.js`'s `currentFiltersFromUrl()`.
 *
 * @return {Object}
 */
function currentFiltersFromUrl() {
	const params = new URLSearchParams( window.location.search );

	return {
		category: params.get( 'category' ) || '',
		priority: params.get( 'priority' ) || '',
		search: params.get( 'search' ) || '',
	};
}

function exportCsv() {
	listContacts( currentFiltersFromUrl(), 1, 10000 )
		.then( ( { items } ) => {
			if ( 0 === items.length ) {
				showToast( 'No contacts to export' );
				return;
			}

			const headers = [
				'Name',
				'Email',
				'Category',
				'Priority',
				'Messages',
				'Replied',
				'Last Contact',
			];
			const rows = items.map( ( c ) => [
				c.sender_name,
				c.sender_email,
				c.source_category,
				c.priority,
				c.message_count,
				c.replied_count,
				c.created_at,
			] );

			downloadCsv( 'contacts-export.csv', headers, rows );
			showToast(
				'Exported ' +
					items.length +
					' contact' +
					( 1 === items.length ? '' : 's' ) +
					' to CSV',
				'success'
			);
		} )
		.catch( ( err ) => showToast( err.message, 'error' ) );
}

export function initListScreen() {
	const screen = document.getElementById( 'main' );

	if ( ! screen || 'contacts' !== screen.dataset.page ) {
		return;
	}

	const filterForm = document.getElementById( 'contacts-filter-form' );

	if ( filterForm ) {
		// `:not(.inboxai-bulk-select)` matters here: the "Bulk actions"
		// dropdown also carries `.inboxai-filter-select` (for shared
		// styling only — see `_toolbar.scss`), so without this exclusion
		// picking a bulk action was itself treated as a filter change and
		// immediately submitted/reloaded the form, before Apply was ever
		// clicked.
		filterForm
			.querySelectorAll( '.inboxai-filter-select:not(.inboxai-bulk-select)' )
			.forEach( ( select ) => {
				select.addEventListener( 'change', () => filterForm.submit() );
			} );

		const search = document.getElementById( 'contacts-search' );
		let searchDebounce = null;

		if ( search ) {
			search.addEventListener( 'input', () => {
				clearTimeout( searchDebounce );
				searchDebounce = setTimeout( () => filterForm.submit(), 500 );
			} );
		}
	}

	const exportBtn = document.getElementById( 'contacts-export-btn' );

	if ( exportBtn ) {
		exportBtn.addEventListener( 'click', exportCsv );
	}

	// Only "Delete" exists on this page — see `contacts/list.php`, which
	// only renders the bar at all for `DELETE_MESSAGES` holders — but it
	// still always needs confirming, since it archives every message from
	// the selected senders.
	initBulkActions( {
		tableBodyId: 'contacts-table-body',
		selectAllId: 'contacts-select-all',
		itemAttr: 'email',
		noun: 'contact',
		confirmMessage: ( count ) =>
			'Delete ' +
			count +
			' contact' +
			( 1 === count ? '' : 's' ) +
			'? Every message from these senders will be archived. This cannot be undone from Contacts.',
		apply: bulkDeleteContacts,
	} );

	// Row actions ("more") — delegated from the table body, matching
	// `componets/inbox/list.js`.
	const tableBody = document.getElementById( 'contacts-table-body' );

	if ( tableBody ) {
		tableBody.addEventListener( 'click', ( e ) => {
			const moreBtn = e.target.closest( '[data-action="more"]' );

			if ( ! moreBtn ) {
				return;
			}

			e.stopPropagation();
			openRowMenu( moreBtn, 'contact', moreBtn.dataset.email, rowMenuItems() );
		} );
	}

	document.addEventListener( 'click', ( e ) => {
		const item = e.target.closest(
			'.inboxai-row-menu__item[data-menu-action]'
		);

		if ( ! item || 'contact' !== item.dataset.kind ) {
			return;
		}

		const email = item.dataset.key;
		const action = item.dataset.menuAction;
		closeRowMenu();

		if ( 'delete' === action ) {
			// eslint-disable-next-line no-alert -- a lightweight, native
			// confirmation is enough here, same as the AI Inbox List's own
			// single-row delete (see `componets/inbox/list.js`); this plugin
			// has no dedicated "confirm" modal outside the reply composer's.
			if (
				! window.confirm(
					'Delete this contact? Every message from this sender will be archived. This cannot be undone from Contacts.'
				)
			) {
				return;
			}

			deleteContact( email )
				.then( () => window.location.reload() )
				.catch( ( err ) => showToast( err.message, 'error' ) );
		}
	} );
}
