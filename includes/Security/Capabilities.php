<?php
/**
 * Custom capability definitions for the AI Inbox.
 *
 * @package InboxAI\Security
 */

namespace InboxAI\Security;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Capabilities
 *
 * Defines the plugin's custom capabilities and grants them to the
 * Administrator role on activation. Kept separate from WordPress's built-in
 * roles so site owners can later hand out narrower access (e.g. a support
 * agent who can view and reply, but not manage settings or export data)
 * without touching core roles like `edit_posts`.
 */
final class Capabilities {

	/**
	 * View AI Inbox submissions.
	 *
	 * @var string
	 */
	public const VIEW_MESSAGES = 'inboxai_view_messages';

	/**
	 * Edit submissions (status, draft replies, etc.).
	 *
	 * @var string
	 */
	public const EDIT_MESSAGES = 'inboxai_edit_messages';

	/**
	 * Delete submissions.
	 *
	 * @var string
	 */
	public const DELETE_MESSAGES = 'inboxai_delete_messages';

	/**
	 * Send AI-drafted (or edited) replies to visitors.
	 *
	 * @var string
	 */
	public const SEND_REPLIES = 'inboxai_send_replies';

	/**
	 * Manage plugin settings (AI provider, prompt, enabled forms).
	 *
	 * @var string
	 */
	public const MANAGE_SETTINGS = 'inboxai_manage_settings';

	/**
	 * View usage/analytics screens.
	 *
	 * @var string
	 */
	public const VIEW_ANALYTICS = 'inboxai_view_analytics';

	/**
	 * Export submission data.
	 *
	 * @var string
	 */
	public const EXPORT_MESSAGES = 'inboxai_export_messages';

	/**
	 * Returns every capability this plugin defines.
	 *
	 * Filterable so a site can register the same set on a custom role
	 * without editing plugin code.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		/**
		 * Filters the full list of Inbox AI capabilities.
		 *
		 * @since 0.1.0
		 *
		 * @param string[] $capabilities Capability names.
		 */
		return apply_filters(
			'inboxai_capabilities',
			array(
				self::VIEW_MESSAGES,
				self::EDIT_MESSAGES,
				self::DELETE_MESSAGES,
				self::SEND_REPLIES,
				self::MANAGE_SETTINGS,
				self::VIEW_ANALYTICS,
				self::EXPORT_MESSAGES,
			)
		);
	}

	/**
	 * Grants every capability to the Administrator role.
	 *
	 * Called on plugin activation. Safe to call repeatedly — `add_cap()`
	 * is a no-op if the role already has the capability.
	 *
	 * @return void
	 */
	public static function add_to_administrator(): void {
		$role = get_role( 'administrator' );

		if ( null === $role ) {
			return;
		}

		foreach ( self::all() as $capability ) {
			$role->add_cap( $capability );
		}
	}

	/**
	 * Removes every plugin capability from the Administrator role.
	 *
	 * Not called on ordinary deactivation (deactivating shouldn't strip
	 * access in case the plugin is reactivated) — reserved for uninstall.
	 *
	 * @return void
	 */
	public static function remove_from_administrator(): void {
		$role = get_role( 'administrator' );

		if ( null === $role ) {
			return;
		}

		foreach ( self::all() as $capability ) {
			$role->remove_cap( $capability );
		}
	}
}
