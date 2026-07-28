<?php
/**
 * Handles plugin activation.
 *
 * @package InboxAI
 */

namespace InboxAI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\Database\Migrator;
use InboxAI\Security\Capabilities;

/**
 * Class Activation
 *
 * Runs once when the plugin is activated: provisions the database schema,
 * grants capabilities to Administrators, and records bookkeeping metadata.
 * Deliberately does not enforce {@see Requirements} here — WordPress 6.5+
 * already blocks activation itself for the "Requires at least" / "Requires
 * PHP" headers, and this plugin deliberately does *not* declare a "Requires
 * Plugins: contact-form-7" header (that would make WordPress core hard-block
 * activation with a dead-end error page whenever Contact Form 7 isn't
 * already active/installed, instead of the friendlier "stay active, show an
 * Install/Activate button, switch features on automatically" behavior
 * {@see \InboxAI\Plugin::init()} implements). Refusing to activate here
 * on unmet requirements would leave the site owner without even an
 * explanatory admin notice.
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
		add_option( 'inboxai_activated_at', current_time( 'mysql' ), '', false );
		update_option( 'inboxai_version', INBOXAI_VERSION, false );
	}
}
