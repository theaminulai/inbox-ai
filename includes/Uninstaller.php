<?php
/**
 * Removes all data the plugin created.
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
 * Class Uninstaller
 *
 * Called only from uninstall.php, i.e. only when a site owner explicitly
 * deletes the plugin from the Plugins screen — never on simple
 * deactivation (see {@see Deactivation}).
 *
 * A configurable "keep my data" retention choice is planned for a later
 * phase (see docs/CF7_AI_Inbox_RnD.md, section 14); until that setting
 * exists, uninstalling removes everything the plugin stored, which is the
 * safer default for a plugin that logs visitor-submitted personal data.
 */
final class Uninstaller {

	/**
	 * Runs the uninstall routine.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		Migrator::drop_tables();
		Capabilities::remove_from_administrator();

		delete_option( 'inboxai_version' );
		delete_option( 'inboxai_activated_at' );
	}
}
