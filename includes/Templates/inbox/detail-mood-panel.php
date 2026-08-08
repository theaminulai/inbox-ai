<?php
/**
 * AI Inbox List page — Submission Detail screen's "Customer Mood" sidebar
 * panel body.
 *
 * Split out of `inbox/detail.php` for the same reason as
 * `inbox/detail-ai-body.php`/`inbox/detail-timeline.php` (see those files'
 * docblocks): re-rendered by
 * {@see \InboxAI\Admin\Ajax\InboxAjaxController::get_message()}/{@see \InboxAI\Admin\Ajax\InboxAjaxController::retry_analysis()}
 * via {@see \InboxAI\Support\Template::render_to_string()} so `detail.js`
 * can swap it into the page in place after a retried/regenerated analysis
 * finishes, instead of reloading the whole page.
 *
 * Mood is a one-time read per message, not a re-scoreable field: the
 * original submission gets one when it's first analyzed, and every genuinely
 * new customer reply gets its own when {@see \InboxAI\AI\AnalysisQueue::process_reply()}
 * runs — but clicking "Regenerate analysis"/"Regenerate reply"/"Retry" on a
 * message that already has a mood never changes it or adds a duplicate
 * history entry (see {@see \InboxAI\AI\AnalysisQueue::process()}'s
 * `$mood_already_set` guard). So "current mood" is just `$message['mood']`,
 * and the history below — styled like the Activity panel's own timeline,
 * each entry with the AI's short one-line reason underneath — is built from
 * every `ai_analysis_completed` activity that actually recorded a mood,
 * oldest-first-shown-last (matching `$activities`' own most-recent-first
 * order from {@see \InboxAI\Database\ActivityRepository::get_for_message()}).
 *
 * @var array<string, mixed>             $message    A row from
 *                                        {@see \InboxAI\Database\MessageRepository::find()}.
 * @var array<int, array<string, mixed>> $activities Rows from
 *                                        {@see \InboxAI\Database\ActivityRepository::get_for_message()}.
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Capped at 6 — a sidebar panel, not a full log (the Activity timeline panel
// already has the complete, uncapped history if it's ever needed).
$inboxai_mood_history = array();

foreach ( $activities as $inboxai_activity ) {
	if ( 'ai_analysis_completed' !== $inboxai_activity['event_type'] || ! isset( $inboxai_activity['event_data']['mood'] ) ) {
		continue;
	}

	$inboxai_mood_history[] = $inboxai_activity;

	if ( count( $inboxai_mood_history ) >= 6 ) {
		break;
	}
}

// Same 4-tier severity color logic as the timeline dot already uses for
// success/failure, just mapped onto mood instead — kept here rather than in
// `Support\Format` since it's purely this one view's presentation, not a
// value other screens need.
$inboxai_mood_dot_modifiers = array(
	'positive'   => 'ok',
	'neutral'    => 'neutral',
	'frustrated' => 'warn',
	'angry'      => 'fail',
);

?>
<div class="inboxai-kv">
	<div class="inboxai-kv-item">
		<div class="inboxai-kv-item__k"><?php esc_html_e( 'Current mood', 'inbox-ai' ); ?></div>
		<div class="inboxai-kv-item__v"><?php echo \InboxAI\Support\Format::mood_badge_html( (string) $message['mood'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see Format::mood_badge_html(), escapes internally. ?></div>
	</div>
</div>

<?php if ( array() !== $inboxai_mood_history ) : ?>
	<div class="inboxai-kv-item__k" style="margin-top:14px;margin-bottom:8px;"><?php esc_html_e( 'History', 'inbox-ai' ); ?></div>
	<div class="inboxai-timeline">
		<?php foreach ( $inboxai_mood_history as $inboxai_mood_event ) : ?>
			<?php
			$inboxai_mood        = (string) $inboxai_mood_event['event_data']['mood'];
			$inboxai_mood_reason = trim( (string) ( $inboxai_mood_event['event_data']['mood_reason'] ?? '' ) );
			$inboxai_dot_class   = 'inboxai-timeline__dot--' . ( $inboxai_mood_dot_modifiers[ $inboxai_mood ] ?? 'neutral' );
			?>
			<div class="inboxai-timeline__item">
				<div class="inboxai-timeline__dot <?php echo esc_attr( $inboxai_dot_class ); ?>"></div>
				<div class="inboxai-timeline__text"><?php echo \InboxAI\Support\Format::mood_badge_html( $inboxai_mood ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see Format::mood_badge_html(), escapes internally. ?></div>
				<?php if ( '' !== $inboxai_mood_reason ) : ?>
					<div class="inboxai-timeline__desc"><?php echo esc_html( $inboxai_mood_reason ); ?></div>
				<?php endif; ?>
				<div class="inboxai-timeline__meta"><?php echo esc_html( \InboxAI\Support\Format::time_ago( (string) $inboxai_mood_event['created_at'] ) ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>
<?php else : ?>
	<div class="inboxai-field__hint" style="margin-top:10px;"><?php esc_html_e( 'No mood history yet — this appears once AI analysis has run at least once.', 'inbox-ai' ); ?></div>
<?php endif; ?>
