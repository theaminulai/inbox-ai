<?php
/**
 * AI Inbox List page shell.
 *
 * Routes to exactly one of `list.php` (the message table) or `detail.php`
 * (one submission, including its "AI analysis failed" state) per request,
 * decided server-side by
 * {@see \InboxAI\Admin\Pages\InboxListPage::render()} — there is a real
 * page load between the list and a submission's detail, not a client-side
 * screen swap, so both views' data is always rendered fresh from the
 * database rather than held in the browser. `src/admin/componets/inbox/*.js`
 * only wires up interactivity on top of whichever view actually rendered
 * (the row "more actions" menu, filter auto-submit, and the reply composer).
 *
 * Expects, via {@see \InboxAI\Support\Template::render()}:
 *
 * @var string $view       One of `list`, `detail`, `not-found`.
 * @var bool   $can_reply  Whether the current user holds `SEND_REPLIES`.
 * @var bool   $can_edit   Whether the current user holds `EDIT_MESSAGES`.
 * @var bool   $can_delete Whether the current user holds `DELETE_MESSAGES`.
 *
 * List view also gets: $messages, $total, $page, $per_page, $filters,
 * $form_titles, $categories (see `list.php`).
 *
 * Detail view also gets: $message, $activities (see `detail.php`).
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap inboxai-wrap">
	<div class="inboxai-main" id="main" data-page="inbox" data-view="<?php echo esc_attr( $view ); ?>"
		data-can-reply="<?php echo esc_attr( $can_reply ? '1' : '0' ); ?>"
		data-can-edit="<?php echo esc_attr( $can_edit ? '1' : '0' ); ?>"
		data-can-delete="<?php echo esc_attr( $can_delete ? '1' : '0' ); ?>">

		<?php
		if ( 'detail' === $view ) {
			\InboxAI\Support\Template::render(
				'inbox/detail',
				array(
					'message'    => $message,
					'activities' => $activities,
					'can_reply'  => $can_reply,
					'can_edit'   => $can_edit,
				)
			);
		} elseif ( 'not-found' === $view ) {
			?>
			<div class="inboxai-state">
				<h2><?php esc_html_e( 'Submission not found', 'inbox-ai' ); ?></h2>
				<p><?php esc_html_e( 'This submission may have been deleted.', 'inbox-ai' ); ?></p>
				<div class="inboxai-state__actions">
					<a class="inboxai-btn--primary" href="<?php echo esc_url( \InboxAI\Admin\Menu::url( 'inboxai-inbox' ) ); ?>"><?php esc_html_e( 'Back to AI Inbox', 'inbox-ai' ); ?></a>
				</div>
			</div>
			<?php
		} else {
			\InboxAI\Support\Template::render(
				'inbox/list',
				array(
					'messages'    => $messages,
					'total'       => $total,
					'page'        => $page,
					'per_page'    => $per_page,
					'filters'     => $filters,
					'form_titles' => $form_titles,
					'categories'  => $categories,
					'can_reply'   => $can_reply,
					'can_edit'    => $can_edit,
					'can_delete'  => $can_delete,
				)
			);
		}
		?>

	</div>
</div>

<?php if ( 'detail' === $view && $can_reply ) : ?>
<div class="inboxai-modal" id="reply-modal-overlay" style="display:none;">
	<div class="inboxai-modal__box">
		<div class="inboxai-modal__header">
			<h3><?php esc_html_e( 'Send this reply?', 'inbox-ai' ); ?></h3>
			<div class="inboxai-btn--icon" data-close-modal="reply-modal-overlay" style="width:26px;height:26px;">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
			</div>
		</div>
		<div class="inboxai-modal__body" id="modal-body-text">
			<?php
			printf(
				/* translators: %s: "Replied" */
				esc_html__( 'This reply will be emailed and the message status will change to %s. This can\'t be undone.', 'inbox-ai' ),
				'<b style="color:var(--text-primary);">' . esc_html__( 'Replied', 'inbox-ai' ) . '</b>'
			);
			?>
			<div class="inboxai-modal__preview" id="modal-preview-text"></div>
		</div>
		<div class="inboxai-modal__footer">
			<button class="inboxai-btn--secondary" data-close-modal="reply-modal-overlay"><?php esc_html_e( 'Cancel', 'inbox-ai' ); ?></button>
			<button class="inboxai-btn--primary" id="modal-confirm-send">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:13px;height:13px;"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></svg>
				<?php esc_html_e( 'Confirm & Send', 'inbox-ai' ); ?>
			</button>
		</div>
	</div>
</div>
<?php endif; ?>

<?php if ( 'list' === $view ) : ?>
<div class="inboxai-row-menu" id="row-menu" data-open-for=""></div>
<?php endif; ?>

<div id="toast-container"></div>
