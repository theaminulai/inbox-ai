<?php
/**
 * Creates and upgrades the plugin's custom database tables.
 *
 * @package InboxAI\Database
 */

namespace InboxAI\Database;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Migrator
 *
 * Owns the plugin's custom tables described in docs/CF7_AI_Inbox_RnD.md
 * (section 6): messages, activities, usage, and contacts. Custom tables were
 * chosen over post types/postmeta for indexed filtering, cleaner analytics,
 * and easier retention/deletion at volume. `inboxai_contacts` was originally
 * deferred past the first pass, but is needed now that
 * {@see \InboxAI\Migration\FlamingoImporter} imports Flamingo's Contact/
 * Address Book records (not just its inbound messages) as real rows of
 * their own, rather than only merging their details into a message.
 */
final class Migrator {

	/**
	 * Table name suffixes, without the site's `$wpdb->prefix`.
	 *
	 * @var string
	 */
	public const MESSAGES_TABLE   = 'inboxai_messages';
	public const ACTIVITIES_TABLE = 'inboxai_activities';
	public const USAGE_TABLE      = 'inboxai_usage';
	public const CONTACTS_TABLE   = 'inboxai_contacts';

	/**
	 * Current schema version. Bumping this triggers {@see self::maybe_migrate()}
	 * to re-run `dbDelta()` on the next `plugins_loaded`.
	 *
	 * @var string
	 */
	private const SCHEMA_VERSION = '0.6.0';

	/**
	 * Option name tracking which schema version has been applied.
	 *
	 * @var string
	 */
	private const VERSION_OPTION = 'inboxai_db_version';

	/**
	 * Creates the tables (via `dbDelta()`) and records the current schema
	 * version. Called from plugin activation.
	 *
	 * @return void
	 */
	public static function install(): void {
		self::create_tables();
		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );
	}

	/**
	 * Runs the schema migration if the installed version is out of date.
	 *
	 * Safe to call on every `plugins_loaded` — it is a single cheap
	 * `get_option()` call once already up to date. `dbDelta()` diffs
	 * additively, so this also covers in-place upgrades that skipped a
	 * deactivate/reactivate cycle.
	 *
	 * @return void
	 */
	public static function maybe_migrate(): void {
		if ( self::SCHEMA_VERSION === get_option( self::VERSION_OPTION, '' ) ) {
			return;
		}

		self::install();
	}

	/**
	 * (Re)creates all three tables to match the current schema.
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$messages_table   = $wpdb->prefix . self::MESSAGES_TABLE;
		$activities_table = $wpdb->prefix . self::ACTIVITIES_TABLE;
		$usage_table      = $wpdb->prefix . self::USAGE_TABLE;
		$contacts_table   = $wpdb->prefix . self::CONTACTS_TABLE;

		$sql = "CREATE TABLE {$messages_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
			form_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			form_title VARCHAR(255) NOT NULL DEFAULT '',
			submission_hash VARCHAR(191) NOT NULL DEFAULT '',
			sender_name VARCHAR(255) NOT NULL DEFAULT '',
			sender_email VARCHAR(320) NOT NULL DEFAULT '',
			subject TEXT NULL,
			message LONGTEXT NULL,
			fields LONGTEXT NULL,
			meta LONGTEXT NULL,
			channel VARCHAR(100) NOT NULL DEFAULT '',
			submission_status VARCHAR(50) NOT NULL DEFAULT '',
			workflow_status VARCHAR(50) NOT NULL DEFAULT 'new',
			is_unread TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
			is_unread_reply TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			mail_status VARCHAR(50) NOT NULL DEFAULT '',
			spam_status TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			priority VARCHAR(30) NOT NULL DEFAULT '',
			mood VARCHAR(20) NOT NULL DEFAULT '',
			category VARCHAR(100) NOT NULL DEFAULT '',
			source_category VARCHAR(100) NOT NULL DEFAULT '',
			confidence DECIMAL(5,2) NULL,
			ai_summary LONGTEXT NULL,
			ai_reasoning LONGTEXT NULL,
			ai_error TEXT NULL,
			ai_provider VARCHAR(50) NOT NULL DEFAULT '',
			ai_model VARCHAR(191) NOT NULL DEFAULT '',
			reply_subject TEXT NULL,
			reply_draft LONGTEXT NULL,
			reply_sent_body LONGTEXT NULL,
			reply_sent_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NULL,
			deleted_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY workflow_status (workflow_status),
			KEY is_unread (is_unread),
			KEY priority (priority),
			KEY mood (mood),
			KEY category (category),
			KEY submission_hash (submission_hash),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );

		$sql = "CREATE TABLE {$activities_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			message_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			event_type VARCHAR(100) NOT NULL DEFAULT '',
			event_data LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY message_id (message_id),
			KEY event_type (event_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );

		$sql = "CREATE TABLE {$usage_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			message_id BIGINT UNSIGNED NULL,
			provider VARCHAR(50) NOT NULL DEFAULT '',
			model VARCHAR(191) NOT NULL DEFAULT '',
			prompt_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
			completion_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0,
			estimated_cost DECIMAL(12,6) NOT NULL DEFAULT 0,
			request_status VARCHAR(30) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY message_id (message_id),
			KEY provider (provider),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );

		$sql = "CREATE TABLE {$contacts_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(320) NOT NULL DEFAULT '',
			name VARCHAR(255) NOT NULL DEFAULT '',
			first_name VARCHAR(191) NOT NULL DEFAULT '',
			last_name VARCHAR(191) NOT NULL DEFAULT '',
			props LONGTEXT NULL,
			tags LONGTEXT NULL,
			last_contacted_at DATETIME NULL,
			source VARCHAR(50) NOT NULL DEFAULT '',
			source_ref VARCHAR(191) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY source_ref (source_ref),
			KEY last_contacted_at (last_contacted_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drops all four tables. Called only from uninstall.php, and only
	 * when the site owner has opted into full data removal.
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;

		foreach ( array( self::MESSAGES_TABLE, self::ACTIVITIES_TABLE, self::USAGE_TABLE, self::CONTACTS_TABLE ) as $table ) {
			$table_name = $wpdb->prefix . $table;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table_name is $wpdb->prefix + a hardcoded class constant, never user input; table names cannot be passed as prepare() placeholders.
			$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		delete_option( self::VERSION_OPTION );
	}
}
