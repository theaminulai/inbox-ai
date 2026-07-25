<?php
/**
 * AI Inbox List page shell.
 *
 * Routes to exactly one of `list.php` (the message table) or `detail.php`
 * (one submission, including its "AI analysis failed" state) per request,
 * decided server-side by
 * {@see \CF7AIInbox\Admin\Pages\InboxListPage::render()} — there is a real
 * page load between the list and a submission's detail, not a client-side
 * screen swap, so both views' data is always rendered fresh from the
 * database rather than held in the browser. `src/admin/componets/inbox/*.js`
 * only wires up interactivity on top of whichever view actually rendered
 * (the row "more actions" menu, filter auto-submit, and the reply composer).
 *
 * Expects, via {@see \CF7AIInbox\Support\Template::render()}:
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
 * @package CF7AIInbox\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap cf7-ai-inbox-wrap">
	<div class="cf7-ai-inbox-main" id="main" data-page="inbox" data-view="<?php echo esc_attr( $view ); ?>"
		data-can-reply="<?php echo esc_attr( $can_reply ? '1' : '0' ); ?>"
		data-can-edit="<?php echo esc_attr( $can_edit ? '1' : '0' ); ?>"
		data-can-delete="<?php echo esc_attr( $can_delete ? '1' : '0' ); ?>">

		<?php
		if ( 'detail' === $view ) {
			\CF7AIInbox\Support\Template::render(
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
			<div class="cf7-ai-inbox-state">
				<h2><?php esc_html_e( 'Submission not found', 'cf7-ai-inbox' ); ?></h2>
				<p><?php esc_html_e( 'This submission may have been deleted.', 'cf7-ai-inbox' ); ?></p>
				<div class="cf7-ai-inbox-state__actions">
					<a class="cf7-ai-inbox-btn--primary" href="<?php echo esc_url( \CF7AIInbox\Admin\Menu::url( 'cf7ai-inbox' ) ); ?>"><?php esc_html_e( 'Back to AI Inbox', 'cf7-ai-inbox' ); ?></a>
				</div>
			</div>
			<?php
		} else {
			\CF7AIInbox\Support\Template::render(
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
<div class="cf7-ai-inbox-modal" id="reply-modal-overlay" style="display:none;">
	<div class="cf7-ai-inbox-modal__box">
		<div class="cf7-ai-inbox-modal__header">
			<h3><?php esc_html_e( 'Send this reply?', 'cf7-ai-inbox' ); ?></h3>
			<div class="cf7-ai-inbox-btn--icon" data-close-modal="reply-modal-overlay" style="width:26px;height:26px;">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
			</div>
		</div>
		<div class="cf7-ai-inbox-modal__body" id="modal-body-text">
			<?php
			printf(
				/* translators: %s: "Replied" */
				esc_html__( 'This reply will be emailed and the message status will change to %s. This can\'t be undone.', 'cf7-ai-inbox' ),
				'<b style="color:var(--text-primary);">' . esc_html__( 'Replied', 'cf7-ai-inbox' ) . '</b>'
			);
			?>
			<div class="cf7-ai-inbox-modal__preview" id="modal-preview-text"></div>
		</div>
		<div class="cf7-ai-inbox-modal__footer">
			<button class="cf7-ai-inbox-btn--secondary" data-close-modal="reply-modal-overlay"><?php esc_html_e( 'Cancel', 'cf7-ai-inbox' ); ?></button>
			<button class="cf7-ai-inbox-btn--primary" id="modal-confirm-send">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:13px;height:13px;"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></svg>
				<?php esc_html_e( 'Confirm & Send', 'cf7-ai-inbox' ); ?>
			</button>
		</div>
	</div>
</div>
<?php endif; ?>

<?php if ( 'list' === $view ) : ?>
<div class="cf7-ai-inbox-row-menu" id="row-menu" data-open-for=""></div>
<?php endif; ?>

<div id="toast-container"></div>
