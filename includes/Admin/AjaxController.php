<?php
/**
 * admin-ajax.php handlers for every admin page's write/read actions.
 *
 * @package CF7AIInbox\Admin
 */

namespace CF7AIInbox\Admin;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIInbox\AI\ProviderFactory;
use CF7AIInbox\Database\UsageRepository;
use CF7AIInbox\Migration\FlamingoImporter;
use CF7AIInbox\Security\Capabilities;
use CF7AIInbox\Settings\Repository as SettingsRepository;

/**
 * Class AjaxController
 *
 * One shared controller for every admin-page AJAX action (see
 * docs/plans/*.md, section 3 of each) rather than a class per page —
 * currently only the Settings page's actions are implemented; the AI
 * Inbox List, Contacts List, and Analytics actions land with those pages'
 * own build passes and register here alongside these.
 */
final class AjaxController {

	/**
	 * Nonce action name shared by every Settings-page AJAX call. One nonce
	 * per page (rather than one per individual action) is enough — it
	 * still ties the request to the current user and a short time window;
	 * the real per-action gate is the `current_user_can()` check in
	 * {@see self::check()}.
	 *
	 * @var string
	 */
	public const SETTINGS_NONCE_ACTION = 'cf7ai_inbox_settings';

	/**
	 * Registers every `wp_ajax_*` hook this controller handles.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_cf7ai_get_settings', array( $this, 'get_settings' ) );
		add_action( 'wp_ajax_cf7ai_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_cf7ai_test_connection', array( $this, 'test_connection' ) );
		add_action( 'wp_ajax_cf7ai_list_models', array( $this, 'list_models' ) );
		add_action( 'wp_ajax_cf7ai_flamingo_detect', array( $this, 'flamingo_detect' ) );
		add_action( 'wp_ajax_cf7ai_flamingo_import_batch', array( $this, 'flamingo_import_batch' ) );
	}

	/**
	 * Shared nonce + capability gate, matching the pattern already used for
	 * `Requirements`/`Capabilities` elsewhere in this plugin. Sends a JSON
	 * error and stops execution if either check fails.
	 *
	 * @param string $capability Required capability.
	 *
	 * @return void
	 */
	private function check( string $capability ): void {
		check_ajax_referer( self::SETTINGS_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to do this.', 'cf7-ai-inbox' ) ),
				403
			);
		}
	}

	/**
	 * `cf7ai_get_settings` — reads current settings for one tab, or all of
	 * them, and (for the Usage tab) the read-only usage figures too.
	 *
	 * @return void
	 */
	public function get_settings(): void {
		$this->check( Capabilities::MANAGE_SETTINGS );

		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';

		$data = array(
			'provider'      => SettingsRepository::get_provider(),
			'apiKeyMasked'  => SettingsRepository::get_masked_api_key(),
			'hasApiKey'     => SettingsRepository::has_api_key(),
			'general'       => SettingsRepository::get_general(),
			'prompts'       => SettingsRepository::get_prompts(),
			'notifications' => SettingsRepository::get_notifications(),
		);

		if ( 'usage' === $tab ) {
			$data['usage'] = array(
				'totals'    => UsageRepository::get_period_totals( '30_days' ),
				'breakdown' => UsageRepository::get_cost_breakdown( '30_days' ),
			);
		}

		wp_send_json_success( $data );
	}

	/**
	 * `cf7ai_save_settings` — persists one tab's fields. `$_POST['values']`
	 * is a JSON-encoded object; every field inside it is sanitized by the
	 * matching `Settings\Repository::save_*()` method, never trusted as-is.
	 *
	 * @return void
	 */
	public function save_settings(): void {
		$this->check( Capabilities::MANAGE_SETTINGS );

		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- wp_unslash() is applied explicitly below before decoding; the decoded array is then sanitized field-by-field by each save_*() method.
		$raw    = isset( $_POST['values'] ) ? wp_unslash( $_POST['values'] ) : '';
		$values = json_decode( (string) $raw, true );
		$values = is_array( $values ) ? $values : array();

		switch ( $tab ) {
			case 'ai-settings':
				SettingsRepository::save_provider( $values );

				if ( ! empty( $values['api_key'] ) ) {
					SettingsRepository::save_api_key( sanitize_text_field( (string) $values['api_key'] ) );
				}

				wp_send_json_success( array( 'saved' => true, 'apiKeyMasked' => SettingsRepository::get_masked_api_key() ) );
				break;

			case 'general-settings':
				SettingsRepository::save_general( $values );
				wp_send_json_success( array( 'saved' => true ) );
				break;

			case 'prompts':
				if ( ! empty( $values['reset'] ) ) {
					$defaults = SettingsRepository::reset_prompts_to_defaults();
					wp_send_json_success( array( 'saved' => true, 'defaults' => $defaults ) );
				}

				SettingsRepository::save_prompts( $values );
				wp_send_json_success( array( 'saved' => true ) );
				break;

			case 'notifications':
				SettingsRepository::save_notifications( $values );
				wp_send_json_success( array( 'saved' => true ) );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown settings tab.', 'cf7-ai-inbox' ) ), 400 );
		}
	}

	/**
	 * `cf7ai_test_connection` — validates a (possibly not-yet-saved) API
	 * key against the selected provider. Never persists anything.
	 *
	 * @return void
	 */
	public function test_connection(): void {
		$this->check( Capabilities::MANAGE_SETTINGS );

		$provider = ProviderFactory::create( $this->posted_provider_id() );

		if ( null === $provider ) {
			wp_send_json_error( array( 'message' => __( 'Unknown provider.', 'cf7-ai-inbox' ) ), 400 );
		}

		$result = $provider->validate_credentials( $this->posted_api_key() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'connected' => true ) );
	}

	/**
	 * `cf7ai_list_models` — lists live models for the selected provider +
	 * (possibly not-yet-saved) API key.
	 *
	 * @return void
	 */
	public function list_models(): void {
		$this->check( Capabilities::MANAGE_SETTINGS );

		$provider = ProviderFactory::create( $this->posted_provider_id() );

		if ( null === $provider ) {
			wp_send_json_error( array( 'message' => __( 'Unknown provider.', 'cf7-ai-inbox' ) ), 400 );
		}

		$models = $provider->get_models( $this->posted_api_key() );

		if ( is_wp_error( $models ) ) {
			wp_send_json_error( array( 'message' => $models->get_error_message() ) );
		}

		wp_send_json_success( array( 'models' => $models ) );
	}

	/**
	 * `cf7ai_flamingo_detect` — Import & Migration wizard step 1.
	 *
	 * @return void
	 */
	public function flamingo_detect(): void {
		$this->check( Capabilities::MANAGE_SETTINGS );

		wp_send_json_success( FlamingoImporter::detect() );
	}

	/**
	 * `cf7ai_flamingo_import_batch` — Import & Migration wizard step 3,
	 * called repeatedly with an increasing offset until `done` is true.
	 *
	 * @return void
	 */
	public function flamingo_import_batch(): void {
		$this->check( Capabilities::MANAGE_SETTINGS );

		$offset = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;
		$run_ai = ! empty( $_POST['run_ai'] );

		wp_send_json_success( FlamingoImporter::import_batch( $offset, 25, $run_ai ) );
	}

	/**
	 * Reads and lightly sanitizes `$_POST['provider']`.
	 *
	 * @return string
	 */
	private function posted_provider_id(): string {
		return isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
	}

	/**
	 * Reads `$_POST['api_key']`, falling back to the already-stored key
	 * when the value is empty or is the masked placeholder the browser
	 * shows (never a real key it should overwrite).
	 *
	 * @return string
	 */
	private function posted_api_key(): string {
		$posted = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

		if ( '' === $posted || false !== strpos( $posted, "\u{2022}" ) ) {
			return (string) SettingsRepository::get_api_key();
		}

		return $posted;
	}
}
