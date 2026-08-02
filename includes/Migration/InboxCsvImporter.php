<?php
/**
 * Imports a CSV shaped for this plugin's own schema — a separate path from
 * {@see FlamingoCsvImporter} (which recognizes a Flamingo plugin export
 * shape and is never touched by this class).
 *
 * @package InboxAI\Migration
 */

namespace InboxAI\Migration;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\AI\AnalysisQueue;
use InboxAI\AI\ResponseValidator;
use InboxAI\Database\ActivityRepository;
use InboxAI\Database\MessageRepository;
use InboxAI\Database\Migrator;
use WP_Error;

/**
 * Class InboxCsvImporter
 *
 * The Flamingo import path only recognizes one specific export shape
 * (arbitrary CF7-style field-name columns ending in a `Date` column — see
 * {@see FlamingoCsvImporter}). That's the wrong shape for someone who just
 * wants to load test/demo data that already matches THIS plugin's own
 * columns directly (`source_category`, `category`, `priority`, `confidence`,
 * `workflow_status`, …) — this class is that second, independent import
 * path, with its own recognized header shape and its own AJAX endpoints
 * ({@see \InboxAI\Admin\Ajax\SettingsAjaxController::native_csv_upload()}/
 * {@see \InboxAI\Admin\Ajax\SettingsAjaxController::native_csv_import_batch()}).
 * It shares the Settings page's one Import & Migration tab/wizard with the
 * Flamingo paths (`includes/Templates/settings/flamingo.php` — Step 1 of
 * that wizard picks which import type the rest of it is for) rather than
 * having a separate tab of its own; nothing here reads or writes anything
 * Flamingo-related.
 *
 * Recognized header (case-insensitive, any order — see {@see self::CANONICAL_COLUMNS}):
 * `sender_name`, `sender_email`, `phone`, `company`, `form_title`,
 * `source_category`, `subject`, `message`, `category`, `priority`,
 * `confidence`, `workflow_status`, `created_at`. Only `sender_email` and
 * `message` are required to be present as columns — every other column is
 * optional per row.
 *
 * A parsed file is staged in a transient (keyed by a random token handed
 * back to the browser), exactly like {@see FlamingoCsvImporter}, so the
 * wizard's Import step can page through it in batches without re-uploading
 * or re-parsing on every request.
 */
final class InboxCsvImporter {

	/**
	 * Transient key prefix. The token suffix is random per upload.
	 *
	 * @var string
	 */
	private const TRANSIENT_PREFIX = 'inboxai_native_csv_';

	/**
	 * How long a staged upload survives without being resumed.
	 *
	 * @var int
	 */
	private const TRANSIENT_TTL = HOUR_IN_SECONDS;

	/**
	 * Every column this format understands. Anything else in the header is
	 * ignored (not an error) — this is a fixed, plugin-defined shape, not a
	 * generic arbitrary-CSV importer.
	 *
	 * @var string[]
	 */
	private const CANONICAL_COLUMNS = array(
		'sender_name',
		'sender_email',
		'phone',
		'company',
		'form_title',
		'source_category',
		'subject',
		'message',
		'category',
		'priority',
		'confidence',
		'workflow_status',
		'created_at',
	);

	/**
	 * `workflow_status` values this format accepts as an explicit override.
	 * An unrecognized/blank value falls back to the same computed default
	 * real submissions get (see {@see self::import_message_row()}).
	 *
	 * @var string[]
	 */
	private const WORKFLOW_STATUSES = array( 'new', 'review', 'drafted', 'reviewed', 'replied', 'archived', 'failed' );

	/**
	 * Parses an uploaded CSV file and stages its rows for
	 * {@see self::import_batch()}. Does not touch this plugin's own tables
	 * yet.
	 *
	 * @param string $file_path Path to the uploaded (already-validated) file.
	 *
	 * @return array{token:string,count:int}|WP_Error
	 */
	public static function stage( string $file_path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged -- WP_Filesystem has no fgetcsv()-equivalent streaming reader; this file is our own already-validated (is_uploaded_file()/wp_handle_upload()) temp upload, not arbitrary user-supplied path, and is deleted right after by the caller.
		$handle = @fopen( $file_path, 'r' );

		if ( false === $handle ) {
			return new WP_Error( 'inboxai_csv_unreadable', __( 'The uploaded file could not be read.', 'inbox-ai' ) );
		}

		$header = fgetcsv( $handle );

		if ( ! is_array( $header ) || array() === $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'inboxai_csv_empty', __( 'The uploaded file is empty.', 'inbox-ai' ) );
		}

		// Strip a leading UTF-8 BOM, which Excel-produced CSVs often add to
		// the very first cell.
		$header[0]  = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header[0] );
		$normalized = array_map( array( __CLASS__, 'normalize_column_name' ), $header );

		if ( ! self::is_native_shape( $normalized ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error(
				'inboxai_csv_unrecognized',
				__( 'This doesn\'t look like an Inbox AI import CSV. Expected at least "sender_email" and "message" columns.', 'inbox-ai' )
			);
		}

		$rows = array();

		while ( false !== ( $line = fgetcsv( $handle ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
			// A stray blank line (fgetcsv() returns [null] for one, not
			// false) rather than a real row.
			if ( 1 === count( $line ) && null === $line[0] ) {
				continue;
			}

			$row = array();

			foreach ( $normalized as $i => $column ) {
				if ( ! in_array( $column, self::CANONICAL_COLUMNS, true ) ) {
					continue; // Unknown column — ignored, not an error.
				}

				$row[ $column ] = isset( $line[ $i ] ) ? trim( (string) $line[ $i ] ) : '';
			}

			$rows[] = $row;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( array() === $rows ) {
			return new WP_Error( 'inboxai_csv_no_rows', __( 'The uploaded file has a header row but no data rows.', 'inbox-ai' ) );
		}

		$token = wp_generate_password( 20, false, false );

		set_transient(
			self::TRANSIENT_PREFIX . $token,
			array( 'rows' => $rows ),
			self::TRANSIENT_TTL
		);

		return array(
			'token' => $token,
			'count' => count( $rows ),
		);
	}

	/**
	 * @param string $name Raw header cell.
	 *
	 * @return string Lowercased, trimmed, with spaces/dashes collapsed to
	 *                underscores (so `"Sender Email"`/`"sender-email"`/
	 *                `"sender_email"` all normalize the same way).
	 */
	private static function normalize_column_name( string $name ): string {
		$name = strtolower( trim( $name ) );

		return (string) preg_replace( '/[\s-]+/', '_', $name );
	}

	/**
	 * @param string[] $normalized_header Normalized header cells.
	 *
	 * @return bool Whether this looks like an Inbox AI import CSV — the two
	 *              columns every row needs to be usable are both present.
	 */
	private static function is_native_shape( array $normalized_header ): bool {
		return in_array( 'sender_email', $normalized_header, true ) && in_array( 'message', $normalized_header, true );
	}

	/**
	 * Imports one batch of a previously staged upload's rows. Called
	 * repeatedly (with an increasing offset) until `done` comes back true —
	 * same shape as {@see FlamingoCsvImporter::import_batch()}.
	 *
	 * @param string $token   Token returned by {@see self::stage()}.
	 * @param int    $offset  Offset into the staged rows to resume from.
	 * @param int    $limit   Batch size.
	 * @param bool   $run_ai  Whether rows with no explicit category/priority
	 *                        of their own should be queued for real AI
	 *                        analysis (like a real submission) rather than
	 *                        left as an unanalyzed `new` row.
	 *
	 * @return array{imported:int,skipped:int,done:bool,offset:int}
	 */
	public static function import_batch( string $token, int $offset, int $limit = 25, bool $run_ai = false ): array {
		$staged = get_transient( self::TRANSIENT_PREFIX . $token );

		if ( ! is_array( $staged ) || ! isset( $staged['rows'] ) ) {
			return array(
				'imported' => 0,
				'skipped'  => 0,
				'done'     => true,
				'offset'   => $offset,
			);
		}

		$rows  = (array) $staged['rows'];
		$slice = array_slice( $rows, $offset, $limit );

		$imported = 0;
		$skipped  = 0;

		foreach ( $slice as $row ) {
			if ( self::import_message_row( $row, $run_ai ) ) {
				++$imported;
			} else {
				++$skipped;
			}
		}

		$fetched    = count( $slice );
		$new_offset = $offset + $fetched;
		$done       = $new_offset >= count( $rows );

		if ( $done ) {
			delete_transient( self::TRANSIENT_PREFIX . $token );
		}

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'done'     => $done,
			'offset'   => $new_offset,
		);
	}

	/**
	 * @param array<string, string> $row    One staged, canonical-keyed row
	 *                                       (see {@see self::CANONICAL_COLUMNS}).
	 * @param bool                  $run_ai See {@see self::import_batch()}.
	 *
	 * @return bool Whether it was imported (false if missing required data,
	 *              or already imported before).
	 */
	private static function import_message_row( array $row, bool $run_ai ): bool {
		$sender_email = (string) ( $row['sender_email'] ?? '' );
		$sender_name  = (string) ( $row['sender_name'] ?? '' );
		$message      = (string) ( $row['message'] ?? '' );

		if ( '' === $message || ( '' === $sender_email && '' === $sender_name ) ) {
			return false;
		}

		// Deterministic per-row hash so re-uploading the same file twice is
		// a safe no-op, exactly like `FlamingoCsvImporter::import_message_row()`.
		$hash = md5( 'inbox_csv:' . wp_json_encode( $row ) );

		if ( null !== MessageRepository::find_by_hash( $hash ) ) {
			return false;
		}

		$id = MessageRepository::insert(
			array(
				'form_id'           => 0,
				'form_title'        => (string) ( $row['form_title'] ?? '' ),
				'submission_hash'   => $hash,
				'sender_name'       => sanitize_text_field( $sender_name ),
				'sender_email'      => sanitize_email( $sender_email ),
				'subject'           => sanitize_text_field( (string) ( $row['subject'] ?? '' ) ),
				'message'           => sanitize_textarea_field( $message ),
				'fields'            => array(),
				'meta'              => array(
					'phone'   => sanitize_text_field( (string) ( $row['phone'] ?? '' ) ),
					'company' => sanitize_text_field( (string) ( $row['company'] ?? '' ) ),
				),
				'channel'           => 'inbox_csv_import',
				'submission_status' => 'imported',
				'mail_status'       => 'sent',
				'source_category'   => sanitize_text_field( mb_substr( (string) ( $row['source_category'] ?? '' ), 0, 100 ) ),
			)
		);

		if ( 0 === $id ) {
			return false;
		}

		self::maybe_backdate( $id, (string) ( $row['created_at'] ?? '' ) );

		ActivityRepository::log( $id, 'received', array(), 0 );

		$has_analysis_data = '' !== trim( (string) ( $row['category'] ?? '' ) )
			|| '' !== trim( (string) ( $row['priority'] ?? '' ) )
			|| '' !== trim( (string) ( $row['confidence'] ?? '' ) );

		if ( $has_analysis_data ) {
			self::apply_provided_analysis( $id, $row );
		} elseif ( $run_ai ) {
			// No analysis data of its own — queue it for the real AI
			// pipeline, exactly like a live Contact Form 7 submission (see
			// `\InboxAI\CF7\SubmissionHandler::capture_once()`).
			AnalysisQueue::enqueue( $id );
		}

		return true;
	}

	/**
	 * Backdates `created_at` when the row provided one — `MessageRepository::insert()`
	 * always stamps "now", so this is a second, explicit write straight
	 * after, matching the same pattern `tools/seed-demo-data.php` uses to
	 * spread demo data across realistic dates.
	 *
	 * @param int    $id       The just-inserted row's id.
	 * @param string $raw_date Raw `created_at` cell; anything `strtotime()`
	 *                         can't parse is silently ignored (the row keeps
	 *                         its real insert timestamp).
	 *
	 * @return void
	 */
	private static function maybe_backdate( int $id, string $raw_date ): void {
		if ( '' === $raw_date ) {
			return;
		}

		$timestamp = strtotime( $raw_date );

		if ( false === $timestamp ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- dev/admin-only import tool writing to this plugin's own custom table; MessageRepository has no way to backdate created_at, which an import needs for realistic date-filter testing (same reasoning as tools/seed-demo-data.php).
		$wpdb->update(
			$wpdb->prefix . Migrator::MESSAGES_TABLE,
			array( 'created_at' => gmdate( 'Y-m-d H:i:s', $timestamp ) ),
			array( 'id' => $id )
		);
	}

	/**
	 * Applies category/priority/confidence/workflow_status provided directly
	 * by the CSV row — this is explicit, admin-authored test data, so it's
	 * trusted and written as-is (normalized/sanitized, not validated against
	 * a form's own {@see \InboxAI\CF7\CategoryTaxonomy}, since an imported
	 * row has no real CF7 form behind it). This is the AI's own `category`
	 * column, independent from `source_category` (already set at insert
	 * time above and never touched here) — matches how a real submission's
	 * two category fields stay independent; see
	 * `MessageRepository::insert()`'s docblock.
	 *
	 * @param int                    $id  The just-inserted row's id.
	 * @param array<string, string>  $row The staged row.
	 *
	 * @return void
	 */
	private static function apply_provided_analysis( int $id, array $row ): void {
		$category = trim( (string) ( $row['category'] ?? '' ) );
		$priority = ResponseValidator::normalize_priority( (string) ( $row['priority'] ?? '' ) );

		$raw_workflow    = strtolower( trim( (string) ( $row['workflow_status'] ?? '' ) ) );
		$workflow_status = in_array( $raw_workflow, self::WORKFLOW_STATUSES, true ) ? $raw_workflow : 'reviewed';

		$fields = array(
			'ai_summary'      => sprintf(
				/* translators: %s: sender name */
				__( 'Imported test data for %s.', 'inbox-ai' ),
				(string) ( $row['sender_name'] ?? $row['sender_email'] ?? '' )
			),
			'ai_reasoning'    => __( 'Category/priority provided directly by the imported CSV row, not computed by an AI call.', 'inbox-ai' ),
			'priority'        => $priority,
			'ai_provider'     => 'csv_import',
			'ai_model'        => 'manual',
			'workflow_status' => $workflow_status,
		);

		if ( '' !== $category ) {
			$fields['category'] = mb_substr( $category, 0, 100 );
		}

		$raw_confidence = trim( (string) ( $row['confidence'] ?? '' ) );

		if ( '' !== $raw_confidence && is_numeric( $raw_confidence ) ) {
			$fields['confidence'] = ResponseValidator::normalize_confidence( $raw_confidence );
		}

		MessageRepository::update_analysis( $id, $fields );

		ActivityRepository::log( $id, 'ai_analysis_completed', array( 'source' => 'csv_import' ) );
	}
}
