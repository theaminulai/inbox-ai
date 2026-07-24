/**
 * The "more actions" row dropdown (the `⋮` icon in a table row), ported from
 * the static mockup's `common.js` (`openRowMenu`/`closeRowMenu`). Generic
 * over which page uses it — the caller supplies the already-built `items`
 * array (mockup's per-kind `messageRowMenuItems()`/`contactRowMenuItems()`
 * logic lives in each page's own module, e.g. `componets/inbox/list.js`,
 * since only that module knows a given row's current status).
 */

let closeWired = false;

/**
 * Wires the "click anywhere else closes the menu" behavior. Safe to call
 * more than once (only wires the document listener the first time).
 */
export function initRowMenuGlobalClose() {
	if ( closeWired ) {
		return;
	}

	closeWired = true;

	document.addEventListener( 'click', ( e ) => {
		if (
			! e.target.closest( '[data-action="more"]' ) &&
			! e.target.closest( '.cf7-ai-inbox-row-menu__item' )
		) {
			closeRowMenu();
		}
	} );
}

/**
 * @param {HTMLElement} anchorEl The `⋮` icon that was clicked.
 * @param {string}      kind     e.g. `message`, `contact` — namespaces `key`
 *                                so a menu for one table's row 3 doesn't
 *                                collide with another table's row 3.
 * @param {string|number} key    The row's id.
 * @param {Array<{action:string,label:string,icon:string,danger?:boolean}>} items
 */
export function openRowMenu( anchorEl, kind, key, items ) {
	const menu = document.getElementById( 'row-menu' );

	if ( ! menu ) {
		return;
	}

	const openKey = kind + ':' + key;

	if ( menu.dataset.openFor === openKey && 'block' === menu.style.display ) {
		closeRowMenu();
		return;
	}

	menu.innerHTML = items.length
		? items
				.map(
					( it ) =>
						'<div class="cf7-ai-inbox-row-menu__item' +
						( it.danger
							? ' cf7-ai-inbox-row-menu__item--danger'
							: '' ) +
						'" data-menu-action="' +
						it.action +
						'" data-kind="' +
						kind +
						'" data-key="' +
						key +
						'">' +
						it.icon +
						'<span>' +
						it.label +
						'</span></div>'
				)
				.join( '' )
		: '<div class="cf7-ai-inbox-row-menu__item" style="color:var(--text-tertiary);cursor:default;">No further actions</div>';

	const rect = anchorEl.getBoundingClientRect();
	menu.style.display = 'block';
	menu.dataset.openFor = openKey;

	let left = rect.right - menu.offsetWidth;

	if ( left < 8 ) {
		left = 8;
	}

	menu.style.top = rect.bottom + 6 + 'px';
	menu.style.left = left + 'px';
}

export function closeRowMenu() {
	const menu = document.getElementById( 'row-menu' );

	if ( ! menu ) {
		return;
	}

	menu.style.display = 'none';
	menu.dataset.openFor = '';
}
