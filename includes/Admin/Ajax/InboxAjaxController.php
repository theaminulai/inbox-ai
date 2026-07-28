<?php
/**
 * admin-ajax.php handlers for the AI Inbox List page.
 *
 * @package InboxAI\Admin\Ajax
 */

namespace InboxAI\Admin\Ajax;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\Admin\Pages\InboxListPage;
use InboxAI\AI\AnalysisQueue;
use InboxAI\Database\ActivityRepository;
use InboxAI\Database\MessageRepository;
use InboxAI\Security\Capabilities;
use InboxAI\Services\ReplyService;

/**
 * Class InboxAjaxController
 *
 * Every AI Inbox List page AJAX action (see
 * docs/plans/02-ai-inbox-list-plan.md, section 3): the filtered/paginated
 * list read (also reused by the CSV export), a single submission's data, and
 * every action that changes a message's state (save draft, send reply, mark
 * reviewed, archive, delete, retry analysis). Split out of the original
 * single `AjaxController` (see `BaseAjaxController`'s docblock) — the AI
 * Inbox List's own slice of that file, unchanged in behavior. Every
 * `$_POST` read here goes through a `BaseAjaxController::post_*()` helper
 * rather than a repeated `isset()`/sanitize/`wp_unslash()` one-liner.
 */
final class InboxAjaxController extends BaseAjaxController {

	/**
	 * Nonce action name shared by every AI Inbox List page AJAX call.
	 *
	 * @var string
	 */
	public const INBOX_NONCE_ACTION = 'inboxai_messages';

	/**
	 * Registers every `wp_ajax_*` hook this controller handles.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_inboxai_list_messages', array( $this, 'list_messages' ) );
		add_action( 'wp_ajax_inboxai_get_message', array( $this, 'get_message' ) );
		add_action( 'wp_ajax_inboxai_save_draft', array( $this, 'save_draft' ) );
		add_action( 'wp_ajax_inboxai_send_reply', array( $this, 'send_reply' ) );
		add_action( 'wp_ajax_inboxai_mark_reviewed', array( $this, 'mark_reviewed' ) );
		add_action( 'wp_ajax_inboxai_archive_message', array( $this, 'archive_message' ) );
		add_action( 'wp_ajax_inboxai_delete_message', array( $this, 'delete_message' ) );
		add_action( 'wp_ajax_inboxai_retry_analysis', array( $this, 'retry_analysis' ) );
	}

	/**
	 * `inboxai_list_messages` — the AI Inbox List screen's filtered, paginated
	 * table.
	 *
	 * @return void
	 */
	public function list_messages(): void {
		$this->check( Capabilities::VIEW_MESSAGES, self::INBOX_NONCE_ACTION );

		$period = $this->post_key( 'period' );

		if ( ! in_array( $period, InboxListPage::PERIODS, true ) ) {
			$period = '';
		}

		$filters = array(
			'status'           => $this->post_key( 'status' ),
			'priority'         => $this->post_key( 'priority' ),
			'category'         => $this->post_string( 'category' ),
			'form'             => $this->post_string( 'form' ),
			'confidence_below' => $this->post_int_or_empty( 'confidence_below' ),
			'search'           => $this->post_string( 'search' ),
			'period'           => $period,
		);

		wp_send_json_success( MessageRepository::get_filtered( $filters, $this->post_page(), $this->post_per_page() ) );
	}

	/**
	 * `inboxai_get_message` — the Submission Detail screen's data, including
	 * its activity timeline.
	 *
	 * @return void
	 */
	public function get_message(): void {
		$this->check( Capabilities::VIEW_MESSAGES, self::INBOX_NONCE_ACTION );

		$id      = $this->post_int( 'id' );
		$message = MessageRepository::find( $id );

		if ( null === $message ) {
			wp_send_json_error( array( 'message' => __( 'This submission could not be found.', 'inbox-ai' ) ), 404 );
		}

		wp_send_json_success(
			array(
				'message'    => $message,
				'activities' => ActivityRepository::get_for_message( $id ),
			)
		);
	}

	/**
	 * `inboxai_save_draft` — persists an edited reply draft without sending it.
	 *
	 * @return void
	 */
	public function save_draft(): void {
		$this->check( Capabilities::EDIT_MESSAGES, self::INBOX_NONCE_ACTION );

		$id      = $this->post_int( 'id' );
		$subject = $this->post_string( 'subject' );
		$body    = $this->post_html( 'body' );

		if ( 0 === $id || ! MessageRepository::save_draft( $id, $subject, $body ) ) {
			wp_send_json_error( array( 'message' => __( 'The draft could not be saved.', 'inbox-ai' ) ), 400 );
		}

		ActivityRepository::log( $id, 'draft_saved', array(), get_current_user_id() );

		wp_send_json_success( array( 'saved' => true ) );
	}

	/**
	 * `inboxai_send_reply` — sends the reply (saved draft, or an edited
	 * subject/body passed along with the request) to the visitor.
	 *
	 * @return void
	 */
	public function send_reply(): void {
		$this->check( Capabilities::SEND_REPLIES, self::INBOX_NONCE_ACTION );

		$id      = $this->post_int( 'id' );
		$subject = $this->post_string( 'subject', null );
		$body    = $this->post_html( 'body', null );

		$result = ReplyService::send( $id, $subject, $body, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'sent' => true ) );
	}

	/**
	 * `inboxai_mark_reviewed` — clears a `review`-status row without drafting
	 * or sending a reply (e.g. the admin handled it another way).
	 *
	 * @return void
	 */
	public function mark_reviewed(): void {
		$this->check( Capabilities::EDIT_MESSAGES, self::INBOX_NONCE_ACTION );

		$id = $this->post_int( 'id' );

		if ( 0 === $id || ! MessageRepository::update_status( $id, 'reviewed' ) ) {
			wp_send_json_error( array( 'message' => __( 'This submission could not be updated.', 'inbox-ai' ) ), 400 );
		}

		ActivityRepository::log( $id, 'reviewed', array(), get_current_user_id() );

		wp_send_json_success( array( 'updated' => true ) );
	}

	/**
	 * `inboxai_archive_message` — moves a row to `archived` (used for both the
	 * row-menu action and manually archiving a false-positive-spam row).
	 *
	 * @return void
	 */
	public function archive_message(): void {
		$this->check( Capabilities::EDIT_MESSAGES, self::INBOX_NONCE_ACTION );

		$id = $this->post_int( 'id' );

		if ( 0 === $id || ! MessageRepository::update_status( $id, 'archived' ) ) {
			wp_send_json_error( array( 'message' => __( 'This submission could not be archived.', 'inbox-ai' ) ), 400 );
		}

		ActivityRepository::log( $id, 'archived', array(), get_current_user_id() );

		wp_send_json_success( array( 'updated' => true ) );
	}

	/**
	 * `inboxai_delete_message` — soft-deletes a row (list queries always
	 * exclude these; nothing is permanently removed from this screen).
	 *
	 * @return void
	 */
	public function delete_message(): void {
		$this->check( Capabilities::DELETE_MESSAGES, self::INBOX_NONCE_ACTION );

		$id = $this->post_int( 'id' );

		if ( 0 === $id || ! MessageRepository::soft_delete( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'This submission could not be deleted.', 'inbox-ai' ) ), 400 );
		}

		wp_send_json_success( array( 'deleted' => true ) );
	}

	/**
	 * `inboxai_retry_analysis` — the Submission Failure screen's "Retry"
	 * action: re-enqueues the message for analysis exactly as its original
	 * capture did, without re-inserting a row.
	 *
	 * @return void
	 */
	public function retry_analysis(): void {
		$this->check( Capabilities::EDIT_MESSAGES, self::INBOX_NONCE_ACTION );

		$id      = $this->post_int( 'id' );
		$message = MessageRepository::find( $id );

		if ( null === $message ) {
			wp_send_json_error( array( 'message' => __( 'This submission could not be found.', 'inbox-ai' ) ), 404 );
		}

		MessageRepository::update_status( $id, 'new' );
		ActivityRepository::log( $id, 'retry_requested', array(), get_current_user_id() );

		AnalysisQueue::enqueue( $id );

		wp_send_json_success( array( 'queued' => true ) );
	}
}
