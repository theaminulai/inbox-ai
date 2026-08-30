<?php
/**
 * Read access to the AI usage/cost table.
 *
 * @package InboxAI\Database
 */

namespace InboxAI\Database;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UsageRepository
 *
 * Powers the Settings page's Usage & Billing tab (thin, read-only view)
 * and, later, the Overview page's AI Usage summary card. The table itself
 * is written to by the AI Inbox List page's analysis queue — until that
 * exists, every method here legitimately returns zeros against an empty
 * table rather than failing.
 */
final class UsageRepository {

	/**
	 * Records one AI request's token usage and estimated cost.
	 *
	 * Written by {@see \InboxAI\AI\AnalysisQueue} for both the analysis
	 * call and (if it ran) the reply-draft call — `$request_status`
	 * distinguishes the two for the Settings page's Usage & Billing "Cost by
	 * Request Type" breakdown (see docs/plans/05-settings-plan.md, section 3.5).
	 *
	 * @param int|null $message_id        Related message row id, if any.
	 * @param string   $provider          Provider id (`openai`, `anthropic`, `google`).
	 * @param string   $model             Model identifier used.
	 * @param int      $prompt_tokens     Prompt tokens reported by the provider.
	 * @param int      $completion_tokens Completion tokens reported by the provider.
	 * @param float    $estimated_cost    Rough estimated cost in USD (blended
	 *                                    per-provider rate, not exact per-model
	 *                                    billing — see `AnalysisQueue::estimate_cost()`).
	 * @param string   $request_status    `analysis` or `reply_draft`.
	 *
	 * @return int The new row's id, or 0 on failure.
	 */
	public static function record( ?int $message_id, string $provider, string $model, int $prompt_tokens, int $completion_tokens, float $estimated_cost, string $request_status ): int {
		global $wpdb;

		$table = $wpdb->prefix . Migrator::USAGE_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; there is no WP API for it, and a write is never cached.
		$inserted = $wpdb->insert(
			$table,
			array(
				'message_id'        => $message_id,
				'provider'          => $provider,
				'model'             => $model,
				'prompt_tokens'     => $prompt_tokens,
				'completion_tokens' => $completion_tokens,
				'estimated_cost'    => $estimated_cost,
				'request_status'    => $request_status,
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%f', '%s', '%s' )
		);

		return false === $inserted ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Total requests/tokens/cost for a period.
	 *
	 * @param string $period `30_days` (default), `this_month`, or `{n}_days`.
	 *
	 * @return array{total_requests:int,prompt_tokens:int,completion_tokens:int,estimated_cost:float}
	 */
	public static function get_period_totals( string $period = '30_days' ): array {
		global $wpdb;

		$table = $wpdb->prefix . Migrator::USAGE_TABLE;
		$since = self::period_to_datetime( $period );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only aggregate against this plugin's own table.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total_requests, COALESCE(SUM(prompt_tokens),0) AS prompt_tokens, COALESCE(SUM(completion_tokens),0) AS completion_tokens, COALESCE(SUM(estimated_cost),0) AS estimated_cost FROM {$table} WHERE created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is $wpdb->prefix + a hardcoded class constant, never user input.
				$since
			),
			ARRAY_A
		);

		return array(
			'total_requests'    => (int) ( $row['total_requests'] ?? 0 ),
			'prompt_tokens'     => (int) ( $row['prompt_tokens'] ?? 0 ),
			'completion_tokens' => (int) ( $row['completion_tokens'] ?? 0 ),
			'estimated_cost'    => (float) ( $row['estimated_cost'] ?? 0 ),
		);
	}

	/**
	 * Cost grouped by `request_status`, as a stand-in for the
	 * analysis/reply-draft/regeneration breakdown the mockup shows.
	 *
	 * A dedicated `request_type` distinction doesn't exist on the usage
	 * table yet (see docs/plans/05-settings-plan.md, section 3.5) — this
	 * groups by whatever `request_status` values the AI Inbox List page
	 * ends up writing, and returns an empty array (rendered as "no data
	 * yet") until it's writing any.
	 *
	 * @param string $period Same accepted values as {@see self::get_period_totals()}.
	 *
	 * @return array<string, float> request_status => total cost.
	 */
	public static function get_cost_breakdown( string $period = '30_days' ): array {
		global $wpdb;

		$table = $wpdb->prefix . Migrator::USAGE_TABLE;
		$since = self::period_to_datetime( $period );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT request_status, COALESCE(SUM(estimated_cost),0) AS cost FROM {$table} WHERE created_at >= %s GROUP BY request_status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is $wpdb->prefix + a hardcoded class constant, never user input.
				$since
			),
			ARRAY_A
		);

		$breakdown = array();

		foreach ( (array) $rows as $row ) {
			$key = '' !== (string) $row['request_status'] ? (string) $row['request_status'] : 'unspecified';

			$breakdown[ $key ] = (float) $row['cost'];
		}

		return $breakdown;
	}

	/**
	 * Resolves a period string to a `created_at >=` cutoff.
	 *
	 * @param string $period `30_days`, `this_month`, `{n}_days`, or `{n}_year`/`{n}_years`.
	 *
	 * @return string MySQL datetime.
	 */
	private static function period_to_datetime( string $period ): string {
		// `created_at` is written via current_time( 'mysql' ) — site-local
		// wall-clock time, not UTC (see self::record()) — so the cutoff here
		// has to be anchored to that same local "now", matching the identical
		// fix in {@see \InboxAI\Database\MessageRepository::period_to_datetime()}.
		$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- comparing against a local-time created_at column, not doing anything timezone-sensitive.

		if ( 'this_month' === $period ) {
			return current_time( 'Y-m-01 00:00:00' );
		}

		// Handled via strtotime()'s own "-N years" parsing (not N*365 days)
		// so leap years land on the correct calendar date.
		if ( preg_match( '/^(\d+)_years?$/', $period, $matches ) ) {
			return gmdate( 'Y-m-d H:i:s', strtotime( '-' . (int) $matches[1] . ' years', $now ) );
		}

		$days = 30;

		if ( preg_match( '/^(\d+)_days$/', $period, $matches ) ) {
			$days = (int) $matches[1];
		}

		return gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days", $now ) );
	}
}
