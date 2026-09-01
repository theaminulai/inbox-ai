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
use InboxAI\Database\MessageRepository;
use InboxAI\Settings\Repository as SettingsRepository;

/**
 * Class NotificationService
 *
 * Email-only: the Settings → Notifications page's `notify_*` toggles (see
 * {@see SettingsRepository::get_notifications()}) that store a preference and
 * are actually wired to a real `wp_mail()` call. Same thin `wp_mail()`-wrapper
 * approach as {@see ReplyService}, just aimed at the site owner's inbox
 * instead of the customer's.
 *
 * Slack — a completely different notification channel with its own settings
 * and its own class — lives in {@see SlackIntegrationService} instead, not
 * here; see that class's docblock for why it's kept separate.
 *
 * {@see self::notify_urgent()}, {@see self::notify_analysis_failure()},
 * {@see self::notify_draft_ready()}, and {@see self::send_daily_digest()}
 * were, until this class's most recent pass, UI-only toggles: Settings →
 * Notifications saved a preference for each of them, but nothing ever read
 * it back to actually send anything — only {@see self::notify_customer_reply()}
 * was wired to a real email. Each is now backed by a real `wp_mail()` call,
 * matching what its label on the Settings page already promised.
 */
final class NotificationService {

	/**
	 * The `wp_schedule_event()` action hook name for the daily digest.
	 *
	 * @var string
	 */
	public const DIGEST_CRON_HOOK = 'inboxai_daily_digest';

	/**
	 * Registers the recurring daily-digest cron event. Unconditional, the
	 * same reasoning as {@see \InboxAI\AI\AnalysisQueue::init()} and
	 * {@see \InboxAI\Mail\InboundMailChecker::init()}: a WP-Cron request is
	 * never `is_admin()`, and keeping the schedule itself always-on means
	 * flipping the `daily_digest` toggle takes effect on the very next
	 * scheduled tick — whether it actually sends anything is decided inside
	 * {@see self::send_daily_digest()}, not here.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! wp_next_scheduled( self::DIGEST_CRON_HOOK ) ) {
			wp_schedule_event( self::next_9am_timestamp(), 'daily', self::DIGEST_CRON_HOOK );
		}

		add_action( self::DIGEST_CRON_HOOK, array( __CLASS__, 'send_daily_digest' ) );
	}

	/**
	 * The next occurrence of 9:00 AM in the site's configured timezone, as a
	 * Unix timestamp — today's if it hasn't happened yet, otherwise
	 * tomorrow's. `DateTimeImmutable::getTimestamp()` always returns seconds
	 * since the epoch regardless of the DateTime's own timezone, so no
	 * separate UTC conversion is needed once the wall-clock time itself is
	 * correctly localized here.
	 *
	 * @return int
	 */
	private static function next_9am_timestamp(): int {
		$now   = new \DateTimeImmutable( 'now', wp_timezone() );
		$today = $now->setTime( 9, 0, 0 );

		return ( $today > $now ? $today : $today->modify( '+1 day' ) )->getTimestamp();
	}

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

	/**
	 * Emails the site admin that a submission just came back `urgent`, if
	 * the `notify_urgent` toggle is on. Called from the same two spots as
	 * {@see SlackIntegrationService::notify_urgent()} — {@see \InboxAI\AI\AnalysisQueue::process()}
	 * and {@see \InboxAI\AI\AnalysisQueue::process_reply()} — right after
	 * priority is actually determined; this is the email channel's
	 * counterpart to that Slack notification, not a replacement for it, so
	 * both can be on at once.
	 *
	 * @param array<string, mixed> $message  Submission row merged with the
	 *                                        just-computed analysis fields
	 *                                        (see the two call sites).
	 * @param string                $priority Normalized priority from
	 *                                        {@see \InboxAI\AI\ResponseValidator::normalize_priority()}.
	 *
	 * @return void
	 */
	public static function notify_urgent( array $message, string $priority ): void {
		if ( 'urgent' !== $priority || empty( SettingsRepository::get_notifications()['notify_urgent'] ) ) {
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
			__( 'Urgent submission from %s', 'inbox-ai' ),
			$sender
		);

		$summary = trim( (string) ( $message['ai_summary'] ?? '' ) );
		$url     = add_query_arg( array( 'id' => (int) $message['id'] ), Menu::url( 'inboxai-inbox' ) );

		$lines = array();

		if ( '' !== $summary ) {
			$lines[] = $summary;
			$lines[] = '';
		}

		$lines[] = __( 'View this submission:', 'inbox-ai' );
		$lines[] = $url;

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}

	/**
	 * Emails the site admin that a submission's AI analysis failed, if the
	 * `notify_analysis_failure` toggle is on. Called from
	 * {@see \InboxAI\AI\AnalysisQueue::fail()} right after the row is marked
	 * `failed` and the failure is logged to its timeline — this is the "you
	 * won't otherwise see this" moment the setting exists for, since a
	 * failed WP-Cron run has no other visible surface.
	 *
	 * @param int    $message_id Message row id.
	 * @param string $error      User-safe error message (same one stored via
	 *                            {@see \InboxAI\Database\MessageRepository::mark_failed()}).
	 *
	 * @return void
	 */
	public static function notify_analysis_failure( int $message_id, string $error ): void {
		if ( empty( SettingsRepository::get_notifications()['notify_analysis_failure'] ) ) {
			return;
		}

		$to = get_option( 'admin_email' );

		if ( ! is_email( $to ) ) {
			return;
		}

		$message = MessageRepository::find( $message_id );
		$sender  = null !== $message
			? ( '' !== trim( (string) ( $message['sender_name'] ?? '' ) ) ? (string) $message['sender_name'] : (string) ( $message['sender_email'] ?? '' ) )
			: '';

		$subject = '' !== $sender
			? sprintf(
				/* translators: %s: customer name or email */
				__( 'AI analysis failed for a submission from %s', 'inbox-ai' ),
				$sender
			)
			: __( 'AI analysis failed for a submission', 'inbox-ai' );

		$url = add_query_arg( array( 'id' => $message_id ), Menu::url( 'inboxai-inbox' ) );

		$lines   = array();
		$lines[] = __( 'Error:', 'inbox-ai' );
		$lines[] = $error;
		$lines[] = '';
		$lines[] = __( 'View this submission:', 'inbox-ai' );
		$lines[] = $url;

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}

	/**
	 * Emails the site admin that the configured AI provider seems
	 * unreachable, if Settings → AI Provider → "Send email alert on provider
	 * outage" is on. Deliberately a separate setting/email from
	 * {@see self::notify_analysis_failure()}: that one covers every failure
	 * reason (missing API key, unparseable response, provider outage) and
	 * lives under Settings → Notifications; this one is scoped to just the
	 * "provider itself is down or erroring" case and lives under Settings →
	 * AI Provider instead, so an admin can enable either independently —
	 * e.g. only wanting a heads-up for a real outage, not every individual
	 * submission failure.
	 *
	 * @param string $error User-safe error message from the failed provider call.
	 *
	 * @return void
	 */
	public static function notify_provider_outage( string $error ): void {
		if ( empty( SettingsRepository::get_provider()['email_alert_outage'] ) ) {
			return;
		}

		$to = get_option( 'admin_email' );

		if ( ! is_email( $to ) ) {
			return;
		}

		$lines   = array();
		$lines[] = __( 'Inbox AI could not reach your configured AI provider:', 'inbox-ai' );
		$lines[] = '';
		$lines[] = $error;
		$lines[] = '';
		$lines[] = __( 'Submissions will keep being captured as normal; AI analysis will resume automatically once the provider is reachable again.', 'inbox-ai' );

		wp_mail( $to, __( 'Inbox AI: AI provider appears unreachable', 'inbox-ai' ), implode( "\n", $lines ) );
	}

	/**
	 * Emails the site admin that a reply draft is ready for review, if the
	 * `notify_draft_ready` toggle is on. Called from
	 * {@see \InboxAI\AI\AnalysisQueue::process()} and
	 * {@see \InboxAI\AI\AnalysisQueue::process_reply()}, both right where
	 * `workflow_status` is actually set to `drafted` — never fires for a
	 * submission that didn't clear the confidence threshold or wasn't
	 * eligible for auto-drafting in the first place.
	 *
	 * @param array<string, mixed> $message Submission row merged with the
	 *                                       just-computed analysis fields,
	 *                                       including `reply_subject`.
	 *
	 * @return void
	 */
	public static function notify_draft_ready( array $message ): void {
		if ( empty( SettingsRepository::get_notifications()['notify_draft_ready'] ) ) {
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
			__( 'A reply draft is ready for %s', 'inbox-ai' ),
			$sender
		);

		$url = add_query_arg( array( 'id' => (int) $message['id'] ), Menu::url( 'inboxai-inbox' ) );

		$lines   = array();
		$lines[] = __( 'AI has drafted a reply for you to review and edit — nothing is sent automatically.', 'inbox-ai' );
		$lines[] = '';
		$lines[] = __( 'Review the draft:', 'inbox-ai' );
		$lines[] = $url;

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}

	/**
	 * Emails the site admin a rolling 24-hour summary, if the `daily_digest`
	 * toggle is on. Registered against {@see self::DIGEST_CRON_HOOK} in
	 * {@see self::init()}; the cron event itself always fires at 9:00 AM
	 * site time regardless of the toggle — this method is where "on or off"
	 * is actually decided, matching {@see \InboxAI\Mail\InboundMailChecker::check()}'s
	 * own always-scheduled-but-conditionally-active pattern.
	 *
	 * Always sends when the toggle is on, even when there's nothing new —
	 * that's what makes it a "digest" the admin can rely on checking every
	 * morning, rather than a conditional alert that might silently not show
	 * up on a quiet day.
	 *
	 * @return void
	 */
	public static function send_daily_digest(): void {
		if ( empty( SettingsRepository::get_notifications()['daily_digest'] ) ) {
			return;
		}

		$to = get_option( 'admin_email' );

		if ( ! is_email( $to ) ) {
			return;
		}

		// `created_at` rows are written via `current_time( 'mysql' )` (site-local
		// wall-clock, see MessageRepository::insert()), not UTC — so the
		// comparison boundary here has to be computed the same way (matching
		// Format::relative_time()'s own `current_time( 'timestamp' )` use)
		// rather than gmdate( 'Y-m-d H:i:s', time() ), which would be off by
		// the site's UTC offset on any site not actually set to UTC.
		$since       = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - DAY_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- matches the write side (current_time('mysql')); comparing against a local-time column, not doing anything timezone-sensitive.
		$new_count   = MessageRepository::count_created_since( $since );
		$unread      = MessageRepository::count_unread();
		$urgent      = MessageRepository::count_unread_by_priority( 'urgent' );
		$inbox_url   = Menu::url( 'inboxai-inbox' );

		$subject = 0 === $new_count
			? __( 'Inbox AI daily digest: nothing new in the last 24 hours', 'inbox-ai' )
			: sprintf(
				/* translators: %d: number of new submissions in the last 24 hours */
				_n( 'Inbox AI daily digest: %d new submission', 'Inbox AI daily digest: %d new submissions', $new_count, 'inbox-ai' ),
				$new_count
			);

		$lines   = array();
		$lines[] = sprintf(
			/* translators: %d: number of new submissions in the last 24 hours */
			_n( '%d new submission in the last 24 hours.', '%d new submissions in the last 24 hours.', $new_count, 'inbox-ai' ),
			$new_count
		);
		$lines[] = sprintf(
			/* translators: %d: number of unread submissions currently needing attention */
			_n( '%d submission still needs your attention.', '%d submissions still need your attention.', $unread, 'inbox-ai' ),
			$unread
		);

		if ( $urgent > 0 ) {
			$lines[] = sprintf(
				/* translators: %d: number of those unread submissions marked urgent */
				_n( 'Of those, %d is marked urgent.', 'Of those, %d are marked urgent.', $urgent, 'inbox-ai' ),
				$urgent
			);
		}

		$lines[] = '';
		$lines[] = __( 'Open the AI Inbox:', 'inbox-ai' );
		$lines[] = $inbox_url;

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}
}
