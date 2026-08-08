<?php
/**
 * Sends a reply to a captured submission's visitor.
 *
 * @package InboxAI\Services
 */

namespace InboxAI\Services;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\Database\ActivityRepository;
use InboxAI\Database\MessageRepository;
use InboxAI\Settings\Repository as SettingsRepository;
use WP_Error;

/**
 * Class ReplyService
 *
 * A thin wrapper around `wp_mail()` — no dedicated "from" address/name
 * setting exists on the Settings page (see `Settings\Repository`), so this
 * relies on `wp_mail()`'s own defaults (the site admin address and blog
 * name), exactly as Contact Form 7's own notification emails do.
 *
 * When Settings → Notifications → Inbound Email is configured, every
 * outbound reply also gets a `Reply-To` using plus-addressing — e.g.
 * `hello+m123@yourdomain.com` for message id 123 — so a customer hitting
 * "Reply" in their own mail client sends back to a literal address
 * {@see \InboxAI\Mail\InboundMailChecker} can recognize on the very same
 * mailbox it polls via IMAP. Plus-addressing (`user+anything@domain`)
 * delivers to `user@domain` on virtually every real mail server without any
 * extra mailbox/alias setup, which is why this needs no DNS or hosting
 * changes to work. If inbound checking isn't configured, this is skipped
 * entirely and a reply behaves exactly as it always has.
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
			return new WP_Error( 'inboxai_not_found', __( 'This submission could not be found.', 'inbox-ai' ) );
		}

		$to = (string) $message['sender_email'];

		if ( '' === $to || ! is_email( $to ) ) {
			return new WP_Error( 'inboxai_no_email', __( 'This submission has no valid sender email to reply to.', 'inbox-ai' ) );
		}

		$subject = null !== $subject_override ? $subject_override : (string) $message['reply_subject'];
		$body    = null !== $body_override ? $body_override : (string) $message['reply_draft'];

		if ( '' === trim( $body ) ) {
			return new WP_Error( 'inboxai_empty_reply', __( 'The reply body is empty.', 'inbox-ai' ) );
		}

		if ( '' === trim( $subject ) ) {
			$subject = 'Re: ' . (string) $message['subject'];
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$reply_to = self::build_reply_to( $message_id );

		if ( null !== $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$sent = wp_mail( $to, $subject, wpautop( $body ), $headers );

		if ( ! $sent ) {
			return new WP_Error( 'inboxai_mail_failed', __( 'The reply could not be sent. Check your site\'s outgoing mail configuration.', 'inbox-ai' ) );
		}

		MessageRepository::set_reply_sent( $message_id, $subject, $body );

		// 'body' is included (not just 'subject') so a later customer reply's
		// AI re-analysis can reconstruct the full back-and-forth — see
		// AnalysisQueue::build_conversation_transcript().
		ActivityRepository::log( $message_id, 'reply_sent', array( 'subject' => $subject, 'body' => $body ), $user_id );

		return true;
	}

	/**
	 * Builds a `local+m{id}@domain` Reply-To address from the Inbound Email
	 * mailbox configured on the Notifications tab, or `null` if inbound
	 * checking isn't enabled/configured (in which case a reply's headers
	 * stay exactly as they were before this feature existed).
	 *
	 * @param int $message_id Message row id.
	 *
	 * @return string|null
	 */
	private static function build_reply_to( int $message_id ): ?string {
		$inbound = SettingsRepository::get_inbound();

		if ( ! $inbound['enabled'] || '' === $inbound['username'] || ! is_email( $inbound['username'] ) ) {
			return null;
		}

		[ $local, $domain ] = explode( '@', $inbound['username'], 2 );

		return $local . '+m' . $message_id . '@' . $domain;
	}
}
