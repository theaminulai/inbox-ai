/**
 * AI Categories checklist box, for the box CategoryTaxonomy::render_metabox()
 * adds to Contact Form 7's "Edit Contact Form" / "Add Contact Form" screens
 * — a checkbox per existing category plus a WooCommerce-"Product
 * categories"-style "+ Add new category" toggle.
 *
 * A plain, unbundled static file (enqueued directly by
 * CategoryTaxonomy::maybe_enqueue_script(), not part of this plugin's own
 * webpack entry point) — CF7's edit-form page runs its entire output through
 * wp_kses() before printing (see WPCF7_HTMLFormatter::print()), which
 * strips <script>/<style> tags outright, so this behavior has to live in a
 * real <script src="">.
 */
( function () {
	'use strict';

	function init() {
		var box = document.querySelector( '.inboxai-categories' );

		if ( ! box ) {
			return;
		}

		var list = document.getElementById( 'inboxai-category-list' );
		var empty = document.getElementById( 'inboxai-category-empty' );
		var toggle = document.getElementById( 'inboxai-add-toggle' );
		var addBox = document.getElementById( 'inboxai-add-new' );
		var input = document.getElementById( 'inboxai-add-input' );
		var submit = document.getElementById( 'inboxai-add-submit' );

		function existingCheckbox( name ) {
			var checkboxes = list.querySelectorAll( 'input[type="checkbox"]' );

			for ( var i = 0; i < checkboxes.length; i++ ) {
				if (
					checkboxes[ i ].value.toLowerCase() === name.toLowerCase()
				) {
					return checkboxes[ i ];
				}
			}

			return null;
		}

		function addCategory() {
			var name = input.value.trim();

			if ( '' === name ) {
				return;
			}

			var existing = existingCheckbox( name );

			if ( existing ) {
				existing.checked = true;
			} else {
				if ( empty ) {
					empty.remove();
					empty = null;
				}

				var label = document.createElement( 'label' );
				label.style.cssText =
					'display:block;font-size:13px;margin-bottom:4px;';

				var checkbox = document.createElement( 'input' );
				checkbox.type = 'checkbox';
				checkbox.name = 'inboxai_categories[]';
				checkbox.value = name;
				checkbox.checked = true;

				label.appendChild( checkbox );
				label.appendChild( document.createTextNode( ' ' + name ) );
				list.appendChild( label );
			}

			input.value = '';
			addBox.style.display = 'none';
		}

		toggle.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var showing = 'none' !== addBox.style.display;

			if ( showing ) {
				addBox.style.display = 'none';
			} else {
				addBox.style.display = 'block';
				input.focus();
			}
		} );

		submit.addEventListener( 'click', addCategory );

		input.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key ) {
				e.preventDefault();
				addCategory();
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
