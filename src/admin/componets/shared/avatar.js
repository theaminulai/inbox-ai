/**
 * Avatar initials/color for a customer, since (unlike the static mockup's
 * hand-authored mock data) real rows don't carry a pre-computed `initials`/
 * `color` pair — both are derived from the sender's name/email on the fly.
 * Shared between the AI Inbox List table and the Submission Detail screen.
 */

const PALETTE = [
	'#3A5CF6',
	'#D93B3B',
	'#1F9254',
	'#DA8A2E',
	'#6B4CE6',
	'#9AA1AC',
];

/**
 * @param {string} name
 * @return {string} Up to two uppercase initials, or `?` if `name` is empty.
 */
export function avatarInitials( name ) {
	const trimmed = ( name || '' ).trim();

	if ( '' === trimmed ) {
		return '?';
	}

	const parts = trimmed.split( /\s+/ );

	return (
		parts.length > 1
			? parts[ 0 ][ 0 ] + parts[ parts.length - 1 ][ 0 ]
			: parts[ 0 ].slice( 0, 2 )
	).toUpperCase();
}

/**
 * A stable (same seed always returns the same color) but arbitrary color
 * pick from a small fixed palette, so the same customer's avatar looks the
 * same across renders without needing to store a color anywhere.
 *
 * @param {string} seed Typically the sender's email.
 * @return {string} CSS color.
 */
export function avatarColor( seed ) {
	const str = seed || '';
	let hash = 0;

	for ( let i = 0; i < str.length; i++ ) {
		hash = ( hash << 5 ) - hash + str.charCodeAt( i );
		hash |= 0;
	}

	return PALETTE[ Math.abs( hash ) % PALETTE.length ];
}
