<?php
/**
 * Handles plugin deactivation.
 *
 * @package InboxAI
 */

namespace InboxAI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivation
 *
 * Runs once when the plugin is deactivated. Deactivation never deletes
 * data or strips capabilities — that only happens on uninstall, and only
 * when the site owner has opted into it (see uninstall.php). This keeps
 * deactivate/reactivate (e.g. during a troubleshooting step, or a plugin
 * update) non-destructive.
 */
final class Deactivation {

	/**
	 * Runs the deactivation routine.
	 *
	 * @return void
	 */
	public static function run(): void {
		/**
		 * Fires once Inbox AI has finished its deactivation routine.
		 *
		 * Reserved for future use (e.g. clearing scheduled AI-processing
		 * jobs once Phase 3 introduces the async queue).
		 *
		 * @since 0.1.0
		 */
		do_action( 'inboxai_deactivated' );
	}
}
