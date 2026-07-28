/* =====================================================================
   Inbox AI — Campaigns page script (campaigns list + new-campaign
   wizard — these two views share one page/URL, switched via JS, since
   the wizard is a drill-down of the list rather than a separate
   top-level destination — same pattern as inbox.js's list/detail split).
   Requires common.js to be loaded first (messages data + shared helpers).

   NOTE: This is a static mockup only, scaffolding the Campaigns feature
   ahead of real backend work. All campaign data below is in-memory mock
   data (mirrors the pattern used by contacts.js/analytics.js) and is not
   persisted or wired to any AJAX endpoint yet.
   ===================================================================== */

const campaigns = [
	{ id: 1, name: 'Q3 Enterprise Sales Update', audienceType: 'category', audienceValue: 'Sales', recipientCount: 2, status: 'sent', openRate: 65, sentDate: 'Jul 12, 2026', subject: 'New features for our enterprise customers' },
	{ id: 2, name: 'Support Satisfaction Check-in', audienceType: 'form', audienceValue: 'Support Form', recipientCount: 3, status: 'sent', openRate: 48, sentDate: 'Jul 5, 2026', subject: "How did we do on your last support request?" }
];
let campaignIdCounter = 3;

const state = {
	campaignStep: 1,
	campaignAudienceType: 'all',
	campaignAudienceValue: '',
	deletedContacts: new Set(),
	emailProvider: { key: 'sendgrid', name: 'SendGrid', connected: false }
};

/* ================= IN-PAGE SCREEN SWITCH (list / wizard) ============= */
function showCampaignScreen( key ) {
	document.querySelectorAll( '.inboxai-screen' ).forEach( ( s ) => s.classList.remove( 'inboxai-is-active' ) );
	const el = document.getElementById( 'screen-' + key );
	if ( el ) el.classList.add( 'inboxai-is-active' );
	document.getElementById( 'main' ).scrollTo( { top: 0, behavior: 'instant' } );
	window.scrollTo( { top: 0, behavior: 'instant' } );
}

/* Clicking "Campaigns" in the top nav while already on this page should
   just return to the list instead of doing a full page reload. Both
   screens carry their own copy of the nav bar, so this binds via the
   shared class rather than an id (mirrors inbox.js's .js-nav-inbox). */
document.querySelectorAll( '.js-nav-campaigns' ).forEach( function ( navCampaignsLink ) {
	navCampaignsLink.addEventListener( 'click', function ( e ) {
		e.preventDefault();
		showCampaignScreen( 'campaigns' );
		renderCampaigns();
	} );
} );

/* ================= CAMPAIGNS: AUDIENCE ================= */
function distinctValues( field ) {
	const set = new Set();
	messages.forEach( ( m ) => {
		if ( ! state.deletedContacts.has( m.email ) ) set.add( m[ field ] );
	} );
	return Array.from( set ).sort();
}
function contactsForAudience() {
	const byEmail = {};
	messages.forEach( ( m ) => {
		if ( ! byEmail[ m.email ] ) byEmail[ m.email ] = { name: m.name, email: m.email, category: m.category, priority: m.priority, form: m.form, status: m.status };
	} );
	return Object.values( byEmail ).filter( ( c ) => ! state.deletedContacts.has( c.email ) );
}
function computeAudience( type, value ) {
	if ( type === 'all' ) return contactsForAudience();
	const seen = new Set();
	const list = [];
	messages.forEach( ( m ) => {
		if ( state.deletedContacts.has( m.email ) ) return;
		const match = ( type === 'category' && m.category === value ) || ( type === 'form' && m.form === value ) || ( type === 'priority' && m.priority === value ) || ( type === 'status' && m.status === value );
		if ( match && ! seen.has( m.email ) ) {
			seen.add( m.email );
			list.push( m );
		}
	} );
	return list;
}
const AUDIENCE_LABELS = {
	category: { label: 'Category', field: 'category' },
	form: { label: 'Form', field: 'form' },
	priority: { label: 'Priority', field: 'priority' },
	status: { label: 'Status', field: 'status' }
};
function updateCampaignSegmentOptions() {
	const wrap = document.getElementById( 'campaign-segment-value-wrap' );
	const type = state.campaignAudienceType;
	if ( type === 'all' ) {
		wrap.style.display = 'none';
		state.campaignAudienceValue = '';
		updateCampaignAudienceCount();
		return;
	}
	wrap.style.display = '';
	const meta = AUDIENCE_LABELS[ type ];
	document.getElementById( 'campaign-segment-label' ).textContent = meta.label;
	const values = distinctValues( meta.field );
	const select = document.getElementById( 'campaign-segment-value' );
	select.innerHTML = values.map( ( v ) => '<option value="' + v + '">' + v + '</option>' ).join( '' );
	state.campaignAudienceValue = values[ 0 ] || '';
	select.value = state.campaignAudienceValue;
	updateCampaignAudienceCount();
}
function updateCampaignAudienceCount() {
	const list = computeAudience( state.campaignAudienceType, state.campaignAudienceValue );
	document.getElementById( 'campaign-recipient-count' ).textContent = list.length;
	document.getElementById( 'campaign-recipient-label' ).textContent = list.length === 1 ? 'contact matches this audience' : 'contacts match this audience';
	document.getElementById( 'campaign-next-1' ).disabled = list.length === 0;
}
function audienceDescription() {
	if ( state.campaignAudienceType === 'all' ) return 'All contacts';
	const meta = AUDIENCE_LABELS[ state.campaignAudienceType ];
	return meta.label + ': ' + state.campaignAudienceValue;
}

/* ================= CAMPAIGNS: LIST ================= */
function campaignStatusBadge( s ) {
	const map = { draft: [ 'Draft', 'inboxai-status--draft' ], scheduled: [ 'Scheduled', 'inboxai-status--scheduled' ], sending: [ 'Sending', 'inboxai-status--sending' ], sent: [ 'Sent', 'inboxai-status--sent' ] };
	const m = map[ s ] || map.draft;
	return '<span class="inboxai-status ' + m[ 1 ] + '">' + m[ 0 ] + '</span>';
}
function renderCampaigns() {
	const tbody = document.getElementById( 'campaigns-table-body' );
	const hasCampaigns = campaigns.length > 0;
	document.getElementById( 'campaigns-populated' ).style.display = hasCampaigns ? '' : 'none';
	document.getElementById( 'campaigns-empty' ).style.display = hasCampaigns ? 'none' : '';

	tbody.innerHTML = campaigns.map( ( c ) => {
		const audience = c.audienceType === 'all' ? 'All contacts' : ( AUDIENCE_LABELS[ c.audienceType ].label + ': ' + c.audienceValue );
		return '<div class="inboxai-grid-table__row" role="row">'
			+ '<div class="inboxai-grid-table__cell" role="cell"><div><div style="font-weight:600;font-size:13px;">' + c.name + '</div><div style="font-size:11.5px;color:var(--text-tertiary);">' + c.subject + '</div></div></div>'
			+ '<div class="inboxai-grid-table__cell" role="cell">' + audience + '</div>'
			+ '<div class="inboxai-grid-table__cell" role="cell"><span style="font-family:var(--mono);">' + c.recipientCount + '</span></div>'
			+ '<div class="inboxai-grid-table__cell" role="cell">' + campaignStatusBadge( c.status ) + '</div>'
			+ '<div class="inboxai-grid-table__cell" role="cell">' + ( c.status === 'sent' ? '<span style="font-family:var(--mono);color:var(--conf-good);">' + c.openRate + '%</span>' : '<span style="color:var(--text-tertiary);">—</span>' ) + '</div>'
			+ '<div class="inboxai-grid-table__cell" role="cell"><span style="font-family:var(--mono);color:var(--text-secondary);">' + ( c.sentDate || '—' ) + '</span></div>'
			+ '<div class="inboxai-grid-table__cell" role="cell"><div class="inboxai-row-actions"><div class="inboxai-btn--icon" data-action="more" data-key="' + c.id + '" title="More actions"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg></div></div></div>'
			+ '</div>';
	} ).join( '' );

	const sentCampaigns = campaigns.filter( ( c ) => c.status === 'sent' );
	document.getElementById( 'campaigns-kpi-total' ).textContent = campaigns.length;
	document.getElementById( 'campaigns-kpi-sent' ).textContent = campaigns.reduce( ( sum, c ) => sum + ( c.status === 'sent' ? c.recipientCount : 0 ), 0 );
	document.getElementById( 'campaigns-kpi-open' ).textContent = sentCampaigns.length ? Math.round( sentCampaigns.reduce( ( s, c ) => s + c.openRate, 0 ) / sentCampaigns.length ) + '%' : '—';
}
function campaignRowMenuItems() {
	return [
		{ action: 'duplicate', label: 'Duplicate', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>' },
		{ action: 'delete', label: 'Delete', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0-1 14a2 2 0 01-2 2H7a2 2 0 01-2-2L4 6"/><path d="M10 11v6M14 11v6"/></svg>', danger: true }
	];
}

/* ================= CAMPAIGNS: WIZARD ================= */
function resetCampaignWizard() {
	state.campaignStep = 1;
	state.campaignAudienceType = 'all';
	state.campaignAudienceValue = '';
	document.querySelectorAll( '#campaign-panel-1 .inboxai-provider__option' ).forEach( ( o ) => {
		o.classList.remove( 'inboxai-is-selected' );
		o.querySelector( '.inboxai-provider__radio' ).classList.remove( 'inboxai-is-checked' );
	} );
	const allOpt = document.querySelector( '#campaign-panel-1 [data-audience-type="all"]' );
	allOpt.classList.add( 'inboxai-is-selected' );
	allOpt.querySelector( '.inboxai-provider__radio' ).classList.add( 'inboxai-is-checked' );
	updateCampaignSegmentOptions();

	document.getElementById( 'campaign-from-name' ).value = 'Aminul Islam';
	document.getElementById( 'campaign-from-email' ).value = 'aminul@yourcompany.com';
	document.getElementById( 'campaign-subject' ).value = '';
	document.getElementById( 'campaign-body' ).innerText = '';
	document.getElementById( 'campaign-send-rate' ).value = '5000';
	updateCampaignProviderDisplay();
	document.getElementById( 'campaign-send-now-toggle' ).classList.add( 'inboxai-is-on' );
	document.getElementById( 'campaign-schedule-row' ).style.display = 'none';
	document.getElementById( 'campaign-consent-toggle' ).classList.remove( 'inboxai-is-on' );
	document.getElementById( 'campaign-consent-warning' ).style.display = 'none';
	document.getElementById( 'campaign-progress-wrap' ).style.display = 'none';
	document.getElementById( 'campaign-progress-fill' ).style.width = '0%';
	document.getElementById( 'campaign-progress-pct' ).textContent = '0%';
	const sendBtn = document.getElementById( 'campaign-send-btn' );
	sendBtn.disabled = false;
	sendBtn.style.display = '';
	document.getElementById( 'campaign-back-4' ).style.display = '';

	goCampaignStep( 1 );
}
function goCampaignStep( n ) {
	state.campaignStep = n;
	for ( let i = 1; i <= 5; i++ ) {
		const panel = document.getElementById( 'campaign-panel-' + i );
		if ( panel ) panel.style.display = ( i === n ) ? '' : 'none';
	}
	document.querySelectorAll( '#screen-campaign-new .inboxai-wizard__step' ).forEach( ( el ) => {
		const step = parseInt( el.dataset.wizardStep, 10 );
		el.classList.toggle( 'inboxai-is-active', step === n );
		el.classList.toggle( 'inboxai-is-done', step < n );
	} );
	document.querySelectorAll( '#screen-campaign-new .inboxai-wizard__line' ).forEach( ( el, idx ) => {
		el.classList.toggle( 'inboxai-is-done', ( idx + 1 ) < n );
	} );
	document.getElementById( 'main' ).scrollTo( { top: 0, behavior: 'smooth' } );
}
function fillCampaignReview() {
	const list = computeAudience( state.campaignAudienceType, state.campaignAudienceValue );
	const rate = parseInt( document.getElementById( 'campaign-send-rate' ).value, 10 );
	const sendNow = document.getElementById( 'campaign-send-now-toggle' ).classList.contains( 'inboxai-is-on' );
	const consent = document.getElementById( 'campaign-consent-toggle' ).classList.contains( 'inboxai-is-on' );

	document.getElementById( 'campaign-review-audience' ).textContent = audienceDescription();
	document.getElementById( 'campaign-review-count' ).textContent = list.length;
	document.getElementById( 'campaign-review-from' ).textContent = document.getElementById( 'campaign-from-name' ).value + ' <' + document.getElementById( 'campaign-from-email' ).value + '>';
	document.getElementById( 'campaign-review-subject' ).textContent = document.getElementById( 'campaign-subject' ).value || '(no subject)';
	document.getElementById( 'campaign-review-rate' ).textContent = rate ? rate.toLocaleString() + ' / day' : 'No limit';
	document.getElementById( 'campaign-review-provider' ).textContent = state.emailProvider.connected ? state.emailProvider.name : 'Not connected';
	const days = rate ? Math.max( 1, Math.ceil( list.length / rate ) ) : 1;
	document.getElementById( 'campaign-review-eta' ).textContent = days <= 1 ? 'Same day' : days + ' days (batched)';
	document.getElementById( 'campaign-review-when' ).textContent = sendNow ? 'Immediately' : ( document.getElementById( 'campaign-schedule-date' ).value || 'Not scheduled' ) + ' at ' + document.getElementById( 'campaign-schedule-time' ).value;
	document.getElementById( 'campaign-review-preview' ).textContent = ( document.getElementById( 'campaign-body' ).innerText || '' ).slice( 0, 140 ) + '…';
	document.getElementById( 'campaign-consent-warning' ).style.display = ( state.campaignAudienceType !== 'all' && ! consent ) ? 'block' : 'none';
}
function updateCampaignProviderDisplay() {
	const nameEl = document.getElementById( 'campaign-provider-name' );
	const hintEl = document.getElementById( 'campaign-provider-hint' );
	const wrap = document.getElementById( 'campaign-provider-status' );
	if ( state.emailProvider.connected ) {
		nameEl.textContent = state.emailProvider.name + ' — Connected';
		nameEl.style.color = 'var(--conf-good)';
		wrap.style.borderColor = '#BEE7CD';
		wrap.style.background = 'var(--conf-good-soft)';
		hintEl.textContent = 'Bulk sends will go out through ' + state.emailProvider.name + '.';
	} else {
		nameEl.textContent = 'Not connected';
		nameEl.style.color = 'var(--urgent)';
		wrap.style.borderColor = '#F3C6C6';
		wrap.style.background = 'var(--urgent-soft)';
		hintEl.textContent = 'Connect an email provider in Settings before sending a campaign.';
	}
}

/* ================= CAMPAIGN RICH TEXT FORMATTING ================= */
function applyCampaignFormat( cmd, value ) {
	document.getElementById( 'campaign-body' ).focus();
	document.execCommand( cmd, false, value || null );
}
[ [ 'campaign-fmt-bold', 'bold' ], [ 'campaign-fmt-italic', 'italic' ], [ 'campaign-fmt-underline', 'underline' ], [ 'campaign-fmt-list', 'insertUnorderedList' ] ].forEach( ( [ id, cmd ] ) => {
	const el = document.getElementById( id );
	el.addEventListener( 'mousedown', ( e ) => e.preventDefault() );
	el.addEventListener( 'click', () => applyCampaignFormat( cmd ) );
} );
document.getElementById( 'campaign-fmt-link' ).addEventListener( 'mousedown', ( e ) => e.preventDefault() );
document.getElementById( 'campaign-fmt-link' ).addEventListener( 'click', function () {
	const url = prompt( 'Link URL:', 'https://' );
	if ( url ) applyCampaignFormat( 'createLink', url );
} );

/* ================= EVENT WIRING ================= */
document.getElementById( 'new-campaign-btn' ).addEventListener( 'click', function () {
	resetCampaignWizard();
	showCampaignScreen( 'campaign-new' );
} );
document.getElementById( 'campaigns-empty-new-btn' ).addEventListener( 'click', function () {
	resetCampaignWizard();
	showCampaignScreen( 'campaign-new' );
} );
document.getElementById( 'campaign-new-another-btn' ).addEventListener( 'click', resetCampaignWizard );
document.getElementById( 'campaign-back-to-list-btn' ).addEventListener( 'click', function () {
	showCampaignScreen( 'campaigns' );
	renderCampaigns();
} );

document.getElementById( 'campaign-segment-value' ).addEventListener( 'change', function () {
	state.campaignAudienceValue = this.value;
	updateCampaignAudienceCount();
} );
document.getElementById( 'campaign-next-1' ).addEventListener( 'click', () => goCampaignStep( 2 ) );
document.getElementById( 'campaign-back-2' ).addEventListener( 'click', () => goCampaignStep( 1 ) );
document.getElementById( 'campaign-next-2' ).addEventListener( 'click', function () {
	if ( ! document.getElementById( 'campaign-subject' ).value.trim() ) {
		showToast( 'Add a subject before continuing' );
		return;
	}
	goCampaignStep( 3 );
} );
document.getElementById( 'campaign-back-3' ).addEventListener( 'click', () => goCampaignStep( 2 ) );
document.getElementById( 'campaign-next-3' ).addEventListener( 'click', function () {
	if ( ! state.emailProvider.connected ) {
		showToast( 'No email provider connected — you can still review, but sending will be blocked', 'error' );
	}
	fillCampaignReview();
	goCampaignStep( 4 );
} );
document.getElementById( 'campaign-back-4' ).addEventListener( 'click', () => goCampaignStep( 3 ) );

document.getElementById( 'campaign-send-now-toggle' ).addEventListener( 'click', function () {
	setTimeout( () => {
		const isOn = this.classList.contains( 'inboxai-is-on' );
		document.getElementById( 'campaign-schedule-row' ).style.display = isOn ? 'none' : 'flex';
	}, 0 );
} );

document.querySelectorAll( '#campaign-panel-1 [data-audience-type]' ).forEach( ( opt ) => {
	opt.addEventListener( 'click', function () {
		document.querySelectorAll( '#campaign-panel-1 .inboxai-provider__option' ).forEach( ( o ) => {
			o.classList.remove( 'inboxai-is-selected' );
			o.querySelector( '.inboxai-provider__radio' ).classList.remove( 'inboxai-is-checked' );
		} );
		this.classList.add( 'inboxai-is-selected' );
		this.querySelector( '.inboxai-provider__radio' ).classList.add( 'inboxai-is-checked' );
		state.campaignAudienceType = this.dataset.audienceType;
		updateCampaignSegmentOptions();
	} );
} );

document.getElementById( 'campaign-send-btn' ).addEventListener( 'click', function () {
	if ( ! state.emailProvider.connected ) {
		showToast( 'Connect an email provider in Settings before sending', 'error' );
		return;
	}
	const consent = document.getElementById( 'campaign-consent-toggle' ).classList.contains( 'inboxai-is-on' );
	if ( state.campaignAudienceType !== 'all' && ! consent ) {
		document.getElementById( 'campaign-consent-warning' ).style.display = 'block';
		showToast( 'Confirm recipient consent before sending', 'error' );
		return;
	}
	const list = computeAudience( state.campaignAudienceType, state.campaignAudienceValue );
	const rate = parseInt( document.getElementById( 'campaign-send-rate' ).value, 10 );
	const sendNow = document.getElementById( 'campaign-send-now-toggle' ).classList.contains( 'inboxai-is-on' );

	if ( ! sendNow ) {
		const newCampaign = {
			id: campaignIdCounter++, name: ( document.getElementById( 'campaign-subject' ).value || 'Untitled campaign' ),
			audienceType: state.campaignAudienceType, audienceValue: state.campaignAudienceValue,
			recipientCount: list.length, status: 'scheduled', openRate: 0,
			sentDate: ( document.getElementById( 'campaign-schedule-date' ).value || 'Scheduled' ),
			subject: document.getElementById( 'campaign-subject' ).value
		};
		campaigns.unshift( newCampaign );
		renderCampaigns();
		document.getElementById( 'campaign-complete-title' ).textContent = 'Campaign Scheduled';
		document.getElementById( 'campaign-complete-summary' ).textContent = 'Your campaign will be sent to ' + list.length + ' recipient' + ( list.length === 1 ? '' : 's' ) + ' at the scheduled time.';
		goCampaignStep( 5 );
		return;
	}

	this.disabled = true;
	document.getElementById( 'campaign-back-4' ).style.display = 'none';
	document.getElementById( 'campaign-progress-wrap' ).style.display = 'block';
	showToast( 'Sending campaign…' );

	let pct = 0;
	const fill = document.getElementById( 'campaign-progress-fill' );
	const pctLabel = document.getElementById( 'campaign-progress-pct' );
	const interval = setInterval( () => {
		pct = Math.min( 100, pct + Math.round( 10 + Math.random() * 18 ) );
		fill.style.width = pct + '%';
		pctLabel.textContent = pct + '%';
		if ( pct >= 100 ) {
			clearInterval( interval );
			const days = rate ? Math.max( 1, Math.ceil( list.length / rate ) ) : 1;
			const newCampaign = {
				id: campaignIdCounter++, name: ( document.getElementById( 'campaign-subject' ).value || 'Untitled campaign' ),
				audienceType: state.campaignAudienceType, audienceValue: state.campaignAudienceValue,
				recipientCount: list.length, status: days > 1 ? 'sending' : 'sent', openRate: days > 1 ? 0 : Math.round( 35 + Math.random() * 40 ),
				sentDate: 'Jul 25, 2026',
				subject: document.getElementById( 'campaign-subject' ).value
			};
			campaigns.unshift( newCampaign );
			renderCampaigns();
			document.getElementById( 'campaign-complete-title' ).textContent = days > 1 ? 'Campaign Sending' : 'Campaign Sent';
			document.getElementById( 'campaign-complete-summary' ).textContent = days > 1
				? ( 'Sending to ' + list.length + ' recipients in batches of ' + rate.toLocaleString() + '/day — expected to finish in ' + days + ' days.' )
				: ( 'Your email was sent to ' + list.length + ' recipient' + ( list.length === 1 ? '' : 's' ) + '.' );
			showToast( 'Campaign sent to ' + list.length + ' recipients', 'success' );
			setTimeout( () => goCampaignStep( 5 ), 500 );
		}
	}, 220 );
} );

/* ================= ROW MENU (list screen) ================= */
document.addEventListener( 'click', function ( e ) {
	const menuItemEl = e.target.closest( '.inboxai-row-menu__item[data-menu-action]' );
	if ( menuItemEl ) {
		const action = menuItemEl.dataset.menuAction;
		const cId = parseInt( menuItemEl.dataset.key, 10 );
		closeRowMenu();
		const c = campaigns.find( ( x ) => x.id === cId );
		if ( ! c ) return;
		if ( action === 'duplicate' ) {
			const copy = Object.assign( {}, c, { id: campaignIdCounter++, name: c.name + ' (Copy)', status: 'draft', sentDate: null, openRate: 0 } );
			campaigns.unshift( copy );
			renderCampaigns();
			showToast( 'Campaign duplicated', 'success' );
		} else if ( action === 'delete' ) {
			const idx = campaigns.findIndex( ( x ) => x.id === cId );
			if ( idx > -1 ) campaigns.splice( idx, 1 );
			renderCampaigns();
			showToast( 'Campaign deleted' );
		}
		return;
	}

	const actionEl = e.target.closest( '[data-action="more"]' );
	if ( actionEl ) {
		e.stopPropagation();
		openRowMenu( actionEl, 'campaign', actionEl.dataset.key );
		return;
	}
} );

/* ================= INIT ================= */
updateCampaignSegmentOptions();
renderCampaigns();

/* Deep link support: campaign.html?new=1 opens the wizard directly
   (mirrors inbox.html's ?search= deep link from the row-menu "view"
   action elsewhere in the app). */
if ( getQueryParam( 'new' ) === '1' ) {
	resetCampaignWizard();
	showCampaignScreen( 'campaign-new' );
}
