<?php
/**
 * Enforces Settings → General → Data Retention's "Keep submissions for" field.
 *
 * @package InboxAI\Database
 */

namespace InboxAI\Database;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\Settings\Repository as SettingsRepository;

/**
 * Class RetentionPurger
 *
 * "Keep submissions for" (Forever / 24 months / 12 months / 6 months) used
 * to be saved to `wp_options` and never read back anywhere — picking
 * anything other than "Forever" had no effect at all. This class is the
 * real enforcement: a daily WP-Cron job that, when a finite window is
 * selected, permanently deletes every submission older than that window via
 * {@see \InboxAI\Database\MessageRepository::purge_older_than()}.
 *
 * Registered unconditionally in `Plugin::init()`, the same reasoning as
 * {@see \InboxAI\AI\AnalysisQueue::init()} and
 * {@see \InboxAI\Mail\InboundMailChecker::init()}: a WP-Cron request is
 * never `is_admin()`, and keeping the schedule itself always-on means
 * changing the retention setting takes effect on the next scheduled tick —
 * "Forever" is checked inside {@see self::purge()}, not here.
 */
final class RetentionPurger {

	/**
	 * The `wp_schedule_event()` action hook name.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'inboxai_purge_old_submissions';

	/**
	 * `retention_period` value => how many months back the cutoff is.
	 * `forever` is deliberately absent — {@see self::purge()} returns early
	 * for it (and for anything else not in this map) instead of purging.
	 *
	 * @var array<string, int>
	 */
	private const RETENTION_MONTHS = array(
		'24_months' => 24,
		'12_months' => 12,
		'6_months'  => 6,
	);

	/**
	 * Registers the recurring daily purge event.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}

		add_action( self::CRON_HOOK, array( $this, 'purge' ) );
	}

	/**
	 * Deletes every submission older than the configured retention window,
	 * if one is set. A no-op when "Forever" is selected (the default).
	 *
	 * @return void
	 */
	public function purge(): void {
		$period = SettingsRepository::get_general()['retention_period'];

		if ( ! isset( self::RETENTION_MONTHS[ $period ] ) ) {
			return;
		}

		// created_at is written via current_time( 'mysql' ) (site-local, see
		// MessageRepository::insert()), so the cutoff is anchored to that
		// same local "now" — matching the identical fix in
		// MessageRepository::period_to_datetime()/UsageRepository::period_to_datetime().
		$now    = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparing against a local-time created_at column, not doing anything timezone-sensitive.
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . self::RETENTION_MONTHS[ $period ] . ' months', $now ) );

		MessageRepository::purge_older_than( $cutoff );
	}
}
