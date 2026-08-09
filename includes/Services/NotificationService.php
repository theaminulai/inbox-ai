<?php
/**
 * Sends admin-facing email alerts for inbox events.
 *
 * @package InboxAI\Services
 */

namespace InboxAI\Services;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\Admin\Menu;
use InboxAI\Settings\Repository as SettingsRepository;

/**
 * Class NotificationService
 *
 * The Settings → Notifications page has several toggles (see
 * {@see SettingsRepository::get_notifications()}) that store a preference but,
 * until this class, never actually sent anything — {@see self::notify_customer_reply()}
 * is the first of them wired to a real `wp_mail()` call, answering "how do I
 * know a customer has replied" without the admin needing to keep the AI Inbox
 * list open and watch for the unread badge (see `Admin\Menu::append_unread_badge()`).
 * Same thin `wp_mail()`-wrapper approach as {@see ReplyService}, just aimed at
 * the site owner's inbox instead of the customer's.
 */
final class NotificationService {

	/**
	 * Emails the site admin that a customer has replied, if the
	 * `notify_customer_reply` toggle is on. Called from
	 * {@see \InboxAI\Mail\InboundMailChecker::process_one()} right after a
	 * reply is matched to a submission and marked unread — this is the
	 * "something happened since you last looked" moment the setting exists
	 * to surface, so it fires on every matched reply, not just the first one
	 * on a given submission.
	 *
	 * @param array<string, mixed> $message Matched submission row (see `MessageRepository::find()`).
	 * @param string                $body    Plain-text body of the customer's reply.
	 *
	 * @return void
	 */
	public static function notify_customer_reply( array $message, string $body ): void {
		if ( empty( SettingsRepository::get_notifications()['notify_customer_reply'] ) ) {
			return;
		}

		$to = get_option( 'admin_email' );

		if ( ! is_email( $to ) ) {
			return;
		}

		$sender = '' !== trim( (string) ( $message['sender_name'] ?? '' ) )
			? (string) $message['sender_name']
			: (string) ( $message['sender_email'] ?? '' );

		$subject = sprintf(
			/* translators: %s: customer name or email */
			__( 'New reply from %s', 'inbox-ai' ),
			$sender
		);

		$preview = trim( $body );
		if ( mb_strlen( $preview ) > 300 ) {
			$preview = mb_substr( $preview, 0, 300 ) . '…';
		}

		$url = add_query_arg( array( 'id' => (int) $message['id'] ), Menu::url( 'inboxai-inbox' ) );

		$original_subject = trim( (string) ( $message['subject'] ?? '' ) );

		$lines   = array();
		$lines[] = sprintf(
			/* translators: %s: customer name or email */
			__( '%s just replied to their message.', 'inbox-ai' ),
			$sender
		);
		$lines[] = '';

		if ( '' !== $original_subject ) {
			/* translators: %s: original submission subject */
			$lines[] = sprintf( __( 'Original subject: %s', 'inbox-ai' ), $original_subject );
			$lines[] = '';
		}

		$lines[] = __( 'Reply:', 'inbox-ai' );
		$lines[] = '' !== $preview ? $preview : __( '(no readable text found in this reply)', 'inbox-ai' );
		$lines[] = '';
		$lines[] = __( 'View this submission:', 'inbox-ai' );
		$lines[] = $url;

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}
}
