/**
 * Pager rendering, ported from the static mockup's `common.js`
 * (`paginationHtml`). Used by any page with a paginated grid-table (AI
 * Inbox List today; Contacts List later).
 */

/**
 * @param {string} pagerId     Element id the pager renders into — stamped
 *                              onto each button's `data-pager` so a shared
 *                              click handler can tell multiple pagers apart.
 * @param {number} totalItems
 * @param {number} currentPage 1-indexed.
 * @param {number} pageSize
 * @return {string} HTML for the `.inboxai-pager` element.
 */
export function paginationHtml( pagerId, totalItems, currentPage, pageSize ) {
	const totalPages = Math.max( 1, Math.ceil( totalItems / pageSize ) );

	if ( currentPage > totalPages ) {
		currentPage = totalPages;
	}

	const prevIcon =
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>';
	const nextIcon =
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>';

	const btn = ( label, page, disabled, active ) =>
		'<button class="inboxai-pager__btn' +
		( active ? ' inboxai-is-active' : '' ) +
		'"' +
		( disabled ? ' disabled' : '' ) +
		' data-pager="' +
		pagerId +
		'" data-page="' +
		page +
		'">' +
		label +
		'</button>';

	const pages = [];

	if ( totalPages <= 7 ) {
		for ( let i = 1; i <= totalPages; i++ ) {
			pages.push( i );
		}
	} else {
		pages.push( 1 );

		if ( currentPage > 3 ) {
			pages.push( '…' );
		}

		for (
			let i = Math.max( 2, currentPage - 1 );
			i <= Math.min( totalPages - 1, currentPage + 1 );
			i++
		) {
			pages.push( i );
		}

		if ( currentPage < totalPages - 2 ) {
			pages.push( '…' );
		}

		pages.push( totalPages );
	}

	let html = '<div class="inboxai-pager">';
	html += btn( prevIcon, currentPage - 1, currentPage <= 1, false );
	pages.forEach( ( p ) => {
		html +=
			'…' === p
				? '<span class="inboxai-pager__ellipsis">…</span>'
				: btn( String( p ), p, false, p === currentPage );
	} );
	html += btn( nextIcon, currentPage + 1, currentPage >= totalPages, false );
	html += '</div>';

	return html;
}

/**
 * Wires a delegated click handler on `containerEl` for every
 * `[data-pager="pagerId"]` button rendered inside it (the pager's own HTML
 * is replaced on every page load, so listeners are attached to the stable
 * parent once instead of to buttons that don't exist yet).
 *
 * @param {HTMLElement} containerEl
 * @param {string}      pagerId
 * @param {(page:number)=>void} onPageChange
 */
export function initPager( containerEl, pagerId, onPageChange ) {
	containerEl.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest( '[data-pager="' + pagerId + '"]' );

		if ( ! btn || btn.disabled ) {
			return;
		}

		const page = parseInt( btn.dataset.page, 10 );

		if ( ! isNaN( page ) && page >= 1 ) {
			onPageChange( page );
		}
	} );
}
