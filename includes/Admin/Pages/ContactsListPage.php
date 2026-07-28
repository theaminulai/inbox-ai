<?php
/**
 * Renderer for the Contacts List admin page (`inboxai-contacts`).
 *
 * @package InboxAI\Admin\Pages
 */

namespace InboxAI\Admin\Pages;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\Admin\Ajax\ContactsAjaxController;
use InboxAI\CF7\CategoryTaxonomy;
use InboxAI\Database\MessageRepository;
use InboxAI\Security\Capabilities;
use InboxAI\Support\Template;

/**
 * Class ContactsListPage
 *
 * A single, fully server-rendered screen — unlike the AI Inbox List, there's
 * no separate detail view to route between, so `render()` queries and hands
 * off to `includes/Templates/contacts/contacts.php` (the page shell, which in
 * turn renders `contacts/list.php` — the same two-file split as
 * `includes/Templates/inbox/{inbox,list}.php`; see
 * docs/plans/03-contacts-list-plan.md). Every contact is derived live from
 * `{@see \InboxAI\Database\MessageRepository::get_contacts()}` — grouped by
 * `sender_email` from the same messages table the AI Inbox List reads,
 * rather than a separate contacts table.
 */
final class ContactsListPage {

	/**
	 * Hooks this page's data into {@see \InboxAI\Admin\Menu::enqueue_assets()}.
	 */
	public function __construct() {
		add_filter( 'inboxai_localize_data', array( $this, 'localize_data' ), 10, 2 );
	}

	/**
	 * Renders the page. Registered as the `add_submenu_page()` callback for
	 * `inboxai-contacts` by {@see \InboxAI\Admin\Menu}.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_MESSAGES ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'inbox-ai' ) );
		}

		$filters  = $this->get_filters_from_request();
		$page     = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter/pagination, not a state-changing request.
		$per_page = 20;

		$result = MessageRepository::get_contacts( $filters, $page, $per_page );

		Template::render(
			'contacts/contacts',
			array(
				'contacts'   => $result['items'],
				'total'      => $result['total'],
				'page'       => $page,
				'per_page'   => $per_page,
				'filters'    => $filters,
				'categories' => $this->get_category_names(),
				'can_delete' => current_user_can( Capabilities::DELETE_MESSAGES ),
			)
		);
	}

	/**
	 * Reads and sanitizes the screen's filters from `$_GET`, matching
	 * {@see \InboxAI\Admin\Ajax\ContactsAjaxController::list_contacts()}'s
	 * own sanitization exactly (that AJAX action still exists, for the CSV
	 * export's "current filters, no pagination cap" request).
	 *
	 * @return array<string, mixed>
	 */
	private function get_filters_from_request(): array {
		return array(
			'category' => isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter, not a state-changing request.
			'priority' => isset( $_GET['priority'] ) ? sanitize_key( wp_unslash( $_GET['priority'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'search'   => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
	}

	/**
	 * Adds this page's AJAX nonce to the shared `inboxaiAdmin` JS object.
	 *
	 * @param array<string, mixed> $data Data collected so far (at least `ajaxUrl`).
	 * @param string               $slug Slug of the admin page currently being enqueued for.
	 *
	 * @return array<string, mixed>
	 */
	public function localize_data( array $data, string $slug ): array {
		if ( 'inboxai-contacts' !== $slug ) {
			return $data;
		}

		$data['nonce'] = wp_create_nonce( ContactsAjaxController::CONTACTS_NONCE_ACTION );

		return $data;
	}

	/**
	 * Every AI category that exists anywhere on this site, for the
	 * "Category" filter dropdown — same source and same reasoning as
	 * {@see \InboxAI\Admin\Pages\InboxListPage::get_category_names()}.
	 *
	 * @return string[]
	 */
	private function get_category_names(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => CategoryTaxonomy::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'names',
			)
		);

		return is_array( $terms ) ? $terms : array();
	}
}
