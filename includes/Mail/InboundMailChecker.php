<?php
/**
 * Polls an IMAP mailbox for customer replies, threads them back onto the
 * right submission, and re-runs AI analysis/drafting against the resulting
 * conversation.
 *
 * @package InboxAI\Mail
 */

namespace InboxAI\Mail;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\AI\AnalysisQueue;
use InboxAI\Database\ActivityRepository;
use InboxAI\Database\MessageRepository;
use InboxAI\Services\NotificationService;
use InboxAI\Settings\Repository as SettingsRepository;

/**
 * Class InboundMailChecker
 *
 * Inbox AI has no REST endpoint or MX-record-based inbound routing — instead
 * this polls a real mailbox via IMAP on a recurring WP-Cron schedule, the
 * same mailbox {@see \InboxAI\Services\ReplyService} already sends outbound
 * replies from. This means: no DNS changes, no third-party inbound-parse
 * service account, and it works with any host that gives you a real IMAP
 * mailbox (cPanel, Google Workspace, etc.) — the tradeoff is a polling delay
 * (however often the cron interval fires) rather than instant delivery.
 *
 * Matching a reply to its submission: {@see \InboxAI\Services\ReplyService::send()}
 * sets a `Reply-To: local+m{id}@domain` header using plus-addressing, which
 * most real mail servers deliver to `local@domain`'s same inbox unchanged.
 * When a customer's mail client replies, it addresses the new message to
 * that literal Reply-To value, so the `+m{id}` marker is usually still
 * present in the "To" header this class reads. If a mail client or relay
 * strips it, {@see self::process_one()} falls back to the most recent row a
 * reply was actually sent to from that same sender address.
 *
 * Once a reply is matched and logged, {@see \InboxAI\AI\AnalysisQueue::process_reply()}
 * re-runs AI analysis against the full conversation so far (not just the
 * original submission) and, if enabled, prepares a follow-up reply draft —
 * the same "AI reads it and drafts a response" experience a fresh submission
 * gets, now repeating on every reply rather than only the first message.
 *
 * Requires PHP's `imap` extension. Not every host enables it — {@see
 * self::check()} records a clear, visible error via
 * {@see \InboxAI\Settings\Repository::record_inbound_check()} rather than
 * failing silently, since there's nowhere else an admin would see this
 * (WP-Cron failures don't otherwise surface anywhere in the UI).
 *
 * Tracking what's already been checked: this class never marks a message
 * `\Seen` and never searches by `UNSEEN`. The mailbox being polled is very
 * often the site owner's actual inbox (the same address CF7 replies go out
 * from), not a dedicated plugin-only address — so touching the `\Seen` flag
 * here would silently mark the owner's own unread mail as read, with no way
 * for them to tell "have I actually looked at this?" from their own mail
 * client anymore. Instead, {@see self::check()} tracks how far it's gotten
 * using each message's IMAP UID (a permanent, per-message id distinct from
 * `\Seen`/`\Unseen`), stored via {@see \InboxAI\Settings\Repository::get_inbound_cursor()}/
 * {@see \InboxAI\Settings\Repository::save_inbound_cursor()}. The very first
 * check after connecting a mailbox starts the cursor at "now" rather than
 * backfilling whatever history already exists there.
 */
final class InboundMailChecker {

	/**
	 * The recurring `wp_schedule_event()` action hook name.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'inboxai_check_inbound_mail';

	/**
	 * Custom `cron_schedules` interval id this class registers. One fixed
	 * name whose *interval length* is computed fresh from Settings →
	 * Notifications → Inbound Email Replies every time {@see
	 * self::register_interval()} runs — see that method's docblock for why
	 * this is enough to make the interval user-configurable without ever
	 * needing to clear/reschedule the event by hand.
	 *
	 * @var string
	 */
	private const CRON_INTERVAL = 'inboxai_inbound_check';

	/**
	 * Fallback interval (minutes) used only if a stored setting is somehow
	 * outside {@see \InboxAI\Settings\Repository::get_inbound_check_interval_options()} —
	 * defense in depth; {@see \InboxAI\Settings\Repository::save_inbound()}
	 * already whitelists this value before it's ever stored.
	 *
	 * @var int
	 */
	private const DEFAULT_INTERVAL_MINUTES = 10;

	/**
	 * Upper bound on how many new messages one run processes — a runaway
	 * mailbox (e.g. inbound checking pointed at a busy general inbox by
	 * mistake) should never turn a single WP-Cron request into a
	 * long-running one.
	 *
	 * @var int
	 */
	private const MAX_MESSAGES_PER_RUN = 25;

	/**
	 * Registers the custom cron interval, the recurring event (if not
	 * already scheduled), and the WordPress hook. Runs unconditionally, the
	 * same as {@see \InboxAI\AI\AnalysisQueue::init()} — a WP-Cron request is
	 * never `is_admin()`, so this can't be gated behind that check. Whether
	 * a check actually does anything is decided inside {@see self::check()}
	 * itself (the `enabled` toggle on Settings → Notifications), not here —
	 * keeping the schedule itself always-on means flipping that toggle on
	 * takes effect on the very next scheduled tick, with no
	 * activation/deactivation dance needed.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'cron_schedules', array( $this, 'register_interval' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- interval is user-configurable (1-60 minutes) via Settings, not a fixed short value; see self::register_interval()'s docblock.

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::CRON_INTERVAL, self::CRON_HOOK );
		}

		add_action( self::CRON_HOOK, array( $this, 'check' ) );
	}

	/**
	 * `cron_schedules` filter callback — registers one interval whose length
	 * is read fresh from Settings → Notifications → Inbound Email Replies'
	 * "Check every" field on every call, rather than a fixed number.
	 *
	 * This is the one piece of WP-Cron behavior that makes the interval
	 * user-configurable with no extra plumbing: after {@see self::CRON_HOOK}
	 * fires, WP-Cron's own `wp_reschedule_event()` calls `wp_get_schedules()`
	 * again — which re-runs this filter — to look up how far in the future
	 * to push the *next* occurrence. So the moment an admin changes the
	 * "Check every" setting, the very next scheduled run already reschedules
	 * itself using the new value; there's nothing to unschedule/reschedule by
	 * hand, and no risk of two competing scheduled events.
	 *
	 * @param array<string, array{interval:int,display:string}> $schedules
	 *
	 * @return array<string, array{interval:int,display:string}>
	 */
	public function register_interval( array $schedules ): array {
		$minutes = SettingsRepository::get_inbound()['check_interval_minutes'];

		if ( ! in_array( $minutes, SettingsRepository::get_inbound_check_interval_options(), true ) ) {
			$minutes = self::DEFAULT_INTERVAL_MINUTES;
		}

		$schedules[ self::CRON_INTERVAL ] = array(
			'interval' => $minutes * MINUTE_IN_SECONDS,
			/* translators: %d: number of minutes between inbound mail checks */
			'display'  => sprintf( _n( 'Every minute (Inbox AI inbound mail check)', 'Every %d minutes (Inbox AI inbound mail check)', $minutes, 'inbox-ai' ), $minutes ),
		);

		return $schedules;
	}

	/**
	 * Runs one inbound-mail check. Registered against {@see self::CRON_HOOK};
	 * never called directly except by WP-Cron or {@see
	 * \InboxAI\Admin\Ajax\SettingsAjaxController::test_inbound_connection()}'s
	 * manual "Test Connection" click (which calls this the same way a cron
	 * tick would, so a Settings-page test behaves identically to the real
	 * background check).
	 *
	 * @return void
	 */
	public function check(): void {
		$inbound = SettingsRepository::get_inbound();

		if ( ! $inbound['enabled'] ) {
			return;
		}

		if ( ! function_exists( 'imap_open' ) ) {
			SettingsRepository::record_inbound_check(
				__( 'PHP\'s imap extension is not available on this server — ask your host to enable it.', 'inbox-ai' )
			);
			return;
		}

		if ( '' === $inbound['host'] || '' === $inbound['username'] ) {
			SettingsRepository::record_inbound_check( __( 'Inbound mailbox host/username is not configured.', 'inbox-ai' ) );
			return;
		}

		$password = SettingsRepository::get_inbound_password();

		if ( null === $password || '' === $password ) {
			SettingsRepository::record_inbound_check( __( 'No inbound mailbox password has been configured.', 'inbox-ai' ) );
			return;
		}

		$mailbox_string = self::build_mailbox_string( $inbound );

		// IMAP functions emit PHP-level warnings on failure rather than
		// throwing — @-suppressed here and checked explicitly via
		// imap_last_error(), the standard defensive pattern for this
		// extension (see php.net/imap_open's own examples).
		$connection = @imap_open( $mailbox_string, $inbound['username'], $password ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- imap_open() has no non-warning failure signal; see comment above.

		if ( false === $connection ) {
			SettingsRepository::record_inbound_check(
				sprintf(
					/* translators: %s: the underlying IMAP error */
					__( 'Could not connect: %s', 'inbox-ai' ),
					imap_last_error() ?: __( 'unknown error', 'inbox-ai' )
				)
			);
			return;
		}

		// UIDVALIDITY + UIDNEXT drive the UID-cursor tracking below, in place
		// of searching for `UNSEEN` messages — see self::process_one()'s
		// docblock for why checking must never rely on (or touch) the
		// `\Seen` flag.
		$status = imap_status( $connection, $mailbox_string, SA_UIDVALIDITY | SA_UIDNEXT );

		if ( false === $status ) {
			imap_close( $connection );
			SettingsRepository::record_inbound_check(
				sprintf(
					/* translators: %s: the underlying IMAP error */
					__( 'Could not read mailbox status: %s', 'inbox-ai' ),
					imap_last_error() ?: __( 'unknown error', 'inbox-ai' )
				)
			);
			return;
		}

		$cursor      = SettingsRepository::get_inbound_cursor();
		$uidvalidity = (int) $status->uidvalidity;
		$uidnext     = (int) $status->uidnext;

		// A UIDVALIDITY that no longer matches what's stored means every
		// previously-recorded UID is meaningless against this mailbox (it
		// was recreated, migrated, or — since a never-configured cursor
		// stores 0 — this is the very first check ever run). Rather than
		// guess how far back to backfill, start watching from whatever's
		// currently newest: nothing existing in the mailbox gets scanned or
		// touched, only mail that arrives from this point forward.
		if ( $uidvalidity !== $cursor['uidvalidity'] ) {
			imap_close( $connection );
			SettingsRepository::save_inbound_cursor( $uidvalidity, max( 0, $uidnext - 1 ) );
			SettingsRepository::record_inbound_check( __( 'Connected — now watching this mailbox for new replies from this point forward.', 'inbox-ai' ) );
			return;
		}

		if ( $uidnext - 1 <= $cursor['last_uid'] ) {
			imap_close( $connection );
			SettingsRepository::record_inbound_check( __( 'Checked just now — no new replies.', 'inbox-ai' ) );
			return;
		}

		$overview = imap_fetch_overview( $connection, ( $cursor['last_uid'] + 1 ) . ':*', FT_UID );

		if ( ! is_array( $overview ) ) {
			$overview = array();
		}

		// Defensive: some IMAP servers return the nearest existing message
		// instead of an empty result when the low end of a UID range no
		// longer exists (e.g. everything before it was deleted) — anything
		// at or below the cursor has already been handled, not new.
		$overview = array_values(
			array_filter( $overview, static fn( $item ) => (int) $item->uid > $cursor['last_uid'] )
		);

		usort( $overview, static fn( $a, $b ) => $a->uid <=> $b->uid );

		$batch       = array_slice( $overview, 0, self::MAX_MESSAGES_PER_RUN );
		$processed   = 0;
		$matched     = 0;
		$highest_uid = $cursor['last_uid'];

		foreach ( $batch as $item ) {
			++$processed;
			$highest_uid = max( $highest_uid, (int) $item->uid );

			if ( self::process_one( $connection, (int) $item->msgno ) ) {
				++$matched;
			}
		}

		imap_close( $connection );

		// If everything currently new fit inside this one run, jump the
		// cursor all the way to UIDNEXT - 1 rather than just the highest UID
		// actually seen — covers the mailbox having a message the overview
		// call happened to skip. If MAX_MESSAGES_PER_RUN capped the batch,
		// only advance past what was actually processed, so the remaining
		// backlog is picked up on the next check instead of silently
		// skipped.
		$new_last_uid = count( $overview ) <= self::MAX_MESSAGES_PER_RUN
			? max( $highest_uid, $uidnext - 1 )
			: $highest_uid;

		SettingsRepository::save_inbound_cursor( $uidvalidity, $new_last_uid );

		SettingsRepository::record_inbound_check(
			sprintf(
				/* translators: 1: number of new messages seen, 2: number matched to a submission */
				__( 'Checked just now — %1$d new message(s), %2$d matched to a submission.', 'inbox-ai' ),
				$processed,
				$matched
			)
		);
	}

	/**
	 * Builds PHP-IMAP's `{host:port/flags}mailbox` connection string.
	 *
	 * @param array{host:string,port:int,encryption:string,mailbox:string} $inbound
	 *
	 * @return string
	 */
	private static function build_mailbox_string( array $inbound ): string {
		$flags = '/imap';

		if ( 'ssl' === $inbound['encryption'] ) {
			$flags .= '/ssl';
		} elseif ( 'tls' === $inbound['encryption'] ) {
			$flags .= '/tls';
		} else {
			$flags .= '/notls';
		}

		return '{' . $inbound['host'] . ':' . $inbound['port'] . $flags . '}' . ( $inbound['mailbox'] ?: 'INBOX' );
	}

	/**
	 * Handles one new IMAP message: matches it to a submission and, if
	 * matched, logs it and triggers AI re-analysis. "Already handled" is
	 * tracked purely by the UID cursor in {@see self::check()} — this
	 * deliberately never touches the message's `\Seen` flag, since this
	 * mailbox is very often the same one the site owner reads as their real
	 * inbox (see this class's own docblock), not a dedicated plugin-only
	 * address. Marking messages read here would silently corrupt the
	 * owner's own "have I seen this yet" state for mail that has nothing to
	 * do with Inbox AI — a message the cron check merely looked at (to see
	 * whether it matched a submission) and didn't match is left exactly as
	 * the owner's mail client already had it.
	 *
	 * @param resource|\IMAP\Connection $connection     PHP-IMAP connection.
	 * @param int                       $message_number Current-session sequence number (not UID) — see {@see self::check()}, which resolves this from the UID overview it fetched.
	 *
	 * @return bool True if matched to a submission and recorded.
	 */
	private static function process_one( $connection, int $message_number ): bool {
		$header = imap_headerinfo( $connection, $message_number );

		if ( false === $header ) {
			return false;
		}

		$to_address     = self::first_email( $header->to ?? array() );
		$from_address   = self::first_email( $header->from ?? array() );
		$matched_message = null;

		if ( '' !== $to_address && preg_match( '/\+m(\d+)@/', $to_address, $found ) ) {
			$matched_message = MessageRepository::find( (int) $found[1] );
		}

		if ( null === $matched_message && '' !== $from_address ) {
			$matched_message = MessageRepository::find_latest_by_sender_email( $from_address );
		}

		if ( null === $matched_message ) {
			return false;
		}

		$body = self::extract_body( $connection, $message_number );

		ActivityRepository::log(
			(int) $matched_message['id'],
			'customer_replied',
			array(
				'from' => $from_address,
				'body' => $body,
			)
		);

		// 'review' — not 'new' (implies unanalyzed) and not 'replied' (already
		// true before this reply arrived) — puts the row back into the
		// "needs a human look" bucket the AI Inbox already highlights. This
		// is also the safe floor if the AI re-analysis call right below
		// doesn't run or fails for any reason (no API key, provider error) —
		// see AnalysisQueue::process_reply()'s own docblock.
		MessageRepository::update_status( (int) $matched_message['id'], 'review' );

		// A reply is new activity the admin hasn't seen yet, even for a
		// submission that was already read once before — see
		// MessageRepository::mark_unread()'s own docblock.
		MessageRepository::mark_unread( (int) $matched_message['id'] );

		// Emails the admin right away, independent of the unread badge —
		// see NotificationService::notify_customer_reply()'s own docblock.
		// No-op if the notify_customer_reply setting is off.
		NotificationService::notify_customer_reply( $matched_message, $body );

		// Re-runs AI analysis against the full conversation so far (not just
		// the original submission) and, if drafting is enabled, prepares a
		// follow-up reply draft — synchronous since this already runs inside
		// a WP-Cron request, with no visitor-facing request to protect from
		// delay.
		( new AnalysisQueue() )->process_reply( (int) $matched_message['id'] );

		return true;
	}

	/**
	 * @param array<int, object> $addresses A `imap_headerinfo()` address list
	 *                                       (`->to`/`->from`).
	 *
	 * @return string The first address as `mailbox@host`, or ''.
	 */
	private static function first_email( array $addresses ): string {
		if ( array() === $addresses || ! isset( $addresses[0]->mailbox, $addresses[0]->host ) ) {
			return '';
		}

		return strtolower( $addresses[0]->mailbox . '@' . $addresses[0]->host );
	}

	/**
	 * Best-effort plain-text body extraction: prefers the first text/plain
	 * part of a multipart message, decoding whatever transfer-encoding it
	 * was sent with, then trims off the quoted-original-message tail most
	 * mail clients append. This is a heuristic, not a full MIME/quote
	 * parser — worst case, a reply shows a bit more quoted history than
	 * strictly necessary, never less of the customer's own new text.
	 *
	 * @param resource|\IMAP\Connection $connection
	 * @param int                       $message_number
	 *
	 * @return string
	 */
	private static function extract_body( $connection, int $message_number ): string {
		$structure = imap_fetchstructure( $connection, $message_number );
		$part_number = null;

		if ( isset( $structure->parts ) && is_array( $structure->parts ) ) {
			foreach ( $structure->parts as $index => $part ) {
				if ( 0 === $part->type && 'PLAIN' === strtoupper( $part->subtype ?? '' ) ) {
					$part_number = (string) ( $index + 1 );
					break;
				}
			}
		}

		$raw = null !== $part_number
			? imap_fetchbody( $connection, $message_number, $part_number )
			: imap_body( $connection, $message_number );

		if ( false === $raw ) {
			return '';
		}

		$raw = quoted_printable_decode( $raw );
		$raw = trim( (string) $raw );

		// Cuts at the first common "quoted reply" marker — covers the two
		// most common formats (Gmail/Apple Mail's "On ... wrote:" line, and
		// Outlook's "-----Original Message-----" separator) plus a run of
		// `>`-quoted lines, which is close enough for a preview without
		// needing a full MIME quote parser.
		$patterns = array(
			'/^\s*On .{0,120} wrote:\s*$/mi',
			'/^-{2,}\s*Original Message\s*-{2,}$/mi',
			'/^>.*$/m',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $raw, $match, PREG_OFFSET_CAPTURE ) ) {
				$raw = substr( $raw, 0, $match[0][1] );
			}
		}

		return mb_substr( trim( $raw ), 0, 5000 );
	}
}
