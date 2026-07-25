<?php
/**
 * Read/write access to the captured-submission table.
 *
 * @package CF7AIInbox\Database
 */

namespace CF7AIInbox\Database;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MessageRepository
 *
 * One row per captured Contact Form 7 submission (see
 * docs/plans/02-ai-inbox-list-plan.md, section 2). Owned by the AI Inbox
 * List page — {@see \CF7AIInbox\CF7\SubmissionHandler} writes the initial
 * row, {@see \CF7AIInbox\AI\AnalysisQueue} fills in the AI columns, and
 * {@see \CF7AIInbox\Admin\AjaxController} reads/writes everything else via
 * this class. Only the read/write methods this page's own plan needs are
 * built here; Plans 1/3/4 add their own aggregate read methods on top of
 * this same table/class later without needing to touch what's here.
 */
final class MessageRepository {

	/**
	 * Returns the fully-qualified table name.
	 *
	 * @return string
	 */
	private static function table(): string {
		global $wpdb;

		return $wpdb->prefix . Migrator::MESSAGES_TABLE;
	}

	/**
	 * Inserts a newly-captured submission.
	 *
	 * Expected keys in `$data`: `form_id` (int), `form_title` (string),
	 * `submission_hash` (string), `sender_name` (string), `sender_email`
	 * (string), `subject` (string), `message` (string), `fields` (array,
	 * JSON-encoded), `meta` (array, JSON-encoded), `channel` (string),
	 * `submission_status` (string), `mail_status` (string, default
	 * `pending`). Always inserted with `workflow_status = 'new'` and
	 * `spam_status = 0` unless explicitly overridden.
	 *
	 * @param array<string, mixed> $data Submission data.
	 *
	 * @return int The new row's id, or 0 on failure.
	 */
	public static function insert( array $data ): int {
		global $wpdb;

		$now = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; there is no WP API for it, and a write is never cached.
		$inserted = $wpdb->insert(
			self::table(),
			array(
				'site_id'           => get_current_blog_id(),
				'form_id'           => (int) ( $data['form_id'] ?? 0 ),
				'form_title'        => (string) ( $data['form_title'] ?? '' ),
				'submission_hash'   => (string) ( $data['submission_hash'] ?? '' ),
				'sender_name'       => (string) ( $data['sender_name'] ?? '' ),
				'sender_email'      => (string) ( $data['sender_email'] ?? '' ),
				'subject'           => (string) ( $data['subject'] ?? '' ),
				'message'           => (string) ( $data['message'] ?? '' ),
				'fields'            => wp_json_encode( $data['fields'] ?? array() ),
				'meta'              => wp_json_encode( $data['meta'] ?? array() ),
				'channel'           => (string) ( $data['channel'] ?? 'contact-form-7' ),
				'submission_status' => (string) ( $data['submission_status'] ?? '' ),
				'workflow_status'   => (string) ( $data['workflow_status'] ?? 'new' ),
				'mail_status'       => (string) ( $data['mail_status'] ?? 'pending' ),
				'spam_status'       => ! empty( $data['spam_status'] ) ? 1 : 0,
				'created_at'        => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Returns a single message row, or null if it doesn't exist (or is
	 * soft-deleted).
	 *
	 * @param int $id Row id.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function find( int $id ): ?array {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + a hardcoded class constant, never user input; identifiers can't be passed through prepare() placeholders anyway. Custom table; a row can change between requests, so not cached.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return self::decode_row( $row );
	}

	/**
	 * Finds an existing, non-deleted row by its dedup hash.
	 *
	 * @param string $hash Value from {@see \CF7AIInbox\CF7\SubmissionMapper::compute_hash()}.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function find_by_hash( string $hash ): ?array {
		global $wpdb;

		if ( '' === $hash ) {
			return null;
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + a hardcoded class constant, never user input; identifiers can't be passed through prepare() placeholders anyway.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE submission_hash = %s ORDER BY id DESC LIMIT 1", $hash ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return self::decode_row( $row );
	}

	/**
	 * Submission counts for the current calendar month, grouped by form —
	 * powers the Settings page's Monitored Forms list (see
	 * `includes/Templates/settings/general.php`). One grouped query rather
	 * than one per form.
	 *
	 * @return array<int, int> form_id => submission count this month.
	 */
	public static function count_this_month_by_form(): array {
		global $wpdb;

		$table = self::table();
		$since = current_time( 'Y-m-01 00:00:00' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- custom table; a form's monthly count can change between requests, so not cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT form_id, COUNT(*) AS total FROM {$table} WHERE deleted_at IS NULL AND created_at >= %s GROUP BY form_id", $since ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is $wpdb->prefix + a hardcoded class constant, never user input.
			ARRAY_A
		);

		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row['form_id'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Returns a filtered, paginated page of messages, most recent first.
	 *
	 * @param array<string, mixed> $filters  `status`, `priority`, `category` (exact
	 *                                       match), `form` (exact match against
	 *                                       `form_title`), `confidence_below` (int),
	 *                                       `search` (matched against sender name/
	 *                                       email/subject/message), `period`
	 *                                       (`created_at` cutoff — see
	 *                                       {@see self::period_to_datetime()}).
	 * @param int                  $page     1-indexed page number.
	 * @param int                  $per_page Rows per page.
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public static function get_filtered( array $filters, int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$page     = max( 1, $page );
		$per_page = max( 1, $per_page );
		$offset   = ( $page - 1 ) * $per_page;
		$table    = self::table();

		[ $where_sql, $where_values ] = self::build_where( $filters );

		// $table/$where_sql are built from hardcoded fragments and a fixed
		// column whitelist in build_where(); every user-supplied value is
		// always passed through $where_values/$per_page/$offset as
		// prepare() placeholders.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
		// No active filters means $where_values is empty and $where_sql has no
		// placeholders left to fill (just the hardcoded "deleted_at IS NULL"
		// clause) — calling wpdb::prepare() with an empty args array in that
		// case trips WordPress's own "query argument must have a placeholder"
		// warning (added in 6.2), so it's skipped entirely when there's
		// nothing to bind; $count_sql still has no user input in it either way.
		$total = (int) $wpdb->get_var( array() === $where_values ? $count_sql : $wpdb->prepare( $count_sql, $where_values ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d",
				array_merge( $where_values, array( $per_page, $offset ) )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$items = array_map(
			static function ( $row ) {
				return self::decode_row( $row );
			},
			is_array( $rows ) ? $rows : array()
		);

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Builds a `WHERE` clause (always excluding soft-deleted rows) and its
	 * matching prepare() values from a set of filters.
	 *
	 * @param array<string, mixed> $filters See {@see self::get_filtered()}.
	 *
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private static function build_where( array $filters ): array {
		global $wpdb;

		$clauses = array( 'deleted_at IS NULL' );
		$values  = array();

		if ( ! empty( $filters['status'] ) && 'all' !== $filters['status'] ) {
			$clauses[] = 'workflow_status = %s';
			$values[]  = (string) $filters['status'];
		}

		if ( ! empty( $filters['priority'] ) && 'all' !== $filters['priority'] ) {
			$clauses[] = 'priority = %s';
			$values[]  = (string) $filters['priority'];
		}

		if ( ! empty( $filters['category'] ) && 'all' !== $filters['category'] ) {
			$clauses[] = 'category = %s';
			$values[]  = (string) $filters['category'];
		}

		if ( ! empty( $filters['form'] ) && 'all' !== $filters['form'] ) {
			$clauses[] = 'form_title = %s';
			$values[]  = (string) $filters['form'];
		}

		if ( isset( $filters['confidence_below'] ) && '' !== $filters['confidence_below'] ) {
			$clauses[] = 'confidence IS NOT NULL AND confidence < %d';
			$values[]  = (int) $filters['confidence_below'];
		}

		if ( ! empty( $filters['search'] ) ) {
			$like      = '%' . $wpdb->esc_like( (string) $filters['search'] ) . '%';
			$clauses[] = '( sender_name LIKE %s OR sender_email LIKE %s OR subject LIKE %s OR message LIKE %s OR form_title LIKE %s )';
			array_push( $values, $like, $like, $like, $like, $like );
		}

		if ( ! empty( $filters['period'] ) && 'all' !== $filters['period'] ) {
			$clauses[] = 'created_at >= %s';
			$values[]  = self::period_to_datetime( (string) $filters['period'] );
		}

		return array( 'WHERE ' . implode( ' AND ', $clauses ), $values );
	}

	/**
	 * Resolves a `period` filter value (`7_days`, `30_days`, `90_days`,
	 * `this_month`, `{n}_year`/`{n}_years`) to a `created_at >=` cutoff.
	 * Mirrors {@see \CF7AIInbox\Database\UsageRepository::period_to_datetime()}
	 * — kept separately rather than shared, matching this table's own
	 * self-contained read methods.
	 *
	 * @param string $period Raw period value; anything unrecognized falls
	 *                       back to 30 days.
	 *
	 * @return string MySQL datetime.
	 */
	private static function period_to_datetime( string $period ): string {
		if ( 'this_month' === $period ) {
			return gmdate( 'Y-m-01 00:00:00' );
		}

		if ( preg_match( '/^(\d+)_years?$/', $period, $matches ) ) {
			return gmdate( 'Y-m-d H:i:s', strtotime( '-' . (int) $matches[1] . ' years' ) );
		}

		$days = 30;

		if ( preg_match( '/^(\d+)_days$/', $period, $matches ) ) {
			$days = (int) $matches[1];
		}

		return gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
	}

	/**
	 * Updates just the workflow status.
	 *
	 * @param int    $id     Row id.
	 * @param string $status New `workflow_status` value.
	 *
	 * @return bool
	 */
	public static function update_status( int $id, string $status ): bool {
		return self::update( $id, array( 'workflow_status' => $status ) );
	}

	/**
	 * Records CF7's own mail delivery outcome (`sent`/`failed`) — separate
	 * from `workflow_status`, which tracks the human review workflow, not
	 * whether CF7's own notification email went out.
	 *
	 * @param int    $id     Row id.
	 * @param string $status `sent` or `failed`.
	 *
	 * @return bool
	 */
	public static function update_mail_status( int $id, string $status ): bool {
		return self::update( $id, array( 'mail_status' => $status ) );
	}

	/**
	 * Flags a row as spam (CF7's own spam detection, not the AI's category).
	 *
	 * @param int $id Row id.
	 *
	 * @return bool
	 */
	public static function mark_spam( int $id ): bool {
		return self::update( $id, array( 'spam_status' => 1 ) );
	}

	/**
	 * Persists the result of a successful AI analysis (and, if it ran, the
	 * auto-drafted reply).
	 *
	 * @param int                  $id     Row id.
	 * @param array<string, mixed> $fields Any of: `ai_summary`, `ai_reasoning`,
	 *                                     `category`, `priority`, `confidence`,
	 *                                     `ai_provider`, `ai_model`, `reply_subject`,
	 *                                     `reply_draft`, `workflow_status`.
	 *
	 * @return bool
	 */
	public static function update_analysis( int $id, array $fields ): bool {
		$allowed = array_intersect_key(
			$fields,
			array_flip( array( 'ai_summary', 'ai_reasoning', 'category', 'priority', 'confidence', 'ai_provider', 'ai_model', 'reply_subject', 'reply_draft', 'workflow_status', 'ai_error' ) )
		);

		return self::update( $id, $allowed );
	}

	/**
	 * Records a failed AI analysis attempt.
	 *
	 * @param int    $id    Row id.
	 * @param string $error User-safe error message (never the raw provider
	 *                      exception/API response).
	 *
	 * @return bool
	 */
	public static function mark_failed( int $id, string $error ): bool {
		return self::update(
			$id,
			array(
				'workflow_status' => 'failed',
				'ai_error'        => $error,
			)
		);
	}

	/**
	 * Persists an edited reply draft without sending anything.
	 *
	 * @param int    $id      Row id.
	 * @param string $subject New reply subject.
	 * @param string $body    New reply body.
	 *
	 * @return bool
	 */
	public static function save_draft( int $id, string $subject, string $body ): bool {
		return self::update(
			$id,
			array(
				'reply_subject' => $subject,
				'reply_draft'   => $body,
			)
		);
	}

	/**
	 * Records that a reply was actually sent, moving the row to `replied`.
	 *
	 * @param int    $id      Row id.
	 * @param string $subject The exact subject that was sent.
	 * @param string $body    The exact body that was sent.
	 *
	 * @return bool
	 */
	public static function set_reply_sent( int $id, string $subject, string $body ): bool {
		return self::update(
			$id,
			array(
				'reply_subject'   => $subject,
				'reply_sent_body' => $body,
				'reply_sent_at'   => current_time( 'mysql' ),
				'workflow_status' => 'replied',
			)
		);
	}

	/**
	 * Soft-deletes a row (list queries always exclude these).
	 *
	 * @param int $id Row id.
	 *
	 * @return bool
	 */
	public static function soft_delete( int $id ): bool {
		return self::update( $id, array( 'deleted_at' => current_time( 'mysql' ) ) );
	}

	/**
	 * Applies a partial column update to one row, always stamping `updated_at`.
	 *
	 * @param int                  $id     Row id.
	 * @param array<string, mixed> $fields Column => value pairs to update.
	 *
	 * @return bool
	 */
	private static function update( int $id, array $fields ): bool {
		global $wpdb;

		if ( array() === $fields ) {
			return false;
		}

		$fields['updated_at'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; there is no WP API for it, and a write is never cached.
		return false !== $wpdb->update( self::table(), $fields, array( 'id' => $id ) );
	}

	/**
	 * Decodes a raw `ARRAY_A` row's JSON columns.
	 *
	 * @param array<string, mixed>|null $row Raw row from `$wpdb`.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function decode_row( ?array $row ): ?array {
		if ( ! is_array( $row ) ) {
			return null;
		}

		$fields         = json_decode( (string) ( $row['fields'] ?? '' ), true );
		$meta           = json_decode( (string) ( $row['meta'] ?? '' ), true );
		$row['fields']  = is_array( $fields ) ? $fields : array();
		$row['meta']    = is_array( $meta ) ? $meta : array();
		$row['id']      = (int) $row['id'];
		$row['form_id'] = (int) $row['form_id'];

		return $row;
	}
}
