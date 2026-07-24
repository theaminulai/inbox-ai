/**
 * WordPress dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

/**
 * External dependencies
 */
const RemoveEmptyScriptsPlugin = require( 'webpack-remove-empty-scripts' );
const path = require('path');

/* CF7 AI Inbox for Contact Form 7 — webpack configuration.
 *
 * Extends the default @wordpress/scripts webpack config with two entry points:
 *
 *  1. JS bundle  — src/admin/admin.js         → assets/js/admin.js
 *  2. CSS bundle — src/admin/admin.scss → assets/css/admin.css
 */

const rootDir = process.cwd();

module.exports = {
	...defaultConfig,

	devtool: false,

	entry: {
		'build/admin/admin': path.resolve(rootDir, 'src/admin/index.js'),
		'build/cf7/category': path.resolve(rootDir, 'src/cf7/category-metabox.js'),
	},

	output: {
		...defaultConfig.output,
		path:  path.resolve( rootDir ),
		clean: false,
		// The `admin` entry is deliberately remapped to `build/admin.js` (see
		// `entry` above), but `output.path` itself is the plugin root — so
		// without an explicit `chunkFilename`, every dynamically-`import()`ed
		// page module (src/admin/index.js's `loaders` map) gets written as
		// its own chunk file directly at the plugin root instead of inside
		// build/ (e.g. `src_admin_componets_inbox_index_js.js` next to
		// composer.json). Nesting chunks under build/ keeps every compiled
		// artifact in one place.
		chunkFilename: 'build/[name].js',
	},

	optimization: {
		...defaultConfig.optimization,
		splitChunks:  false,
		runtimeChunk: false,
	},

	plugins: [
		...defaultConfig.plugins,
		// Run after @wordpress/scripts has
		// already written the *.asset.php dependency files.
		new RemoveEmptyScriptsPlugin( {
			stage: RemoveEmptyScriptsPlugin.STAGE_AFTER_PROCESS_PLUGINS,
		} ),
	],
};