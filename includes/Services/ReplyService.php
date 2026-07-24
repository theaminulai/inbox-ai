<?php
/**
 * Sends a reply to a captured submission's visitor.
 *
 * @package CF7AIInbox\Services
 */

namespace CF7AIInbox\Services;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIInbox\Database\ActivityRepository;
use CF7AIInbox\Database\MessageRepository;
use WP_Error;

/**
 * Class ReplyService
 *
 * A thin wrapper around `wp_mail()` — no dedicated "from" address/name
 * setting exists on the Settings page (see `Settings\Repository`), so this
 * relies on `wp_mail()`'s own defaults (the site admin address and blog
 * name), exactly as Contact Form 7's own notification emails do.
 */
final class ReplyService {

	/**
	 * Sends a reply for one message, using either its already-saved draft or
	 * an explicit subject/body override (e.g. the admin edited the draft in
	 * the composer before sending without saving first).
	 *
	 * @param int         $message_id     Message row id.
	 * @param string|null $subject_override Subject to send instead of the saved draft.
	 * @param string|null $body_override    Body to send instead of the saved draft.
	 * @param int         $user_id          Acting user id, for the activity log.
	 *
	 * @return true|WP_Error
	 */
	public static function send( int $message_id, ?string $subject_override = null, ?string $body_override = null, int $user_id = 0 ) {
		$message = MessageRepository::find( $message_id );

		if ( null === $message ) {
			return new WP_Error( 'cf7ai_not_found', __( 'This submission could not be found.', 'cf7-ai-inbox' ) );
		}

		$to = (string) $message['sender_email'];

		if ( '' === $to || ! is_email( $to ) ) {
			return new WP_Error( 'cf7ai_no_email', __( 'This submission has no valid sender email to reply to.', 'cf7-ai-inbox' ) );
		}

		$subject = null !== $subject_override ? $subject_override : (string) $message['reply_subject'];
		$body    = null !== $body_override ? $body_override : (string) $message['reply_draft'];

		if ( '' === trim( $body ) ) {
			return new WP_Error( 'cf7ai_empty_reply', __( 'The reply body is empty.', 'cf7-ai-inbox' ) );
		}

		if ( '' === trim( $subject ) ) {
			$subject = 'Re: ' . (string) $message['subject'];
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$sent = wp_mail( $to, $subject, wpautop( $body ), $headers );

		if ( ! $sent ) {
			return new WP_Error( 'cf7ai_mail_failed', __( 'The reply could not be sent. Check your site\'s outgoing mail configuration.', 'cf7-ai-inbox' ) );
		}

		MessageRepository::set_reply_sent( $message_id, $subject, $body );

		ActivityRepository::log( $message_id, 'reply_sent', array( 'subject' => $subject ), $user_id );

		return true;
	}
}
