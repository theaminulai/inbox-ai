<?php
/**
 * Imports a Flamingo CSV export (an alternative to reading live Flamingo
 * data — see {@see FlamingoImporter}) into this plugin's own tables.
 *
 * @package CF7AIInbox\Migration
 */

namespace CF7AIInbox\Migration;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIInbox\Database\MessageRepository;
use CF7AIInbox\Database\Migrator;
use WP_Error;

/**
 * Class FlamingoCsvImporter
 *
 * A second import path alongside {@see FlamingoImporter}'s direct read of
 * this site's own live Flamingo data — for when Flamingo itself isn't
 * installed here (a migration from another site) but an admin has a CSV
 * exported from Flamingo's own "Export" button. Recognizes the shape
 * Flamingo's own `includes/csv.php` produces for an Inbound Messages
 * export: whatever a form's own field names are, as dynamic columns, plus a
 * trailing `Date` column.
 *
 * This class also had a companion path for Flamingo's Contacts-export CSV
 * shape at one point, alongside a minimal Contacts admin page — both were
 * built and then deliberately reverted at the user's request ("Contacts
 * page are not needed for now, I will develop them later according to my
 * development plan."), so this importer is messages-only again pending
 * that later, from-scratch build.
 *
 * A parsed file is staged in a transient (keyed by a random token handed
 * back to the browser) so the wizard's "Import" step can page through it in
 * batches exactly like {@see FlamingoImporter}'s live-data batches, without
 * re-uploading or re-parsing the file on every request.
 */
final class FlamingoCsvImporter {

	/**
	 * Transient key prefix. The token suffix is random per upload.
	 *
	 * @var string
	 */
	private const TRANSIENT_PREFIX = 'cf7ai_flamingo_csv_';

	/**
	 * How long a staged upload survives without being resumed — long enough
	 * for a slow multi-batch import, short enough not to accumulate stale
	 * transients from abandoned wizard sessions.
	 *
	 * @var int
	 */
	private const TRANSIENT_TTL = HOUR_IN_SECONDS;

	/**
	 * Parses an uploaded CSV file and stages its rows for
	 * {@see self::import_batch()}. Does not touch this plugin's own tables
	 * yet — this is the wizard's "detect" step, not its "import" step.
	 *
	 * @param string $file_path Path to the uploaded (already-validated) file.
	 *
	 * @return array{token:string,count:int}|WP_Error
	 */
	public static function stage( string $file_path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged -- WP_Filesystem has no fgetcsv()-equivalent streaming reader; this file is our own already-validated (is_uploaded_file()/wp_handle_upload()) temp upload, not arbitrary user-supplied path, and is deleted right after by the caller. fopen() failure is handled explicitly below; a warning here would just be noise.
		$handle = @fopen( $file_path, 'r' );

		if ( false === $handle ) {
			return new WP_Error( 'cf7ai_csv_unreadable', __( 'The uploaded file could not be read.', 'cf7-ai-inbox' ) );
		}

		$header = fgetcsv( $handle );

		if ( ! is_array( $header ) || array() === $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see the fopen() call above; WP_Filesystem has no fgetcsv() streaming equivalent.
			return new WP_Error( 'cf7ai_csv_empty', __( 'The uploaded file is empty.', 'cf7-ai-inbox' ) );
		}

		// Strip a leading UTF-8 BOM, which Excel-produced CSVs often add to
		// the very first cell.
		$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header[0] );
		$header    = array_map( 'trim', $header );

		if ( ! self::is_messages_shape( $header ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see the fopen() call above.
			return new WP_Error(
				'cf7ai_csv_unrecognized',
				__( 'This doesn\'t look like a Flamingo Inbound Messages export. Expected your form\'s own field names as columns, ending in a Date column.', 'cf7-ai-inbox' )
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

			foreach ( $header as $i => $column ) {
				$row[ $column ] = isset( $line[ $i ] ) ? (string) $line[ $i ] : '';
			}

			$rows[] = $row;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see the fopen() call above.

		if ( array() === $rows ) {
			return new WP_Error( 'cf7ai_csv_no_rows', __( 'The uploaded file has a header row but no data rows.', 'cf7-ai-inbox' ) );
		}

		$token = wp_generate_password( 20, false, false );

		set_transient(
			self::TRANSIENT_PREFIX . $token,
			array(
				'rows' => $rows,
			),
			self::TRANSIENT_TTL
		);

		return array(
			'token' => $token,
			'count' => count( $rows ),
		);
	}

	/**
	 * @param string[] $header Trimmed header cells.
	 *
	 * @return bool Whether this looks like a Flamingo Inbound Messages export
	 *              (arbitrary field-name columns ending in a `Date` column).
	 */
	private static function is_messages_shape( array $header ): bool {
		$normalized = array_map( 'strtolower', $header );

		return array() !== $normalized && 'date' === end( $normalized );
	}

	/**
	 * Imports one batch of a previously staged upload's rows. Called
	 * repeatedly (with an increasing offset) by the wizard's Import step
	 * until `done` comes back true — the same shape/pattern as
	 * {@see FlamingoImporter::import_batch()} so the client-side polling
	 * loop can treat both sources identically.
	 *
	 * @param string $token   Token returned by {@see self::stage()}.
	 * @param int    $offset  Offset into the staged rows to resume from.
	 * @param int    $limit   Batch size.
	 * @param bool   $run_ai  Whether imported messages should be queued for
	 *                        AI analysis.
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
	 * @param array<string, string> $row    One row from a Messages-shaped CSV
	 *                                       (arbitrary field columns + `Date`).
	 * @param bool                  $run_ai Whether to queue this row for AI analysis.
	 *
	 * @return bool Whether it was imported (false if already imported before).
	 */
	private static function import_message_row( array $row, bool $run_ai ): bool {
		global $wpdb;

		$date   = (string) ( $row['Date'] ?? '' );
		$fields = $row;
		unset( $fields['Date'] );

		// Deterministic per-row hash (not a per-request one like
		// `SubmissionMapper::compute_hash()`, which is only meant for a
		// single form submission's double-submit debounce window) so
		// re-uploading the same export twice is a safe no-op.
		$hash = md5( 'flamingo_csv:' . wp_json_encode( $row ) );

		if ( null !== MessageRepository::find_by_hash( $hash ) ) {
			return false;
		}

		$name    = self::guess_field( $fields, array( 'name' ) );
		$email   = self::guess_field( $fields, array( 'email' ) );
		$subject = self::guess_field( $fields, array( 'subject' ) );
		$message = self::guess_field( $fields, array( 'message', 'comment', 'body' ) );

		$timestamp  = '' !== $date ? strtotime( $date ) : false;
		$created_at = false !== $timestamp ? wp_date( 'Y-m-d H:i:s', $timestamp ) : current_time( 'mysql' );

		$table = $wpdb->prefix . Migrator::MESSAGES_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- writing to this plugin's own custom table; no caching layer applies.
		$wpdb->insert(
			$table,
			array(
				'form_title'      => '',
				'submission_hash' => $hash,
				'sender_name'     => sanitize_text_field( $name ),
				'sender_email'    => sanitize_email( $email ),
				'subject'         => sanitize_text_field( $subject ),
				'message'         => sanitize_textarea_field( $message ),
				'fields'          => wp_json_encode( array_map( 'sanitize_text_field', $fields ) ),
				'meta'            => wp_json_encode( array() ),
				'channel'         => 'flamingo_csv',
				'workflow_status' => $run_ai ? 'new' : 'reviewed',
				'created_at'      => $created_at,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return true;
	}

	/**
	 * Best-effort match of a CF7-form-shaped CSV column to a well-known
	 * field (name/email/subject/message) by checking whether any of
	 * `$needles` appears in the column's own name — matches Contact Form 7's
	 * own default field-name conventions (`your-name`, `your-email`,
	 * `your-subject`, `your-message`) as well as plainer custom names. Every
	 * column is kept in `fields` regardless of whether this finds a match,
	 * so nothing is lost even when a form used unconventional names.
	 *
	 * @param array<string, string> $fields
	 * @param string[]              $needles
	 *
	 * @return string
	 */
	private static function guess_field( array $fields, array $needles ): string {
		foreach ( $fields as $key => $value ) {
			$key_lower = strtolower( $key );

			foreach ( $needles as $needle ) {
				if ( false !== strpos( $key_lower, $needle ) ) {
					return $value;
				}
			}
		}

		return '';
	}
}
