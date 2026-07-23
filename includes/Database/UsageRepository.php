<?php
/**
 * Read access to the AI usage/cost table.
 *
 * @package CF7AIInbox\Database
 */

namespace CF7AIInbox\Database;

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
	 * @param string $period `30_days`, `this_month`, or `{n}_days`.
	 *
	 * @return string MySQL datetime.
	 */
	private static function period_to_datetime( string $period ): string {
		if ( 'this_month' === $period ) {
			return gmdate( 'Y-m-01 00:00:00' );
		}

		$days = 30;

		if ( preg_match( '/^(\d+)_days$/', $period, $matches ) ) {
			$days = (int) $matches[1];
		}

		return gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
	}
}
