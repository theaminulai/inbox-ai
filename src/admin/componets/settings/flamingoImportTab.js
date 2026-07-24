/**
 * Settings page — Import & Migration (Flamingo) tab.
 *
 * A 4-step wizard, messages-only, with two source paths:
 *  - "live": reads this site's own Flamingo `flamingo_inbound` posts
 *    directly (`cf7ai_flamingo_detect` / `cf7ai_flamingo_import_batch`).
 *  - "csv": a Flamingo Inbound Messages CSV export uploaded from disk
 *    (`cf7ai_flamingo_upload_csv` to parse+stage it, then
 *    `cf7ai_flamingo_import_csv_batch` to import in the same batched-loop
 *    shape as the live path).
 *
 * Both paths converge on the same step 2/3/4 panels; only the summary text
 * and which batch endpoint step 3 calls differ.
 *
 * This tab also had a contacts-import path at one point (a second Flamingo
 * Address Book pass on the live side, and a Contacts-CSV shape on the
 * upload side), alongside a minimal Contacts admin page — both were built
 * and then deliberately reverted at the user's request ("Contacts page are
 * not needed for now, I will develop them later according to my
 * development plan."), so this wizard is messages-only again pending that
 * later, from-scratch build.
 */

import { cf7aiAjax, cf7aiUpload } from '../shared/api.js';
import { showToast } from '../shared/toast.js';
import { openModal, closeModal } from '../shared/modal.js';

export function initFlamingoImportTab() {
	const screen = document.getElementById( 'screen-flamingo' );

	if ( ! screen ) {
		return;
	}

	const sourceLiveRadio = document.getElementById( 'flamingo-source-live' );
	const sourceCsvRadio = document.getElementById( 'flamingo-source-csv' );

	// Nothing left to wire if this tab's markup isn't present.
	if ( ! sourceLiveRadio || ! sourceCsvRadio ) {
		return;
	}

	const livePanel = document.getElementById( 'flamingo-source-live-panel' );
	const csvPanel = document.getElementById( 'flamingo-source-csv-panel' );
	const checkLiveBtn = document.getElementById( 'flamingo-check-live-btn' );
	const liveDetectedInfo = document.getElementById( 'flamingo-detected-info' );
	const fileInput = document.getElementById( 'flamingo-file-input' );
	const fileNameEl = document.getElementById( 'flamingo-file-name' );
	const csvDetectedInfo = document.getElementById(
		'flamingo-csv-detected-info'
	);
	const next1 = document.getElementById( 'flamingo-next-1' );

	// Detection results, per source — cleared whenever the source switches
	// or a new check/upload starts, so step 3 can never run against stale
	// counts from the other path.
	let detected = {
		live: { messages: 0 },
		csv: { token: '', count: 0 },
	};

	function currentSource() {
		return sourceCsvRadio.checked ? 'csv' : 'live';
	}

	function updateSourcePanels() {
		const source = currentSource();
		livePanel.style.display = 'live' === source ? '' : 'none';
		csvPanel.style.display = 'csv' === source ? '' : 'none';

		const ready =
			'live' === source
				? detected.live.messages > 0
				: detected.csv.count > 0;

		next1.disabled = ! ready;
	}

	sourceLiveRadio.addEventListener( 'change', updateSourcePanels );
	sourceCsvRadio.addEventListener( 'change', updateSourcePanels );

	function goStep( n ) {
		for ( let i = 1; i <= 4; i++ ) {
			const panel = document.getElementById( 'flamingo-panel-' + i );

			if ( panel ) {
				panel.style.display = i === n ? '' : 'none';
			}
		}

		screen
			.querySelectorAll( '.cf7-ai-inbox-wizard__step' )
			.forEach( ( el ) => {
				const step = parseInt( el.dataset.wizardStep, 10 );
				el.classList.toggle( 'cf7-ai-inbox-is-active', step === n );
				el.classList.toggle( 'cf7-ai-inbox-is-done', step < n );
			} );

		screen
			.querySelectorAll( '.cf7-ai-inbox-wizard__line' )
			.forEach( ( el, idx ) => {
				el.classList.toggle( 'cf7-ai-inbox-is-done', idx + 1 < n );
			} );

		const main = document.getElementById( 'main' );

		if ( main && main.scrollTo ) {
			main.scrollTo( { top: 0, behavior: 'smooth' } );
		}
	}

	function resetWizard() {
		detected = {
			live: { messages: 0 },
			csv: { token: '', count: 0 },
		};

		fileInput.value = '';
		fileNameEl.textContent = 'No file chosen';
		liveDetectedInfo.style.display = 'none';
		csvDetectedInfo.style.display = 'none';
		next1.disabled = true;

		document.getElementById( 'flamingo-progress-wrap' ).style.display =
			'none';
		document.getElementById( 'flamingo-progress-fill' ).style.width = '0%';
		document.getElementById( 'flamingo-progress-pct' ).textContent = '0%';

		const startBtn = document.getElementById( 'flamingo-start-import-btn' );
		startBtn.disabled = false;
		startBtn.textContent = 'Start Import';

		updateSourcePanels();
		goStep( 1 );
	}

	checkLiveBtn.addEventListener( 'click', () => {
		liveDetectedInfo.style.display = 'none';
		next1.disabled = true;
		showToast( 'Checking for Flamingo data…' );

		cf7aiAjax( 'cf7ai_flamingo_detect' )
			.then( ( data ) => {
				detected.live.messages = data.messages || 0;

				if ( ! data.available || detected.live.messages < 1 ) {
					showToast( 'No Flamingo data found to import.', 'error' );
					return;
				}

				liveDetectedInfo.style.display = 'flex';
				liveDetectedInfo.querySelector( 'span' ).textContent =
					detected.live.messages +
					' Flamingo message(s) found and ready to import.';

				updateSourcePanels();
				showToast( 'Flamingo data detected', 'success' );
			} )
			.catch( ( err ) => showToast( err.message, 'error' ) );
	} );

	fileInput.addEventListener( 'change', function () {
		const file = this.files[ 0 ];

		if ( ! file ) {
			return;
		}

		fileNameEl.textContent = file.name;
		csvDetectedInfo.style.display = 'none';
		next1.disabled = true;
		detected.csv = { token: '', count: 0 };
		showToast( 'Uploading and checking file…' );

		cf7aiUpload( 'cf7ai_flamingo_upload_csv', file )
			.then( ( data ) => {
				detected.csv = {
					token: data.token,
					count: data.count || 0,
				};

				csvDetectedInfo.style.display = 'flex';
				csvDetectedInfo.querySelector( 'span' ).textContent =
					detected.csv.count +
					' message(s) found in this file and ready to import.';

				updateSourcePanels();
				showToast( 'File checked', 'success' );
			} )
			.catch( ( err ) => {
				showToast( err.message, 'error' );
			} );
	} );

	next1.addEventListener( 'click', () => {
		const source = currentSource();
		const summaryRow = document.getElementById(
			'flamingo-options-summary-row'
		);

		summaryRow.innerHTML =
			'live' === source
				? '<span>Source</span><b>Live Flamingo data — ' +
				  detected.live.messages +
				  ' message(s)</b>'
				: '<span>Source</span><b>CSV upload — ' +
				  detected.csv.count +
				  ' message(s)</b>';

		goStep( 2 );
	} );

	const back2 = document.getElementById( 'flamingo-back-2' );
	if ( back2 ) {
		back2.addEventListener( 'click', () => goStep( 1 ) );
	}

	const next2 = document.getElementById( 'flamingo-next-2' );
	if ( next2 ) {
		next2.addEventListener( 'click', () => {
			const source = currentSource();
			const sourceEl = document.getElementById( 'flamingo-summary-source' );

			sourceEl.textContent =
				'live' === source ? 'Flamingo (live data)' : 'Flamingo (CSV upload)';
			document.getElementById( 'flamingo-summary-messages' ).textContent =
				String(
					'live' === source ? detected.live.messages : detected.csv.count
				);

			goStep( 3 );
		} );
	}

	const back3 = document.getElementById( 'flamingo-back-3' );
	if ( back3 ) {
		back3.addEventListener( 'click', () => goStep( 2 ) );
	}

	const startBtn = document.getElementById( 'flamingo-start-import-btn' );
	if ( startBtn ) {
		startBtn.addEventListener( 'click', () =>
			openModal( 'import-modal-overlay' )
		);
	}

	const confirmBtn = document.getElementById( 'modal-confirm-import' );
	if ( confirmBtn ) {
		confirmBtn.addEventListener( 'click', () => {
			closeModal( 'import-modal-overlay' );

			const btn = document.getElementById( 'flamingo-start-import-btn' );
			btn.disabled = true;
			btn.textContent = 'Importing…';
			document.getElementById( 'flamingo-progress-wrap' ).style.display =
				'block';
			showToast( 'Import started…' );

			const runAi = document
				.getElementById( 'flamingo-toggle-ai' )
				.classList.contains( 'cf7-ai-inbox-is-on' );

			runImport( currentSource(), runAi )
				.then( ( totalImported ) => {
					document.getElementById(
						'flamingo-progress-label'
					).textContent = 'Import complete';
					document.getElementById(
						'flamingo-complete-summary'
					).textContent =
						totalImported + ' message(s) were imported successfully.';
					showToast(
						totalImported + ' messages imported from Flamingo',
						'success'
					);
					setTimeout( () => goStep( 4 ), 400 );
				} )
				.catch( ( err ) => {
					showToast( err.message, 'error' );
					btn.disabled = false;
					btn.textContent = 'Start Import';
				} );
		} );
	}

	const restartBtn = document.getElementById( 'flamingo-restart-btn' );
	if ( restartBtn ) {
		restartBtn.addEventListener( 'click', resetWizard );
	}

	/**
	 * Runs the chosen source's batch loop, updating the progress bar, and
	 * resolves with the total number of messages actually imported.
	 *
	 * @param {string}  source Either `live` or `csv`.
	 * @param {boolean} runAi  Whether imported messages should be queued for
	 *                         AI analysis.
	 * @return {Promise<number>}
	 */
	function runImport( source, runAi ) {
		const action =
			'live' === source
				? 'cf7ai_flamingo_import_batch'
				: 'cf7ai_flamingo_import_csv_batch';
		const total =
			'live' === source ? detected.live.messages : detected.csv.count;
		const baseArgs =
			'live' === source
				? { run_ai: runAi ? 1 : 0 }
				: { token: detected.csv.token, run_ai: runAi ? 1 : 0 };

		let importedSoFar = 0;

		return new Promise( ( resolve, reject ) => {
			const step = ( offset ) => {
				cf7aiAjax( action, { offset, ...baseArgs } )
					.then( ( data ) => {
						importedSoFar += data.imported;

						const pct =
							total > 0
								? Math.min(
										100,
										Math.round( ( data.offset / total ) * 100 )
								  )
								: 100;
						document.getElementById(
							'flamingo-progress-fill'
						).style.width = pct + '%';
						document.getElementById(
							'flamingo-progress-pct'
						).textContent = pct + '%';

						if ( data.done ) {
							resolve( importedSoFar );
							return;
						}

						step( data.offset );
					} )
					.catch( reject );
			};

			step( 0 );
		} );
	}
}
