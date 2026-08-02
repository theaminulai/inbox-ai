/**
 * Settings page — Import & Migration tab.
 *
 * A 5-step wizard: Type → Source → Options → Import → Complete. Step 1
 * picks which of the two import paths this plugin supports the rest of the
 * wizard is for:
 *  - "flamingo": this site's own live Flamingo `flamingo_inbound` posts
 *    (`inboxai_flamingo_detect` / `inboxai_flamingo_import_batch`), or a
 *    Flamingo Inbound Messages CSV export uploaded from disk
 *    (`inboxai_flamingo_upload_csv` to parse+stage it, then
 *    `inboxai_flamingo_import_csv_batch` to import in batches) — chosen via
 *    the Step 2 "live"/"csv" sub-radio, same as before.
 *  - "native": a CSV shaped for this plugin's own columns directly
 *    (`inboxai_native_csv_upload` / `inboxai_native_csv_import_batch`,
 *    backed by `InboxCsvImporter`) — Step 2 shows just a single upload
 *    panel, no sub-choice.
 *
 * Steps 3–5 (Options/Import/Complete) are shared by both paths; only the
 * summary text and which batch endpoint step 4 calls differ. This file used
 * to have a sibling `nativeImportTab.js` + its own Settings tab for the
 * "native" path — that duplicated the whole wizard shell for one extra
 * step's worth of difference, so it was merged in here instead; there is
 * only ever one Import & Migration tab now.
 *
 * This tab also had a contacts-import path at one point (a second Flamingo
 * Address Book pass on the live side, and a Contacts-CSV shape on the
 * upload side), alongside a minimal Contacts admin page — both were built
 * and then deliberately reverted at the user's request ("Contacts page are
 * not needed for now, I will develop them later according to my
 * development plan."), so this wizard is messages-only again pending that
 * later, from-scratch build.
 */

import { inboxaiAjax, inboxaiUpload } from '../shared/api.js';
import { showToast } from '../shared/toast.js';
import { openModal, closeModal } from '../shared/modal.js';

export function initFlamingoImportTab() {
	const screen = document.getElementById( 'screen-flamingo' );

	if ( ! screen ) {
		return;
	}

	const typeFlamingoRadio = document.getElementById( 'flamingo-type-flamingo' );
	const typeNativeRadio = document.getElementById( 'flamingo-type-native' );
	const sourceLiveRadio = document.getElementById( 'flamingo-source-live' );
	const sourceCsvRadio = document.getElementById( 'flamingo-source-csv' );

	// Nothing left to wire if this tab's markup isn't present.
	if ( ! typeFlamingoRadio || ! sourceLiveRadio || ! sourceCsvRadio ) {
		return;
	}

	const flamingoWrap = document.getElementById( 'flamingo-source-flamingo-wrap' );
	const nativeWrap = document.getElementById( 'flamingo-source-native-wrap' );
	const livePanel = document.getElementById( 'flamingo-source-live-panel' );
	const csvPanel = document.getElementById( 'flamingo-source-csv-panel' );
	const checkLiveBtn = document.getElementById( 'flamingo-check-live-btn' );
	const liveDetectedInfo = document.getElementById( 'flamingo-detected-info' );
	const fileInput = document.getElementById( 'flamingo-file-input' );
	const fileNameEl = document.getElementById( 'flamingo-file-name' );
	const csvDetectedInfo = document.getElementById( 'flamingo-csv-detected-info' );
	const nativeFileInput = document.getElementById( 'flamingo-native-file-input' );
	const nativeFileNameEl = document.getElementById( 'flamingo-native-file-name' );
	const nativeDetectedInfo = document.getElementById( 'flamingo-native-detected-info' );
	const next1 = document.getElementById( 'flamingo-next-1' );
	const next2 = document.getElementById( 'flamingo-next-2' );

	// Detection results, per source — cleared whenever the relevant source
	// switches or a new check/upload starts, so later steps can never run
	// against stale counts from a different source.
	let detected = {
		live: { messages: 0 },
		csv: { token: '', count: 0 },
		native: { token: '', count: 0 },
	};

	/**
	 * @return {string} "flamingo" or "native" — Step 1's choice.
	 */
	function currentType() {
		return typeNativeRadio.checked ? 'native' : 'flamingo';
	}

	/**
	 * @return {string} "live", "csv", or "native" — which detected.* bucket
	 *                   Steps 3+ should read from.
	 */
	function currentSource() {
		if ( 'native' === currentType() ) {
			return 'native';
		}

		return sourceCsvRadio.checked ? 'csv' : 'live';
	}

	function detectedCount( source ) {
		if ( 'live' === source ) {
			return detected.live.messages;
		}

		return 'csv' === source ? detected.csv.count : detected.native.count;
	}

	function updateSourcePanels() {
		const type = currentType();

		flamingoWrap.style.display = 'flamingo' === type ? '' : 'none';
		nativeWrap.style.display = 'native' === type ? '' : 'none';

		if ( 'flamingo' === type ) {
			const source = currentSource();
			livePanel.style.display = 'live' === source ? '' : 'none';
			csvPanel.style.display = 'csv' === source ? '' : 'none';
		}

		next2.disabled = detectedCount( currentSource() ) < 1;
	}

	typeFlamingoRadio.addEventListener( 'change', updateSourcePanels );
	typeNativeRadio.addEventListener( 'change', updateSourcePanels );
	sourceLiveRadio.addEventListener( 'change', updateSourcePanels );
	sourceCsvRadio.addEventListener( 'change', updateSourcePanels );

	function goStep( n ) {
		for ( let i = 1; i <= 5; i++ ) {
			const panel = document.getElementById( 'flamingo-panel-' + i );

			if ( panel ) {
				panel.style.display = i === n ? '' : 'none';
			}
		}

		screen
			.querySelectorAll( '.inboxai-wizard__step' )
			.forEach( ( el ) => {
				const step = parseInt( el.dataset.wizardStep, 10 );
				el.classList.toggle( 'inboxai-is-active', step === n );
				el.classList.toggle( 'inboxai-is-done', step < n );
			} );

		screen
			.querySelectorAll( '.inboxai-wizard__line' )
			.forEach( ( el, idx ) => {
				el.classList.toggle( 'inboxai-is-done', idx + 1 < n );
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
			native: { token: '', count: 0 },
		};

		typeFlamingoRadio.checked = true;

		fileInput.value = '';
		fileNameEl.textContent = 'No file chosen';
		liveDetectedInfo.style.display = 'none';
		csvDetectedInfo.style.display = 'none';

		nativeFileInput.value = '';
		nativeFileNameEl.textContent = 'No file chosen';
		nativeDetectedInfo.style.display = 'none';

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
		next2.disabled = true;
		showToast( 'Checking for Flamingo data…' );

		inboxaiAjax( 'inboxai_flamingo_detect' )
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
		next2.disabled = true;
		detected.csv = { token: '', count: 0 };
		showToast( 'Uploading and checking file…' );

		inboxaiUpload( 'inboxai_flamingo_upload_csv', file )
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

	nativeFileInput.addEventListener( 'change', function () {
		const file = this.files[ 0 ];

		if ( ! file ) {
			return;
		}

		nativeFileNameEl.textContent = file.name;
		nativeDetectedInfo.style.display = 'none';
		next2.disabled = true;
		detected.native = { token: '', count: 0 };
		showToast( 'Uploading and checking file…' );

		inboxaiUpload( 'inboxai_native_csv_upload', file )
			.then( ( data ) => {
				detected.native = {
					token: data.token,
					count: data.count || 0,
				};

				nativeDetectedInfo.style.display = 'flex';
				nativeDetectedInfo.querySelector( 'span' ).textContent =
					detected.native.count +
					' row(s) found in this file and ready to import.';

				updateSourcePanels();
				showToast( 'File checked', 'success' );
			} )
			.catch( ( err ) => {
				showToast( err.message, 'error' );
			} );
	} );

	next1.addEventListener( 'click', () => {
		const type = currentType();
		const sourceTitle = document.getElementById( 'flamingo-source-title' );
		const sourceSub = document.getElementById( 'flamingo-source-sub' );

		if ( 'native' === type ) {
			sourceTitle.textContent = 'Upload a CSV';
			sourceSub.textContent =
				'A CSV shaped for this plugin\'s own columns';
		} else {
			sourceTitle.textContent = 'Choose a Source';
			sourceSub.textContent =
				'Read this site\'s live Flamingo data, or upload a CSV exported from Flamingo';
		}

		updateSourcePanels();
		goStep( 2 );
	} );

	const back2 = document.getElementById( 'flamingo-back-2' );
	if ( back2 ) {
		back2.addEventListener( 'click', () => goStep( 1 ) );
	}

	next2.addEventListener( 'click', () => {
		const type = currentType();
		const source = currentSource();
		const summaryRow = document.getElementById(
			'flamingo-options-summary-row'
		);
		const toggleAiText = document.getElementById( 'flamingo-toggle-ai-text' );

		if ( 'native' === type ) {
			summaryRow.innerHTML =
				'<span>Source</span><b>Inbox AI CSV upload — ' +
				detected.native.count +
				' row(s)</b>';
			toggleAiText.textContent =
				'Run AI analysis on rows with no category/priority of their own';
		} else {
			summaryRow.innerHTML =
				'live' === source
					? '<span>Source</span><b>Live Flamingo data — ' +
					  detected.live.messages +
					  ' message(s)</b>'
					: '<span>Source</span><b>CSV upload — ' +
					  detected.csv.count +
					  ' message(s)</b>';
			toggleAiText.textContent = 'Run AI analysis on imported messages';
		}

		goStep( 3 );
	} );

	const back3 = document.getElementById( 'flamingo-back-3' );
	if ( back3 ) {
		back3.addEventListener( 'click', () => goStep( 2 ) );
	}

	const next3 = document.getElementById( 'flamingo-next-3' );
	if ( next3 ) {
		next3.addEventListener( 'click', () => {
			const type = currentType();
			const source = currentSource();
			const sourceEl = document.getElementById( 'flamingo-summary-source' );

			if ( 'native' === type ) {
				sourceEl.textContent = 'Inbox AI CSV upload';
				document.getElementById( 'flamingo-summary-messages' ).textContent =
					String( detected.native.count );
			} else {
				sourceEl.textContent =
					'live' === source
						? 'Flamingo (live data)'
						: 'Flamingo (CSV upload)';
				document.getElementById( 'flamingo-summary-messages' ).textContent =
					String(
						'live' === source ? detected.live.messages : detected.csv.count
					);
			}

			goStep( 4 );
		} );
	}

	const back4 = document.getElementById( 'flamingo-back-4' );
	if ( back4 ) {
		back4.addEventListener( 'click', () => goStep( 3 ) );
	}

	const startBtn = document.getElementById( 'flamingo-start-import-btn' );
	if ( startBtn ) {
		startBtn.addEventListener( 'click', () => {
			const type = currentType();
			const modalBody = document.getElementById( 'flamingo-modal-body' );

			modalBody.textContent =
				'native' === type
					? 'This will import every row in the uploaded CSV into AI Inbox as new submissions, and optionally queue AI analysis for rows that didn\'t already include their own category/priority.'
					: 'This will import the detected Flamingo messages into AI Inbox, and optionally run AI analysis on each one. Original Flamingo entries are left untouched.';

			openModal( 'import-modal-overlay' );
		} );
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
				.classList.contains( 'inboxai-is-on' );

			runImport( currentSource(), runAi )
				.then( ( totalImported ) => {
					document.getElementById(
						'flamingo-progress-label'
					).textContent = 'Import complete';
					document.getElementById(
						'flamingo-complete-summary'
					).textContent =
						totalImported + ' row(s) were imported successfully.';
					showToast(
						totalImported + ' rows imported',
						'success'
					);
					setTimeout( () => goStep( 5 ), 400 );
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
	 * resolves with the total number of rows actually imported.
	 *
	 * @param {string}  source "live", "csv", or "native" — see {@see currentSource}.
	 * @param {boolean} runAi  Whether imported rows should be queued for AI
	 *                         analysis.
	 * @return {Promise<number>}
	 */
	function runImport( source, runAi ) {
		let action;
		let total;
		let baseArgs;

		if ( 'native' === source ) {
			action = 'inboxai_native_csv_import_batch';
			total = detected.native.count;
			baseArgs = { token: detected.native.token, run_ai: runAi ? 1 : 0 };
		} else if ( 'csv' === source ) {
			action = 'inboxai_flamingo_import_csv_batch';
			total = detected.csv.count;
			baseArgs = { token: detected.csv.token, run_ai: runAi ? 1 : 0 };
		} else {
			action = 'inboxai_flamingo_import_batch';
			total = detected.live.messages;
			baseArgs = { run_ai: runAi ? 1 : 0 };
		}

		let importedSoFar = 0;

		return new Promise( ( resolve, reject ) => {
			const step = ( offset ) => {
				inboxaiAjax( action, { offset, ...baseArgs } )
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
