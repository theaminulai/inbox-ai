<?php
/**
 * admin-ajax.php handlers for every admin page's write/read actions.
 *
 * @package CF7AIInbox\Admin
 */

namespace CF7AIInbox\Admin;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIInbox\AI\AnalysisQueue;
use CF7AIInbox\AI\ProviderFactory;
use CF7AIInbox\Database\ActivityRepository;
use CF7AIInbox\Database\MessageRepository;
use CF7AIInbox\Database\UsageRepository;
use CF7AIInbox\Migration\FlamingoCsvImporter;
use CF7AIInbox\Migration\FlamingoImporter;
use CF7AIInbox\Security\Capabilities;
use CF7AIInbox\Services\ReplyService;
use CF7AIInbox\Settings\Repository as SettingsRepository;

/**
 * Class AjaxController
 *
 * One shared controller for every admin-page AJAX action (see
 * docs/plans/*.md, section 3 of each) rather than a class per page — the
 * Settings page and the AI Inbox List page's actions are both implemented
 * here; the Contacts List and Analytics actions land with those pages' own
 * build passes and register here alongside these.
 */
final class AjaxController {

	/**
	 * Nonce action name shared by every Settings-page AJAX call. One nonce
	 * per page (rather than one per individual action) is enough — it
	 * still ties the request to the current user and a short time window;
	 * the real per-action gate is the `current_user_can()` check in
	 * {@see self::check()}.
	 *
	 * @var string
	 */
	public const SETTINGS_NONCE_ACTION = 'cf7ai_inbox_settings';

	/**
	 * Nonce action name shared by every AI Inbox List page AJAX call.
	 *
	 * @var string
	 */
	public const INBOX_NONCE_ACTION = 'cf7ai_inbox_messages';

	/**
	 * Registers every `wp_ajax_*` hook this controller handles.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_cf7ai_get_settings', array( $this, 'get_settings' ) );
		add_action( 'wp_ajax_cf7ai_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_cf7ai_test_connection', array( $this, 'test_connection' ) );
		add_action( 'wp_ajax_cf7ai_list_models', array( $this, 'list_models' ) );
		add_action( 'wp_ajax_cf7ai_flamingo_detect', array( $this, 'flamingo_detect' ) );
		add_action( 'wp_ajax_cf7ai_flamingo_import_batch', array( $this, 'flamingo_import_batch' ) );
		add_action( 'wp_ajax_cf7ai_flamingo_upload_csv', array( $this, 'flamingo_upload_csv' ) );
		add_action( 'wp_ajax_cf7ai_flamingo_import_csv_batch', array( $this, 'flamingo_import_csv_batch' ) );

		add_action( 'wp_ajax_cf7ai_list_messages', array( $this, 'list_messages' ) );
		add_action( 'wp_ajax_cf7ai_get_message', array( $this, 'get_message' ) );
		add_action( 'wp_ajax_cf7ai_save_draft', array( $this, 'save_draft' ) );
		add_action( 'wp_ajax_cf7ai_send_reply', array( $this, 'send_reply' ) );
		add_action( 'wp_ajax_cf7ai_mark_reviewed', array( $this, 'mark_reviewed' ) );
		add_action( 'wp_ajax_cf7ai_archive_message', array( $this, 'archive_message' ) );
		add_action( 'wp_ajax_cf7ai_delete_message', array( $this, 'delete_message' ) );
		add_action( 'wp_ajax_cf7ai_retry_analysis', array( $this, 'retry_analysis' ) );
	}

	/**
	 * Shared nonce + capability gate, matching the pattern already used for
	 * `Requirements`/`Capabilities` elsewhere in this plugin. Sends a JSON
	 * error and stops execution if either check fails.
	 *
	 * @param string $capability   Required capability.
	 * @param string $nonce_action Which page's nonce this request must carry —
	 *                             {@see self::SETTINGS_NONCE_ACTION} or
	 *                             {@see self::INBOX_NONCE_ACTION}.
	 *
	 * @return void
	 */
	private function check( string $capability, string $nonce_action = self::SETTINGS_NONCE_ACTION ): void {
		check_ajax_referer( $nonce_action, 'nonce' );

		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to do this.', 'cf7-ai-inbox' ) ),
				403
			);
		}
	}

	/**
	 * `cf7ai_get_settings` — reads current settings for one tab, or all of
	 * them, and (for the Usage tab) the read-only usage figures too.
	 *
	 * @return void
	 */
	public function get_settings(): void {
		$this->check( Capabilities::MANAGE_SETTINGS );

		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above; phpcs can't trace verification through a helper method call.

		$data = array(
			'provider'      => SettingsRepository::get_provider(),
			'apiKeyMasked'  => SettingsRepository::get_masked_api_key(),
			'hasApiKey'     => SettingsRepository::has_api_key(),
			'general'       => SettingsRepository::get_general(),
			'prompts'       => SettingsRepository::get_prompts(),
			'notifications' => SettingsRepository::get_notifications(),
		);

		if ( 'usage' === $tab ) {
			$data['usage'] = array(
				'totals'    => UsageRepository::get_period_totals( '30_days' ),
				'breakdown' => UsageRepository::get_cost_breakdown( '30_days' ),
			);
		}

		wp_send_json_success( $data );
	}

	/**
	 * `cf7ai_save_settings` — persists one tab's fields. `$_POST['values']`
	 * is a JSON-encoded object; every field inside it is sanitized by the
	 * matching `Settings\Repository::save_*()` method, never trusted as-is.
	 *
	 * @return void
	 */
	public function save_settings(): void {
		$this->check( Capabilities::MANAGE_SETTINGS );

		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce already verified in self::check() above; wp_unslash() is applied explicitly below before decoding; the decoded array is then sanitized field-by-field by each save_*() method (see the docblock above), never trusted or echoed as raw JSON itself.
		$raw    = isset( $_POST['values'] ) ? wp_unslash( $_POST['values'] ) : '';
		$values = json_decode( (string) $raw, true );
		$values = is_array( $values ) ? $values : array();

		switch ( $tab ) {
			case 'ai-settings':
				SettingsRepository::save_provider( $values );

				if ( ! empty( $values['api_key'] ) ) {
					SettingsRepository::save_api_key( sanitize_text_field( (string) $values['api_key'] ) );
				}

				wp_send_json_success(
					array(
						'saved'        => true,
						'apiKeyMasked' => SettingsRepository::get_masked_api_key(),
					)
				);
				break;

			case 'general-settings':
				SettingsRepository::save_general( $values );
				wp_send_json_success( array( 'saved' => true ) );
				break;

			case 'prompts':
				if ( ! empty( $values['reset'] ) ) {
					$defaults = SettingsRepository::reset_prompts_to_defaults();
					wp_send_json_success(
						array(
							'saved'    => true,
							'defaults' => $defaults,
						)
					);
				}

				SettingsRepository::save_prompts( $values );
				wp_send_json_success( array( 'saved' => true ) );
				break;

			case 'notifications':
				SettingsRepository::save_notifications( $values );
				wp_send_json_success( array( 'saved' => true ) );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown settings tab.', 'cf7-ai-inbox' ) ), 400 );
		}
	}

	/**
	 * `cf7ai_test_connection` — validates a (possibly not-yet-saved) API
	 * key against the selected provider. Never persists anything.
	 *
	 * @return void
	 */
	public function test_connection(): void {
		$this->check( Capabilities::MANAGE_SETTINGS );

		$provider = ProviderFactory::create( $this->posted_provider_id() );

		if ( null === $provider ) {
			wp_send_json_error( array( 'message' => __( 'Unknown provider.', 'cf7-ai-inbox' ) ), 400 );
		}

		$result = $provider->validate_credentials( $this->posted_api_key() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'connected' => true ) );
	}

	/**
	 * `cf7ai_list_models` — lists live models for the selected provider +
	 * (possibly not-yet-saved) API key.
	 *
	 * @return void
	 */
	public function list_models(): void {
		$this->check( Capabilities::MANAGE_SETTINGS );

		$provider = ProviderFactory::create( $this->posted_provider_id() );

		if ( null === $provider ) {
			wp_send_json_error( array( 'message' => __( 'Unknown provider.', 'cf7-ai-inbox' ) ), 400 );
		}

		$models = $provider->get_models( $this->posted_api_key() );

		if ( is_wp_error( $models ) ) {
			wp_send_json_error( array( 'message' => $models->get_error_message() ) );
		}

		wp_send_json_success( array( 'models' => $models ) );
	}

	/**
	 * `cf7ai_flamingo_detect` — Import & Migration wizard step 1.
	 *
	 * @return void
	 */
	public function flamingo_detect(): void {
		$this->check( Capabilities::MANAGE_SETTINGS );

		wp_send_json_success( FlamingoImporter::detect() );
	}

	/**
	 * `cf7ai_flamingo_import_batch` — Import & Migration wizard step 3,
	 * called repeatedly with an increasing offset until `done` is true.
	 *
	 * @return void
	 */
	public function flamingo_import_batch(): void {
		$this->check( Capabilities::MANAGE_SETTINGS );

		$offset = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.
		$run_ai = ! empty( $_POST['run_ai'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.

		wp_send_json_success( FlamingoImporter::import_batch( $offset, 25, $run_ai ) );
	}

	/**
	 * `cf7ai_flamingo_upload_csv` — Import & Migration wizard's alternate
	 * "Upload a CSV export" path, step 1: validates and parses the
	 * uploaded file, stages its rows, and reports back what was detected
	 * (mirroring `cf7ai_flamingo_detect`'s response shape) without
	 * touching this plugin's own tables yet.
	 *
	 * @return void
	 */
	public function flamingo_upload_csv(): void {
		$this->check( Capabilities::MANAGE_SETTINGS );

		if ( empty( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce already verified in self::check() above; $_FILES['file']['tmp_name'] is only used with is_uploaded_file()/wp_handle_upload(), which validate the path themselves; never echoed or used in a filesystem call directly.
			wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'cf7-ai-inbox' ) ), 400 );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$overrides = array(
			'test_form' => false,
			'mimes'     => array( 'csv' => 'text/csv' ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- this request's own nonce was already verified in self::check() above; wp_handle_upload() has no nonce concept of its own.
		$moved = wp_handle_upload( $_FILES['file'], $overrides );

		if ( ! isset( $moved['file'] ) ) {
			wp_send_json_error(
				array( 'message' => $moved['error'] ?? __( 'The file could not be uploaded.', 'cf7-ai-inbox' ) ),
				400
			);
		}

		$staged = FlamingoCsvImporter::stage( $moved['file'] );

		// The parsed rows are already safely in a transient (or the
		// upload was rejected) — nothing further needs this temp copy.
		wp_delete_file( $moved['file'] );

		if ( is_wp_error( $staged ) ) {
			wp_send_json_error( array( 'message' => $staged->get_error_message() ), 400 );
		}

		wp_send_json_success( $staged );
	}

	/**
	 * `cf7ai_flamingo_import_csv_batch` — Import & Migration wizard step
	 * 3's batch loop for the CSV-upload path, called repeatedly
	 * (increasing offset) until `done` is true, exactly like
	 * `cf7ai_flamingo_import_batch`.
	 *
	 * @return void
	 */
	public function flamingo_import_csv_batch(): void {
		$this->check( Capabilities::MANAGE_SETTINGS );

		$token  = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.
		$offset = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.
		$run_ai = ! empty( $_POST['run_ai'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.

		if ( '' === $token ) {
			wp_send_json_error( array( 'message' => __( 'This import session has expired. Please upload the file again.', 'cf7-ai-inbox' ) ), 400 );
		}

		wp_send_json_success( FlamingoCsvImporter::import_batch( $token, $offset, 25, $run_ai ) );
	}

	/**
	 * `cf7ai_list_messages` — the AI Inbox List screen's filtered, paginated
	 * table.
	 *
	 * @return void
	 */
	public function list_messages(): void {
		$this->check( Capabilities::VIEW_MESSAGES, self::INBOX_NONCE_ACTION );

		// Nonce already verified in self::check() above; phpcs can't trace verification through a helper method call, hence the per-line ignore comments below.
		$filters = array(
			'status'           => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'priority'         => isset( $_POST['priority'] ) ? sanitize_key( wp_unslash( $_POST['priority'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'category'         => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'form'             => isset( $_POST['form'] ) ? sanitize_text_field( wp_unslash( $_POST['form'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'confidence_below' => isset( $_POST['confidence_below'] ) && '' !== $_POST['confidence_below'] ? absint( wp_unslash( $_POST['confidence_below'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'search'           => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		$page     = isset( $_POST['page'] ) ? max( 1, absint( wp_unslash( $_POST['page'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.
		$per_page = isset( $_POST['per_page'] ) ? max( 1, absint( wp_unslash( $_POST['per_page'] ) ) ) : 20; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.

		wp_send_json_success( MessageRepository::get_filtered( $filters, $page, $per_page ) );
	}

	/**
	 * `cf7ai_get_message` — the Submission Detail screen's data, including
	 * its activity timeline.
	 *
	 * @return void
	 */
	public function get_message(): void {
		$this->check( Capabilities::VIEW_MESSAGES, self::INBOX_NONCE_ACTION );

		$id      = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.
		$message = MessageRepository::find( $id );

		if ( null === $message ) {
			wp_send_json_error( array( 'message' => __( 'This submission could not be found.', 'cf7-ai-inbox' ) ), 404 );
		}

		wp_send_json_success(
			array(
				'message'    => $message,
				'activities' => ActivityRepository::get_for_message( $id ),
			)
		);
	}

	/**
	 * `cf7ai_save_draft` — persists an edited reply draft without sending it.
	 *
	 * @return void
	 */
	public function save_draft(): void {
		$this->check( Capabilities::EDIT_MESSAGES, self::INBOX_NONCE_ACTION );

		$id      = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce already verified in self::check() above; wp_unslash() applied explicitly; wp_kses_post() sanitizes the HTML reply body immediately after.
		$body = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';

		if ( 0 === $id || ! MessageRepository::save_draft( $id, $subject, $body ) ) {
			wp_send_json_error( array( 'message' => __( 'The draft could not be saved.', 'cf7-ai-inbox' ) ), 400 );
		}

		ActivityRepository::log( $id, 'draft_saved', array(), get_current_user_id() );

		wp_send_json_success( array( 'saved' => true ) );
	}

	/**
	 * `cf7ai_send_reply` — sends the reply (saved draft, or an edited
	 * subject/body passed along with the request) to the visitor.
	 *
	 * @return void
	 */
	public function send_reply(): void {
		$this->check( Capabilities::SEND_REPLIES, self::INBOX_NONCE_ACTION );

		$id      = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce already verified in self::check() above; wp_unslash() applied explicitly; wp_kses_post() sanitizes the HTML reply body immediately after.
		$body = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : null;

		$result = ReplyService::send( $id, $subject, $body, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'sent' => true ) );
	}

	/**
	 * `cf7ai_mark_reviewed` — clears a `review`-status row without drafting
	 * or sending a reply (e.g. the admin handled it another way).
	 *
	 * @return void
	 */
	public function mark_reviewed(): void {
		$this->check( Capabilities::EDIT_MESSAGES, self::INBOX_NONCE_ACTION );

		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.

		if ( 0 === $id || ! MessageRepository::update_status( $id, 'reviewed' ) ) {
			wp_send_json_error( array( 'message' => __( 'This submission could not be updated.', 'cf7-ai-inbox' ) ), 400 );
		}

		ActivityRepository::log( $id, 'reviewed', array(), get_current_user_id() );

		wp_send_json_success( array( 'updated' => true ) );
	}

	/**
	 * `cf7ai_archive_message` — moves a row to `archived` (used for both the
	 * row-menu action and manually archiving a false-positive-spam row).
	 *
	 * @return void
	 */
	public function archive_message(): void {
		$this->check( Capabilities::EDIT_MESSAGES, self::INBOX_NONCE_ACTION );

		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.

		if ( 0 === $id || ! MessageRepository::update_status( $id, 'archived' ) ) {
			wp_send_json_error( array( 'message' => __( 'This submission could not be archived.', 'cf7-ai-inbox' ) ), 400 );
		}

		ActivityRepository::log( $id, 'archived', array(), get_current_user_id() );

		wp_send_json_success( array( 'updated' => true ) );
	}

	/**
	 * `cf7ai_delete_message` — soft-deletes a row (list queries always
	 * exclude these; nothing is permanently removed from this screen).
	 *
	 * @return void
	 */
	public function delete_message(): void {
		$this->check( Capabilities::DELETE_MESSAGES, self::INBOX_NONCE_ACTION );

		$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.

		if ( 0 === $id || ! MessageRepository::soft_delete( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'This submission could not be deleted.', 'cf7-ai-inbox' ) ), 400 );
		}

		wp_send_json_success( array( 'deleted' => true ) );
	}

	/**
	 * `cf7ai_retry_analysis` — the Submission Failure screen's "Retry"
	 * action: re-enqueues the message for analysis exactly as its original
	 * capture did, without re-inserting a row.
	 *
	 * @return void
	 */
	public function retry_analysis(): void {
		$this->check( Capabilities::EDIT_MESSAGES, self::INBOX_NONCE_ACTION );

		$id      = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in self::check() above.
		$message = MessageRepository::find( $id );

		if ( null === $message ) {
			wp_send_json_error( array( 'message' => __( 'This submission could not be found.', 'cf7-ai-inbox' ) ), 404 );
		}

		MessageRepository::update_status( $id, 'new' );
		ActivityRepository::log( $id, 'retry_requested', array(), get_current_user_id() );

		AnalysisQueue::enqueue( $id );

		wp_send_json_success( array( 'queued' => true ) );
	}

	/**
	 * Reads and lightly sanitizes `$_POST['provider']`.
	 *
	 * Only called from methods that already ran `self::check()` (which
	 * verifies the request's nonce) before reaching this helper — phpcs
	 * can't trace that verification through the call, hence the ignore
	 * comment below.
	 *
	 * @return string
	 */
	private function posted_provider_id(): string {
		return isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- see docblock above; nonce verified by the calling method.
	}

	/**
	 * Reads `$_POST['api_key']`, falling back to the already-stored key
	 * when the value is empty or is the masked placeholder the browser
	 * shows (never a real key it should overwrite).
	 *
	 * Only called from methods that already ran `self::check()` before
	 * reaching this helper — same nonce-verification note as
	 * {@see self::posted_provider_id()}.
	 *
	 * @return string
	 */
	private function posted_api_key(): string {
		$posted = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- see docblock above; nonce verified by the calling method.

		if ( '' === $posted || false !== strpos( $posted, "\u{2022}" ) ) {
			return (string) SettingsRepository::get_api_key();
		}

		return $posted;
	}
}
