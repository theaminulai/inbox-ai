<?php
/**
 * Read/write access to the per-message activity timeline table.
 *
 * @package InboxAI\Database
 */

namespace InboxAI\Database;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ActivityRepository
 *
 * One row per timeline event (`received`, `ai_analysis_completed`,
 * `ai_analysis_failed`, `draft_saved`, `status_changed`, `reply_sent`,
 * `reviewed`, `archived`, ...). Powers this page's own Activity Timeline
 * card and, later, Plan 1's Recent Activity and Plan 4's accuracy/
 * response-time metrics — only the write method and the one read method
 * this page needs are built here.
 */
final class ActivityRepository {

	/**
	 * Returns the fully-qualified table name.
	 *
	 * @return string
	 */
	private static function table(): string {
		global $wpdb;

		return $wpdb->prefix . Migrator::ACTIVITIES_TABLE;
	}

	/**
	 * Records one timeline event.
	 *
	 * @param int                  $message_id Message row id this event belongs to.
	 * @param string               $event_type One of the event-type strings listed
	 *                                          in the class docblock.
	 * @param array<string, mixed> $event_data Extra structured data about the event
	 *                                          (e.g. the AI's confidence score, or the
	 *                                          old/new status on a `status_changed`
	 *                                          event). JSON-encoded as stored.
	 * @param int                  $user_id    ID of the acting user, or `0` for a
	 *                                          system-generated event (AI analysis,
	 *                                          the initial `received` event).
	 *
	 * @return int The new row's id, or 0 on failure.
	 */
	public static function log( int $message_id, string $event_type, array $event_data = array(), int $user_id = 0 ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; there is no WP API for it, and a write is never cached.
		$inserted = $wpdb->insert(
			self::table(),
			array(
				'message_id' => $message_id,
				'user_id'    => $user_id,
				'event_type' => $event_type,
				'event_data' => wp_json_encode( $event_data ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		return false === $inserted ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Returns every event for one message, most recent first — exactly the
	 * order the Submission Detail screen's Activity timeline renders in.
	 *
	 * @param int $message_id Message row id.
	 * @param int $limit      Maximum number of events.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_for_message( int $message_id, int $limit = 50 ): array {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + a hardcoded class constant, never user input; table/column identifiers can't be passed through wpdb::prepare()'s placeholders anyway. Custom table; a message's timeline can change between requests, so not cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE message_id = %d ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$message_id,
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( $row ) {
				$data              = json_decode( (string) ( $row['event_data'] ?? '' ), true );
				$row['event_data'] = is_array( $data ) ? $data : array();
				$row['message_id'] = (int) $row['message_id'];
				$row['user_id']    = (int) $row['user_id'];

				return $row;
			},
			$rows
		);
	}
}
