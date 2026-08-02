/**
 * Settings page — General tab's "Manage Categories" card.
 *
 * The only place an {@see \InboxAI\CF7\CategoryTaxonomy} term can be renamed
 * or deleted — the per-form "AI Categories" box on each Contact Form 7
 * form's own edit screen (`src/cf7/category-metabox.js`) is deliberately
 * add/assign-only (renaming/deleting there would affect every form that
 * uses it, from a screen scoped to just one form). Adding a category is
 * available in both places, since that's non-destructive.
 *
 * Each row is its own independent inline-edit widget (click the pencil to
 * reveal a text input in place of the name, check/x to save/cancel) plus a
 * delete button with a confirm prompt. Add/rename/delete all call their own
 * AJAX endpoint immediately (`inboxai_add_category`/`inboxai_rename_category`/
 * `inboxai_delete_category`), not the batched "Save Changes" flow the rest
 * of this tab uses — each is a standalone, immediately-effective action
 * rather than a draft field.
 */

import { inboxaiAjax } from '../shared/api.js';
import { showToast } from '../shared/toast.js';

const EMPTY_STATE_HTML =
	'<p id="categories-empty" style="color:var(--text-tertiary);font-size:13px;">No categories yet — add one above.</p>';

export function initCategoriesManager() {
	const list = document.getElementById( 'categories-list' );
	const addInput = document.getElementById( 'category-add-input' );
	const addBtn = document.getElementById( 'category-add-btn' );

	if ( ! list ) {
		return;
	}

	function row( el ) {
		return el.closest( '[data-category-row]' );
	}

	function setEditing( rowEl, editing ) {
		rowEl.querySelector( '[data-category-display]' ).style.display = editing
			? 'none'
			: '';
		rowEl.querySelector( '[data-category-input]' ).style.display = editing
			? ''
			: 'none';
		rowEl.querySelector( '[data-category-edit]' ).style.display = editing
			? 'none'
			: '';
		rowEl.querySelector( '[data-category-save]' ).style.display = editing
			? ''
			: 'none';
		rowEl.querySelector( '[data-category-cancel]' ).style.display = editing
			? ''
			: 'none';
	}

	/**
	 * Builds one category row's DOM node — the same shape
	 * `includes/Templates/settings/general.php` renders server-side, used
	 * here to add a freshly-created category to the list without a full
	 * page reload. Built with DOM APIs (not an innerHTML template string),
	 * so the category name — arbitrary admin-entered text — never gets
	 * parsed as markup.
	 *
	 * @param {number} termId
	 * @param {string} name
	 * @param {number} count
	 * @return {HTMLElement}
	 */
	function buildRow( termId, name, count ) {
		const rowEl = document.createElement( 'div' );
		rowEl.className = 'inboxai-category-row';
		rowEl.dataset.categoryRow = '';
		rowEl.dataset.termId = String( termId );

		const main = document.createElement( 'div' );
		main.className = 'inboxai-category-row__main';

		const display = document.createElement( 'div' );
		display.className = 'inboxai-category-row__display';
		display.dataset.categoryDisplay = '';
		display.textContent = name;

		const input = document.createElement( 'input' );
		input.type = 'text';
		input.className = 'inboxai-category-row__input';
		input.dataset.categoryInput = '';
		input.style.display = 'none';
		input.value = name;

		const sub = document.createElement( 'div' );
		sub.className = 'inboxai-category-row__sub';
		sub.textContent =
			1 === count ? 'Used by 1 form' : 'Used by ' + count + ' forms';

		main.appendChild( display );
		main.appendChild( input );
		main.appendChild( sub );

		const actions = document.createElement( 'div' );
		actions.className = 'inboxai-category-row__actions';
		actions.innerHTML =
			'<button type="button" class="inboxai-btn--icon" data-category-edit title="Rename"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4z"/></svg></button>' +
			'<button type="button" class="inboxai-btn--icon" data-category-save title="Save" style="display:none;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg></button>' +
			'<button type="button" class="inboxai-btn--icon" data-category-cancel title="Cancel" style="display:none;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>' +
			'<button type="button" class="inboxai-btn--icon" data-category-delete title="Delete" style="color:var(--urgent);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0l-1 14a2 2 0 01-2 2H7a2 2 0 01-2-2L4 6"/></svg></button>';

		rowEl.appendChild( main );
		rowEl.appendChild( actions );

		return rowEl;
	}

	list.addEventListener( 'click', ( e ) => {
		const editBtn = e.target.closest( '[data-category-edit]' );
		if ( editBtn ) {
			const rowEl = row( editBtn );
			setEditing( rowEl, true );
			const input = rowEl.querySelector( '[data-category-input]' );
			input.focus();
			input.select();
			return;
		}

		const cancelBtn = e.target.closest( '[data-category-cancel]' );
		if ( cancelBtn ) {
			const rowEl = row( cancelBtn );
			const display = rowEl.querySelector( '[data-category-display]' );
			rowEl.querySelector( '[data-category-input]' ).value =
				display.textContent;
			setEditing( rowEl, false );
			return;
		}

		const saveBtn = e.target.closest( '[data-category-save]' );
		if ( saveBtn ) {
			saveRename( row( saveBtn ) );
			return;
		}

		const deleteBtn = e.target.closest( '[data-category-delete]' );
		if ( deleteBtn ) {
			deleteCategory( row( deleteBtn ) );
		}
	} );

	list.addEventListener( 'keydown', ( e ) => {
		const input = e.target.closest( '[data-category-input]' );

		if ( ! input ) {
			return;
		}

		if ( 'Enter' === e.key ) {
			e.preventDefault();
			saveRename( row( input ) );
		} else if ( 'Escape' === e.key ) {
			e.preventDefault();
			const rowEl = row( input );
			input.value = rowEl.querySelector(
				'[data-category-display]'
			).textContent;
			setEditing( rowEl, false );
		}
	} );

	function saveRename( rowEl ) {
		const termId = parseInt( rowEl.dataset.termId, 10 );
		const input = rowEl.querySelector( '[data-category-input]' );
		const display = rowEl.querySelector( '[data-category-display]' );
		const name = input.value.trim();

		if ( '' === name ) {
			showToast( 'A category name is required.', 'error' );
			return;
		}

		if ( name === display.textContent ) {
			setEditing( rowEl, false );
			return;
		}

		inboxaiAjax( 'inboxai_rename_category', { term_id: termId, name } )
			.then( ( data ) => {
				display.textContent = data.name;
				input.value = data.name;
				setEditing( rowEl, false );
				showToast( 'Category renamed', 'success' );
			} )
			.catch( ( err ) => showToast( err.message, 'error' ) );
	}

	function deleteCategory( rowEl ) {
		const name = rowEl.querySelector( '[data-category-display]' )
			.textContent;

		// eslint-disable-next-line no-alert -- matches this plugin's own
		// single-row delete confirmations elsewhere (e.g. the AI Inbox
		// List's row menu); there's no dedicated confirm modal for a single
		// inline row action like this one.
		if (
			! window.confirm(
				'Delete "' +
					name +
					'"? This removes it from every form that has it checked. Messages already tagged with this category keep that value.'
			)
		) {
			return;
		}

		const termId = parseInt( rowEl.dataset.termId, 10 );

		inboxaiAjax( 'inboxai_delete_category', { term_id: termId } )
			.then( () => {
				rowEl.remove();
				showToast( 'Category deleted', 'success' );

				if ( ! list.querySelector( '[data-category-row]' ) ) {
					list.innerHTML = EMPTY_STATE_HTML;
				}
			} )
			.catch( ( err ) => showToast( err.message, 'error' ) );
	}

	function addCategory() {
		if ( ! addInput ) {
			return;
		}

		const name = addInput.value.trim();

		if ( '' === name ) {
			showToast( 'A category name is required.', 'error' );
			return;
		}

		inboxaiAjax( 'inboxai_add_category', { name } )
			.then( ( data ) => {
				const empty = document.getElementById( 'categories-empty' );

				if ( empty ) {
					empty.remove();
				}

				list.appendChild( buildRow( data.term_id, data.name, 0 ) );
				addInput.value = '';
				showToast( 'Category added', 'success' );
			} )
			.catch( ( err ) => showToast( err.message, 'error' ) );
	}

	if ( addBtn ) {
		addBtn.addEventListener( 'click', addCategory );
	}

	if ( addInput ) {
		addInput.addEventListener( 'keydown', ( e ) => {
			if ( 'Enter' === e.key ) {
				e.preventDefault();
				addCategory();
			}
		} );
	}
}
