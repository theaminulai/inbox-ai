<?php
/**
 * Registers the admin menu under Contact Form 7.
 *
 * @package CF7AIInbox\Admin
 */

namespace CF7AIInbox\Admin;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIInbox\Admin\Pages\InboxListPage;
use CF7AIInbox\Admin\Pages\SettingsPage;
use CF7AIInbox\Security\Capabilities;

/**
 * Class Menu
 *
 * Adds submenu pages under Contact Form 7's own top-level menu, one per
 * top-level section from the Admin UX plan in docs/CF7_AI_Inbox_RnD.md
 * (section 12):
 *
 *   Contact
 *   ├── Contact Forms   (Contact Form 7's own default page)
 *   ├── Overview
 *   ├── AI Inbox List
 *   ├── Contacts List
 *   ├── Analytics
 *   └── Settings
 *
 * Only pages with a real, finished, data-backed renderer are registered in
 * {@see self::PAGES} — currently AI Inbox List
 * ({@see \CF7AIInbox\Admin\Pages\InboxListPage}, see
 * docs/plans/02-ai-inbox-list-plan.md) and Settings
 * ({@see \CF7AIInbox\Admin\Pages\SettingsPage}, see
 * docs/plans/05-settings-plan.md). There is no more static-mockup iframe
 * preview fallback: Overview, Contacts, and Analytics
 * (docs/plans/01,03,04-*.md) are added to {@see self::PAGES} — with their
 * own page class, the same way these are — once each one's own build pass
 * is actually complete. A minimal Contacts page and a Flamingo contacts
 * importer were built and then deliberately reverted (kept out of this list
 * and out of the Flamingo import wizard) so the full docs/plans/03 design
 * can be built from scratch later, per its own plan, without an in-between
 * page to reconcile.
 *
 * This class also owns every page's asset loading. `enqueue_assets()`
 * enqueues the one shared `build/admin.js` / `build/admin.css` bundle
 * (see webpack.config.js) once, only on this plugin's own admin screens —
 * individual page classes never enqueue anything themselves.
 * A page that needs its own AJAX nonce or other localized data hooks the
 * shared `cf7ai_inbox_localize_data` filter (see
 * {@see \CF7AIInbox\Admin\Pages\SettingsPage::localize_data()}), checking
 * the current page slug passed as the filter's second argument, instead of
 * calling `wp_localize_script()` directly — only one such call per script
 * handle actually takes effect.
 */
final class Menu {

	/**
	 * Contact Form 7's own top-level admin menu slug.
	 *
	 * @var string
	 */
	private const PARENT_SLUG = 'wpcf7';

	/**
	 * Page slug => [ menu title, page title, required capability, page class ].
	 *
	 * Add a page here only once it has a real page class with its own
	 * `render()` method — see the class docblock.
	 *
	 * @var array<string, array{0:string,1:string,2:string,3:class-string}>
	 */
	private const PAGES = array(
		'cf7ai-inbox'    => array( 'AI Inbox', 'CF7 AI Inbox', Capabilities::VIEW_MESSAGES, InboxListPage::class ),
		'cf7ai-settings' => array( 'Settings', 'CF7 AI Inbox Settings', Capabilities::MANAGE_SETTINGS, SettingsPage::class ),
	);

	/**
	 * Page slug => hook suffix, filled in by {@see self::register_menu()} so
	 * {@see self::enqueue_assets()} can cheaply tell this plugin's own
	 * screens apart from every other wp-admin page.
	 *
	 * @var array<string, string>
	 */
	private array $hook_suffixes = array();

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registers every submenu page listed in {@see self::PAGES} under
	 * Contact Form 7.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		foreach ( self::PAGES as $slug => $page ) {
			[ $menu_title, $page_title, $capability, $page_class ] = $page;

			$hook_suffix = add_submenu_page(
				self::PARENT_SLUG,
				$page_title,
				$menu_title,
				$capability,
				$slug,
				array( new $page_class(), 'render' )
			);

			if ( is_string( $hook_suffix ) ) {
				$this->hook_suffixes[ $slug ] = $hook_suffix;
			}
		}
	}

	/**
	 * Enqueues this plugin's one shared admin script/style bundle, but only
	 * on this plugin's own admin screens (every slug in {@see self::PAGES}).
	 *
	 * `build/admin.asset.php` (written by `@wordpress/scripts`/webpack — see
	 * webpack.config.js) supplies the real dependency list and a
	 * content-hash version instead of `CF7AI_INBOX_VERSION`, so browsers
	 * bust their cache exactly when the bundle changes.
	 *
	 * The shared `cf7ai_inbox_localize_data` filter (starting from just
	 * `ajaxUrl`, plus the current page slug as its second argument) is
	 * applied right before the one `wp_localize_script()` call — only one
	 * such call per handle survives, so this is the only place it happens;
	 * page classes add to `$data` via that filter (checking `$slug` for
	 * their own page) instead of localizing anything themselves.
	 *
	 * @param string $hook_suffix The current admin screen's hook suffix, as
	 *                            passed by the `admin_enqueue_scripts` action.
	 *
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$slug = array_search( $hook_suffix, $this->hook_suffixes, true );

		if ( false === $slug ) {
			return;
		}

		$asset_file = CF7AI_INBOX_PATH . 'build/admin/admin.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array(),
				'version'      => CF7AI_INBOX_VERSION,
			);

		wp_enqueue_style(
			'cf7ai-inbox-admin',
			CF7AI_INBOX_URL . 'build/admin/admin.css',
			array(),
			$asset['version']
		);
		// Swaps in build/admin-rtl.css automatically for RTL locales.
		wp_style_add_data( 'cf7ai-inbox-admin', 'rtl', 'replace' );

		wp_enqueue_script(
			'cf7ai-inbox-admin',
			CF7AI_INBOX_URL . 'build/admin/admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		$data = apply_filters(
			'cf7ai_inbox_localize_data',
			array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ),
			$slug
		);

		wp_localize_script( 'cf7ai-inbox-admin', 'cf7aiInboxAdmin', $data );
	}

	/**
	 * Builds the admin URL for one of this plugin's pages.
	 *
	 * @param string $slug Page slug (a key of {@see self::PAGES}).
	 *
	 * @return string
	 */
	public static function url( string $slug ): string {
		return add_query_arg( array( 'page' => $slug ), admin_url( 'admin.php' ) );
	}
}
