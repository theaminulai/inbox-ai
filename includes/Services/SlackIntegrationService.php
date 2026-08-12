<?php
/**
 * Sends Slack messages for the Slack Integration card.
 *
 * @package InboxAI\Services
 */

namespace InboxAI\Services;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InboxAI\Admin\Menu;
use InboxAI\Settings\SlackRepository;
use WP_Error;

/**
 * Class SlackIntegrationService
 *
 * Everything that actually talks to Slack, split out of the general
 * {@see NotificationService} (which only ever emails the site admin) so
 * Slack has its own class with its own settings source
 * ({@see SlackRepository}) — nothing shared with email notifications or
 * the CRM Data Collection card.
 */
final class SlackIntegrationService {

	/**
	 * Posts a message to the configured Slack Incoming Webhook when a
	 * submission's priority comes back `urgent`, if the Slack Integration
	 * card's "enabled" switch is on and a webhook URL is saved.
	 *
	 * Called from {@see \InboxAI\AI\AnalysisQueue::process()} (a fresh
	 * submission's first analysis) and {@see \InboxAI\AI\AnalysisQueue::process_reply()}
	 * (a customer reply that gets re-analyzed as urgent) — both places
	 * `priority` is actually determined. This is a fire-and-forget,
	 * non-blocking `wp_remote_post()`: a Slack outage or a bad webhook URL
	 * must never delay or fail the analysis pipeline it's called from.
	 *
	 * @param array<string, mixed> $message  Submission row (see `MessageRepository::find()`).
	 * @param string                $priority Normalized priority from {@see \InboxAI\AI\ResponseValidator::normalize_priority()}.
	 *
	 * @return void
	 */
	public static function notify_urgent( array $message, string $priority ): void {
		if ( 'urgent' !== $priority ) {
			return;
		}

		$slack = SlackRepository::get();

		if ( empty( $slack['enabled'] ) || '' === $slack['webhook_url'] ) {
			return;
		}

		$sender = '' !== trim( (string) ( $message['sender_name'] ?? '' ) )
			? (string) $message['sender_name']
			: (string) ( $message['sender_email'] ?? '' );

		$summary = trim( (string) ( $message['ai_summary'] ?? '' ) );
		$url     = add_query_arg( array( 'id' => (int) $message['id'] ), Menu::url( 'inboxai-inbox' ) );

		$text = sprintf(
			/* translators: 1: customer name or email, 2: link to the submission */
			__( ':rotating_light: Urgent submission from %1$s — %2$s', 'inbox-ai' ),
			$sender,
			$url
		);

		if ( '' !== $summary ) {
			$text .= "\n" . $summary;
		}

		wp_remote_post(
			$slack['webhook_url'],
			array(
				'timeout'  => 10,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode( array( 'text' => $text ) ),
			)
		);
	}

	/**
	 * Posts a one-off test message to a (possibly not-yet-saved) Slack
	 * webhook URL and reports whether it actually succeeded — the
	 * Integrations tab's "Send test message" button, mirroring how the AI
	 * Provider and Inbound Email cards each let an admin verify their own
	 * credentials work before relying on them silently in the background
	 * (see `SettingsAjaxController::test_connection()`/`test_inbound_connection()`).
	 *
	 * Unlike {@see self::notify_urgent()}, this is a *blocking* request —
	 * the whole point is waiting for Slack's real response so a bad URL or a
	 * revoked webhook is reported back immediately instead of failing
	 * silently in the background later.
	 *
	 * @param string $webhook_url Webhook URL currently typed into the field
	 *                            (not necessarily saved yet).
	 *
	 * @return true|WP_Error True on a confirmed 2xx response from Slack.
	 */
	public static function send_test( string $webhook_url ) {
		if ( '' === $webhook_url || 0 !== strpos( $webhook_url, 'https://' ) || ! wp_http_validate_url( $webhook_url ) ) {
			return new WP_Error( 'inboxai_invalid_webhook', __( 'Enter a valid HTTPS Slack webhook URL first.', 'inbox-ai' ) );
		}

		$response = wp_remote_post(
			$webhook_url,
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array( 'text' => __( 'This is a test message from Inbox AI — your Slack integration is connected.', 'inbox-ai' ) )
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$body = trim( (string) wp_remote_retrieve_body( $response ) );

			return new WP_Error(
				'inboxai_slack_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: response body text from Slack */
					__( 'Slack responded with an error (%1$d): %2$s', 'inbox-ai' ),
					$code,
					'' !== $body ? $body : __( 'no details returned', 'inbox-ai' )
				)
			);
		}

		return true;
	}
}
