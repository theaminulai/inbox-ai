<?php
/**
 * Read/write access to the captured-submission table.
 *
 * @package InboxAI\Database
 */

namespace InboxAI\Database;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MessageRepository
 *
 * One row per captured Contact Form 7 submission (see
 * docs/plans/02-ai-inbox-list-plan.md, section 2). Owned by the AI Inbox
 * List page — {@see \InboxAI\CF7\SubmissionHandler} writes the initial
 * row, {@see \InboxAI\AI\AnalysisQueue} fills in the AI columns, and
 * {@see \InboxAI\Admin\Ajax\InboxAjaxController} reads/writes everything
 * else via this class. The Contacts List page
 * ({@see \InboxAI\Admin\Ajax\ContactsAjaxController}) reads this same table
 * too, via its own aggregate methods ({@see self::get_contacts()},
 * {@see self::archive_by_email()}) — see docs/plans/03-contacts-list-plan.md.
 * Plan 4 (Analytics) adds its own aggregate read methods on top of this same
 * table/class later without needing to touch what's here.
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
	 * `pending`), `source_category` (string — the CF7 form's own
	 * {@see \InboxAI\CF7\CategoryTaxonomy} assignment, captured once here;
	 * see that column's own note below). Always inserted with
	 * `workflow_status = 'new'` and `spam_status = 0` unless explicitly
	 * overridden.
	 *
	 * `source_category` is set exactly once, right here, and is never part
	 * of {@see self::update_analysis()}'s allowed-fields whitelist — unlike
	 * `category` (the AI's own classification, which a regenerate/retry can
	 * update), it stays fixed for the life of the row.
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
				'source_category'   => (string) ( $data['source_category'] ?? '' ),
				'created_at'        => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
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
	 * @param string $hash Value from {@see \InboxAI\CF7\SubmissionMapper::compute_hash()}.
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
	 * Finds the most recent non-deleted row a reply was actually sent for,
	 * from a given sender email — the fallback match {@see
	 * \InboxAI\Mail\InboundMailChecker} uses when an inbound reply's `To`
	 * address is missing the `+m{id}` marker {@see
	 * \InboxAI\Services\ReplyService::send()} sets (some mail clients/relays
	 * strip plus-addressing). Restricted to rows with `reply_sent_at` set,
	 * since a customer replying makes sense only against a thread they were
	 * actually sent something on.
	 *
	 * @param string $email Sender email address.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function find_latest_by_sender_email( string $email ): ?array {
		global $wpdb;

		if ( '' === $email ) {
			return null;
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + a hardcoded class constant, never user input; identifiers can't be passed through prepare() placeholders anyway.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE sender_email = %s AND deleted_at IS NULL AND reply_sent_at IS NOT NULL ORDER BY reply_sent_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$email
			),
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
			// Filters against `source_category` (the form-defined category
			// captured once at submission time), not `category` (the AI's
			// own, re-computable classification) — the AI Inbox List's
			// category filter is meant to slice by "what this form was
			// tagged as", which stays stable across regenerates; see
			// `MessageRepository::insert()`'s docblock.
			$clauses[] = 'source_category = %s';
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
	 * Mirrors {@see \InboxAI\Database\UsageRepository::period_to_datetime()}
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
	 * Returns a filtered, paginated page of contacts — one row per distinct
	 * `sender_email`, derived live from this same table rather than a
	 * separate contacts table (see docs/plans/03-contacts-list-plan.md,
	 * section 2). Every displayed field except the aggregate counts comes
	 * from that sender's single most-recent (highest id — the same recency
	 * proxy {@see self::get_filtered()} already sorts by) message, so two
	 * submissions from the same email with different names/categories never
	 * flap between page loads.
	 *
	 * `category`/`priority`/`search` filter against that most-recent
	 * message's own columns (matching the original mockup's
	 * `filteredContacts()`), not against every message the sender ever sent.
	 *
	 * @param array<string, mixed> $filters  `category` (exact match), `priority`
	 *                                       (exact match), `search` (matched
	 *                                       against sender name/email only).
	 * @param int                  $page     1-indexed page number.
	 * @param int                  $per_page Rows per page.
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public static function get_contacts( array $filters, int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$page     = max( 1, $page );
		$per_page = max( 1, $per_page );
		$offset   = ( $page - 1 ) * $per_page;
		$table    = self::table();

		[ $where_sql, $where_values ] = self::build_contacts_where( $filters );

		// One sender_email per row: an inner "latest message per sender"
		// aggregate (grouped by sender_email, MAX(id) as the recency
		// tie-break) joined back to this same table to pull that one row's
		// own columns — every user-supplied value still only ever reaches
		// SQL via $where_values/$per_page/$offset prepare() placeholders,
		// same as get_filtered() above.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$base_sql =
			"FROM {$table} m " .
			"INNER JOIN ( " .
			"SELECT sender_email, MAX(id) AS latest_id, COUNT(*) AS message_count, " .
			"SUM(workflow_status = 'replied') AS replied_count " .
			"FROM {$table} WHERE deleted_at IS NULL GROUP BY sender_email " .
			') agg ON agg.latest_id = m.id ' .
			"{$where_sql}";

		$count_sql = "SELECT COUNT(*) {$base_sql}";
		$total     = (int) $wpdb->get_var( array() === $where_values ? $count_sql : $wpdb->prepare( $count_sql, $where_values ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, agg.message_count, agg.replied_count {$base_sql} ORDER BY m.id DESC LIMIT %d OFFSET %d",
				array_merge( $where_values, array( $per_page, $offset ) )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$items = array_map(
			static function ( $row ) {
				$decoded                  = self::decode_row( $row );
				$decoded['message_count'] = (int) ( $row['message_count'] ?? 0 );
				$decoded['replied_count'] = (int) ( $row['replied_count'] ?? 0 );
				return $decoded;
			},
			is_array( $rows ) ? $rows : array()
		);

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Builds a `WHERE` clause (against the joined "latest message" row
	 * alias `m`, always excluding soft-deleted rows) and its matching
	 * prepare() values for {@see self::get_contacts()}.
	 *
	 * @param array<string, mixed> $filters See {@see self::get_contacts()}.
	 *
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private static function build_contacts_where( array $filters ): array {
		global $wpdb;

		// A sender's latest message being `archived` is this plugin's own
		// "delete contact" (see `self::archive_by_email()` — it archives
		// every message from that sender specifically so the contact drops
		// out of this list). Without this clause the row kept showing up
		// after "Delete" even though the archive itself succeeded.
		$clauses = array( 'm.deleted_at IS NULL', "m.workflow_status != 'archived'" );
		$values  = array();

		if ( ! empty( $filters['category'] ) && 'all' !== $filters['category'] ) {
			// See the matching comment in `build_where()` — filters by the
			// fixed `source_category`, not the AI's own `category`.
			$clauses[] = 'm.source_category = %s';
			$values[]  = (string) $filters['category'];
		}

		if ( ! empty( $filters['priority'] ) && 'all' !== $filters['priority'] ) {
			$clauses[] = 'm.priority = %s';
			$values[]  = (string) $filters['priority'];
		}

		if ( ! empty( $filters['search'] ) ) {
			$like      = '%' . $wpdb->esc_like( (string) $filters['search'] ) . '%';
			$clauses[] = '( m.sender_name LIKE %s OR m.sender_email LIKE %s )';
			array_push( $values, $like, $like );
		}

		return array( 'WHERE ' . implode( ' AND ', $clauses ), $values );
	}

	/**
	 * Archives every non-deleted message from one sender email — this
	 * plugin's definition of "delete contact" (see
	 * docs/plans/03-contacts-list-plan.md, section 3): reversible, and reuses
	 * the same `archived` workflow status and `DELETE_MESSAGES` capability
	 * the AI Inbox List's own per-message archive/delete actions already use,
	 * rather than adding a new capability or a hard delete for a table that
	 * has no dedicated contacts row to remove in the first place.
	 *
	 * @param string $email Sender email every matching message will be
	 *                       archived under.
	 *
	 * @return int[] ids of every message row archived (used to log an
	 *               activity event per row; empty array if none matched).
	 */
	public static function archive_by_email( string $email ): array {
		global $wpdb;

		if ( '' === $email ) {
			return array();
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + a hardcoded class constant, never user input.
		$ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE sender_email = %s AND deleted_at IS NULL", $email ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( array() === $ids ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; there is no WP API for it, and a write is never cached.
		$wpdb->update(
			$table,
			array(
				'workflow_status' => 'archived',
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'sender_email' => $email )
		);

		return array_map( 'intval', $ids );
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
			array_flip( array( 'ai_summary', 'ai_reasoning', 'category', 'priority', 'mood', 'confidence', 'ai_provider', 'ai_model', 'reply_subject', 'reply_draft', 'workflow_status', 'ai_error' ) )
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
