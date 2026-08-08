<?php
/**
 * admin-ajax.php handlers for the Settings page.
 *
 * @package InboxAI\Admin\Ajax
 */

namespace InboxAI\Admin\Ajax;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\AI\ProviderFactory;
use InboxAI\CF7\CategoryTaxonomy;
use InboxAI\Database\UsageRepository;
use InboxAI\Mail\InboundMailChecker;
use InboxAI\Migration\FlamingoCsvImporter;
use InboxAI\Migration\FlamingoImporter;
use InboxAI\Migration\InboxCsvImporter;
use InboxAI\Security\Capabilities;
use InboxAI\Settings\Repository as SettingsRepository;

/**
 * Class SettingsAjaxController
 *
 * Every Settings page AJAX action (see docs/plans/05-settings-plan.md,
 * section 3): the settings tabs' get/save, the AI Provider tab's
 * connection test and model list, and every endpoint the one Import &
 * Migration wizard's five steps call across its two import paths — the
 * Flamingo-table path and the Flamingo-CSV-upload path
 * ({@see FlamingoImporter}/{@see FlamingoCsvImporter}), and the plugin-native
 * CSV path ({@see self::native_csv_upload()}/{@see self::native_csv_import_batch()},
 * backed by {@see InboxCsvImporter}). All three read/write completely
 * separate transient namespaces and tables; nothing here couples one path's
 * behavior to another's. Split out of the original single `AjaxController`
 * (see `BaseAjaxController`'s docblock) — the Settings page's own slice of
 * that file, unchanged in behavior. Every `$_POST` read here goes through a
 * `BaseAjaxController::post_*()` helper rather than a repeated
 * `isset()`/sanitize/`wp_unslash()` one-liner.
 */
final class SettingsAjaxController extends BaseAjaxController {

	/**
	 * Nonce action name shared by every Settings-page AJAX call. One nonce
	 * per page (rather than one per individual action) is enough — it
	 * still ties the request to the current user and a short time window;
	 * the real per-action gate is the `current_user_can()` check in
	 * {@see BaseAjaxController::check()}.
	 *
	 * @var string
	 */
	public const SETTINGS_NONCE_ACTION = 'inboxai_settings';

	/**
	 * Period values the Usage & Billing tab's date-range control accepts —
	 * whatever's not in this list falls back to `30_days`. Kept here (rather
	 * than reading whatever `UsageRepository::period_to_datetime()` happens
	 * to parse) so an unrecognized value from the request can never reach it.
	 *
	 * @var string[]
	 */
	public const USAGE_PERIODS = array( '7_days', '30_days', '90_days', 'this_month', '1_year', '2_years', '3_years', '5_years' );

	/**
	 * Registers every `wp_ajax_*` hook this controller handles.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_inboxai_get_settings', array( $this, 'get_settings' ) );
		add_action( 'wp_ajax_inboxai_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_inboxai_test_connection', array( $this, 'test_connection' ) );
		add_action( 'wp_ajax_inboxai_test_inbound_connection', array( $this, 'test_inbound_connection' ) );
		add_action( 'wp_ajax_inboxai_list_models', array( $this, 'list_models' ) );
		add_action( 'wp_ajax_inboxai_flamingo_detect', array( $this, 'flamingo_detect' ) );
		add_action( 'wp_ajax_inboxai_flamingo_import_batch', array( $this, 'flamingo_import_batch' ) );
		add_action( 'wp_ajax_inboxai_flamingo_upload_csv', array( $this, 'flamingo_upload_csv' ) );
		add_action( 'wp_ajax_inboxai_flamingo_import_csv_batch', array( $this, 'flamingo_import_csv_batch' ) );
		add_action( 'wp_ajax_inboxai_native_csv_upload', array( $this, 'native_csv_upload' ) );
		add_action( 'wp_ajax_inboxai_native_csv_import_batch', array( $this, 'native_csv_import_batch' ) );
		add_action( 'wp_ajax_inboxai_add_category', array( $this, 'add_category' ) );
		add_action( 'wp_ajax_inboxai_rename_category', array( $this, 'rename_category' ) );
		add_action( 'wp_ajax_inboxai_delete_category', array( $this, 'delete_category' ) );
	}

	/**
	 * `inboxai_get_settings` — reads current settings for one tab, or all of
	 * them, and (for the Usage tab) the read-only usage figures too.
	 *
	 * @return void
	 */
	public function get_settings(): void {
		$this->check( Capabilities::MANAGE_SETTINGS, self::SETTINGS_NONCE_ACTION );

		$tab = $this->post_key( 'tab' );

		$data = array(
			'provider'      => SettingsRepository::get_provider(),
			'apiKeyMasked'  => SettingsRepository::get_masked_api_key(),
			'hasApiKey'     => SettingsRepository::has_api_key(),
			'general'       => SettingsRepository::get_general(),
			'prompts'       => SettingsRepository::get_prompts(),
			'notifications' => SettingsRepository::get_notifications(),
		);

		if ( 'usage' === $tab ) {
			$period = $this->post_key( 'period', '30_days' );

			if ( ! in_array( $period, self::USAGE_PERIODS, true ) ) {
				$period = '30_days';
			}

			$data['usage'] = array(
				'period'    => $period,
				'totals'    => UsageRepository::get_period_totals( $period ),
				'breakdown' => UsageRepository::get_cost_breakdown( $period ),
			);
		}

		wp_send_json_success( $data );
	}

	/**
	 * `inboxai_save_settings` — persists one tab's fields. `$_POST['values']`
	 * is a JSON-encoded object; every field inside it is sanitized by the
	 * matching `Settings\Repository::save_*()` method, never trusted as-is.
	 *
	 * @return void
	 */
	public function save_settings(): void {
		$this->check( Capabilities::MANAGE_SETTINGS, self::SETTINGS_NONCE_ACTION );

		$tab    = $this->post_key( 'tab' );
		$values = $this->post_json_array( 'values' );

		switch ( $tab ) {
			case 'ai-settings':
				SettingsRepository::save_provider( $values );

				if ( ! empty( $values['api_key'] ) ) {
					SettingsRepository::save_api_key( sanitize_text_field( (string) $values['api_key'] ) );
				}

				wp_send_json_success(
					array(
						'saved'        => true,
						'apiKeyMasked' => SettingsRepository::get_masked_api_key(),
					)
				);
				break;

			case 'general-settings':
				SettingsRepository::save_general( $values );
				wp_send_json_success( array( 'saved' => true ) );
				break;

			case 'prompts':
				if ( ! empty( $values['reset'] ) ) {
					$defaults = SettingsRepository::reset_prompts_to_defaults();
					wp_send_json_success(
						array(
							'saved'    => true,
							'defaults' => $defaults,
						)
					);
				}

				SettingsRepository::save_prompts( $values );
				wp_send_json_success( array( 'saved' => true ) );
				break;

			case 'notifications':
				SettingsRepository::save_notifications( $values );
				SettingsRepository::save_inbound( $values );
				wp_send_json_success( array( 'saved' => true ) );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown settings tab.', 'inbox-ai' ) ), 400 );
		}
	}

	/**
	 * `inboxai_test_connection` — validates a (possibly not-yet-saved) API
	 * key against the selected provider. Never persists anything.
	 *
	 * @return void
	 */
	public function test_connection(): void {
		$this->check( Capabilities::MANAGE_SETTINGS, self::SETTINGS_NONCE_ACTION );

		$provider = ProviderFactory::create( $this->post_key( 'provider' ) );

		if ( null === $provider ) {
			wp_send_json_error( array( 'message' => __( 'Unknown provider.', 'inbox-ai' ) ), 400 );
		}

		$result = $provider->validate_credentials( $this->posted_api_key() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'connected' => true ) );
	}

	/**
	 * `inboxai_test_inbound_connection` — runs one real inbound-mail check
	 * immediately, synchronously, against the currently *saved* Inbound
	 * Email settings (unlike {@see self::test_connection()}, this doesn't
	 * accept not-yet-saved field values — save the Notifications tab first,
	 * then test, since {@see InboundMailChecker::check()} always reads from
	 * {@see SettingsRepository::get_inbound()} the same way the real WP-Cron
	 * tick does; duplicating its IMAP connection logic here just to support
	 * testing unsaved credentials wasn't worth the extra surface for a
	 * once-in-a-while settings check).
	 *
	 * @return void
	 */
	public function test_inbound_connection(): void {
		$this->check( Capabilities::MANAGE_SETTINGS, self::SETTINGS_NONCE_ACTION );

		( new InboundMailChecker() )->check();

		$inbound = SettingsRepository::get_inbound();

		wp_send_json_success(
			array(
				'message' => $inbound['last_check_message'],
			)
		);
	}

	/**
	 * `inboxai_list_models` — lists live models for the selected provider +
	 * (possibly not-yet-saved) API key.
	 *
	 * @return void
	 */
	public function list_models(): void {
		$this->check( Capabilities::MANAGE_SETTINGS, self::SETTINGS_NONCE_ACTION );

		$provider = ProviderFactory::create( $this->post_key( 'provider' ) );

		if ( null === $provider ) {
			wp_send_json_error( array( 'message' => __( 'Unknown provider.', 'inbox-ai' ) ), 400 );
		}

		$models = $provider->get_models( $this->posted_api_key() );

		if ( is_wp_error( $models ) ) {
			wp_send_json_error( array( 'message' => $models->get_error_message() ) );
		}

		wp_send_json_success( array( 'models' => $models ) );
	}

	/**
	 * `inboxai_flamingo_detect` — Import & Migration wizard step 1.
	 *
	 * @return void
	 */
	public function flamingo_detect(): void {
		$this->check( Capabilities::MANAGE_SETTINGS, self::SETTINGS_NONCE_ACTION );

		wp_send_json_success( FlamingoImporter::detect() );
	}

	/**
	 * `inboxai_flamingo_import_batch` — Import & Migration wizard step 3,
	 * called repeatedly with an increasing offset until `done` is true.
	 *
	 * @return void
	 */
	public function flamingo_import_batch(): void {
		$this->check( Capabilities::MANAGE_SETTINGS, self::SETTINGS_NONCE_ACTION );

		$offset = $this->post_int( 'offset' );
		$run_ai = $this->post_bool( 'run_ai' );

		wp_send_json_success( FlamingoImporter::import_batch( $offset, 25, $run_ai ) );
	}

	/**
	 * `inboxai_flamingo_upload_csv` — Import & Migration wizard's alternate
	 * "Upload a CSV export" path, step 1: validates and parses the
	 * uploaded file, stages its rows, and reports back what was detected
	 * (mirroring `inboxai_flamingo_detect`'s response shape) without
	 * touching this plugin's own tables yet.
	 *
	 * @return void
	 */
	public function flamingo_upload_csv(): void {
		$this->check( Capabilities::MANAGE_SETTINGS, self::SETTINGS_NONCE_ACTION );

		if ( empty( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce already verified in self::check() above; $_FILES['file']['tmp_name'] is only used with is_uploaded_file()/wp_handle_upload(), which validate the path themselves; never echoed or used in a filesystem call directly.
			wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'inbox-ai' ) ), 400 );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$overrides = array(
			'test_form' => false,
			'mimes'     => array( 'csv' => 'text/csv' ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- this request's own nonce was already verified in self::check() above; wp_handle_upload() has no nonce concept of its own.
		$moved = wp_handle_upload( $_FILES['file'], $overrides );

		if ( ! isset( $moved['file'] ) ) {
			wp_send_json_error(
				array( 'message' => $moved['error'] ?? __( 'The file could not be uploaded.', 'inbox-ai' ) ),
				400
			);
		}

		$staged = FlamingoCsvImporter::stage( $moved['file'] );

		// The parsed rows are already safely in a transient (or the
		// upload was rejected) — nothing further needs this temp copy.
		wp_delete_file( $moved['file'] );

		if ( is_wp_error( $staged ) ) {
			wp_send_json_error( array( 'message' => $staged->get_error_message() ), 400 );
		}

		wp_send_json_success( $staged );
	}

	/**
	 * `inboxai_flamingo_import_csv_batch` — Import & Migration wizard step
	 * 3's batch loop for the CSV-upload path, called repeatedly
	 * (increasing offset) until `done` is true, exactly like
	 * `inboxai_flamingo_import_batch`.
	 *
	 * @return void
	 */
	public function flamingo_import_csv_batch(): void {
		$this->check( Capabilities::MANAGE_SETTINGS, self::SETTINGS_NONCE_ACTION );

		$token  = $this->post_string( 'token' );
		$offset = $this->post_int( 'offset' );
		$run_ai = $this->post_bool( 'run_ai' );

		if ( '' === $token ) {
			wp_send_json_error( array( 'message' => __( 'This import session has expired. Please upload the file again.', 'inbox-ai' ) ), 400 );
		}

		wp_send_json_success( FlamingoCsvImporter::import_batch( $token, $offset, 25, $run_ai ) );
	}

	/**
	 * `inboxai_native_csv_upload` — the Import & Migration wizard's step 2
	 * upload path when Step 1 chose "Inbox AI CSV": validates and parses a
	 * CSV shaped for this plugin's own columns (see {@see InboxCsvImporter}'s
	 * docblock for the recognized header), stages its rows, and reports back
	 * what was detected. Entirely independent of
	 * {@see self::flamingo_upload_csv()} — different importer, different
	 * recognized shape, different transient namespace.
	 *
	 * @return void
	 */
	public function native_csv_upload(): void {
		$this->check( Capabilities::MANAGE_SETTINGS, self::SETTINGS_NONCE_ACTION );

		if ( empty( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce already verified in self::check() above; $_FILES['file']['tmp_name'] is only used with is_uploaded_file()/wp_handle_upload(), which validate the path themselves; never echoed or used in a filesystem call directly.
			wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'inbox-ai' ) ), 400 );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$overrides = array(
			'test_form' => false,
			'mimes'     => array( 'csv' => 'text/csv' ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- this request's own nonce was already verified in self::check() above; wp_handle_upload() has no nonce concept of its own.
		$moved = wp_handle_upload( $_FILES['file'], $overrides );

		if ( ! isset( $moved['file'] ) ) {
			wp_send_json_error(
				array( 'message' => $moved['error'] ?? __( 'The file could not be uploaded.', 'inbox-ai' ) ),
				400
			);
		}

		$staged = InboxCsvImporter::stage( $moved['file'] );

		// The parsed rows are already safely in a transient (or the upload
		// was rejected) — nothing further needs this temp copy.
		wp_delete_file( $moved['file'] );

		if ( is_wp_error( $staged ) ) {
			wp_send_json_error( array( 'message' => $staged->get_error_message() ), 400 );
		}

		wp_send_json_success( $staged );
	}

	/**
	 * `inboxai_native_csv_import_batch` — the Import & Migration wizard's
	 * step 4 batch loop for the "Inbox AI CSV" path, called repeatedly
	 * (increasing offset) until `done` is true, same shape as
	 * {@see self::flamingo_import_csv_batch()} but backed by
	 * {@see InboxCsvImporter}.
	 *
	 * @return void
	 */
	public function native_csv_import_batch(): void {
		$this->check( Capabilities::MANAGE_SETTINGS, self::SETTINGS_NONCE_ACTION );

		$token  = $this->post_string( 'token' );
		$offset = $this->post_int( 'offset' );
		$run_ai = $this->post_bool( 'run_ai' );

		if ( '' === $token ) {
			wp_send_json_error( array( 'message' => __( 'This import session has expired. Please upload the file again.', 'inbox-ai' ) ), 400 );
		}

		wp_send_json_success( InboxCsvImporter::import_batch( $token, $offset, 25, $run_ai ) );
	}

	/**
	 * `inboxai_add_category` — the General tab's "Manage Categories" card's
	 * own "+ Add category" row: creates a brand-new
	 * {@see \InboxAI\CF7\CategoryTaxonomy} term, unassigned to any form yet
	 * (same end state a category added from the per-form checklist reaches
	 * before any form actually checks it) — it becomes available to every
	 * form's own checklist immediately, matching the per-form box's own
	 * "+ Add new category" behavior; the two are just two entry points to
	 * the same term-creation action.
	 *
	 * @return void
	 */
	public function add_category(): void {
		$this->check( Capabilities::MANAGE_SETTINGS, self::SETTINGS_NONCE_ACTION );

		$name = sanitize_text_field( $this->post_string( 'name' ) );

		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'A category name is required.', 'inbox-ai' ) ), 400 );
		}

		$result = wp_insert_term( $name, CategoryTaxonomy::TAXONOMY );

		if ( is_wp_error( $result ) ) {
			if ( 'term_exists' === $result->get_error_code() ) {
				wp_send_json_error( array( 'message' => __( 'This category already exists.', 'inbox-ai' ) ), 400 );
			}

			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'term_id' => (int) $result['term_id'],
				'name'    => $name,
			)
		);
	}

	/**
	 * `inboxai_rename_category` — the General tab's "Manage Categories" card:
	 * renames one {@see \InboxAI\CF7\CategoryTaxonomy} term. This is the only
	 * place a category can be renamed — the per-form checklist on each CF7
	 * form's own edit screen is deliberately add/assign-only (see
	 * {@see \InboxAI\CF7\CategoryTaxonomy::render_metabox()}'s docblock).
	 * Renaming the term does not touch any message already stored with the
	 * old name in its own `category`/`source_category` column — those are
	 * plain strings captured at the time, not a live reference to the term.
	 *
	 * @return void
	 */
	public function rename_category(): void {
		$this->check( Capabilities::MANAGE_SETTINGS, self::SETTINGS_NONCE_ACTION );

		$term_id = $this->post_int( 'term_id' );
		$name    = sanitize_text_field( $this->post_string( 'name' ) );

		if ( 0 === $term_id || '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'A category name is required.', 'inbox-ai' ) ), 400 );
		}

		$result = wp_update_term( $term_id, CategoryTaxonomy::TAXONOMY, array( 'name' => $name ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'renamed' => true, 'name' => $name ) );
	}

	/**
	 * `inboxai_delete_category` — the General tab's "Manage Categories" card:
	 * deletes one {@see \InboxAI\CF7\CategoryTaxonomy} term outright
	 * (unassigning it from every form that had it checked). This is the
	 * only place a category can be deleted — see
	 * {@see self::rename_category()}'s docblock for why it's not available
	 * on the per-form checklist. Deleting the term does not touch any
	 * message already stored with this category's name in its own
	 * `category`/`source_category` column.
	 *
	 * @return void
	 */
	public function delete_category(): void {
		$this->check( Capabilities::MANAGE_SETTINGS, self::SETTINGS_NONCE_ACTION );

		$term_id = $this->post_int( 'term_id' );

		if ( 0 === $term_id ) {
			wp_send_json_error( array( 'message' => __( 'This category could not be found.', 'inbox-ai' ) ), 400 );
		}

		$result = wp_delete_term( $term_id, CategoryTaxonomy::TAXONOMY );

		if ( is_wp_error( $result ) || false === $result ) {
			wp_send_json_error( array( 'message' => __( 'This category could not be deleted.', 'inbox-ai' ) ), 400 );
		}

		wp_send_json_success( array( 'deleted' => true ) );
	}

	/**
	 * Reads `$_POST['api_key']` (via {@see BaseAjaxController::post_string()}),
	 * falling back to the already-stored key when the value is empty or is
	 * the masked placeholder the browser shows (never a real key it should
	 * overwrite). The one piece of `api_key` handling that isn't just a
	 * generic sanitize-and-default read, so it stays here rather than moving
	 * to the base class.
	 *
	 * @return string
	 */
	private function posted_api_key(): string {
		$posted = (string) $this->post_string( 'api_key' );

		if ( '' === $posted || false !== strpos( $posted, "\u{2022}" ) ) {
			return (string) SettingsRepository::get_api_key();
		}

		return $posted;
	}
}
