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
		// 'assets/js/admin':  path.resolve( rootDir, 'src/main.jsx' ),
		// 'assets/css/admin': path.resolve( rootDir, 'src/styles/main.scss' ),
	},

	output: {
		...defaultConfig.output,
		path:  path.resolve( rootDir ),
		clean: false,
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