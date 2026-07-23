/**
 * Settings page — Import & Migration (Flamingo) tab.
 *
 * Same four-step wizard UI as the static mockup; the simulated
 * `setTimeout()`-based file-parse and import-progress code is replaced with
 * real `cf7ai_flamingo_detect` / `cf7ai_flamingo_import_batch` calls.
 */

import { cf7aiAjax } from '../shared/api.js';
import { showToast } from '../shared/toast.js';
import { openModal, closeModal } from '../shared/modal.js';

export function initFlamingoImportTab() {
	const screen = document.getElementById( 'screen-flamingo' );

	if ( ! screen ) {
		return;
	}

	const fileInput = document.getElementById( 'flamingo-file-input' );

	// Nothing left to wire if Flamingo isn't active — the template renders
	// an explanatory message instead of the wizard in that case.
	if ( ! fileInput ) {
		return;
	}

	let detectedMessages = 0;

	function goStep( n ) {
		for ( let i = 1; i <= 4; i++ ) {
			const panel = document.getElementById( 'flamingo-panel-' + i );

			if ( panel ) {
				panel.style.display = i === n ? '' : 'none';
			}
		}

		screen.querySelectorAll( '.cf7-ai-inbox-wizard__step' ).forEach( ( el ) => {
			const step = parseInt( el.dataset.wizardStep, 10 );
			el.classList.toggle( 'cf7-ai-inbox-is-active', step === n );
			el.classList.toggle( 'cf7-ai-inbox-is-done', step < n );
		} );

		screen.querySelectorAll( '.cf7-ai-inbox-wizard__line' ).forEach( ( el, idx ) => {
			el.classList.toggle( 'cf7-ai-inbox-is-done', idx + 1 < n );
		} );

		const main = document.getElementById( 'main' );

		if ( main && main.scrollTo ) {
			main.scrollTo( { top: 0, behavior: 'smooth' } );
		}
	}

	function resetWizard() {
		fileInput.value = '';
		document.getElementById( 'flamingo-file-name' ).textContent = 'No file chosen';
		document.getElementById( 'flamingo-detected-info' ).style.display = 'none';
		document.getElementById( 'flamingo-next-1' ).disabled = true;
		document.getElementById( 'flamingo-progress-wrap' ).style.display = 'none';
		document.getElementById( 'flamingo-progress-fill' ).style.width = '0%';
		document.getElementById( 'flamingo-progress-pct' ).textContent = '0%';

		const startBtn = document.getElementById( 'flamingo-start-import-btn' );
		startBtn.disabled = false;
		startBtn.textContent = 'Start Import';

		detectedMessages = 0;
		goStep( 1 );
	}

	fileInput.addEventListener( 'change', function () {
		const file = this.files[ 0 ];

		if ( ! file ) {
			return;
		}

		document.getElementById( 'flamingo-file-name' ).textContent = file.name;
		document.getElementById( 'flamingo-detected-info' ).style.display = 'none';
		document.getElementById( 'flamingo-next-1' ).disabled = true;
		showToast( 'Checking for Flamingo data…' );

		cf7aiAjax( 'cf7ai_flamingo_detect' )
			.then( ( data ) => {
				detectedMessages = data.messages || 0;

				if ( ! data.available || detectedMessages < 1 ) {
					showToast( 'No Flamingo data found to import.', 'error' );
					return;
				}

				const info = document.getElementById( 'flamingo-detected-info' );
				info.style.display = 'flex';
				info.querySelector( 'span' ).textContent = detectedMessages + ' Flamingo message(s) found and ready to import.';

				document.getElementById( 'flamingo-next-1' ).disabled = false;
				showToast( 'Flamingo data detected', 'success' );
			} )
			.catch( ( err ) => showToast( err.message, 'error' ) );
	} );

	const next1 = document.getElementById( 'flamingo-next-1' );
	if ( next1 ) {
		next1.addEventListener( 'click', () => goStep( 2 ) );
	}

	const back2 = document.getElementById( 'flamingo-back-2' );
	if ( back2 ) {
		back2.addEventListener( 'click', () => goStep( 1 ) );
	}

	const next2 = document.getElementById( 'flamingo-next-2' );
	if ( next2 ) {
		next2.addEventListener( 'click', () => {
			const messagesOn = document.getElementById( 'flamingo-toggle-messages' ).classList.contains( 'cf7-ai-inbox-is-on' );
			document.getElementById( 'flamingo-summary-messages' ).textContent = messagesOn ? String( detectedMessages ) : '0';
			goStep( 3 );
		} );
	}

	const back3 = document.getElementById( 'flamingo-back-3' );
	if ( back3 ) {
		back3.addEventListener( 'click', () => goStep( 2 ) );
	}

	const startBtn = document.getElementById( 'flamingo-start-import-btn' );
	if ( startBtn ) {
		startBtn.addEventListener( 'click', () => openModal( 'import-modal-overlay' ) );
	}

	const confirmBtn = document.getElementById( 'modal-confirm-import' );
	if ( confirmBtn ) {
		confirmBtn.addEventListener( 'click', () => {
			closeModal( 'import-modal-overlay' );

			const btn = document.getElementById( 'flamingo-start-import-btn' );
			btn.disabled = true;
			btn.textContent = 'Importing…';
			document.getElementById( 'flamingo-progress-wrap' ).style.display = 'block';
			showToast( 'Import started…' );

			const runAi = document.getElementById( 'flamingo-toggle-ai' ).classList.contains( 'cf7-ai-inbox-is-on' );
			const total = detectedMessages || 0;
			let importedSoFar = 0;

			function runBatch( offset ) {
				cf7aiAjax( 'cf7ai_flamingo_import_batch', { offset, run_ai: runAi ? 1 : 0 } )
					.then( ( data ) => {
						importedSoFar += data.imported;

						const pct = total > 0 ? Math.min( 100, Math.round( ( data.offset / total ) * 100 ) ) : 100;
						document.getElementById( 'flamingo-progress-fill' ).style.width = pct + '%';
						document.getElementById( 'flamingo-progress-pct' ).textContent = pct + '%';

						if ( data.done ) {
							document.getElementById( 'flamingo-progress-label' ).textContent = 'Import complete';
							document.getElementById( 'flamingo-complete-summary' ).textContent =
								importedSoFar + ' message(s) were imported successfully.';
							showToast( importedSoFar + ' messages imported from Flamingo', 'success' );
							setTimeout( () => goStep( 4 ), 400 );
							return;
						}

						runBatch( data.offset );
					} )
					.catch( ( err ) => showToast( err.message, 'error' ) );
			}

			runBatch( 0 );
		} );
	}

	const restartBtn = document.getElementById( 'flamingo-restart-btn' );
	if ( restartBtn ) {
		restartBtn.addEventListener( 'click', resetWizard );
	}
}
