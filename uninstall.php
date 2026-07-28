<?php
/**
 * Uninstall routine for Inbox AI.
 *
 * Executed by WordPress when the plugin is deleted from the Plugins screen
 * (never on simple deactivation). WordPress guarantees `WP_UNINSTALL_PLUGIN`
 * is defined and that this file runs inside the WordPress context.
 *
 * @package InboxAI
 * @link    https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 */

// Security check — must be invoked by WordPress core during plugin deletion.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

define( 'INBOXAI_INCLUDES_DIR', plugin_dir_path( __FILE__ ) . 'includes/' );

require_once INBOXAI_INCLUDES_DIR . 'Autoloader.php';
\InboxAI\Autoloader::register();

\InboxAI\Uninstaller::uninstall();
