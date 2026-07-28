<?php
/**
 * Routes Contact Form 7 submissions into message capture + AI analysis.
 *
 * @package InboxAI\CF7
 */

namespace InboxAI\CF7;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\AI\AnalysisQueue;
use InboxAI\Database\ActivityRepository;
use InboxAI\Database\MessageRepository;
use InboxAI\Settings\Repository as SettingsRepository;

/**
 * Class SubmissionHandler
 *
 * Hooked on `wpcf7_before_send_mail` — fired before Contact Form 7 even
 * attempts to send its own notification email — rather than
 * `wpcf7_mail_sent`, so a submission is captured even if the site's mail
 * sending is broken. `wpcf7_mail_sent`/`wpcf7_mail_failed` only update the
 * already-captured row's `mail_status` afterwards; they never capture
 * anything themselves.
 *
 * A submission that Contact Form 7 (or another plugin, e.g. Akismet) flags
 * as spam never reaches `wpcf7_before_send_mail` at all, so spam is
 * captured separately via the `wpcf7_spam` filter — a genuinely spam-marked
 * submission skips AI analysis entirely (no need to spend an API call
 * confirming what's already known) and is archived immediately if the
 * General settings' "auto-archive detected spam" toggle is on.
 */
final class SubmissionHandler {

	/**
	 * The message row id captured for the current request, if any — lets
	 * the later `wpcf7_mail_sent`/`wpcf7_mail_failed` hooks (which only
	 * receive the contact form, not the submission or a row id) find their
	 * way back to the row `capture()` just inserted.
	 *
	 * @var int|null
	 */
	private static ?int $current_message_id = null;

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wpcf7_before_send_mail', array( $this, 'capture' ), 10, 3 );
		add_action( 'wpcf7_mail_sent', array( $this, 'mark_sent' ) );
		add_action( 'wpcf7_mail_failed', array( $this, 'mark_mail_failed' ) );
		add_filter( 'wpcf7_spam', array( $this, 'capture_spam' ), 20, 2 );
	}

	/**
	 * Handles `wpcf7_before_send_mail`.
	 *
	 * @param \WPCF7_ContactForm     $contact_form The form that was submitted.
	 * @param bool                   $abort        Unused — this plugin never aborts a send.
	 * @param \WPCF7_Submission|null $submission   The current submission, when CF7 passes it.
	 *
	 * @return void
	 */
	public function capture( $contact_form, &$abort, $submission = null ): void {
		self::$current_message_id = null;

		if ( ! $contact_form instanceof \WPCF7_ContactForm ) {
			return;
		}

		if ( ! SettingsRepository::is_form_monitored( $contact_form->id() ) ) {
			return;
		}

		$submission = $submission instanceof \WPCF7_Submission ? $submission : \WPCF7_Submission::get_instance();

		if ( ! $submission instanceof \WPCF7_Submission ) {
			return;
		}

		$this->capture_once( $contact_form, $submission );
	}

	/**
	 * Handles the `wpcf7_spam` filter — captures a submission CF7's own spam
	 * check (or a plugin like Akismet hooked earlier) has already flagged,
	 * since a flagged submission never reaches `wpcf7_before_send_mail`.
	 * Always returns `$spam` unchanged; this only observes and captures.
	 *
	 * @param bool                   $spam       Whether the submission is considered spam so far.
	 * @param \WPCF7_Submission|null $submission The current submission.
	 *
	 * @return bool
	 */
	public function capture_spam( $spam, $submission ) {
		if ( ! $spam || ! $submission instanceof \WPCF7_Submission ) {
			return $spam;
		}

		$contact_form = $submission->get_contact_form();

		if ( ! $contact_form instanceof \WPCF7_ContactForm || ! SettingsRepository::is_form_monitored( $contact_form->id() ) ) {
			return $spam;
		}

		$message_id = $this->capture_once( $contact_form, $submission, true );

		if ( null !== $message_id ) {
			MessageRepository::mark_spam( $message_id );

			if ( SettingsRepository::get_general()['auto_archive_spam'] ) {
				MessageRepository::update_status( $message_id, 'archived' );
			}
		}

		return $spam;
	}

	/**
	 * Shared capture logic for both hooks above: dedupes via
	 * {@see SubmissionMapper::compute_hash()}, inserts the row, logs the
	 * initial `received` activity, and (unless this is a known-spam capture)
	 * enqueues AI analysis.
	 *
	 * @param \WPCF7_ContactForm $contact_form The form that was submitted.
	 * @param \WPCF7_Submission  $submission   The current submission.
	 * @param bool               $is_spam      Whether this capture is happening via the
	 *                                         `wpcf7_spam` filter (skips AI analysis).
	 *
	 * @return int|null The message row id, or null if the insert failed.
	 */
	private function capture_once( \WPCF7_ContactForm $contact_form, \WPCF7_Submission $submission, bool $is_spam = false ): ?int {
		$hash     = SubmissionMapper::compute_hash( $contact_form, $submission );
		$existing = MessageRepository::find_by_hash( $hash );

		if ( null !== $existing ) {
			self::$current_message_id = (int) $existing['id'];

			return self::$current_message_id;
		}

		$data                    = SubmissionMapper::map( $contact_form, $submission );
		$data['submission_hash'] = $hash;

		$id = MessageRepository::insert( $data );

		if ( 0 === $id ) {
			return null;
		}

		self::$current_message_id = $id;

		ActivityRepository::log( $id, 'received' );

		if ( ! $is_spam ) {
			AnalysisQueue::enqueue( $id );
		}

		return $id;
	}

	/**
	 * Handles `wpcf7_mail_sent`.
	 *
	 * @return void
	 */
	public function mark_sent(): void {
		$this->update_mail_status( 'sent' );
	}

	/**
	 * Handles `wpcf7_mail_failed`.
	 *
	 * @return void
	 */
	public function mark_mail_failed(): void {
		$this->update_mail_status( 'failed' );
	}

	/**
	 * @param string $status `sent` or `failed`.
	 *
	 * @return void
	 */
	private function update_mail_status( string $status ): void {
		if ( null === self::$current_message_id ) {
			return;
		}

		MessageRepository::update_mail_status( self::$current_message_id, $status );
	}
}
