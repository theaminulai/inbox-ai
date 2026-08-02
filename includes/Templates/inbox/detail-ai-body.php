<?php
/**
 * AI Inbox List page — Submission Detail screen's AI Analysis thread-item
 * body (the failed-analysis error state, or the summary/category/priority/
 * confidence/reasoning state).
 *
 * Split out of `inbox/detail.php` into its own partial for one reason:
 * `InboxAjaxController::get_message()` re-renders exactly this fragment via
 * {@see \InboxAI\Support\Template::render_to_string()} so `detail.js` can
 * swap it into the page in place after a retried/regenerated analysis
 * actually finishes, instead of reloading the whole page — see that
 * controller method and `detail.js`'s `wireRegeneratingAction()`. Keeping
 * this markup in one PHP file (rather than duplicating the same formatting
 * in JS) is what keeps the polled-in result visually identical to a real
 * page load.
 *
 * @var array<string, mixed> $message  A row from
 *                                     {@see \InboxAI\Database\MessageRepository::find()}.
 * @var bool                 $can_edit Whether the current user holds `EDIT_MESSAGES`.
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$inboxai_is_failed = 'failed' === $message['workflow_status'];

?>
<?php if ( $inboxai_is_failed ) : ?>
	<div class="inboxai-error">
		<div class="inboxai-error__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg></div>
		<div class="inboxai-error__body">
			<h3><?php esc_html_e( 'AI analysis could not be completed', 'inbox-ai' ); ?></h3>
			<p><?php echo esc_html( $message['ai_error'] ?: __( "The AI analysis request did not complete. This submission has not been summarized, categorized, or scored — it's safe to handle manually in the meantime.", 'inbox-ai' ) ); ?></p>
			<?php if ( $can_edit ) : ?>
			<div class="inboxai-error__actions">
				<button class="inboxai-btn--primary" id="detail-retry-btn" style="background:var(--urgent);border-color:#B92E2E;">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:13px;height:13px;"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
					<?php esc_html_e( 'Retry', 'inbox-ai' ); ?>
				</button>
				<button class="inboxai-btn--secondary" id="detail-manual-btn"><?php esc_html_e( 'Mark Reviewed', 'inbox-ai' ); ?></button>
				<a class="inboxai-btn--secondary" href="<?php echo esc_url( \InboxAI\Admin\Menu::url( 'inboxai-settings' ) ); ?>"><?php esc_html_e( 'Provider Settings', 'inbox-ai' ); ?></a>
			</div>
			<?php endif; ?>
		</div>
	</div>
<?php else : ?>
	<p class="inboxai-ai-summary"><?php echo esc_html( $message['ai_summary'] ?: __( 'No AI analysis available for this submission. Regenerate the analysis or fill in details manually.', 'inbox-ai' ) ); ?></p>
	<div class="inboxai-ai-grid">
		<div>
			<div class="inboxai-ai-field-label"><?php esc_html_e( 'Category', 'inbox-ai' ); ?></div>
			<span class="inboxai-badge" style="background:var(--accent-soft);color:var(--accent-deep);"><?php echo esc_html( $message['category'] ?: '—' ); ?></span>
		</div>
		<div>
			<div class="inboxai-ai-field-label"><?php esc_html_e( 'Priority', 'inbox-ai' ); ?></div>
			<?php echo \InboxAI\Support\Format::priority_badge_html( (string) $message['priority'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see Format::priority_badge_html(), escapes internally. ?>
		</div>
		<div>
			<div class="inboxai-ai-field-label"><?php esc_html_e( 'Confidence', 'inbox-ai' ); ?></div>
			<?php echo \InboxAI\Support\Format::confidence_cell_html( null === $message['confidence'] ? null : (int) $message['confidence'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see Format::confidence_cell_html(), escapes internally. ?>
		</div>
	</div>
	<div class="inboxai-ai-field-label"><?php esc_html_e( 'Reasoning', 'inbox-ai' ); ?></div>
	<div class="inboxai-ai-reasoning"><?php echo esc_html( $message['ai_reasoning'] ?: __( 'Not available.', 'inbox-ai' ) ); ?></div>
	<?php if ( $can_edit ) : ?>
	<div class="inboxai-ai-actions">
		<button class="inboxai-btn--secondary" id="detail-regenerate-analysis"><?php esc_html_e( 'Regenerate analysis', 'inbox-ai' ); ?></button>
	</div>
	<?php endif; ?>
<?php endif; ?>
