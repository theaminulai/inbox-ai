<?php
/**
 * Plugin Name:       Inbox AI – Contact Form 7
 * Description:       An AI-powered review inbox for Contact Form 7 submissions — summaries, suggested replies, categorization, and priority scoring, with nothing ever sent automatically.
 * Version: 1.1.4
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Requires Plugins:  contact-form-7
 * Author:            theaminuldev
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       inbox-ai
 * Domain Path:       /languages
 *
 * @package InboxAI
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'INBOXAI_FILE', __FILE__ );

// Retrieve version dynamically from this file's header.
$inboxai_plugin_data    = get_file_data( INBOXAI_FILE, array( 'Version' => 'Version' ), 'plugin' );
$inboxai_plugin_version = ! empty( $inboxai_plugin_data['Version'] ) ? $inboxai_plugin_data['Version'] : '0.0.1';

// Define plugin constants.
define( 'INBOXAI_VERSION', $inboxai_plugin_version );
define( 'INBOXAI_PATH', plugin_dir_path( INBOXAI_FILE ) );
define( 'INBOXAI_URL', plugin_dir_url( INBOXAI_FILE ) );
define( 'INBOXAI_BASENAME', plugin_basename( INBOXAI_FILE ) );
define( 'INBOXAI_API_NAMESPACE', 'inboxai/v1' );

// Shorthand aliases used throughout the codebase.
define( 'INBOXAI_PLUGIN_DIR', INBOXAI_PATH );
define( 'INBOXAI_PLUGIN_URL', INBOXAI_URL );
define( 'INBOXAI_PLUGIN_FILE', INBOXAI_FILE );

// PSR-4 autoloader for the InboxAI\ namespace (see composer.json).
// Loaded manually — must be required before any InboxAI\ class is referenced.
require_once INBOXAI_PATH . 'includes/Autoloader.php';
\InboxAI\Autoloader::register();

// Optional Composer vendor autoloader (third-party deps only, e.g. installed
// via `composer install --no-dev` for release builds).
if ( file_exists( INBOXAI_PATH . 'vendor/autoload.php' ) ) {
	require_once INBOXAI_PATH . 'vendor/autoload.php';
}

// Boot the plugin once every other active plugin (including Contact Form 7)
// has registered its hooks. Plugin::init() owns the requirements check
// (PHP/WP version, Contact Form 7 presence) and never fatals if unmet —
// it queues an admin notice and stays dormant instead.
add_action(
	'plugins_loaded',
	static function (): void {
		\InboxAI\Plugin::instance()->init();
	},
	11
);

// Activation / Deactivation hooks.
register_activation_hook( INBOXAI_FILE, array( \InboxAI\Activation::class, 'run' ) );
register_deactivation_hook( INBOXAI_FILE, array( \InboxAI\Deactivation::class, 'run' ) );

/**
 * Global helper — returns the plugin singleton.
 *
 * @return \InboxAI\Plugin
 */
function inboxai_plugin(): \InboxAI\Plugin {
	return \InboxAI\Plugin::instance();
}
