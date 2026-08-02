<?php
/**
 * AI Inbox List page — Submission Detail screen's Activity timeline items.
 *
 * Split out of `inbox/detail.php` for the same reason as
 * `inbox/detail-ai-body.php` (see that file's docblock): re-rendered by
 * {@see \InboxAI\Admin\Ajax\InboxAjaxController::get_message()} so
 * `detail.js` can swap the whole timeline back in after a retried/
 * regenerated analysis finishes, without reloading the page.
 *
 * @var array<int, array<string, mixed>> $activities Rows from
 *                                       {@see \InboxAI\Database\ActivityRepository::get_for_message()}.
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<?php if ( array() === $activities ) : ?>
	<div class="inboxai-timeline__item"><div class="inboxai-timeline__text"><?php esc_html_e( 'No activity recorded yet.', 'inbox-ai' ); ?></div></div>
<?php else : ?>
	<?php foreach ( $activities as $inboxai_activity ) : ?>
		<div class="inboxai-timeline__item">
			<div class="inboxai-timeline__dot <?php echo 'ai_analysis_failed' === $inboxai_activity['event_type'] ? 'inboxai-timeline__dot--fail' : 'inboxai-timeline__dot--ok'; ?>"></div>
			<div class="inboxai-timeline__text"><?php echo esc_html( \InboxAI\Support\Format::activity_event_label( (string) $inboxai_activity['event_type'] ) ); ?></div>
			<div class="inboxai-timeline__meta"><?php echo esc_html( \InboxAI\Support\Format::time_ago( (string) $inboxai_activity['created_at'] ) ); ?></div>
		</div>
	<?php endforeach; ?>
<?php endif; ?>
