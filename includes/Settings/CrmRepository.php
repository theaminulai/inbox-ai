<?php
/**
 * Typed access to the CRM Data Collection card's own settings.
 *
 * @package InboxAI\Settings
 */

namespace InboxAI\Settings;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\Security\Encryption;

/**
 * Class CrmRepository
 *
 * Settings → Integrations → "CRM Data Collection" card's storage, split out
 * of the general {@see Repository} into its own class — nothing here is
 * shared with {@see SlackRepository} or any other tab's settings.
 *
 * UI scaffold only: this class only ever saves which CRM provider is
 * selected and its API key — nothing in the codebase yet reads it to push
 * data to HubSpot, Mailchimp, or any other CRM. It exists so the connection
 * details are already in place, ready for a future release to wire up an
 * actual sync.
 */
final class CrmRepository {

	private const OPTION         = 'inboxai_settings_crm';
	private const API_KEY_OPTION = 'inboxai_crm_api_key';

	/**
	 * Allowed CRM provider ids for the "CRM provider" dropdown.
	 */
	private const PROVIDERS = array( 'none', 'hubspot', 'mailchimp' );

	/**
	 * @return array{provider:string}
	 */
	public static function get(): array {
		$defaults = array(
			'provider' => 'none',
		);

		$stored = get_option( self::OPTION, array() );

		return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
	}

	/**
	 * Saves the selected provider (excluding the API key — see {@see self::save_api_key()}).
	 *
	 * @param array<string, mixed> $data Raw, unsanitized input.
	 *
	 * @return void
	 */
	public static function save( array $data ): void {
		$current = self::get();

		update_option(
			self::OPTION,
			array(
				'provider' => in_array( $data['crm_provider'] ?? '', self::PROVIDERS, true ) ? $data['crm_provider'] : $current['provider'],
			),
			false
		);

		if ( isset( $data['crm_api_key'] ) ) {
			self::save_api_key( (string) $data['crm_api_key'] );
		}
	}

	/**
	 * Whether a CRM API key is currently stored.
	 *
	 * @return bool
	 */
	public static function has_api_key(): bool {
		return '' !== (string) get_option( self::API_KEY_OPTION, '' );
	}

	/**
	 * Decrypts and returns the stored CRM API key.
	 *
	 * @return string|null Null if none is stored or it fails to decrypt.
	 */
	public static function get_api_key(): ?string {
		$stored = (string) get_option( self::API_KEY_OPTION, '' );

		if ( '' === $stored ) {
			return null;
		}

		return Encryption::decrypt( $stored );
	}

	/**
	 * A masked representation safe to send back to the browser — same shape
	 * as {@see \InboxAI\Settings\Repository::get_masked_api_key()}.
	 *
	 * @return string Empty string if no key is stored.
	 */
	public static function get_masked_api_key(): string {
		$key = self::get_api_key();

		if ( null === $key || strlen( $key ) < 8 ) {
			return '';
		}

		return substr( $key, 0, 3 ) . str_repeat( '•', 20 ) . substr( $key, -4 );
	}

	/**
	 * Encrypts and stores a new CRM API key. Same masked-value guard as
	 * {@see \InboxAI\Settings\Repository::save_api_key()} — resaving the
	 * masked display value must never overwrite the real stored key.
	 *
	 * @param string $plain New plaintext API key.
	 *
	 * @return void
	 */
	public static function save_api_key( string $plain ): void {
		if ( '' === $plain || false !== strpos( $plain, "\u{2022}" ) ) {
			return;
		}

		update_option( self::API_KEY_OPTION, Encryption::encrypt( $plain ), false );
	}
}
