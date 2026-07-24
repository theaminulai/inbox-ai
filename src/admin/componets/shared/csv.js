/**
 * CSV export helper, ported from the static mockup's `common.js`
 * (`csvEscape`/`downloadCsv`).
 */

function csvEscape( val ) {
	const s = String( null === val || undefined === val ? '' : val );

	return /[",\n]/.test( s ) ? '"' + s.replace( /"/g, '""' ) + '"' : s;
}

/**
 * @param {string}          filename
 * @param {Array<string>}   headers
 * @param {Array<Array<*>>} rows
 */
export function downloadCsv( filename, headers, rows ) {
	const lines = [ headers.map( csvEscape ).join( ',' ) ].concat(
		rows.map( ( r ) => r.map( csvEscape ).join( ',' ) )
	);
	const blob = new Blob( [ lines.join( '\r\n' ) ], {
		type: 'text/csv;charset=utf-8;',
	} );
	const url = URL.createObjectURL( blob );
	const a = document.createElement( 'a' );
	a.href = url;
	a.download = filename;
	document.body.appendChild( a );
	a.click();
	a.remove();
	URL.revokeObjectURL( url );
}
