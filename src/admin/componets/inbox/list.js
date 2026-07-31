/**
 * AI Inbox List page — message list screen (`#screen-inbox`).
 *
 * The table, filters' current values, and pagination are all rendered
 * server-side now (see `includes/Templates/inbox-list.php` and
 * `InboxListPage::render_list()`) — this file only adds interactivity on top
 * of that already-rendered HTML: auto-submitting the filter form (a plain
 * GET form; it works with JS disabled too, just without the auto-submit),
 * the row "more actions" menu, and quick row actions (mark reviewed, archive,
 * retry, delete) that reload the page on success so the server-rendered
 * table reflects the change — there is no client-held copy of the table to
 * patch in place.
 */

import { markReviewed, archiveMessage, retryAnalysis, deleteMessage, bulkAction } from './api.js';
import { openRowMenu, closeRowMenu } from '../shared/rowMenu.js';
import { showToast } from '../shared/toast.js';
import { downloadCsv } from '../shared/csv.js';
import { inboxaiAjax } from '../shared/api.js';
import { initBulkActions } from '../shared/bulkActions.js';

// Read once from `#main`'s `data-can-delete` attribute (see
// `includes/Templates/inbox.php`) — the row-menu's "Delete" item is only
// ever built for users who actually hold `DELETE_MESSAGES`, mirroring how
// the Submission Detail screen's Reply Composer card is only rendered at
// all for `EDIT_MESSAGES` holders.
const inboxaiMainEl = document.getElementById( 'main' );
const canDelete = !! inboxaiMainEl && '1' === inboxaiMainEl.dataset.canDelete;

/**
 * Builds the row-menu items contextual to one row's current status — read
 * from that row's own `data-status` attribute (see `inbox-list.php`) since
 * there's no client-side list of message objects to look the row up in
 * anymore.
 *
 * @param {string} status A `workflow_status` value.
 * @return {Array<Object>}
 */
function rowMenuItemsFor( status ) {
	const items = [];

	if ( 'failed' === status ) {
		items.push( {
			action: 'retry',
			label: 'Retry analysis',
			icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>',
		} );
	} else if ( 'new' === status || 'review' === status ) {
		items.push( {
			action: 'reviewed',
			label: 'Mark reviewed',
			icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>',
		} );
	}

	if ( 'archived' !== status ) {
		items.push( {
			action: 'archive',
			label: 'Archive',
			icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8M1 3h22v5H1z"/><path d="M10 12h4"/></svg>',
		} );
	}

	if ( canDelete ) {
		items.push( {
			action: 'delete',
			label: 'Delete',
			icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0-1 14a2 2 0 01-2 2H7a2 2 0 01-2-2L4 6"/><path d="M10 11v6M14 11v6"/></svg>',
			danger: true,
		} );
	}

	return items;
}

/**
 * Reads the list's current filters straight from the URL's query string
 * (the filter form is a plain GET form, so the URL is always the single
 * source of truth for "what's currently filtered") for the CSV export
 * request, which still goes through `wp_ajax_inboxai_list_messages` with a
 * large `per_page` rather than a dedicated export endpoint.
 *
 * @return {Object}
 */
function currentFiltersFromUrl() {
	const params = new URLSearchParams( window.location.search );

	return {
		status: params.get( 'status' ) || '',
		priority: params.get( 'priority' ) || '',
		category: params.get( 'category' ) || '',
		form: params.get( 'form' ) || '',
		confidence_below: params.get( 'confidence_below' ) || '',
		search: params.get( 'search' ) || '',
		period: params.get( 'period' ) || '',
	};
}

function exportCsv() {
	inboxaiAjax( 'inboxai_list_messages', {
		...currentFiltersFromUrl(),
		page: 1,
		per_page: 10000,
	} )
		.then( ( { items } ) => {
			if ( 0 === items.length ) {
				showToast( 'No messages to export' );
				return;
			}

			const statusLabels = {
				new: 'New',
				review: 'Needs Review',
				reviewed: 'Reviewed',
				drafted: 'Drafted',
				replied: 'Replied',
				failed: 'Failed',
				archived: 'Archived',
			};

			const headers = [
				'Customer',
				'Email',
				'Message',
				'Form',
				'Priority',
				'Category',
				'AI Confidence',
				'Status',
				'Received',
			];
			const rows = items.map( ( m ) => [
				m.sender_name,
				m.sender_email,
				m.message,
				m.form_title,
				m.priority,
				m.category,
				null === m.confidence || undefined === m.confidence
					? ''
					: m.confidence + '%',
				statusLabels[ m.workflow_status ] || m.workflow_status,
				m.created_at,
			] );

			downloadCsv( 'ai-inbox-export.csv', headers, rows );
			showToast(
				'Exported ' +
					items.length +
					' message' +
					( 1 === items.length ? '' : 's' ) +
					' to CSV',
				'success'
			);
		} )
		.catch( ( err ) => showToast( err.message, 'error' ) );
}

export function initListScreen() {
	const screen = document.getElementById( 'screen-inbox' );

	if ( ! screen ) {
		return;
	}

	// The date-range control lives in the page header, not inside
	// `#inbox-filter-form`'s toolbar (see `inbox/list.php`), so it can't
	// reuse that form's generic `.inboxai-filter-select` auto-submit
	// below — it navigates directly, carrying over every other filter
	// already in the URL and resetting pagination, same as changing any
	// other filter does.
	const periodSelect = document.getElementById( 'inbox-period-select' );

	if ( periodSelect ) {
		periodSelect.addEventListener( 'change', () => {
			const params = new URLSearchParams( window.location.search );

			if ( periodSelect.value ) {
				params.set( 'period', periodSelect.value );
			} else {
				params.delete( 'period' );
			}

			params.delete( 'paged' );
			window.location.search = params.toString();
		} );
	}

	// Auto-submit the filter form on any change, and after a short debounce
	// while typing in search — the form itself is a plain GET form (see
	// `inbox-list.php`), so this is purely a convenience: it still works
	// (via its own submit button) with JavaScript disabled.
	const filterForm = document.getElementById( 'inbox-filter-form' );

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

		const search = document.getElementById( 'inbox-search' );
		let searchDebounce = null;

		if ( search ) {
			search.addEventListener( 'input', () => {
				clearTimeout( searchDebounce );
				searchDebounce = setTimeout( () => filterForm.submit(), 500 );
			} );
		}
	}

	const exportBtn = document.getElementById( 'inbox-export-btn' );

	if ( exportBtn ) {
		exportBtn.addEventListener( 'click', exportCsv );
	}

	initBulkActions( {
		tableBodyId: 'inbox-table-body',
		selectAllId: 'inbox-select-all',
		itemAttr: 'id',
		parseItem: ( value ) => parseInt( value, 10 ),
		noun: 'submission',
		// Only `delete` needs a confirmation prompt — `reviewed`/`archive`
		// apply immediately, matching the row-menu's own single-row actions.
		confirmMessage: ( count, action ) =>
			'delete' === action
				? 'Delete ' +
				  count +
				  ' submission' +
				  ( 1 === count ? '' : 's' ) +
				  '? This cannot be undone from the Inbox.'
				: null,
		apply: bulkAction,
	} );

	// Row actions ("more") — delegated from the table body, since it's the
	// one part of this screen large enough to matter for a single listener.
	const tableBody = document.getElementById( 'inbox-table-body' );

	if ( tableBody ) {
		tableBody.addEventListener( 'click', ( e ) => {
			const moreBtn = e.target.closest( '[data-action="more"]' );

			if ( ! moreBtn ) {
				return;
			}

			e.stopPropagation();

			const id = parseInt( moreBtn.dataset.id, 10 );
			openRowMenu(
				moreBtn,
				'message',
				id,
				rowMenuItemsFor( moreBtn.dataset.status )
			);
		} );
	}

	// Row-menu item clicks land on the shared `#row-menu` element, outside
	// the table, so this needs its own listener.
	document.addEventListener( 'click', ( e ) => {
		const item = e.target.closest(
			'.inboxai-row-menu__item[data-menu-action]'
		);

		if ( ! item || 'message' !== item.dataset.kind ) {
			return;
		}

		const id = parseInt( item.dataset.key, 10 );
		const action = item.dataset.menuAction;
		closeRowMenu();

		if ( 'reviewed' === action ) {
			markReviewed( id )
				.then( () => window.location.reload() )
				.catch( ( err ) => showToast( err.message, 'error' ) );
		} else if ( 'archive' === action ) {
			archiveMessage( id )
				.then( () => window.location.reload() )
				.catch( ( err ) => showToast( err.message, 'error' ) );
		} else if ( 'retry' === action ) {
			showToast( 'Retrying analysis…' );
			retryAnalysis( id )
				.then( () => window.location.reload() )
				.catch( ( err ) => showToast( err.message, 'error' ) );
		} else if ( 'delete' === action ) {
			// eslint-disable-next-line no-alert -- a lightweight, native
			// confirmation is enough for a single-row delete; this plugin
			// has no dedicated "confirm" modal outside the reply composer's.
			if (
				! window.confirm(
					'Delete this submission? This cannot be undone from the Inbox.'
				)
			) {
				return;
			}

			deleteMessage( id )
				.then( () => window.location.reload() )
				.catch( ( err ) => showToast( err.message, 'error' ) );
		}
	} );
}
