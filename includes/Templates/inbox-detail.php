<?php
/**
 * AI Inbox List page — submission detail screen.
 *
 * Fully server-rendered from `$message`/`$activities` (see
 * {@see \CF7AIInbox\Admin\Pages\InboxListPage::render_detail()}) — this is
 * its own real page load (`?page=cf7ai-inbox&id=123`), not a client-side
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
 *                                               {@see \CF7AIInbox\Database\MessageRepository::find()}.
 * @var array<int, array<string, mixed>> $activities Rows from
 *                                               {@see \CF7AIInbox\Database\ActivityRepository::get_for_message()}.
 * @var bool                        $can_reply  Whether the current user holds `SEND_REPLIES`.
 * @var bool                        $can_edit   Whether the current user holds `EDIT_MESSAGES`.
 *
 * @package CF7AIInbox\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cf7ai_is_failed          = 'failed' === $message['workflow_status'];
$cf7ai_list_url           = \CF7AIInbox\Admin\Menu::url( 'cf7ai-inbox' );
$cf7ai_mail_status_labels = array(
	'pending' => array( __( 'Pending', 'cf7-ai-inbox' ), 'cf7-ai-inbox-status--new' ),
	'sent'    => array( __( 'Mail sent successfully', 'cf7-ai-inbox' ), 'cf7-ai-inbox-status--replied' ),
	'failed'  => array( __( 'Mail delivery failed', 'cf7-ai-inbox' ), 'cf7-ai-inbox-status--failed' ),
);
$cf7ai_mail_status        = $cf7ai_mail_status_labels[ $message['mail_status'] ] ?? $cf7ai_mail_status_labels['pending'];
$cf7ai_event_labels       = array(
	'received'              => __( 'Submission received', 'cf7-ai-inbox' ),
	'ai_analysis_completed' => __( 'AI analysis completed', 'cf7-ai-inbox' ),
	'ai_analysis_failed'    => __( 'AI analysis failed', 'cf7-ai-inbox' ),
	'draft_saved'           => __( 'Reply draft saved', 'cf7-ai-inbox' ),
	'reply_sent'            => __( 'Reply sent', 'cf7-ai-inbox' ),
	'reviewed'              => __( 'Marked as reviewed', 'cf7-ai-inbox' ),
	'archived'              => __( 'Archived', 'cf7-ai-inbox' ),
	'retry_requested'       => __( 'Analysis retry requested', 'cf7-ai-inbox' ),
);

?>
<section class="cf7-ai-inbox-screen cf7-ai-inbox-is-active" id="screen-detail" data-message-id="<?php echo (int) $message['id']; ?>" data-recipient-email="<?php echo esc_attr( $message['sender_email'] ); ?>">
	<div class="cf7-ai-inbox-breadcrumb">
		<a href="<?php echo esc_url( $cf7ai_list_url ); ?>"><?php esc_html_e( 'AI Inbox', 'cf7-ai-inbox' ); ?></a> <span>/</span> <span><?php echo esc_html( '#' . $message['id'] ); ?></span>
	</div>
	<div class="cf7-ai-inbox-page-header">
		<div>
			<h1><?php echo esc_html( $message['subject'] ?: __( '(no subject)', 'cf7-ai-inbox' ) ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: 1: sender name, 2: sender email, 3: form title */
					esc_html__( 'From %1$s · %2$s · %3$s', 'cf7-ai-inbox' ),
					esc_html( $message['sender_name'] ?: __( 'Unknown', 'cf7-ai-inbox' ) ),
					esc_html( $message['sender_email'] ?: '—' ),
					esc_html( $message['form_title'] )
				);
				?>
			</p>
		</div>
		<div class="cf7-ai-inbox-page-header__controls">
			<?php echo \CF7AIInbox\Support\Format::priority_badge_html( (string) $message['priority'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Format::priority_badge_html() escapes every dynamic piece internally (esc_attr()/esc_html()) before returning; see includes/Support/Format.php. ?>
			<?php echo \CF7AIInbox\Support\Format::status_badge_html( (string) $message['workflow_status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- same as above, see Format::status_badge_html(). ?>
			<span class="cf7-ai-inbox-card__muted" style="white-space:nowrap;"><?php echo esc_html( \CF7AIInbox\Support\Format::format_datetime( (string) $message['created_at'] ) ); ?></span>
		</div>
	</div>

	<div class="cf7-ai-inbox-content-grid cf7-ai-inbox-content-grid--split" style="margin-bottom:16px;">
		<div class="cf7-ai-inbox-stack">
			<div class="cf7-ai-inbox-card">
				<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Customer Information', 'cf7-ai-inbox' ); ?></h2></div>
				<div class="cf7-ai-inbox-card__body">
					<div style="display:flex;gap:12px;align-items:center;margin-bottom:14px;">
						<div class="cf7-ai-inbox-avatar" style="width:44px;height:44px;font-size:14px;background:<?php echo esc_attr( \CF7AIInbox\Support\Format::avatar_color( (string) $message['sender_email'] ) ); ?>;"><?php echo esc_html( \CF7AIInbox\Support\Format::avatar_initials( (string) $message['sender_name'] ) ); ?></div>
						<div><div class="cf7-ai-inbox-customer__name" style="font-size:14.5px;"><?php echo esc_html( $message['sender_name'] ?: __( '(no name)', 'cf7-ai-inbox' ) ); ?></div><div class="cf7-ai-inbox-customer__email"><?php echo esc_html( $message['sender_email'] ?: '—' ); ?></div></div>
					</div>
					<div class="cf7-ai-inbox-field" style="margin-bottom:10px;"><label><?php esc_html_e( 'Phone', 'cf7-ai-inbox' ); ?></label><div style="font-size:13px;color:var(--text-secondary);"><?php echo esc_html( $message['meta']['phone'] ?? '—' ); ?></div></div>
					<div class="cf7-ai-inbox-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Company', 'cf7-ai-inbox' ); ?></label><div style="font-size:13px;color:var(--text-secondary);"><?php echo esc_html( $message['meta']['company'] ?? '—' ); ?></div></div>
				</div>
			</div>
			<div class="cf7-ai-inbox-card">
				<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Submission Details', 'cf7-ai-inbox' ); ?></h2></div>
				<div class="cf7-ai-inbox-card__body" style="display:flex;flex-direction:column;gap:10px;">
					<div class="cf7-ai-inbox-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Submission ID', 'cf7-ai-inbox' ); ?></label><div style="font-size:13px;color:var(--text-secondary);font-family:var(--mono);"><?php echo esc_html( '#' . $message['id'] ); ?></div></div>
					<div class="cf7-ai-inbox-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Form Name', 'cf7-ai-inbox' ); ?></label><div style="font-size:13px;color:var(--text-secondary);"><?php echo esc_html( $message['form_title'] ?: '—' ); ?></div></div>
					<div class="cf7-ai-inbox-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Source Page', 'cf7-ai-inbox' ); ?></label><div style="font-size:13px;color:var(--accent-deep);word-break:break-all;"><?php echo esc_html( $message['meta']['source_page'] ?? '—' ); ?></div></div>
					<div class="cf7-ai-inbox-field" style="margin-bottom:0;"><label><?php esc_html_e( 'IP Address', 'cf7-ai-inbox' ); ?></label><div style="font-size:13px;color:var(--text-secondary);font-family:var(--mono);"><?php echo esc_html( $message['meta']['ip'] ?? '—' ); ?></div></div>
					<div class="cf7-ai-inbox-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Submitted', 'cf7-ai-inbox' ); ?></label><div style="font-size:13px;color:var(--text-secondary);"><?php echo esc_html( \CF7AIInbox\Support\Format::format_datetime( (string) $message['created_at'] ) ); ?></div></div>
					<div class="cf7-ai-inbox-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Mail Status', 'cf7-ai-inbox' ); ?></label><span class="cf7-ai-inbox-status <?php echo esc_attr( $cf7ai_mail_status[1] ); ?>"><?php echo esc_html( $cf7ai_mail_status[0] ); ?></span></div>
				</div>
			</div>
		</div>

		<?php if ( $cf7ai_is_failed ) : ?>
			<div class="cf7-ai-inbox-card">
				<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'AI Analysis', 'cf7-ai-inbox' ); ?></h2></div>
				<div class="cf7-ai-inbox-card__body">
					<div class="cf7-ai-inbox-error">
						<div class="cf7-ai-inbox-error__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg></div>
						<div class="cf7-ai-inbox-error__body">
							<h3><?php esc_html_e( 'AI analysis could not be completed', 'cf7-ai-inbox' ); ?></h3>
							<p><?php echo esc_html( $message['ai_error'] ?: __( "The AI analysis request did not complete. This submission has not been summarized, categorized, or scored — it's safe to handle manually in the meantime.", 'cf7-ai-inbox' ) ); ?></p>
							<?php if ( $can_edit ) : ?>
							<div class="cf7-ai-inbox-error__actions">
								<button class="cf7-ai-inbox-btn--primary" id="detail-retry-btn" style="background:var(--urgent);border-color:#B92E2E;">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:13px;height:13px;"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
									<?php esc_html_e( 'Retry', 'cf7-ai-inbox' ); ?>
								</button>
								<button class="cf7-ai-inbox-btn--secondary" style="background:#fff;" id="detail-manual-btn"><?php esc_html_e( 'Mark Reviewed', 'cf7-ai-inbox' ); ?></button>
								<a class="cf7-ai-inbox-btn--secondary" style="background:#fff;" href="<?php echo esc_url( \CF7AIInbox\Admin\Menu::url( 'cf7ai-settings' ) ); ?>"><?php esc_html_e( 'Provider Settings', 'cf7-ai-inbox' ); ?></a>
							</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		<?php else : ?>
			<div class="cf7-ai-inbox-card">
				<div class="cf7-ai-inbox-card__header">
					<h2><?php esc_html_e( 'AI Analysis', 'cf7-ai-inbox' ); ?></h2>
					<?php if ( $can_edit ) : ?>
					<button class="cf7-ai-inbox-summary__link" id="detail-regenerate-analysis"><?php esc_html_e( 'Regenerate Analysis', 'cf7-ai-inbox' ); ?></button>
					<?php endif; ?>
				</div>
				<div class="cf7-ai-inbox-card__body">
					<div class="cf7-ai-inbox-field"><label><?php esc_html_e( 'Summary', 'cf7-ai-inbox' ); ?></label><div style="font-size:13.5px;line-height:1.7;"><?php echo esc_html( $message['ai_summary'] ?: __( 'No AI analysis available for this submission. Regenerate the analysis or fill in details manually.', 'cf7-ai-inbox' ) ); ?></div></div>
					<div class="cf7-ai-inbox-field-row">
						<div class="cf7-ai-inbox-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Category', 'cf7-ai-inbox' ); ?></label><span class="cf7-ai-inbox-badge" style="background:var(--accent-soft);color:var(--accent-deep);"><?php echo esc_html( $message['category'] ?: '—' ); ?></span></div>
						<div class="cf7-ai-inbox-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Priority', 'cf7-ai-inbox' ); ?></label><?php echo \CF7AIInbox\Support\Format::priority_badge_html( (string) $message['priority'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see Format::priority_badge_html(), escapes internally. ?></div>
					</div>
					<div class="cf7-ai-inbox-field">
						<label><?php esc_html_e( 'Confidence', 'cf7-ai-inbox' ); ?></label>
						<?php
						$cf7ai_confidence = null === $message['confidence'] ? null : (int) $message['confidence'];
						?>
						<div class="cf7-ai-inbox-confidence" style="min-width:100%;">
							<div class="cf7-ai-inbox-confidence__value" style="<?php echo null === $cf7ai_confidence ? 'color:var(--text-tertiary);' : ''; ?>"><?php echo null === $cf7ai_confidence ? '—' : esc_html( $cf7ai_confidence . '% confident' ); ?></div>
							<div class="cf7-ai-inbox-confidence__track"><div class="cf7-ai-inbox-confidence__fill" style="width:<?php echo (int) ( $cf7ai_confidence ?? 0 ); ?>%;"></div></div>
						</div>
					</div>
					<div class="cf7-ai-inbox-field" style="margin-bottom:0;"><label><?php esc_html_e( 'AI Reasoning', 'cf7-ai-inbox' ); ?></label><div style="font-size:12.5px;line-height:1.7;color:var(--text-secondary);background:var(--surface-2);border-radius:8px;padding:12px;"><?php echo esc_html( $message['ai_reasoning'] ?: __( 'Not available.', 'cf7-ai-inbox' ) ); ?></div></div>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<div class="cf7-ai-inbox-card" style="margin-bottom:16px;">
		<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Submitted Fields', 'cf7-ai-inbox' ); ?></h2></div>
		<div class="cf7-ai-inbox-card__body" style="display:flex;flex-direction:column;gap:14px;">
			<div class="cf7-ai-inbox-field" style="margin-bottom:0;"><label><?php esc_html_e( 'Subject', 'cf7-ai-inbox' ); ?></label><div style="font-size:13.5px;"><?php echo esc_html( $message['subject'] ?: '—' ); ?></div></div>
			<div class="cf7-ai-inbox-field" style="margin-bottom:0;">
				<label><?php esc_html_e( 'Message', 'cf7-ai-inbox' ); ?></label>
				<div style="font-size:13.5px;line-height:1.7;color:var(--text-secondary);background:var(--surface-2);border-radius:8px;padding:12px;white-space:pre-wrap;"><?php echo esc_html( $message['message'] ?: '—' ); ?></div>
			</div>
			<?php foreach ( (array) $message['fields'] as $cf7ai_field_key => $cf7ai_field_value ) : ?>
				<div class="cf7-ai-inbox-field" style="margin-bottom:0;"><label><?php echo esc_html( $cf7ai_field_key ); ?></label><div style="font-size:13.5px;"><?php echo esc_html( (string) $cf7ai_field_value ); ?></div></div>
			<?php endforeach; ?>
		</div>
	</div>

	<?php if ( $can_edit && ! $cf7ai_is_failed ) : ?>
	<div class="cf7-ai-inbox-card" id="reply-composer" style="margin-bottom:16px;">
		<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Reply Composer', 'cf7-ai-inbox' ); ?></h2><span class="cf7-ai-inbox-card__muted" id="detail-draft-status"></span></div>
		<div class="cf7-ai-inbox-card__body">
			<div class="cf7-ai-inbox-field-row">
				<div class="cf7-ai-inbox-field"><label><?php esc_html_e( 'Recipient', 'cf7-ai-inbox' ); ?></label><input class="cf7-ai-inbox-field__input" id="detail-recipient" value="<?php echo esc_attr( $message['sender_email'] ); ?>" readonly></div>
				<div class="cf7-ai-inbox-field"><label><?php esc_html_e( 'Drafted By', 'cf7-ai-inbox' ); ?></label><input class="cf7-ai-inbox-field__input" id="detail-provider-info" value="<?php echo esc_attr( $message['ai_provider'] ? $message['ai_provider'] . ' · ' . $message['ai_model'] : __( 'Not yet drafted', 'cf7-ai-inbox' ) ); ?>" readonly></div>
			</div>
			<div class="cf7-ai-inbox-field"><label><?php esc_html_e( 'Subject', 'cf7-ai-inbox' ); ?></label><input class="cf7-ai-inbox-field__input" id="detail-subject" value="<?php echo esc_attr( $message['reply_subject'] ?: ( 'Re: ' . $message['subject'] ) ); ?>"></div>
			<div class="cf7-ai-inbox-field" style="margin-bottom:12px;">
				<label><?php esc_html_e( 'Message', 'cf7-ai-inbox' ); ?></label>
				<div style="border:1px solid var(--border-strong);border-radius:8px;overflow:hidden;">
					<div style="display:flex;align-items:center;gap:2px;padding:8px 10px;border-bottom:1px solid var(--border);background:var(--surface-2);flex-wrap:wrap;">
						<div class="cf7-ai-inbox-btn--icon" id="fmt-bold" title="<?php esc_attr_e( 'Bold', 'cf7-ai-inbox' ); ?>" style="width:26px;height:26px;border:none;background:transparent;box-shadow:none;font-weight:700;font-size:12px;">B</div>
						<div class="cf7-ai-inbox-btn--icon" id="fmt-italic" title="<?php esc_attr_e( 'Italic', 'cf7-ai-inbox' ); ?>" style="width:26px;height:26px;border:none;background:transparent;box-shadow:none;font-style:italic;font-size:12px;">I</div>
						<div class="cf7-ai-inbox-btn--icon" id="fmt-underline" title="<?php esc_attr_e( 'Underline', 'cf7-ai-inbox' ); ?>" style="width:26px;height:26px;border:none;background:transparent;box-shadow:none;text-decoration:underline;font-size:12px;">U</div>
						<div class="cf7-ai-inbox-btn--icon" id="fmt-list" title="<?php esc_attr_e( 'Bulleted list', 'cf7-ai-inbox' ); ?>" style="width:26px;height:26px;border:none;background:transparent;box-shadow:none;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg></div>
						<div class="cf7-ai-inbox-btn--icon" id="detail-regenerate-reply" title="<?php esc_attr_e( 'Regenerate reply with AI', 'cf7-ai-inbox' ); ?>" style="width:26px;height:26px;border:none;background:transparent;box-shadow:none;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg></div>
					</div>
					<div class="cf7-ai-inbox-field__input" id="detail-reply-body" contenteditable="true" style="border:none;border-radius:0;min-height:150px;font-family:'Inter';font-size:13px;overflow-y:auto;white-space:pre-wrap;"><?php echo wp_kses_post( $message['reply_draft'] ?: '' ); ?></div>
				</div>
			</div>
			<div style="display:flex;gap:10px;flex-wrap:wrap;">
				<button class="cf7-ai-inbox-btn--secondary" id="detail-save-draft"><?php esc_html_e( 'Save Draft', 'cf7-ai-inbox' ); ?></button>
				<div style="flex:1;"></div>
				<?php if ( $can_reply ) : ?>
				<button class="cf7-ai-inbox-btn--primary" id="open-reply-modal">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></svg>
					<?php esc_html_e( 'Send Reply', 'cf7-ai-inbox' ); ?>
				</button>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<div class="cf7-ai-inbox-card">
		<div class="cf7-ai-inbox-card__header"><h2><?php esc_html_e( 'Activity', 'cf7-ai-inbox' ); ?></h2></div>
		<div class="cf7-ai-inbox-card__body">
			<div class="cf7-ai-inbox-timeline">
				<?php if ( array() === $activities ) : ?>
					<div class="cf7-ai-inbox-timeline__item"><div class="cf7-ai-inbox-timeline__text"><?php esc_html_e( 'No activity recorded yet.', 'cf7-ai-inbox' ); ?></div></div>
				<?php else : ?>
					<?php foreach ( $activities as $cf7ai_activity ) : ?>
						<div class="cf7-ai-inbox-timeline__item">
							<div class="cf7-ai-inbox-timeline__dot <?php echo 'ai_analysis_failed' === $cf7ai_activity['event_type'] ? 'cf7-ai-inbox-timeline__dot--fail' : 'cf7-ai-inbox-timeline__dot--ok'; ?>"></div>
							<div class="cf7-ai-inbox-timeline__text"><?php echo esc_html( $cf7ai_event_labels[ $cf7ai_activity['event_type'] ] ?? $cf7ai_activity['event_type'] ); ?></div>
							<div class="cf7-ai-inbox-timeline__meta"><?php echo esc_html( \CF7AIInbox\Support\Format::time_ago( (string) $cf7ai_activity['created_at'] ) ); ?></div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
