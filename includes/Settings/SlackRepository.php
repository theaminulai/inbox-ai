<?php
/**
 * Typed access to the Slack Integration card's own settings.
 *
 * @package InboxAI\Settings
 */

namespace InboxAI\Settings;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SlackRepository
 *
 * Settings → Integrations → "Slack Integration" card's storage, split out
 * of the general {@see Repository} into its own class/option so Slack has
 * nothing to do with the Notifications tab's `notify_*` toggles or the CRM
 * Data Collection card — each integration owns its own settings row and its
 * own class, with nothing shared between them. See {@see \InboxAI\Services\SlackIntegrationService}
 * for the class that actually *uses* these settings to send something.
 */
final class SlackRepository {

	private const OPTION = 'inboxai_settings_slack';

	/**
	 * The option Slack's two fields used to live under, back when the Slack
	 * Integration card was still part of the Notifications tab (before the
	 * dedicated Integrations tab existed) — see {@see self::get()}'s
	 * docblock for why this is still read.
	 */
	private const LEGACY_NOTIFICATIONS_OPTION = 'inboxai_settings_notifications';

	/**
	 * @return array{enabled:bool,webhook_url:string}
	 */
	public static function get(): array {
		$defaults = array(
			'enabled'     => false,
			'webhook_url' => '',
		);

		$stored = get_option( self::OPTION, array() );

		// Slack's two fields originally lived inside the shared Notifications
		// option (`inboxai_settings_notifications`), back before the
		// Integrations tab existed. The very first read after that option
		// never having been written falls back to whatever's still sitting
		// in the old shared row, so an already-configured webhook doesn't
		// appear to silently vanish the first time this class is used —
		// nothing here writes that value back, {@see self::save()} is the
		// only thing that ever populates `self::OPTION` going forward.
		if ( ! is_array( $stored ) || array() === $stored ) {
			$legacy = get_option( self::LEGACY_NOTIFICATIONS_OPTION, array() );

			if ( is_array( $legacy ) && ( ! empty( $legacy['slack_enabled'] ) || ! empty( $legacy['slack_webhook_url'] ) ) ) {
				$defaults['enabled']     = ! empty( $legacy['slack_enabled'] );
				$defaults['webhook_url'] = (string) ( $legacy['slack_webhook_url'] ?? '' );
			}
		}

		return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
	}

	/**
	 * @param array<string, mixed> $data Raw, unsanitized input — `slack_enabled`/
	 *                                   `slack_webhook_url`, matching the
	 *                                   Integrations tab's own `data-field`
	 *                                   names (see `integrations.php`).
	 *
	 * @return void
	 */
	public static function save( array $data ): void {
		$current = self::get();

		$webhook = isset( $data['slack_webhook_url'] ) ? esc_url_raw( (string) $data['slack_webhook_url'] ) : $current['webhook_url'];

		if ( '' !== $webhook && ( 0 !== strpos( $webhook, 'https://' ) || ! wp_http_validate_url( $webhook ) ) ) {
			$webhook = $current['webhook_url'];
		}

		update_option(
			self::OPTION,
			array(
				'enabled'     => ! empty( $data['slack_enabled'] ),
				'webhook_url' => $webhook,
			),
			false
		);
	}
}
