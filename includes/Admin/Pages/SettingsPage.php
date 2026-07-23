<?php
/**
 * Renderer for the Settings admin page (`cf7ai-settings`).
 *
 * @package CF7AIInbox\Admin\Pages
 */

namespace CF7AIInbox\Admin\Pages;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIInbox\Admin\AjaxController;
use CF7AIInbox\Database\UsageRepository;
use CF7AIInbox\Migration\FlamingoImporter;
use CF7AIInbox\Security\Capabilities;
use CF7AIInbox\Settings\Repository as SettingsRepository;
use CF7AIInbox\Support\Template;

/**
 * Class SettingsPage
 *
 * Every other page depends on at least this page's AI Provider and General
 * tabs (see docs/plans/05-settings-plan.md). `render()` contains no inline
 * markup — it assembles the current settings into a view model and hands
 * off to `includes/Templates/settings.php` (the shared six-tab shell) and
 * the six per-tab template files it in turn includes.
 *
 * This class enqueues nothing itself — {@see \CF7AIInbox\Admin\Menu}
 * enqueues the one shared admin script/style bundle for every plugin page.
 * {@see self::localize_data()} hooks that class's shared `cf7ai_inbox_localize_data`
 * filter to add this page's own AJAX nonce to the shared localized JS object.
 */
final class SettingsPage {

	/**
	 * Valid `?tab=` values, in the same order as the mockup's subnav.
	 *
	 * @var string[]
	 */
	private const TABS = array( 'ai-settings', 'general-settings', 'prompts', 'usage', 'notifications', 'flamingo' );

	/**
	 * Hooks this page's data into {@see \CF7AIInbox\Admin\Menu::enqueue_assets()}.
	 */
	public function __construct() {
		add_filter( 'cf7ai_inbox_localize_data', array( $this, 'localize_data' ), 10, 2 );
	}

	/**
	 * Renders the page. Registered as the `add_submenu_page()` callback for
	 * `cf7ai-settings` by {@see \CF7AIInbox\Admin\Menu}.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cf7-ai-inbox' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selector (which of six already-rendered sections to show first); not a state-changing request.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'ai-settings';

		if ( ! in_array( $tab, self::TABS, true ) ) {
			$tab = 'ai-settings';
		}

		Template::render( 'settings', array_merge( $this->build_view_model(), array( 'active_tab' => $tab ) ) );
	}

	/**
	 * Adds this page's AJAX nonce to the shared `cf7aiInboxAdmin` JS object.
	 *
	 * Hooked onto the shared `cf7ai_inbox_localize_data` filter by
	 * {@see self::__construct()}; applied by
	 * {@see \CF7AIInbox\Admin\Menu::enqueue_assets()} right before its one
	 * `wp_localize_script()` call, on every plugin page — bails out via the
	 * `$slug` argument on any page that isn't this one, so Menu never needs
	 * to know anything Settings-specific.
	 *
	 * @param array<string, mixed> $data Data collected so far (at least `ajaxUrl`).
	 * @param string               $slug Slug of the admin page currently being enqueued for.
	 *
	 * @return array<string, mixed>
	 */
	public function localize_data( array $data, string $slug ): array {
		if ( 'cf7ai-settings' !== $slug ) {
			return $data;
		}

		$data['nonce'] = wp_create_nonce( AjaxController::SETTINGS_NONCE_ACTION );

		return $data;
	}

	/**
	 * Assembles every value the six tab templates read from.
	 *
	 * @return array<string, mixed>
	 */
	private function build_view_model(): array {
		return array(
			'provider'        => SettingsRepository::get_provider(),
			'api_key_masked'  => SettingsRepository::get_masked_api_key(),
			'has_api_key'     => SettingsRepository::has_api_key(),
			'general'         => SettingsRepository::get_general(),
			'cf7_forms'       => $this->get_cf7_forms(),
			'prompts'         => SettingsRepository::get_prompts(),
			'notifications'   => SettingsRepository::get_notifications(),
			'usage_totals'    => UsageRepository::get_period_totals( '30_days' ),
			'usage_breakdown' => UsageRepository::get_cost_breakdown( '30_days' ),
			'flamingo_active' => FlamingoImporter::is_available(),
		);
	}

	/**
	 * Every real Contact Form 7 form, for the Monitored Forms list.
	 *
	 * @return array<int, array{id:int,title:string,monitored:bool}>
	 */
	private function get_cf7_forms(): array {
		if ( ! class_exists( '\WPCF7_ContactForm' ) ) {
			return array();
		}

		$monitored = SettingsRepository::get_general()['monitored_forms'];
		$list      = array();

		foreach ( \WPCF7_ContactForm::find() as $form ) {
			$list[] = array(
				'id'        => $form->id(),
				'title'     => $form->title(),
				'monitored' => in_array( $form->id(), $monitored, true ),
			);
		}

		return $list;
	}
}
