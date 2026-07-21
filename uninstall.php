<?php
/**
 * Uninstall routine for CF7 AI Inbox.
 *
 * Executed by WordPress when the plugin is deleted from the Plugins screen
 * (never on simple deactivation). WordPress guarantees `WP_UNINSTALL_PLUGIN`
 * is defined and that this file runs inside the WordPress context.
 *
 * @package CF7AIInbox
 * @link    https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 */

// Security check — must be invoked by WordPress core during plugin deletion.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

define( 'CF7AI_INBOX_INCLUDES_DIR', plugin_dir_path( __FILE__ ) . 'includes/' );

require_once CF7AI_INBOX_INCLUDES_DIR . 'Autoloader.php';
\CF7AIInbox\Autoloader::register();

\CF7AIInbox\Uninstaller::uninstall();
