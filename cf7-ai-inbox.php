<?php
/**
 * Plugin Name:       CF7 AI Inbox
 * Description:       An AI-powered review inbox for Contact Form 7 submissions — summaries, suggested replies, categorization, and priority scoring, with nothing ever sent automatically.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Requires Plugins:  contact-form-7
 * Author:            theaminulai
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       cf7-ai-inbox
 * Domain Path:       /languages
 *
 * @package CF7AIInbox
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'CF7AI_INBOX_FILE', __FILE__ );

// Retrieve version dynamically from this file's header.
$cf7ai_inbox_plugin_data    = get_file_data( CF7AI_INBOX_FILE, array( 'Version' => 'Version' ), 'plugin' );
$cf7ai_inbox_plugin_version = ! empty( $cf7ai_inbox_plugin_data['Version'] ) ? $cf7ai_inbox_plugin_data['Version'] : '0.0.1';

// Define plugin constants.
define( 'CF7AI_INBOX_VERSION', $cf7ai_inbox_plugin_version );
define( 'CF7AI_INBOX_PATH', plugin_dir_path( CF7AI_INBOX_FILE ) );
define( 'CF7AI_INBOX_URL', plugin_dir_url( CF7AI_INBOX_FILE ) );
define( 'CF7AI_INBOX_BASENAME', plugin_basename( CF7AI_INBOX_FILE ) );
define( 'CF7AI_INBOX_API_NAMESPACE', 'cf7ai-inbox/v1' );

// Shorthand aliases used throughout the codebase.
define( 'CF7AI_INBOX_PLUGIN_DIR', CF7AI_INBOX_PATH );
define( 'CF7AI_INBOX_PLUGIN_URL', CF7AI_INBOX_URL );
define( 'CF7AI_INBOX_PLUGIN_FILE', CF7AI_INBOX_FILE );

// PSR-4 autoloader for the CF7AIInbox\ namespace (see composer.json).
// Loaded manually — must be required before any CF7AIInbox\ class is referenced.
require_once CF7AI_INBOX_PATH . 'includes/Autoloader.php';
\CF7AIInbox\Autoloader::register();

// Optional Composer vendor autoloader (third-party deps only, e.g. installed
// via `composer install --no-dev` for release builds).
if ( file_exists( CF7AI_INBOX_PATH . 'vendor/autoload.php' ) ) {
	require_once CF7AI_INBOX_PATH . 'vendor/autoload.php';
}

// Boot the plugin once every other active plugin (including Contact Form 7)
// has registered its hooks. Plugin::init() owns the requirements check
// (PHP/WP version, Contact Form 7 presence) and never fatals if unmet —
// it queues an admin notice and stays dormant instead.
add_action(
	'plugins_loaded',
	static function (): void {
		\CF7AIInbox\Plugin::instance()->init();
	},
	11
);

// Activation / Deactivation hooks.
register_activation_hook( CF7AI_INBOX_FILE, array( \CF7AIInbox\Activation::class, 'run' ) );
register_deactivation_hook( CF7AI_INBOX_FILE, array( \CF7AIInbox\Deactivation::class, 'run' ) );

/**
 * Global helper — returns the plugin singleton.
 *
 * @return \CF7AIInbox\Plugin
 */
function cf7ai_inbox_plugin(): \CF7AIInbox\Plugin {
	return \CF7AIInbox\Plugin::instance();
}