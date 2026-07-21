<?php
/**
 * Handles plugin activation.
 *
 * @package CF7AIInbox
 */

namespace CF7AIInbox;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIInbox\Database\Migrator;
use CF7AIInbox\Security\Capabilities;

/**
 * Class Activation
 *
 * Runs once when the plugin is activated: provisions the database schema,
 * grants capabilities to Administrators, and records bookkeeping metadata.
 * Deliberately does not enforce {@see Requirements} here — WordPress 6.5+
 * already blocks activation itself via the "Requires at least" / "Requires
 * PHP" / "Requires Plugins" headers, and refusing to activate on older core
 * would leave the site owner without even an explanatory admin notice.
 */
final class Activation {

	/**
	 * Runs the activation routine.
	 *
	 * @return void
	 */
	public static function run(): void {
		Migrator::install();
		Capabilities::add_to_administrator();

		// Track the version that last ran activation, and when the plugin
		// was first activated. Never overwritten on subsequent activations.
		add_option( 'cf7ai_inbox_activated_at', current_time( 'mysql' ), '', false );
		update_option( 'cf7ai_inbox_version', CF7AI_INBOX_VERSION, false );
	}
}
