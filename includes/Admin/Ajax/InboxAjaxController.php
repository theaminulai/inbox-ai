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
		add_action( 'wp_ajax_inboxai_bulk_action', array( $this, 'bulk_action' ) );
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
	 * its activity timeline. Also returns that same data pre-rendered as
	 * HTML fragments (`ai_card_html`/`timeline_html`/the two badges) — used
	 * by `detail.js`'s polling after a retried/regenerated analysis (see
	 * `wireRegeneratingAction()`) to swap the finished result into the page
	 * in place, without a full reload, while staying visually identical to
	 * what a real page load would render (same PHP templates either way; see
	 * `inbox/detail-ai-body.php`/`inbox/detail-timeline.php` and
	 * {@see \InboxAI\Support\Template::render_to_string()}).
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

		$activities = ActivityRepository::get_for_message( $id );
		$can_edit   = current_user_can( Capabilities::EDIT_MESSAGES );

		wp_send_json_success(
			array(
				'message'         => $message,
				'activities'      => $activities,
				'ai_card_html'    => \InboxAI\Support\Template::render_to_string( 'inbox/detail-ai-body', array( 'message' => $message, 'can_edit' => $can_edit ) ),
				'timeline_html'   => \InboxAI\Support\Template::render_to_string( 'inbox/detail-timeline', array( 'activities' => $activities ) ),
				'mood_panel_html' => \InboxAI\Support\Template::render_to_string( 'inbox/detail-mood-panel', array( 'message' => $message, 'activities' => $activities ) ),
				'priority_badge'  => \InboxAI\Support\Format::priority_badge_html( (string) $message['priority'] ),
				'status_badge'    => \InboxAI\Support\Format::status_badge_html( (string) $message['workflow_status'] ),
				'ai_time_ago'     => \InboxAI\Support\Format::time_ago( (string) $message['updated_at'] ),
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
	 * `inboxai_retry_analysis` — the Submission Failure screen's "Retry" and
	 * the Submission Detail screen's "Regenerate" actions.
	 *
	 * Runs the AI call synchronously, in this same request, rather than
	 * scheduling a WP-Cron event and returning immediately. Unlike a fresh
	 * submission's first analysis — which deliberately stays off the
	 * visitor-facing request (see {@see AnalysisQueue}'s own docblock) — an
	 * admin explicitly clicking Retry/Regenerate is already sitting on this
	 * screen waiting for a result, the same as clicking Settings → AI
	 * Provider's "Test Connection" button. Queuing it instead only added a
	 * multi-second-to-multi-minute wait (real AI provider latency plus
	 * however long until WP-Cron next actually runs) for no benefit here.
	 *
	 * Returns the same shape as {@see self::get_message()} so `detail.js`
	 * can apply the finished result directly, without polling for it.
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

		( new AnalysisQueue() )->process( $id );

		$message    = MessageRepository::find( $id );
		$activities = ActivityRepository::get_for_message( $id );
		$can_edit   = current_user_can( Capabilities::EDIT_MESSAGES );

		wp_send_json_success(
			array(
				'message'         => $message,
				'activities'      => $activities,
				'ai_card_html'    => \InboxAI\Support\Template::render_to_string( 'inbox/detail-ai-body', array( 'message' => $message, 'can_edit' => $can_edit ) ),
				'timeline_html'   => \InboxAI\Support\Template::render_to_string( 'inbox/detail-timeline', array( 'activities' => $activities ) ),
				'mood_panel_html' => \InboxAI\Support\Template::render_to_string( 'inbox/detail-mood-panel', array( 'message' => $message, 'activities' => $activities ) ),
				'priority_badge'  => \InboxAI\Support\Format::priority_badge_html( (string) $message['priority'] ),
				'status_badge'    => \InboxAI\Support\Format::status_badge_html( (string) $message['workflow_status'] ),
				'ai_time_ago'     => \InboxAI\Support\Format::time_ago( (string) $message['updated_at'] ),
			)
		);
	}

	/**
	 * `inboxai_bulk_action` — the AI Inbox List's "Bulk actions" bar: applies
	 * `reviewed`/`archive`/`delete` to every selected row id in one request,
	 * reusing the exact same per-row methods the row-menu's single actions
	 * already call rather than a separate batch code path.
	 *
	 * @return void
	 */
	public function bulk_action(): void {
		$this->check( Capabilities::EDIT_MESSAGES, self::INBOX_NONCE_ACTION );

		$action = $this->post_key( 'bulk_action' );
		$ids    = array_filter( array_map( 'absint', $this->post_json_array( 'ids' ) ) );

		if ( array() === $ids || ! in_array( $action, array( 'reviewed', 'archive', 'delete' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'No valid submissions were selected.', 'inbox-ai' ) ), 400 );
		}

		// `delete` needs its own, stricter capability — everything else this
		// bar offers only needs the same EDIT_MESSAGES already checked above.
		if ( 'delete' === $action && ! current_user_can( Capabilities::DELETE_MESSAGES ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to delete submissions.', 'inbox-ai' ) ), 403 );
		}

		$updated    = 0;
		$user_id    = get_current_user_id();
		$status_map = array(
			'reviewed' => 'reviewed',
			'archive'  => 'archived',
		);

		foreach ( $ids as $id ) {
			$ok = false;

			if ( 'delete' === $action ) {
				$ok = MessageRepository::soft_delete( $id );
			} elseif ( isset( $status_map[ $action ] ) ) {
				$ok = MessageRepository::update_status( $id, $status_map[ $action ] );

				if ( $ok ) {
					ActivityRepository::log( $id, $status_map[ $action ], array( 'bulk' => true ), $user_id );
				}
			}

			if ( $ok ) {
				++$updated;
			}
		}

		wp_send_json_success( array( 'updated' => $updated ) );
	}
}
