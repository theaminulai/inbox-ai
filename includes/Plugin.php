<?php
/**
 * Root plugin class.
 *
 * Phase 1 (Foundation) scope only: requirements checks, admin notices, and
 * the safety-net schema migration. Later phases wire in submission capture,
 * the AI layer, and the admin inbox UI (see docs/CF7_AI_Inbox_RnD.md).
 *
 * @package CF7AIInbox
 */

namespace CF7AIInbox;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIInbox\Database\Migrator;

/**
 * Class Plugin
 *
 * Main plugin orchestrator, instantiated once from the bootstrap file on
 * the `plugins_loaded` hook (priority 11, after other plugins — including
 * Contact Form 7 — have registered their own hooks).
 */
final class Plugin {

	/**
	 * The single plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Whether every runtime requirement was met the last time {@see self::init()} ran.
	 *
	 * @var bool
	 */
	private bool $requirements_met = false;

	/**
	 * Private constructor — use {@see self::instance()}.
	 */
	private function __construct() {}

	/**
	 * Returns the singleton plugin instance, creating it on first call.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boots the plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		$errors = Requirements::get_errors();

		if ( array() !== $errors ) {
			$this->requirements_met = false;

			if ( is_admin() ) {
				add_action(
					'admin_notices',
					static function () use ( $errors ): void {
						foreach ( $errors as $error ) {
							printf(
								'<div class="notice notice-error"><p>%s</p></div>',
								esc_html( $error )
							);
						}
					}
				);
			}

			return;
		}

		$this->requirements_met = true;

		// Cheap no-op once already up to date; catches installs upgraded
		// in place without a deactivate/reactivate cycle.
		Migrator::maybe_migrate();

		/**
		 * Fires once CF7 AI Inbox has confirmed its requirements are met
		 * and finished its Phase 1 bootstrap.
		 *
		 * @since 0.1.0
		 */
		do_action( 'cf7ai_inbox_loaded' );
	}

	/**
	 * Whether the plugin's requirements were met on the last {@see self::init()} call.
	 *
	 * @return bool
	 */
	public function requirements_met(): bool {
		return $this->requirements_met;
	}
}
