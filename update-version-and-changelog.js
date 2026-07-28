/* eslint-disable no-console */

/**
 * External dependencies
 */
const fs = require( 'fs' );
const core = require( '@actions/core' );
const simpleGit = require( 'simple-git' );
const { promisify } = require( 'util' );
const exec = promisify( require( 'child_process' ).exec );

const git = simpleGit.default();

const releaseType = process.env.RELEASE_TYPE;
const VALID_RELEASE_TYPES = [ 'major', 'minor', 'patch' ];

// Whether a given tag actually exists in this repo's history yet.
async function tagExists( tag ) {
	try {
		const tagsResult = await git.tags();
		return tagsResult.all.includes( tag );
	} catch ( error ) {
		return false;
	}
}

// To get the merges since the last (previous) tag
async function getChangesSinceLastTag() {
	try {
		// Fetch all tags, sorted by creation date
		const tagsResult = await git.tags( {
			'--sort': '-creatordate',
		} );
		const tags = tagsResult.all;
		if ( tags.length === 0 ) {
			// No previous tag exists yet — this is the first-ever release,
			// so there's no baseline to diff against. Fall back to the
			// full commit history instead of crashing/returning null (which
			// would blow up later at `changes.all.map(...)`).
			console.info(
				'ℹ️ No previous tags found — treating this as the first release and using the full commit history.'
			);
			return await git.log();
		}
		const previousTag = tags[ 0 ]; // The most recent tag

		// Now get the changes since this tag
		const changes = await git.log( [ `${ previousTag }..HEAD` ] );
		return changes;
	} catch ( error ) {
		throw error;
	}
}

// To know if there are changes since the last tag.
// we are not using getChangesSinceGitTag because it returns the just the merges and not the commits.
// So for example if a hotfix was committed directly to trunk this function will detect it but getChangesSinceGitTag will not.
async function getHasChangesSinceGitTag( tag ) {
	if ( ! ( await tagExists( tag ) ) ) {
		// The tag doesn't exist yet (e.g. the very first release, so there's
		// no prior `v*` tag to compare against). `git log a...b` requires
		// both sides to be valid revisions, so running it against a
		// non-existent tag throws "ambiguous argument" instead of just
		// telling us there's nothing to compare. Treat "any commits exist
		// at all" as "yes, there are changes to release" in that case.
		console.info(
			`ℹ️ Tag ${ tag } does not exist yet — treating this as the first release.`
		);
		const allCommits = await git.log();
		return ( allCommits?.all?.length ?? 0 ) > 0;
	}

	const changes = await git.log( [ `HEAD...${ tag }` ] );
	return changes?.all?.length > 0;
}

async function updateVersion() {
	if ( ! VALID_RELEASE_TYPES.includes( releaseType ) ) {
		console.error(
			'❌ Error: Release type is not valid. Valid release types are: major, minor, patch.'
		);
		process.exit( 1 );
	}

	if (
		! fs.existsSync( './package.json' ) ||
		! fs.existsSync( './package-lock.json' )
	) {
		console.error( '❌ Error: package.json or lock file not found.' );
		process.exit( 1 );
	}

	if ( ! fs.existsSync( './readme.txt' ) ) {
		console.error( '❌ Error: readme.txt file not found.' );
		process.exit( 1 );
	}

	if ( ! fs.existsSync( './inbox-ai.php' ) ) {
		console.error( '❌ Error: inbox-ai.php file not found.' );
		process.exit( 1 );
	}

	// get changes since last tag
	let changes = [];
	try {
		changes = await getChangesSinceLastTag();
	} catch ( error ) {
		console.error(
			`❌ Error: failed to get changes since last tag: ${ error }`
		);
		process.exit( 1 );
	}

	const packageJson = require( './package.json' );
	const currentVersion = packageJson.version;

	// version bump package.json and package-lock.json using npm
	const { stdout, stderr } = await exec(
		`npm version --commit-hooks false --git-tag-version false ${ releaseType }`
	);
	if ( stderr ) {
		console.error( `❌ Error: failed to bump the version."` );
		process.exit( 1 );
	}

	const currentTag = `v${ currentVersion }`;
	const newTag = stdout.trim();
	const newVersion = newTag.replace( 'v', '' );
	const hasChangesSinceGitTag = await getHasChangesSinceGitTag( currentTag );

	// check if there are any changes
	if ( ! hasChangesSinceGitTag ) {
		console.error(
			`❌ No changes since last tag (${ currentTag }). There is nothing new to release.`
		);
		// revert version update
		await exec(
			`npm version --commit-hooks false --git-tag-version false ${ currentVersion }`
		);
		process.exit( 1 );
	}

	console.info( '✅ Package.json version updated', currentTag, '=>', newTag );

	// update readme.txt version with the new changelog
	const readme = fs.readFileSync( './readme.txt', 'utf8' );
	const capitalizeFirstLetter = ( string ) =>
		string.charAt( 0 ).toUpperCase() + string.slice( 1 );

	const changelogChanges = changes.all
		.map(
			( change ) =>
				`* ${ capitalizeFirstLetter( change.message || change.body ) }`
		)
		.join( '\n' );
	const newChangelog = `== Changelog ==\n\n= ${ newVersion } =\n${ changelogChanges }`;
	let newReadme = readme.replace( '== Changelog ==', newChangelog );
	// update version in readme.txt
	newReadme = newReadme.replace(
		/Stable tag: (.*)/,
		`Stable tag: ${ newVersion }`
	);
	fs.writeFileSync( './readme.txt', newReadme );
	console.info( '✅  Readme version updated', currentTag, '=>', newTag );

	// update inbox-ai.php version
	const pluginPhpFile = fs.readFileSync( './inbox-ai.php', 'utf8' );
	const newPluginPhpFile = pluginPhpFile.replace(
		/Version: (.*)/,
		`Version: ${ newVersion }`
	);
	fs.writeFileSync( './inbox-ai.php', newPluginPhpFile );
	console.info(
		'✅  inbox-ai.php file version updated',
		currentTag,
		'=>',
		newTag
	);

	// output data to be used by the next steps of the github action
	core.setOutput( 'NEW_VERSION', newVersion );
	core.setOutput( 'NEW_TAG', newTag );
	core.setOutput( 'CHANGELOG', changelogChanges );
}

updateVersion();
