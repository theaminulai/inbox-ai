<?php
/**
 * AI Inbox List page — submission detail screen ("Conversation" thread).
 *
 * Fully server-rendered from `$message`/`$activities` (see
 * {@see \InboxAI\Admin\Pages\InboxListPage::render_detail()}) — this is its
 * own real page load (`?page=inboxai-inbox&id=123`), not a client-side
 * screen swap over an AJAX call. Thread order is: the original submission
 * first, then every staff reply and every customer reply either side has
 * ever sent, oldest to newest — a real multi-round history, the same as any
 * email client's own conversation view — then the AI Analysis card
 * **always last**, regardless of how many messages exist above it: it's a
 * living annotation re-run on every new customer reply (see
 * {@see \InboxAI\AI\AnalysisQueue::process_reply()}), not a fixed point in
 * the conversation, so it's deliberately kept out of the chronological
 * ordering every other item gets (see where `$inboxai_reply_events` is
 * built and sorted, below).
 * All render as collapsible items in one Gmail-style conversation thread,
 * with a sidebar for customer info, submission metadata, customer mood,
 * the activity timeline, and quick actions.
 * `src/admin/componets/inbox/detail.js` wires up the thread/panel
 * collapse toggles, the retry/regenerate buttons' AJAX calls, and the quick
 * actions; `src/admin/componets/inbox/replyComposer.js` wires the composer
 * itself (rich-text toolbar, save draft, send reply).
 *
 * @var array<string, mixed>        $message    A row from
 *                                               {@see \InboxAI\Database\MessageRepository::find()}.
 * @var array<int, array<string, mixed>> $activities Rows from
 *                                               {@see \InboxAI\Database\ActivityRepository::get_for_message()}.
 * @var bool                        $can_reply  Whether the current user holds `SEND_REPLIES`.
 * @var bool                        $can_edit   Whether the current user holds `EDIT_MESSAGES`.
 * @var bool                        $can_delete Whether the current user holds `DELETE_MESSAGES`.
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$inboxai_is_failed   = 'failed' === $message['workflow_status'];
$inboxai_list_url    = \InboxAI\Admin\Menu::url( 'inboxai-inbox' );

// Every reply either side has ever sent on this submission, oldest first —
// a real multi-round history, like an actual email client, not just the
// most recent staff reply and most recent customer reply. Every
// {@see \InboxAI\Services\ReplyService::send()} call and every matched
// {@see \InboxAI\Mail\InboundMailChecker::process_one()} reply logs its own
// `reply_sent`/`customer_replied` activity row specifically so this full
// history is always reconstructable here — `$message['reply_sent_body']`/
// `reply_sent_at` only ever hold the single *most recent* staff reply (see
// {@see \InboxAI\Database\MessageRepository::set_reply_sent()}, which
// overwrites them on every send), so those columns alone can't drive this
// thread once more than one reply has gone back and forth.
$inboxai_reply_events = array_values(
	array_filter(
		$activities,
		static function ( array $inboxai_activity ): bool {
			return in_array( $inboxai_activity['event_type'], array( 'reply_sent', 'customer_replied' ), true );
		}
	)
);

// `$activities` comes back most-recent-first (see
// `ActivityRepository::get_for_message()`); the thread itself reads
// top-to-bottom oldest-first, the same direction a real email client shows
// a conversation in. `id` breaks a same-second tie deterministically since
// two events logged in the same request can share a `created_at` value down
// to the second.
usort(
	$inboxai_reply_events,
	static function ( array $a, array $b ): int {
		$by_time = strtotime( (string) $a['created_at'] ) <=> strtotime( (string) $b['created_at'] );

		return 0 !== $by_time ? $by_time : ( (int) $a['id'] <=> (int) $b['id'] );
	}
);

$inboxai_sender_name  = $message['sender_name'] ?: __( '(no name)', 'inbox-ai' );
$inboxai_sender_email = $message['sender_email'] ?: '—';

$inboxai_mail_status_labels = array(
	'pending' => array( __( 'Pending', 'inbox-ai' ), 'inboxai-status--new' ),
	'sent'    => array( __( 'Sent successfully', 'inbox-ai' ), 'inboxai-status--replied' ),
	'failed'  => array( __( 'Delivery failed', 'inbox-ai' ), 'inboxai-status--failed' ),
);
$inboxai_mail_status        = $inboxai_mail_status_labels[ $message['mail_status'] ] ?? $inboxai_mail_status_labels['pending'];

// Every posted field beyond `subject`/`message` (already shown as the
// thread's title and the customer message's own body) — whatever a form
// author added beyond CF7's own basic tags, shown inline under the
// customer's message rather than in a separate "Submitted Fields" card, so
// the whole submission reads as one message.
$inboxai_extra_fields = (array) $message['fields'];

$inboxai_current_user = wp_get_current_user();
$inboxai_staff_name   = $inboxai_current_user->display_name ?: __( 'You', 'inbox-ai' );
$inboxai_staff_seed   = $inboxai_current_user->user_email ?: (string) $inboxai_current_user->ID;

$inboxai_sparkle_icon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l2.4 6.6L21 11l-6.6 2.4L12 20l-2.4-6.6L3 11l6.6-2.4z"/></svg>';
$inboxai_chevron_icon = '<svg class="inboxai-thread-item__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 9l6 6 6-6"/></svg>';

?>
<section class="inboxai-screen inboxai-is-active" id="screen-detail" data-message-id="<?php echo (int) $message['id']; ?>" data-recipient-email="<?php echo esc_attr( $message['sender_email'] ); ?>" data-activity-count="<?php echo (int) count( $activities ); ?>">
	<div class="inboxai-breadcrumb">
		<a href="<?php echo esc_url( $inboxai_list_url ); ?>"><?php esc_html_e( 'AI Inbox', 'inbox-ai' ); ?></a> <span>/</span> <span><?php echo esc_html( '#' . $message['id'] ); ?></span>
	</div>

	<div class="inboxai-card inboxai-detail-header">
		<div>
			<h1><?php echo esc_html( $message['subject'] ?: __( '(no subject)', 'inbox-ai' ) ); ?></h1>
			<div class="inboxai-detail-header__meta">
				<?php
				printf(
					/* translators: 1: sender name, 2: sender email, 3: form title */
					esc_html__( 'From %1$s · %2$s · %3$s', 'inbox-ai' ),
					'<b>' . esc_html( $inboxai_sender_name ) . '</b>',
					esc_html( $inboxai_sender_email ),
					esc_html( $message['form_title'] )
				);
				?>
			</div>
		</div>
		<div class="inboxai-detail-header__badges">
			<span id="detail-priority-badge"><?php echo \InboxAI\Support\Format::priority_badge_html( (string) $message['priority'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Format::priority_badge_html() escapes internally; see includes/Support/Format.php. ?></span>
			<span id="detail-status-badge"><?php echo \InboxAI\Support\Format::status_badge_html( (string) $message['workflow_status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see Format::status_badge_html(), escapes internally. ?></span>
			<span class="inboxai-detail-header__date"><?php echo esc_html( \InboxAI\Support\Format::format_datetime( (string) $message['created_at'] ) ); ?></span>
		</div>
	</div>

	<div class="inboxai-detail-grid">

		<div>
			<div class="inboxai-section-label"><?php esc_html_e( 'Conversation', 'inbox-ai' ); ?></div>

			<div class="inboxai-thread" id="detail-thread">

				<div class="inboxai-thread-item inboxai-thread-item--customer" data-role="customer">
					<div class="inboxai-thread-item__rail">
						<div class="inboxai-avatar inboxai-avatar--lg" style="background:<?php echo esc_attr( \InboxAI\Support\Format::avatar_color( (string) $message['sender_email'] ) ); ?>;"><?php echo esc_html( \InboxAI\Support\Format::avatar_initials( (string) $message['sender_name'] ) ); ?></div>
						<div class="inboxai-thread-item__spine"></div>
					</div>
					<div class="inboxai-card">
						<div class="inboxai-thread-item__head" data-toggle-thread-item>
							<div class="inboxai-thread-item__head-left">
								<span class="inboxai-thread-item__sender"><?php echo esc_html( $inboxai_sender_name ); ?></span>
								<span class="inboxai-role-tag inboxai-role-tag--customer"><?php esc_html_e( 'Customer', 'inbox-ai' ); ?></span>
								<span class="inboxai-thread-item__sub"><?php echo esc_html( $inboxai_sender_email ); ?></span>
							</div>
							<div class="inboxai-thread-item__head-right">
								<span class="inboxai-thread-item__time"><?php echo esc_html( \InboxAI\Support\Format::time_ago( (string) $message['created_at'] ) ); ?></span>
								<?php echo $inboxai_chevron_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted markup defined above, not user input. ?>
							</div>
						</div>
						<div class="inboxai-thread-item__body">
							<p class="inboxai-thread-item__text"><?php echo esc_html( $message['message'] ?: __( '(no message)', 'inbox-ai' ) ); ?></p>
							<?php if ( array() !== $inboxai_extra_fields ) : ?>
								<div class="inboxai-thread-item__fields">
									<?php foreach ( $inboxai_extra_fields as $inboxai_field_key => $inboxai_field_value ) : ?>
										<div class="inboxai-thread-item__field"><b><?php echo esc_html( (string) $inboxai_field_key ); ?></b><?php echo esc_html( (string) $inboxai_field_value ); ?></div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
							<div class="inboxai-thread-item__meta-row">
								<?php
								printf(
									/* translators: 1: form title, 2: source page URL, 3: submission id */
									esc_html__( 'Submitted via %1$s · %2$s · Submission #%3$d', 'inbox-ai' ),
									esc_html( $message['form_title'] ?: '—' ),
									esc_html( $message['meta']['source_page'] ?? '—' ),
									(int) $message['id']
								);
								?>
							</div>
						</div>
					</div>
				</div>

				<?php foreach ( $inboxai_reply_events as $inboxai_event ) : ?>
					<?php if ( 'reply_sent' === $inboxai_event['event_type'] ) : ?>
						<?php
						// Attributed to whoever actually sent it, not whoever's
						// currently viewing the page — on a multi-admin site an
						// older reply may well have been sent by a different user
						// than the one looking at this thread now. `user_id` is 0
						// for a send with no logged-in acting user (shouldn't
						// normally happen for `reply_sent`, but falls back to the
						// current viewer the same way this thread item always did
						// before per-event attribution existed).
						$inboxai_reply_user = $inboxai_event['user_id'] > 0 ? get_userdata( $inboxai_event['user_id'] ) : false;
						$inboxai_reply_name = $inboxai_reply_user ? ( $inboxai_reply_user->display_name ?: $inboxai_staff_name ) : $inboxai_staff_name;
						$inboxai_reply_seed = $inboxai_reply_user ? ( $inboxai_reply_user->user_email ?: (string) $inboxai_reply_user->ID ) : $inboxai_staff_seed;
						?>
						<div class="inboxai-thread-item inboxai-thread-item--staff" data-role="staff">
							<div class="inboxai-thread-item__rail">
								<div class="inboxai-avatar inboxai-avatar--lg" style="background:<?php echo esc_attr( \InboxAI\Support\Format::avatar_color( $inboxai_reply_seed ) ); ?>;"><?php echo esc_html( \InboxAI\Support\Format::avatar_initials( $inboxai_reply_name ) ); ?></div>
								<div class="inboxai-thread-item__spine"></div>
							</div>
							<div class="inboxai-card">
								<div class="inboxai-thread-item__head" data-toggle-thread-item>
									<div class="inboxai-thread-item__head-left">
										<span class="inboxai-thread-item__sender"><?php echo esc_html( $inboxai_reply_name ); ?></span>
										<span class="inboxai-role-tag inboxai-role-tag--staff"><?php esc_html_e( 'Sent', 'inbox-ai' ); ?></span>
									</div>
									<div class="inboxai-thread-item__head-right">
										<span class="inboxai-thread-item__time"><?php echo esc_html( \InboxAI\Support\Format::time_ago( (string) $inboxai_event['created_at'] ) ); ?></span>
										<?php echo $inboxai_chevron_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted markup defined above. ?>
									</div>
								</div>
								<div class="inboxai-thread-item__body">
									<p class="inboxai-thread-item__text"><?php echo esc_html( $inboxai_event['event_data']['body'] ?? '—' ); ?></p>
								</div>
							</div>
						</div>
					<?php else : ?>
						<div class="inboxai-thread-item inboxai-thread-item--customer" data-role="customer">
							<div class="inboxai-thread-item__rail">
								<div class="inboxai-avatar inboxai-avatar--lg" style="background:<?php echo esc_attr( \InboxAI\Support\Format::avatar_color( (string) $message['sender_email'] ) ); ?>;"><?php echo esc_html( \InboxAI\Support\Format::avatar_initials( (string) $message['sender_name'] ) ); ?></div>
								<div class="inboxai-thread-item__spine"></div>
							</div>
							<div class="inboxai-card">
								<div class="inboxai-thread-item__head" data-toggle-thread-item>
									<div class="inboxai-thread-item__head-left">
										<span class="inboxai-thread-item__sender"><?php echo esc_html( $inboxai_sender_name ); ?></span>
										<span class="inboxai-role-tag inboxai-role-tag--customer"><?php esc_html_e( 'Customer · Replied by email', 'inbox-ai' ); ?></span>
									</div>
									<div class="inboxai-thread-item__head-right">
										<span class="inboxai-thread-item__time"><?php echo esc_html( \InboxAI\Support\Format::time_ago( (string) $inboxai_event['created_at'] ) ); ?></span>
										<?php echo $inboxai_chevron_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted markup defined above. ?>
									</div>
								</div>
								<div class="inboxai-thread-item__body">
									<p class="inboxai-thread-item__text"><?php echo esc_html( $inboxai_event['event_data']['body'] ?? '' ); ?></p>
									<div class="inboxai-thread-item__meta-row">
										<?php esc_html_e( 'Received by Inbox AI\'s inbound mail check — see Settings → Notifications.', 'inbox-ai' ); ?>
									</div>
								</div>
							</div>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>

				<div class="inboxai-thread-item inboxai-thread-item--ai" data-role="ai">
					<div class="inboxai-thread-item__rail">
						<div class="inboxai-avatar inboxai-avatar--lg inboxai-avatar--ai"><?php echo $inboxai_sparkle_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted markup defined above. ?></div>
						<div class="inboxai-thread-item__spine"></div>
					</div>
					<div class="inboxai-card">
						<div class="inboxai-thread-item__head" data-toggle-thread-item>
							<div class="inboxai-thread-item__head-left">
								<span class="inboxai-thread-item__sender"><?php esc_html_e( 'AI Analysis', 'inbox-ai' ); ?></span>
								<span class="inboxai-role-tag inboxai-role-tag--ai"><?php esc_html_e( 'Automated · internal', 'inbox-ai' ); ?></span>
							</div>
							<div class="inboxai-thread-item__head-right">
								<span class="inboxai-thread-item__time" id="detail-ai-timestamp"><?php echo esc_html( \InboxAI\Support\Format::time_ago( (string) $message['updated_at'] ) ); ?></span>
								<?php echo $inboxai_chevron_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted markup defined above. ?>
							</div>
						</div>
						<div class="inboxai-thread-item__body" id="detail-ai-body">
							<?php \InboxAI\Support\Template::render( 'inbox/detail-ai-body', array( 'message' => $message, 'can_edit' => $can_edit ) ); ?>
						</div>
					</div>
				</div>

			</div>

			<?php if ( $can_edit && ! $inboxai_is_failed ) : ?>
			<div class="inboxai-section-label" style="margin-top:22px;"><?php esc_html_e( 'Reply', 'inbox-ai' ); ?></div>
			<div class="inboxai-composer-wrap" id="reply-composer">
				<div>
					<div class="inboxai-avatar inboxai-avatar--lg" style="background:<?php echo esc_attr( \InboxAI\Support\Format::avatar_color( $inboxai_staff_seed ) ); ?>;"><?php echo esc_html( \InboxAI\Support\Format::avatar_initials( $inboxai_staff_name ) ); ?></div>
				</div>
				<div class="inboxai-composer<?php echo $message['reply_draft'] ? ' inboxai-is-open' : ''; ?>" id="detail-composer">
					<div class="inboxai-composer__collapsed" id="detail-composer-collapsed">
						<?php
						printf(
							/* translators: %s: sender name */
							esc_html__( 'Reply to %s…', 'inbox-ai' ),
							esc_html( $inboxai_sender_name )
						);
						?>
					</div>
					<div class="inboxai-composer__open" id="detail-composer-open">
						<div class="inboxai-field-row">
							<div class="inboxai-field"><label><?php esc_html_e( 'Recipient', 'inbox-ai' ); ?></label><input class="inboxai-field__input" id="detail-recipient" value="<?php echo esc_attr( $message['sender_email'] ); ?>" readonly></div>
							<div class="inboxai-field"><label><?php esc_html_e( 'Drafted By', 'inbox-ai' ); ?></label><input class="inboxai-field__input" id="detail-provider-info" value="<?php echo esc_attr( $message['ai_provider'] ? $message['ai_provider'] . ' · ' . $message['ai_model'] : __( 'Not yet drafted', 'inbox-ai' ) ); ?>" readonly></div>
						</div>
						<div class="inboxai-field"><label><?php esc_html_e( 'Subject', 'inbox-ai' ); ?></label><input class="inboxai-field__input" id="detail-subject" value="<?php echo esc_attr( $message['reply_subject'] ?: ( 'Re: ' . $message['subject'] ) ); ?>"></div>
						<div class="inboxai-field" style="margin-bottom:14px;">
							<label><?php esc_html_e( 'Message', 'inbox-ai' ); ?></label>
							<div class="inboxai-composer-field">
								<div class="inboxai-composer-toolbar">
									<button type="button" class="inboxai-composer-toolbar__btn" id="fmt-bold" title="<?php esc_attr_e( 'Bold', 'inbox-ai' ); ?>" style="font-weight:800;">B</button>
									<button type="button" class="inboxai-composer-toolbar__btn" id="fmt-italic" title="<?php esc_attr_e( 'Italic', 'inbox-ai' ); ?>" style="font-style:italic;">I</button>
									<button type="button" class="inboxai-composer-toolbar__btn underline" id="fmt-underline" title="<?php esc_attr_e( 'Underline', 'inbox-ai' ); ?>">U</button>
									<button type="button" class="inboxai-composer-toolbar__btn" id="fmt-list" title="<?php esc_attr_e( 'Bulleted list', 'inbox-ai' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg></button>
									<div class="inboxai-composer-toolbar__divider"></div>
									<button type="button" class="inboxai-composer-toolbar__btn" id="detail-regenerate-reply" title="<?php esc_attr_e( 'Regenerate reply with AI', 'inbox-ai' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg></button>
								</div>
								<div class="inboxai-composer-body" id="detail-reply-body" contenteditable="true"><?php echo wp_kses_post( $message['reply_draft'] ?: '' ); ?></div>
							</div>
						</div>
						<div class="inboxai-composer-footer">
							<div class="inboxai-composer-footer__actions">
								<?php if ( $can_reply ) : ?>
								<button class="inboxai-btn--primary" id="open-reply-modal">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></svg>
									<?php esc_html_e( 'Send reply', 'inbox-ai' ); ?>
								</button>
								<?php endif; ?>
								<button class="inboxai-btn--secondary" id="detail-save-draft"><?php esc_html_e( 'Save draft', 'inbox-ai' ); ?></button>
							</div>
							<span class="inboxai-composer-autosave" id="detail-draft-status"></span>
						</div>
					</div>
				</div>
			</div>
			<?php endif; ?>

		</div>

		<div class="inboxai-detail-sidebar">

			<div class="inboxai-card inboxai-detail-panel">
				<div class="inboxai-detail-panel__head" data-toggle-panel>
					<h3><?php esc_html_e( 'Customer', 'inbox-ai' ); ?></h3>
					<svg class="inboxai-detail-panel__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 9l6 6 6-6"/></svg>
				</div>
				<div class="inboxai-detail-panel__body">
					<div class="inboxai-detail-customer-row">
						<div class="inboxai-avatar" style="background:<?php echo esc_attr( \InboxAI\Support\Format::avatar_color( (string) $message['sender_email'] ) ); ?>;"><?php echo esc_html( \InboxAI\Support\Format::avatar_initials( (string) $message['sender_name'] ) ); ?></div>
						<div>
							<div class="inboxai-detail-customer-row__name"><?php echo esc_html( $inboxai_sender_name ); ?></div>
							<div class="inboxai-detail-customer-row__email"><?php echo esc_html( $inboxai_sender_email ); ?></div>
						</div>
					</div>
					<div class="inboxai-kv">
						<div class="inboxai-kv-item"><div class="inboxai-kv-item__k"><?php esc_html_e( 'Company', 'inbox-ai' ); ?></div><div class="inboxai-kv-item__v"><?php echo esc_html( $message['meta']['company'] ?? '—' ); ?></div></div>
						<div class="inboxai-kv-item"><div class="inboxai-kv-item__k"><?php esc_html_e( 'Phone', 'inbox-ai' ); ?></div><div class="inboxai-kv-item__v" style="font-family:var(--mono);"><?php echo esc_html( $message['meta']['phone'] ?? '—' ); ?></div></div>
					</div>
				</div>
			</div>

			<div class="inboxai-card inboxai-detail-panel">
				<div class="inboxai-detail-panel__head" data-toggle-panel>
					<h3><?php esc_html_e( 'Submission details', 'inbox-ai' ); ?></h3>
					<svg class="inboxai-detail-panel__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 9l6 6 6-6"/></svg>
				</div>
				<div class="inboxai-detail-panel__body">
					<div class="inboxai-kv">
						<div class="inboxai-kv-item"><div class="inboxai-kv-item__k"><?php esc_html_e( 'Submission ID', 'inbox-ai' ); ?></div><div class="inboxai-kv-item__v" style="font-family:var(--mono);"><?php echo esc_html( '#' . $message['id'] ); ?></div></div>
						<div class="inboxai-kv-item"><div class="inboxai-kv-item__k"><?php esc_html_e( 'Form', 'inbox-ai' ); ?></div><div class="inboxai-kv-item__v"><?php echo esc_html( $message['form_title'] ?: '—' ); ?></div></div>
						<div class="inboxai-kv-item"><div class="inboxai-kv-item__k"><?php esc_html_e( 'Source category', 'inbox-ai' ); ?></div><div class="inboxai-kv-item__v"><?php echo esc_html( $message['source_category'] ?: '—' ); ?></div></div>
						<div class="inboxai-kv-item"><div class="inboxai-kv-item__k"><?php esc_html_e( 'Source page', 'inbox-ai' ); ?></div><div class="inboxai-kv-item__v" style="word-break:break-all;"><?php echo esc_html( $message['meta']['source_page'] ?? '—' ); ?></div></div>
						<div class="inboxai-kv-item"><div class="inboxai-kv-item__k"><?php esc_html_e( 'IP address', 'inbox-ai' ); ?></div><div class="inboxai-kv-item__v inboxai-is-muted" style="font-family:var(--mono);"><?php echo esc_html( $message['meta']['ip'] ?? '—' ); ?></div></div>
						<div class="inboxai-kv-item"><div class="inboxai-kv-item__k"><?php esc_html_e( 'Submitted', 'inbox-ai' ); ?></div><div class="inboxai-kv-item__v"><?php echo esc_html( \InboxAI\Support\Format::format_datetime( (string) $message['created_at'] ) ); ?></div></div>
						<div class="inboxai-kv-item"><div class="inboxai-kv-item__k"><?php esc_html_e( 'Mail status', 'inbox-ai' ); ?></div><div class="inboxai-kv-item__v"><span class="inboxai-status <?php echo esc_attr( $inboxai_mail_status[1] ); ?>"><?php echo esc_html( $inboxai_mail_status[0] ); ?></span></div></div>
					</div>
				</div>
			</div>

			<?php if ( $can_edit ) : ?>
			<div class="inboxai-card inboxai-detail-panel">
				<div class="inboxai-detail-panel__head" data-toggle-panel>
					<h3><?php esc_html_e( 'Quick actions', 'inbox-ai' ); ?></h3>
					<svg class="inboxai-detail-panel__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 9l6 6 6-6"/></svg>
				</div>
				<div class="inboxai-detail-panel__body">
					<div class="inboxai-quick-actions">
						<?php if ( 'reviewed' !== $message['workflow_status'] && 'replied' !== $message['workflow_status'] ) : ?>
						<button class="inboxai-btn--secondary" id="detail-quick-reviewed"><?php esc_html_e( 'Mark as reviewed', 'inbox-ai' ); ?></button>
						<?php endif; ?>
						<?php if ( 'archived' !== $message['workflow_status'] ) : ?>
						<button class="inboxai-btn--secondary" id="detail-quick-archive"><?php esc_html_e( 'Archive', 'inbox-ai' ); ?></button>
						<?php endif; ?>
						<?php if ( $can_delete ) : ?>
						<button class="inboxai-btn--secondary" id="detail-quick-delete" style="color:var(--urgent);"><?php esc_html_e( 'Delete', 'inbox-ai' ); ?></button>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<div class="inboxai-card inboxai-detail-panel">
				<div class="inboxai-detail-panel__head" data-toggle-panel>
					<h3><?php esc_html_e( 'Customer Mood', 'inbox-ai' ); ?></h3>
					<svg class="inboxai-detail-panel__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 9l6 6 6-6"/></svg>
				</div>
				<div class="inboxai-detail-panel__body" id="detail-mood-body">
					<?php \InboxAI\Support\Template::render( 'inbox/detail-mood-panel', array( 'message' => $message, 'activities' => $activities ) ); ?>
				</div>
			</div>

			<div class="inboxai-card inboxai-detail-panel">
				<div class="inboxai-detail-panel__head" data-toggle-panel>
					<h3><?php esc_html_e( 'Activity', 'inbox-ai' ); ?></h3>
					<svg class="inboxai-detail-panel__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 9l6 6 6-6"/></svg>
				</div>
				<div class="inboxai-detail-panel__body">
					<div class="inboxai-timeline" id="detail-timeline">
						<?php \InboxAI\Support\Template::render( 'inbox/detail-timeline', array( 'activities' => $activities ) ); ?>
					</div>
				</div>
			</div>

		</div>

	</div>
</section>
