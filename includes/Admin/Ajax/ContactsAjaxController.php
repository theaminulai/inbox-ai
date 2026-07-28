<?php
/**
 * admin-ajax.php handlers for the Contacts List page.
 *
 * @package InboxAI\Admin\Ajax
 */

namespace InboxAI\Admin\Ajax;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\Database\ActivityRepository;
use InboxAI\Database\MessageRepository;
use InboxAI\Security\Capabilities;

/**
 * Class ContactsAjaxController
 *
 * Every Contacts List page AJAX action (see
 * docs/plans/03-contacts-list-plan.md, section 4): the filtered list read
 * (also reused by the CSV export, same "reuse the list action with a large
 * per_page" pattern as {@see InboxAjaxController::list_messages()}), and
 * "Delete contact" (archives every message from that sender — see
 * {@see \InboxAI\Database\MessageRepository::archive_by_email()}). Every
 * `$_POST` read here goes through a `BaseAjaxController::post_*()` helper
 * rather than a repeated `isset()`/sanitize/`wp_unslash()` one-liner.
 */
final class ContactsAjaxController extends BaseAjaxController {

	/**
	 * Nonce action name shared by every Contacts List page AJAX call.
	 *
	 * @var string
	 */
	public const CONTACTS_NONCE_ACTION = 'inboxai_contacts';

	/**
	 * Registers every `wp_ajax_*` hook this controller handles.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_inboxai_list_contacts', array( $this, 'list_contacts' ) );
		add_action( 'wp_ajax_inboxai_delete_contact', array( $this, 'delete_contact' ) );
	}

	/**
	 * `inboxai_list_contacts` — the Contacts List screen's filtered,
	 * paginated table. The initial page load renders this server-side (see
	 * {@see \InboxAI\Admin\Pages\ContactsListPage::render()}); this action
	 * exists only for the client's CSV export, which needs every matching
	 * contact in one call rather than just the current page.
	 *
	 * @return void
	 */
	public function list_contacts(): void {
		$this->check( Capabilities::VIEW_MESSAGES, self::CONTACTS_NONCE_ACTION );

		$filters = array(
			'category' => $this->post_string( 'category' ),
			'priority' => $this->post_key( 'priority' ),
			'search'   => $this->post_string( 'search' ),
		);

		wp_send_json_success( MessageRepository::get_contacts( $filters, $this->post_page(), $this->post_per_page() ) );
	}

	/**
	 * `inboxai_delete_contact` — "Delete contact" row action: archives every
	 * message from that sender email (see
	 * {@see \InboxAI\Database\MessageRepository::archive_by_email()} for why
	 * archive, not a hard delete). Logs the same `archived` activity event
	 * per affected message as the AI Inbox List's own single-row
	 * {@see InboxAjaxController::archive_message()} does, so the timeline
	 * stays consistent whichever screen an archive happened from.
	 *
	 * @return void
	 */
	public function delete_contact(): void {
		$this->check( Capabilities::DELETE_MESSAGES, self::CONTACTS_NONCE_ACTION );

		$email = $this->post_email( 'email' );

		if ( '' === $email ) {
			wp_send_json_error( array( 'message' => __( 'This contact could not be found.', 'inbox-ai' ) ), 400 );
		}

		$archived_ids = MessageRepository::archive_by_email( $email );

		if ( array() === $archived_ids ) {
			wp_send_json_error( array( 'message' => __( 'This contact could not be found.', 'inbox-ai' ) ), 404 );
		}

		foreach ( $archived_ids as $archived_id ) {
			ActivityRepository::log( $archived_id, 'archived', array(), get_current_user_id() );
		}

		wp_send_json_success( array( 'deleted' => true ) );
	}
}
