<?php
/**
 * Imports Flamingo's captured messages into this plugin's own tables.
 *
 * @package CF7AIInbox\Migration
 */

namespace CF7AIInbox\Migration;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIInbox\Database\Migrator;

/**
 * Class FlamingoImporter
 *
 * Powers the Settings page's Import & Migration tab (R&D §10). Reads
 * Flamingo's `flamingo_inbound` posts through public APIs only
 * (`get_posts()`/`get_post_meta()`) — Flamingo's internal classes are never
 * treated as a stable dependency (R&D §10.1) — and never deletes or
 * modifies Flamingo's own data; every import creates a new row in this
 * plugin's own `cf7ai_messages` table instead.
 */
final class FlamingoImporter {

	/**
	 * Whether Flamingo is active on this site.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return post_type_exists( 'flamingo_inbound' );
	}

	/**
	 * Counts what's available to import. Powers the wizard's Upload step.
	 *
	 * @return array{available:bool,messages:int}
	 */
	public static function detect(): array {
		if ( ! self::is_available() ) {
			return array(
				'available' => false,
				'messages'  => 0,
			);
		}

		$counts = (array) wp_count_posts( 'flamingo_inbound' );
		$total  = 0;

		foreach ( $counts as $count ) {
			$total += (int) $count;
		}

		return array(
			'available' => true,
			'messages'  => $total,
		);
	}

	/**
	 * Imports one batch of Flamingo messages, skipping any already
	 * imported. Called repeatedly (with an increasing offset) by the
	 * wizard's Import step until `done` comes back true.
	 *
	 * @param int  $offset Offset into the Flamingo post list to resume from.
	 * @param int  $limit  Batch size.
	 * @param bool $run_ai Whether imported messages should be queued for AI
	 *                      analysis (`new`) or left as-is (`reviewed`) —
	 *                      analysis itself is the AI Inbox List page's job,
	 *                      not this importer's.
	 *
	 * @return array{imported:int,skipped:int,done:bool,offset:int}
	 */
	public static function import_batch( int $offset, int $limit = 25, bool $run_ai = false ): array {
		if ( ! self::is_available() ) {
			return array(
				'imported' => 0,
				'skipped'  => 0,
				'done'     => true,
				'offset'   => $offset,
			);
		}

		$post_ids = get_posts(
			array(
				'post_type'      => 'flamingo_inbound',
				'post_status'    => 'any',
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		global $wpdb;

		$table    = $wpdb->prefix . Migrator::MESSAGES_TABLE;
		$imported = 0;
		$skipped  = 0;

		foreach ( $post_ids as $post_id ) {
			if ( self::already_imported( (int) $post_id ) ) {
				++$skipped;
				continue;
			}

			$post = get_post( $post_id );

			if ( null === $post ) {
				++$skipped;
				continue;
			}

			$fields_meta = get_post_meta( $post_id, '_fields', true );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- writing to this plugin's own custom table; no caching layer applies.
			$wpdb->insert(
				$table,
				array(
					'form_title'      => sanitize_text_field( (string) get_post_meta( $post_id, '_channel', true ) ),
					'sender_name'     => sanitize_text_field( (string) get_post_meta( $post_id, '_from_name', true ) ),
					'sender_email'    => sanitize_email( (string) get_post_meta( $post_id, '_from_email', true ) ),
					'subject'         => sanitize_text_field( (string) get_post_meta( $post_id, '_subject', true ) ),
					'message'         => wp_strip_all_tags( $post->post_content ),
					'fields'          => wp_json_encode( is_array( $fields_meta ) ? $fields_meta : array() ),
					'meta'            => wp_json_encode( array( 'flamingo_source_id' => (int) $post_id ) ),
					'channel'         => 'flamingo_import',
					'workflow_status' => $run_ai ? 'new' : 'reviewed',
					'created_at'      => $post->post_date_gmt,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			++$imported;
		}

		$fetched = count( $post_ids );

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'done'     => $fetched < $limit,
			'offset'   => $offset + $fetched,
		);
	}

	/**
	 * Whether a given Flamingo post has already been imported, checked via
	 * the `flamingo_source_id` this importer stamps into the `meta` JSON
	 * column of every row it creates.
	 *
	 * @param int $flamingo_post_id Flamingo's `flamingo_inbound` post id.
	 *
	 * @return bool
	 */
	private static function already_imported( int $flamingo_post_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . Migrator::MESSAGES_TABLE;

		// The `meta` column always stores this key as the sole entry in a
		// single-level JSON object for Flamingo-imported rows, so matching
		// through the closing brace avoids `123` also matching `1234`.
		$needle = '%"flamingo_source_id":' . $flamingo_post_id . '}%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off existence check against this plugin's own table.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE meta LIKE %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is $wpdb->prefix + a hardcoded class constant, never user input.
				$needle
			)
		);

		return null !== $found;
	}
}
