<?php
/**
 * AI Inbox List page — submission detail screen.
 *
 * Fully server-rendered from `$message`/`$activities` (see
 * {@see \InboxAI\Admin\Pages\InboxListPage::render_detail()}) — this is
 * its own real page load (`?page=inboxai-inbox&id=123`), not a client-side
 * screen swap over an AJAX call. When `$message['workflow_status']` is
 * `failed`, the "AI Analysis" card is replaced with an error card and the
 * Reply Composer is skipped (there's no AI draft to send yet) — folding what
 * used to be a separate "AI failure" screen into this same submission page,
 * since it's the same submission either way and deserves the same customer/
 * submission-detail context. `src/admin/componets/inbox/detail.js` only
 * wires up interactivity from here: the reply composer's rich-text toolbar
 * and save/send calls, and the retry/regenerate buttons' AJAX calls.
 *
 * @var array<string, mixed>        $message    A row from
 *                                               {@see \InboxAI\Database\MessageRepository::find()}.
 * @var array<int, array<string, mixed>> $activities Rows from
 *                                               {@see \InboxAI\Database\ActivityRepository::get_for_message()}.
 * @var bool                        $can_reply  Whether the current user holds `SEND_REPLIES`.
 * @var bool                        $can_edit   Whether the current user holds `EDIT_MESSAGES`.
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$inboxai_is_failed          = 'failed' === $message['workflow_status'];
$inboxai_list_url           = \InboxAI\Admin\Menu::url( 'inboxai-inbox' );
$inboxai_mail_status_labels = array(
	'pending' => array( __( 'Pending', 'inbox-ai' ), 'inboxai-status--new' ),
	'sent'    => array( __( 'Mail sent successfully', 'inbox-ai' ), 'inboxai-status--replied' ),
	'failed'  => array( __( 'Mail delivery failed', 'inbox-ai' ), 'inboxai-status--failed' ),
);
$inboxai_mail_status        = $inboxai_mail_status_labels[ $message['mail_status'] ] ?? $inboxai_mail_status_labels['pending'];
$inboxai_event_labels       = array(
	'received'              => __( 'Submission received', 'inbox-ai' ),
	'ai_analysis_completed' => __( 'AI analysis completed', 'inbox-ai' ),
	'ai_analysis_failed'    => __( 'AI analysis failed', 'inbox-ai' ),
	'draft_saved'           => __( 'Reply draft saved', 'inbox-ai' ),
	'reply_sent'            => __( 'Reply sent', 'inbox-ai' ),
	'reviewed'              => __( 'Marked as reviewed', 'inbox-ai' ),
	'archived'              => __( 'Archived', 'inbox-ai' ),
	'retry_requested'       => __( 'Analysis retry requested', 'inbox-ai' ),
);

?>
<section class="inboxai-screen inboxai-is-active" id="screen-detail" data-message-id="<?php echo (int) $message['id']; ?>" data-recipient-email="<?php echo esc_attr( $message['sender_email'] ); ?>">
	<div class="inboxai-breadcrumb">
		<a href="<?php echo esc_url( $inboxai_list_url ); ?>"><?php esc_html_e( 'AI Inbox', 'inbox-ai' ); ?></a> <span>/</span> <span><?php echo esc_html( '#' . $message['id'] ); ?></span>
	</div>
	<div class="inboxai-page-header">
		<div>
			<h1><?php echo esc_html( $message['subject'] ?: __( '(no subject)', 'inbox-ai' ) ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: 1: sender name, 2: sender email, 3: form title */
					esc_html__( 'From %1$s · %2$s · %3$s', 'inbox-ai' ),
					esc_html( $message['sender_name'] ?: __( 'Unknown', 'inbox-ai' ) ),
					esc_html( $message['sender_email'] ?: '—' ),
					esc_html( $message['form_title'] )
				);
				?>
			</p>
		</div>
		<div class="inboxai-page-header__controls">
			<?php echo \InboxAI\Support\Format::priority_badge_html( (string) $message['priority'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Format::priority_badge_html() escapes every dynamic piece internally (esc_attr()/esc_html()) before returning; see includes/Support/Format.php. ?>
			<?php echo \InboxAI\Support\Format::status_badge_html( (string) $message['workflow_status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- same as above, see Format::status_badge_html(). ?>
			<span class="inboxai-card__muted" style="white-space:nowrap;"><?php echo esc_html( \InboxAI\Support\Format::format_datetime( (string) $message['created_at'] ) ); ?></span>
		</div>
	</div>

	<div class="inboxai-content-grid inboxai-content-grid--split" style="margin-bottom:16px;">
		<div class="inboxai-stack">
			<div class="inboxai-card">
				<div class="inboxai-card__header"><h2><?php esc_html_e( 'Customer Information', 'inbox-ai' ); ?></h2></div>
				<div class="inboxai-card__body">
					<div style="display:flex;gap:12px;align-items:center;margin-bottom:14px;">
						<div class="inboxai-avatar" style="width:44px;height:44px;font-size:14px;background:<?php echo esc_attr( \InboxAI\Support\Format::avatar_color( (string) $message['sender_email'] ) ); ?>;"><?php echo esc_html( \InboxAI\Support\Format::avatar_initials( (string) $message['sender_name'] ) ); ?></div>
						<div><div class="inboxai-customer__name" style="font-size:14.5px;"><?php echo esc_html( $message['sender_name'] ?: __( '(no name)', 'inbox-ai' ) ); ?></div><div class="inboxai-customer__email"><?php echo esc_html( $message['sender_email'] ?: '—' ); ?></div></div>
					</div>
					<div class="inboxai-field" style="margin-bottom:10px;"><label><?php esc_html_e( 'Phone', 'inbox-ai' ); ?></label><div style="font-size:13px;color:var(--text-secondary);"><?php echo esc_html( $message['meta']['phone'] ?? '—' ); ?></div></div>
					<div class="inboxai-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Company', 'inbox-ai' ); ?></label><div style="font-size:13px;color:var(--text-secondary);"><?php echo esc_html( $message['meta']['company'] ?? '—' ); ?></div></div>
				</div>
			</div>
			<div class="inboxai-card">
				<div class="inboxai-card__header"><h2><?php esc_html_e( 'Submission Details', 'inbox-ai' ); ?></h2></div>
				<div class="inboxai-card__body" style="display:flex;flex-direction:column;gap:10px;">
					<div class="inboxai-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Submission ID', 'inbox-ai' ); ?></label><div style="font-size:13px;color:var(--text-secondary);font-family:var(--mono);"><?php echo esc_html( '#' . $message['id'] ); ?></div></div>
					<div class="inboxai-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Form Name', 'inbox-ai' ); ?></label><div style="font-size:13px;color:var(--text-secondary);"><?php echo esc_html( $message['form_title'] ?: '—' ); ?></div></div>
					<div class="inboxai-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Source Page', 'inbox-ai' ); ?></label><div style="font-size:13px;color:var(--accent-deep);word-break:break-all;"><?php echo esc_html( $message['meta']['source_page'] ?? '—' ); ?></div></div>
					<div class="inboxai-field" style="margin-bottom:0;"><label><?php esc_html_e( 'IP Address', 'inbox-ai' ); ?></label><div style="font-size:13px;color:var(--text-secondary);font-family:var(--mono);"><?php echo esc_html( $message['meta']['ip'] ?? '—' ); ?></div></div>
					<div class="inboxai-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Submitted', 'inbox-ai' ); ?></label><div style="font-size:13px;color:var(--text-secondary);"><?php echo esc_html( \InboxAI\Support\Format::format_datetime( (string) $message['created_at'] ) ); ?></div></div>
					<div class="inboxai-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Mail Status', 'inbox-ai' ); ?></label><span class="inboxai-status <?php echo esc_attr( $inboxai_mail_status[1] ); ?>"><?php echo esc_html( $inboxai_mail_status[0] ); ?></span></div>
				</div>
			</div>
		</div>

		<?php if ( $inboxai_is_failed ) : ?>
			<div class="inboxai-card">
				<div class="inboxai-card__header"><h2><?php esc_html_e( 'AI Analysis', 'inbox-ai' ); ?></h2></div>
				<div class="inboxai-card__body">
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
								<button class="inboxai-btn--secondary" style="background:#fff;" id="detail-manual-btn"><?php esc_html_e( 'Mark Reviewed', 'inbox-ai' ); ?></button>
								<a class="inboxai-btn--secondary" style="background:#fff;" href="<?php echo esc_url( \InboxAI\Admin\Menu::url( 'inboxai-settings' ) ); ?>"><?php esc_html_e( 'Provider Settings', 'inbox-ai' ); ?></a>
							</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		<?php else : ?>
			<div class="inboxai-card">
				<div class="inboxai-card__header">
					<h2><?php esc_html_e( 'AI Analysis', 'inbox-ai' ); ?></h2>
					<?php if ( $can_edit ) : ?>
					<button class="inboxai-summary__link" id="detail-regenerate-analysis"><?php esc_html_e( 'Regenerate Analysis', 'inbox-ai' ); ?></button>
					<?php endif; ?>
				</div>
				<div class="inboxai-card__body">
					<div class="inboxai-field"><label><?php esc_html_e( 'Summary', 'inbox-ai' ); ?></label><div style="font-size:13.5px;line-height:1.7;"><?php echo esc_html( $message['ai_summary'] ?: __( 'No AI analysis available for this submission. Regenerate the analysis or fill in details manually.', 'inbox-ai' ) ); ?></div></div>
					<div class="inboxai-field-row">
						<div class="inboxai-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Category', 'inbox-ai' ); ?></label><span class="inboxai-badge" style="background:var(--accent-soft);color:var(--accent-deep);"><?php echo esc_html( $message['category'] ?: '—' ); ?></span></div>
						<div class="inboxai-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Priority', 'inbox-ai' ); ?></label><?php echo \InboxAI\Support\Format::priority_badge_html( (string) $message['priority'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see Format::priority_badge_html(), escapes internally. ?></div>
					</div>
					<div class="inboxai-field">
						<label><?php esc_html_e( 'Confidence', 'inbox-ai' ); ?></label>
						<?php
						$inboxai_confidence = null === $message['confidence'] ? null : (int) $message['confidence'];
						?>
						<div class="inboxai-confidence" style="min-width:100%;">
							<div class="inboxai-confidence__value" style="<?php echo null === $inboxai_confidence ? 'color:var(--text-tertiary);' : ''; ?>"><?php echo null === $inboxai_confidence ? '—' : esc_html( $inboxai_confidence . '% confident' ); ?></div>
							<div class="inboxai-confidence__track"><div class="inboxai-confidence__fill" style="width:<?php echo (int) ( $inboxai_confidence ?? 0 ); ?>%;"></div></div>
						</div>
					</div>
					<div class="inboxai-field" style="margin-bottom:0;"><label><?php esc_html_e( 'AI Reasoning', 'inbox-ai' ); ?></label><div style="font-size:12.5px;line-height:1.7;color:var(--text-secondary);background:var(--surface-2);border-radius:8px;padding:12px;"><?php echo esc_html( $message['ai_reasoning'] ?: __( 'Not available.', 'inbox-ai' ) ); ?></div></div>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<div class="inboxai-card" style="margin-bottom:16px;">
		<div class="inboxai-card__header"><h2><?php esc_html_e( 'Submitted Fields', 'inbox-ai' ); ?></h2></div>
		<div class="inboxai-card__body" style="display:flex;flex-direction:column;gap:14px;">
			<div class="inboxai-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Subject', 'inbox-ai' ); ?></label><div style="font-size:13.5px;"><?php echo esc_html( $message['subject'] ?: '—' ); ?></div></div>
			<div class="inboxai-field" style="margin-bottom:0;">
				<label><?php esc_html_e( 'Message', 'inbox-ai' ); ?></label>
				<div style="font-size:13.5px;line-height:1.7;color:var(--text-secondary);background:var(--surface-2);border-radius:8px;padding:12px;white-space:pre-wrap;"><?php echo esc_html( $message['message'] ?: '—' ); ?></div>
			</div>
			<?php foreach ( (array) $message['fields'] as $inboxai_field_key => $inboxai_field_value ) : ?>
				<div class="inboxai-field" style="margin-bottom:0;"><label><?php echo esc_html( $inboxai_field_key ); ?></label><div style="font-size:13.5px;"><?php echo esc_html( (string) $inboxai_field_value ); ?></div></div>
			<?php endforeach; ?>
		</div>
	</div>

	<?php if ( $can_edit && ! $inboxai_is_failed ) : ?>
	<div class="inboxai-card" id="reply-composer" style="margin-bottom:16px;">
		<div class="inboxai-card__header"><h2><?php esc_html_e( 'Reply Composer', 'inbox-ai' ); ?></h2><span class="inboxai-card__muted" id="detail-draft-status"></span></div>
		<div class="inboxai-card__body">
			<div class="inboxai-field-row">
				<div class="inboxai-field"><label><?php esc_html_e( 'Recipient', 'inbox-ai' ); ?></label><input class="inboxai-field__input" id="detail-recipient" value="<?php echo esc_attr( $message['sender_email'] ); ?>" readonly></div>
				<div class="inboxai-field"><label><?php esc_html_e( 'Drafted By', 'inbox-ai' ); ?></label><input class="inboxai-field__input" id="detail-provider-info" value="<?php echo esc_attr( $message['ai_provider'] ? $message['ai_provider'] . ' · ' . $message['ai_model'] : __( 'Not yet drafted', 'inbox-ai' ) ); ?>" readonly></div>
			</div>
			<div class="inboxai-field"><label><?php esc_html_e( 'Subject', 'inbox-ai' ); ?></label><input class="inboxai-field__input" id="detail-subject" value="<?php echo esc_attr( $message['reply_subject'] ?: ( 'Re: ' . $message['subject'] ) ); ?>"></div>
			<div class="inboxai-field" style="margin-bottom:12px;">
				<label><?php esc_html_e( 'Message', 'inbox-ai' ); ?></label>
				<div style="border:1px solid var(--border-strong);border-radius:8px;overflow:hidden;">
					<div style="display:flex;align-items:center;gap:2px;padding:8px 10px;border-bottom:1px solid var(--border);background:var(--surface-2);flex-wrap:wrap;">
						<div class="inboxai-btn--icon" id="fmt-bold" title="<?php esc_attr_e( 'Bold', 'inbox-ai' ); ?>" style="width:26px;height:26px;border:none;background:transparent;box-shadow:none;font-weight:700;font-size:12px;">B</div>
						<div class="inboxai-btn--icon" id="fmt-italic" title="<?php esc_attr_e( 'Italic', 'inbox-ai' ); ?>" style="width:26px;height:26px;border:none;background:transparent;box-shadow:none;font-style:italic;font-size:12px;">I</div>
						<div class="inboxai-btn--icon" id="fmt-underline" title="<?php esc_attr_e( 'Underline', 'inbox-ai' ); ?>" style="width:26px;height:26px;border:none;background:transparent;box-shadow:none;text-decoration:underline;font-size:12px;">U</div>
						<div class="inboxai-btn--icon" id="fmt-list" title="<?php esc_attr_e( 'Bulleted list', 'inbox-ai' ); ?>" style="width:26px;height:26px;border:none;background:transparent;box-shadow:none;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg></div>
						<div class="inboxai-btn--icon" id="detail-regenerate-reply" title="<?php esc_attr_e( 'Regenerate reply with AI', 'inbox-ai' ); ?>" style="width:26px;height:26px;border:none;background:transparent;box-shadow:none;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg></div>
					</div>
					<div class="inboxai-field__input" id="detail-reply-body" contenteditable="true" style="border:none;border-radius:0;min-height:150px;font-family:'Inter';font-size:13px;overflow-y:auto;white-space:pre-wrap;"><?php echo wp_kses_post( $message['reply_draft'] ?: '' ); ?></div>
				</div>
			</div>
			<div style="display:flex;gap:10px;flex-wrap:wrap;">
				<button class="inboxai-btn--secondary" id="detail-save-draft"><?php esc_html_e( 'Save Draft', 'inbox-ai' ); ?></button>
				<div style="flex:1;"></div>
				<?php if ( $can_reply ) : ?>
				<button class="inboxai-btn--primary" id="open-reply-modal">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></svg>
					<?php esc_html_e( 'Send Reply', 'inbox-ai' ); ?>
				</button>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<div class="inboxai-card">
		<div class="inboxai-card__header"><h2><?php esc_html_e( 'Activity', 'inbox-ai' ); ?></h2></div>
		<div class="inboxai-card__body">
			<div class="inboxai-timeline">
				<?php if ( array() === $activities ) : ?>
					<div class="inboxai-timeline__item"><div class="inboxai-timeline__text"><?php esc_html_e( 'No activity recorded yet.', 'inbox-ai' ); ?></div></div>
				<?php else : ?>
					<?php foreach ( $activities as $inboxai_activity ) : ?>
						<div class="inboxai-timeline__item">
							<div class="inboxai-timeline__dot <?php echo 'ai_analysis_failed' === $inboxai_activity['event_type'] ? 'inboxai-timeline__dot--fail' : 'inboxai-timeline__dot--ok'; ?>"></div>
							<div class="inboxai-timeline__text"><?php echo esc_html( $inboxai_event_labels[ $inboxai_activity['event_type'] ] ?? $inboxai_activity['event_type'] ); ?></div>
							<div class="inboxai-timeline__meta"><?php echo esc_html( \InboxAI\Support\Format::time_ago( (string) $inboxai_activity['created_at'] ) ); ?></div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
