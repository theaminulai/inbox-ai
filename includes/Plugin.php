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

use CF7AIInbox\Admin\AjaxController;
use CF7AIInbox\Admin\Menu;
use CF7AIInbox\AI\AnalysisQueue;
use CF7AIInbox\CF7\CategoryTaxonomy;
use CF7AIInbox\CF7\SubmissionHandler;
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
		// Requirements::are_met() does no string-building (no __() calls),
		// so it's safe here even though this method runs on `plugins_loaded`
		// — well before the `init` hook WordPress 6.7+ requires translation
		// loading to wait for. The actual error messages (Requirements::get_errors(),
		// which does call __()) are only ever built later, inside the
		// `admin_notices` callback below — see self::render_requirement_notices().
		if ( ! Requirements::are_met() ) {
			$this->requirements_met = false;

			if ( is_admin() ) {
				add_action( 'admin_notices', array( $this, 'render_requirement_notices' ) );
			}

			return;
		}

		$this->requirements_met = true;

		// Cheap no-op once already up to date; catches installs upgraded
		// in place without a deactivate/reactivate cycle.
		Migrator::maybe_migrate();

		if ( is_admin() ) {
			( new Menu() )->init();
			( new AjaxController() )->init();
		}

		// Front-end form submissions and WP-Cron requests are never
		// `is_admin()`, so these two must be registered unconditionally —
		// otherwise a visitor's submission would never be captured and a
		// scheduled analysis would never run.
		( new SubmissionHandler() )->init();
		( new AnalysisQueue() )->init();

		// Registers the per-form AI category taxonomy and its edit-screen
		// sidebar box. Also unconditional: a WP-Cron analysis run needs to
		// read a form's assigned categories just as much as the admin
		// screen needs to display/edit them.
		( new CategoryTaxonomy() )->init();

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

	/**
	 * The `admin_notices` callback registered by {@see self::init()} when a
	 * requirement isn't met. Deliberately a separate method (rather than
	 * building the error strings inline in `init()`) so `Requirements::get_errors()` —
	 * which calls `__()` — only ever runs once `admin_notices` actually
	 * fires, well after the `init` hook; see {@see Requirements::are_met()}'s
	 * docblock for why that ordering matters.
	 *
	 * @return void
	 */
	public function render_requirement_notices(): void {
		foreach ( Requirements::get_errors() as $error ) {
			// The Contact Form 7 message gets an actionable Install/Activate
			// button instead of plain text — see self::render_cf7_dependency_notice().
			// Every other requirement (PHP/WP version) has no such one-click
			// fix, so it stays a plain notice.
			if ( Requirements::cf7_missing_message() === $error ) {
				self::render_cf7_dependency_notice();
				continue;
			}

			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( $error )
			);
		}
	}

	/**
	 * Renders the missing-Contact-Form-7 admin notice with a real,
	 * one-click Install or Activate button — instead of core's own
	 * "Requires Plugins" header mechanism, which hard-blocks activating
	 * CF7 AI Inbox at all until Contact Form 7 is already active. This
	 * plugin deliberately doesn't declare a `Requires Plugins` header for
	 * that reason: it always activates, stays active, and simply no-ops
	 * (see {@see self::init()}) with this notice until Contact Form 7 is
	 * active, at which point every hook wires up on the very next request
	 * with no reactivation needed.
	 *
	 * @return void
	 */
	private static function render_cf7_dependency_notice(): void {
		if ( Requirements::is_cf7_installed() ) {
			$action_url = wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'activate',
						'plugin' => Requirements::CF7_PLUGIN_FILE,
					),
					admin_url( 'plugins.php' )
				),
				'activate-plugin_' . Requirements::CF7_PLUGIN_FILE
			);
			$label = __( 'Activate Contact Form 7', 'cf7-ai-inbox' );
		} else {
			$action_url = wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'install-plugin',
						'plugin' => 'contact-form-7',
					),
					self_admin_url( 'update.php' )
				),
				'install-plugin_contact-form-7'
			);
			$label = __( 'Install Contact Form 7', 'cf7-ai-inbox' );
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p><p><a href="%s" class="button button-primary">%s</a></p></div>',
			esc_html__( 'CF7 AI Inbox needs Contact Form 7 to do anything — it will stay installed and switch its features on automatically as soon as Contact Form 7 is active.', 'cf7-ai-inbox' ),
			esc_url( $action_url ),
			esc_html( $label )
		);
	}
}
