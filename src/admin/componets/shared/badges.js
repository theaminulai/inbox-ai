/**
 * Priority/status/confidence badge rendering, ported from the static
 * mockup's `common.js` (`priorityBadgeHtml`, `statusBadgeHtml`,
 * `confidenceCellHtml`, `setPriorityBadge`, `setStatusBadge`). Used by both
 * the AI Inbox List table (`componets/inbox/list.js`) and the Submission
 * Detail screen (`componets/inbox/detail.js`).
 */

const PRIORITY_MAP = {
	urgent: [ 'Urgent', 'var(--urgent)', 'inboxai-badge--urgent' ],
	high: [ 'High', 'var(--high)', 'inboxai-badge--high' ],
	normal: [ 'Normal', 'var(--normal)', 'inboxai-badge--normal' ],
	low: [ 'Low', 'var(--low)', 'inboxai-badge--low' ],
};

const STATUS_MAP = {
	new: [ 'New', 'inboxai-status--new' ],
	review: [ 'Needs Review', 'inboxai-status--review' ],
	reviewed: [ 'Reviewed', 'inboxai-status--reviewed' ],
	drafted: [ 'Drafted', 'inboxai-status--drafted' ],
	replied: [ 'Replied', 'inboxai-status--replied' ],
	failed: [ 'Failed', 'inboxai-status--failed' ],
	archived: [ 'Archived', 'inboxai-status--archived' ],
};

/**
 * @param {string} priority `urgent`|`high`|`normal`|`low`.
 * @return {string} HTML for a `<span class="inboxai-badge">`.
 */
export function priorityBadgeHtml( priority ) {
	const [ label, color, cssClass ] =
		PRIORITY_MAP[ priority ] || PRIORITY_MAP.normal;

	return (
		'<span class="inboxai-badge ' +
		cssClass +
		'"><span class="inboxai-badge__dot" style="background:' +
		color +
		';"></span>' +
		label +
		'</span>'
	);
}

/**
 * @param {string} status One of the `inboxai_messages.workflow_status` values.
 * @return {string} HTML for a `<span class="inboxai-status">`.
 */
export function statusBadgeHtml( status ) {
	const [ label, cssClass ] = STATUS_MAP[ status ] || STATUS_MAP.new;

	return (
		'<span class="inboxai-status ' +
		cssClass +
		'">' +
		label +
		'</span>'
	);
}

/**
 * Writes a priority badge into an existing `<span id="elId">` element
 * in-place (used on the Submission Detail screen's page header/AI Analysis
 * card, where the element already exists in the template).
 *
 * @param {string} elId
 * @param {string} priority
 */
export function setPriorityBadge( elId, priority ) {
	const el = document.getElementById( elId );

	if ( ! el ) {
		return;
	}

	const [ label, color, cssClass ] =
		PRIORITY_MAP[ priority ] || PRIORITY_MAP.normal;

	el.className = 'inboxai-badge ' + cssClass;
	el.innerHTML =
		'<span class="inboxai-badge__dot" style="background:' +
		color +
		';"></span>' +
		label;
}

/**
 * @param {string} elId
 * @param {string} status
 */
export function setStatusBadge( elId, status ) {
	const el = document.getElementById( elId );

	if ( ! el ) {
		return;
	}

	const [ label, cssClass ] = STATUS_MAP[ status ] || STATUS_MAP.new;

	el.className = 'inboxai-status ' + cssClass;
	el.textContent = label;
}

/**
 * @param {number|null} confidence 0-100, or null/undefined when a message
 *                                 has no AI analysis yet (failed/pending).
 * @return {string} HTML for the confidence bar cell.
 */
export function confidenceCellHtml( confidence ) {
	if ( null === confidence || undefined === confidence ) {
		return (
			'<div class="inboxai-confidence"><div class="inboxai-confidence__value" style="color:var(--text-tertiary);">—</div>' +
			'<div class="inboxai-confidence__track"><div class="inboxai-confidence__fill" style="width:0%;"></div></div></div>'
		);
	}

	const color =
		confidence >= 70
			? 'var(--conf-good)'
			: confidence >= 40
			? 'var(--conf-mid)'
			: 'var(--conf-low)';
	const warn =
		confidence < 70
			? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>'
			: '';

	return (
		'<div class="inboxai-confidence"><div class="inboxai-confidence__value" style="color:' +
		color +
		';">' +
		warn +
		confidence +
		'%</div><div class="inboxai-confidence__track"><div class="inboxai-confidence__fill" style="width:' +
		confidence +
		'%;background:' +
		color +
		';"></div></div></div>'
	);
}
