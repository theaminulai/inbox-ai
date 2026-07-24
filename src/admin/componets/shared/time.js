/**
 * Relative-time formatting for `created_at`-style MySQL datetimes (stored/
 * returned in the site's local time by `current_time( 'mysql' )` — see
 * every `Database\*Repository` write). No timezone conversion is needed
 * here for the same reason.
 */

/**
 * @param {string} mysqlDatetime e.g. `2026-07-20 14:32:00`.
 * @return {string} e.g. `2h ago`, `18m ago`, `3d ago`, or the original
 *                   string if it can't be parsed.
 */
export function timeAgo( mysqlDatetime ) {
	if ( ! mysqlDatetime ) {
		return '—';
	}

	// Safari/older engines don't reliably parse "Y-m-d H:i:s" — normalize to
	// an ISO-ish string first.
	const parsed = new Date( mysqlDatetime.replace( ' ', 'T' ) );

	if ( isNaN( parsed.getTime() ) ) {
		return mysqlDatetime;
	}

	const seconds = Math.max(
		0,
		Math.floor( ( Date.now() - parsed.getTime() ) / 1000 )
	);

	if ( seconds < 60 ) {
		return 'just now';
	}

	const minutes = Math.floor( seconds / 60 );

	if ( minutes < 60 ) {
		return minutes + 'm ago';
	}

	const hours = Math.floor( minutes / 60 );

	if ( hours < 24 ) {
		return hours + 'h ago';
	}

	const days = Math.floor( hours / 24 );

	return days + 'd ago';
}

/**
 * @param {string} mysqlDatetime
 * @return {string} A longer, absolute format for detail screens, e.g.
 *                   `Jul 20, 2026 at 2:14 PM`.
 */
export function formatDateTime( mysqlDatetime ) {
	if ( ! mysqlDatetime ) {
		return '—';
	}

	const parsed = new Date( mysqlDatetime.replace( ' ', 'T' ) );

	if ( isNaN( parsed.getTime() ) ) {
		return mysqlDatetime;
	}

	return parsed.toLocaleString( undefined, {
		month: 'short',
		day: 'numeric',
		year: 'numeric',
		hour: 'numeric',
		minute: '2-digit',
	} );
}
