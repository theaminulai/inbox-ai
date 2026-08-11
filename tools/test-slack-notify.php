<?php
/**
 * Dev-only tool: fires `SlackIntegrationService::notify_urgent()` with fake
 * submission data, the same way `AI\AnalysisQueue` does the moment a real
 * submission's priority comes back `urgent` — without needing to wait for a
 * real AI analysis to actually score something as urgent, and without
 * WP-CLI (`wp eval`), which is not set up as a shell command in this
 * environment (only vendored as a PHP library under `vendor/wp-cli/` for
 * the `make-pot` npm script — that is not the same thing as the `wp`
 * command-line tool). Not part of the shipped plugin — delete this file (or
 * just leave it out of the SVN/zip package) before release.
 *
 * Only actually posts anything if Settings → Integrations → Slack
 * Integration has a webhook URL saved and the switch is on — same gate the
 * real code path uses (see `SlackRepository::get()`). If you just want to
 * confirm a webhook URL itself is reachable, without any of the priority
 * logic in the way, use the "Send test message" button on that same
 * screen instead — it's simpler and doesn't need this file at all.
 *
 * HOW TO RUN:
 *
 * Browser, while logged into wp-admin as an administrator, visit this
 * file's URL directly, e.g.
 *   http://localhost/wp-plugin/cf7-ai-inbox/wp-content/plugins/inbox-ai/tools/test-slack-notify.php?confirm=yes
 * The `?confirm=yes` is required on purpose, so this can't fire by accident.
 *
 * Safe to run more than once — it never writes anything to the database,
 * it only calls the same one-way "post to Slack" method a real urgent
 * submission would trigger.
 *
 * @package InboxAI
 */

use InboxAI\Services\SlackIntegrationService;
use InboxAI\Settings\SlackRepository;

// Bootstrap WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	require dirname( __FILE__, 5 ) . '/wp-load.php';
}

if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
	wp_die( 'You must be logged in as an administrator to run this.' );
}

if ( ! isset( $_GET['confirm'] ) || 'yes' !== $_GET['confirm'] ) {
	wp_die( 'Add <code>?confirm=yes</code> to the URL to actually send a test Slack notification.' );
}

if ( ! class_exists( SlackIntegrationService::class ) ) {
	die( 'Inbox AI does not appear to be active on this site.' );
}

$slack = SlackRepository::get();

if ( empty( $slack['enabled'] ) || '' === $slack['webhook_url'] ) {
	die(
		"Slack Integration is off or has no webhook URL saved — nothing would happen on a real urgent "
		. "submission either, so this script stopped here instead of pretending to succeed. Go to "
		. "Settings &rarr; Integrations, turn 'Send a Slack message for urgent submissions' on, save a "
		. "webhook URL, then reload this page."
	);
}

// Fake submission data, shaped like the real array `MessageRepository::find()`
// returns merged with a fresh analysis's fields — only the keys
// `SlackIntegrationService::notify_urgent()` actually reads are needed.
$fake_message = array(
	'id'           => 0,
	'sender_name'  => 'Test User',
	'sender_email' => 'test@example.com',
	'ai_summary'   => 'This is a fake summary from tools/test-slack-notify.php, not a real submission.',
);

SlackIntegrationService::notify_urgent( $fake_message, 'urgent' );

echo esc_html(
	'Called SlackIntegrationService::notify_urgent() with fake urgent submission data. '
	. 'This call is fire-and-forget (non-blocking), so it does not confirm Slack actually '
	. 'accepted it — check your Slack channel now, or use the "Send test message" button on '
	. 'Settings -> Integrations for a version that waits for and reports Slack\'s real response.'
);
