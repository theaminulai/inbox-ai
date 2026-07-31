<?php
/**
 * Contacts List page — the page-header, filter toolbar, and contacts table.
 *
 * Fully server-rendered: `$contacts` is already the current page's rows
 * (from {@see \InboxAI\Database\MessageRepository::get_contacts()}), the
 * filter controls are a plain GET form
 * (`src/admin/componets/contacts/list.js` only auto-submits it on change),
 * and pagination reuses {@see \InboxAI\Support\Format::pagination_links()} —
 * the same approach as `includes/Templates/inbox/list.php`.
 *
 * @var array<int, array<string, mixed>> $contacts   Current page's rows —
 *                                                     each one a message row
 *                                                     (that sender's most
 *                                                     recent), plus
 *                                                     `message_count`/
 *                                                     `replied_count`.
 * @var int                              $total      Total matching contacts.
 * @var int                              $page       1-indexed current page.
 * @var int                              $per_page   Rows per page.
 * @var array<string, mixed>             $filters    Current filter values
 *                                                    (see `ContactsListPage::get_filters_from_request()`).
 * @var string[]                         $categories Every AI category that exists site-wide.
 * @var bool                             $can_delete Whether the current user holds `DELETE_MESSAGES`
 *                                                    — gates the checkbox column and bulk-actions bar,
 *                                                    same as `includes/Templates/inbox/list.php`.
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$inboxai_has_active_filters = array() !== array_filter(
	$filters,
	static function ( $value ) {
		return '' !== $value && null !== $value;
	}
);

$inboxai_clear_url = add_query_arg( array( 'page' => 'inboxai-contacts' ), admin_url( 'admin.php' ) );

/**
 * @param string $email
 * @return string
 */
$inboxai_inbox_search_url = static function ( string $email ) {
	return \InboxAI\Admin\Menu::url( 'inboxai-inbox' ) . '&search=' . rawurlencode( $email );
};

?>
<div class="inboxai-page-header">
	<div>
		<h1><?php esc_html_e( 'Contacts', 'inbox-ai' ); ?></h1>
		<p><?php esc_html_e( 'Everyone who has submitted a Contact Form 7 message, grouped by sender. Click a contact to see their messages.', 'inbox-ai' ); ?></p>
	</div>
	<div class="inboxai-page-header__controls">
		<button class="inboxai-btn--secondary" id="contacts-export-btn">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;vertical-align:-2px;margin-right:5px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
			<?php esc_html_e( 'Export', 'inbox-ai' ); ?>
		</button>
	</div>
</div>

<?php if ( 0 === $total && ! $inboxai_has_active_filters ) : ?>
	<div class="inboxai-state">
		<svg width="120" height="90" viewBox="0 0 120 90" fill="none">
			<rect x="10" y="20" width="100" height="60" rx="8" fill="#EEF1FF" stroke="#D8DFFE" stroke-width="2"/>
			<path d="M10 30l50 30 50-30" stroke="#3A5CF6" stroke-width="2" fill="none"/>
			<circle cx="94" cy="18" r="14" fill="#3A5CF6"/><path d="M88 18h12M94 12v12" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>
		</svg>
		<h2><?php esc_html_e( 'No contacts yet', 'inbox-ai' ); ?></h2>
		<p><?php esc_html_e( 'Contacts will appear here once a monitored form receives its first submission. Turn a form on in General Settings to start receiving messages.', 'inbox-ai' ); ?></p>
		<div class="inboxai-state__actions">
			<a class="inboxai-btn--primary" href="<?php echo esc_url( \InboxAI\Admin\Menu::url( 'inboxai-settings' ) . '&tab=general-settings' ); ?>"><?php esc_html_e( 'Select Forms', 'inbox-ai' ); ?></a>
			<a class="inboxai-btn--secondary" href="<?php echo esc_url( \InboxAI\Admin\Menu::url( 'inboxai-inbox' ) ); ?>"><?php esc_html_e( 'View AI Inbox', 'inbox-ai' ); ?></a>
		</div>
	</div>
<?php else : ?>
	<div class="inboxai-card">
		<form class="inboxai-table__toolbar" id="contacts-filter-form" method="get">
			<input type="hidden" name="page" value="inboxai-contacts">
			<?php if ( $can_delete ) : ?>
				<div class="inboxai-bulk-bar" id="contacts-bulk-bar">
					<select class="inboxai-filter-select inboxai-bulk-select" id="contacts-bulk-select">
						<option value=""><?php esc_html_e( 'Bulk actions', 'inbox-ai' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete', 'inbox-ai' ); ?></option>
					</select>
					<button type="button" class="inboxai-btn--secondary inboxai-btn--tertiary inboxai-bulk-apply"><?php esc_html_e( 'Apply', 'inbox-ai' ); ?></button>
				</div>
			<?php endif; ?>
			<div class="inboxai-search">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
				<input type="text" name="search" id="contacts-search" autocomplete="off" data-1p-ignore data-lpignore="true" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search contacts…', 'inbox-ai' ); ?>">
			</div>
			<select class="inboxai-filter-select" id="contacts-filter-category" name="category">
				<option value=""><?php esc_html_e( 'All categories', 'inbox-ai' ); ?></option>
				<?php foreach ( $categories as $inboxai_category_name ) : ?>
					<option value="<?php echo esc_attr( $inboxai_category_name ); ?>" <?php selected( $filters['category'], $inboxai_category_name ); ?>><?php echo esc_html( $inboxai_category_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<select class="inboxai-filter-select" id="contacts-filter-priority" name="priority">
				<option value=""><?php esc_html_e( 'All priorities', 'inbox-ai' ); ?></option>
				<?php
				$inboxai_priority_options = array(
					'urgent' => __( 'Urgent', 'inbox-ai' ),
					'high'   => __( 'High', 'inbox-ai' ),
					'normal' => __( 'Normal', 'inbox-ai' ),
					'low'    => __( 'Low', 'inbox-ai' ),
				);
				foreach ( $inboxai_priority_options as $inboxai_priority_value => $inboxai_priority_label ) :
					?>
					<option value="<?php echo esc_attr( $inboxai_priority_value ); ?>" <?php selected( $filters['priority'], $inboxai_priority_value ); ?>><?php echo esc_html( $inboxai_priority_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="inboxai-btn--secondary inboxai-btn--tertiary"><?php esc_html_e( 'Filter', 'inbox-ai' ); ?></button>
			<?php if ( $inboxai_has_active_filters ) : ?>
				<a class="inboxai-btn--secondary inboxai-btn--tertiary" href="<?php echo esc_url( $inboxai_clear_url ); ?>"><?php esc_html_e( 'Clear filters', 'inbox-ai' ); ?></a>
			<?php endif; ?>
		</form>
		<?php if ( 0 === $total ) : ?>
			<div class="inboxai-grid-table__cell inboxai-grid-table__cell--empty">
				<?php esc_html_e( 'No contacts match your filters.', 'inbox-ai' ); ?>
				<a class="inboxai-btn--secondary" href="<?php echo esc_url( $inboxai_clear_url ); ?>"><?php esc_html_e( 'Clear filters', 'inbox-ai' ); ?></a>
			</div>
		<?php else : ?>
			<div style="overflow-x:auto;">
				<div class="inboxai-grid-table inboxai-grid-table--contacts<?php echo $can_delete ? ' inboxai-grid-table--with-checkbox' : ''; ?>" role="table">
					<div class="inboxai-grid-table__row inboxai-grid-table__row--head" role="row">
						<?php if ( $can_delete ) : ?>
							<div class="inboxai-grid-table__cell inboxai-grid-table__cell--checkbox" role="columnheader">
								<input type="checkbox" id="contacts-select-all" aria-label="<?php esc_attr_e( 'Select all', 'inbox-ai' ); ?>">
							</div>
						<?php endif; ?>
						<div class="inboxai-grid-table__cell" role="columnheader"><?php esc_html_e( 'Contact', 'inbox-ai' ); ?><span class="inboxai-selected-count" data-bulk-count></span></div>
						<div class="inboxai-grid-table__cell" role="columnheader"><?php esc_html_e( 'Email', 'inbox-ai' ); ?></div>
						<div class="inboxai-grid-table__cell" role="columnheader"><?php esc_html_e( 'Category', 'inbox-ai' ); ?></div>
						<div class="inboxai-grid-table__cell" role="columnheader"><?php esc_html_e( 'Priority', 'inbox-ai' ); ?></div>
						<div class="inboxai-grid-table__cell" role="columnheader"><?php esc_html_e( 'Messages', 'inbox-ai' ); ?></div>
						<div class="inboxai-grid-table__cell" role="columnheader"><?php esc_html_e( 'Replied', 'inbox-ai' ); ?></div>
						<div class="inboxai-grid-table__cell" role="columnheader"><?php esc_html_e( 'Last Contact', 'inbox-ai' ); ?></div>
						<div class="inboxai-grid-table__cell" role="columnheader"></div>
					</div>
					<div id="contacts-table-body" class="inboxai-grid-table__body" role="rowgroup">
						<?php foreach ( $contacts as $inboxai_c ) : ?>
							<?php
							$inboxai_name = $inboxai_c['sender_name'] ?: __( '(no name)', 'inbox-ai' );
							$inboxai_url  = $inboxai_inbox_search_url( (string) $inboxai_c['sender_email'] );
							?>
							<div class="inboxai-grid-table__row" role="row">
								<?php if ( $can_delete ) : ?>
									<div class="inboxai-grid-table__cell inboxai-grid-table__cell--checkbox" role="cell">
										<input type="checkbox" class="inboxai-bulk-checkbox" data-email="<?php echo esc_attr( $inboxai_c['sender_email'] ); ?>" aria-label="<?php esc_attr_e( 'Select this contact', 'inbox-ai' ); ?>">
									</div>
								<?php endif; ?>
								<div class="inboxai-grid-table__cell inboxai-customer__cell" role="cell">
									<div class="inboxai-avatar" style="background:<?php echo esc_attr( \InboxAI\Support\Format::avatar_color( (string) $inboxai_c['sender_email'] ) ); ?>;"><?php echo esc_html( \InboxAI\Support\Format::avatar_initials( $inboxai_name ) ); ?></div>
									<a class="inboxai-customer__name inboxai-customer__link" href="<?php echo esc_url( $inboxai_url ); ?>"><?php echo esc_html( $inboxai_name ); ?></a>
								</div>
								<a class="inboxai-grid-table__cell inboxai-customer__link" href="<?php echo esc_url( $inboxai_url ); ?>" role="cell" style="color:var(--text-secondary);"><?php echo esc_html( $inboxai_c['sender_email'] ); ?></a>
								<div class="inboxai-grid-table__cell" role="cell"><?php echo esc_html( $inboxai_c['category'] ?: '—' ); ?></div>
								<div class="inboxai-grid-table__cell" role="cell"><?php echo \InboxAI\Support\Format::priority_badge_html( (string) $inboxai_c['priority'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Format::priority_badge_html() escapes every dynamic piece internally; see includes/Support/Format.php. ?></div>
								<div class="inboxai-grid-table__cell" role="cell"><span style="font-family:var(--mono);"><?php echo (int) $inboxai_c['message_count']; ?></span></div>
								<div class="inboxai-grid-table__cell" role="cell"><span style="font-family:var(--mono);"><?php echo (int) $inboxai_c['replied_count']; ?></span></div>
								<div class="inboxai-grid-table__cell" role="cell"><span style="font-family:var(--mono);color:var(--text-secondary);"><?php echo esc_html( \InboxAI\Support\Format::time_ago( (string) $inboxai_c['created_at'] ) ); ?></span></div>
								<div class="inboxai-grid-table__cell" role="cell">
									<div class="inboxai-row-actions">
										<div class="inboxai-btn--icon" data-action="more" data-email="<?php echo esc_attr( $inboxai_c['sender_email'] ); ?>" title="<?php esc_attr_e( 'More actions', 'inbox-ai' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg></div>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
			<div class="inboxai-table__footer">
				<?php
				$inboxai_start = 0 === $total ? 0 : ( $page - 1 ) * $per_page + 1;
				$inboxai_end   = min( $page * $per_page, $total );
				?>
				<span id="contacts-count-label">
					<?php
					printf(
						/* translators: 1: first row number, 2: last row number, 3: total rows */
						esc_html__( 'Showing %1$d to %2$d of %3$d contacts', 'inbox-ai' ),
						(int) $inboxai_start,
						(int) $inboxai_end,
						(int) $total
					);
					?>
				</span>
				<div id="contacts-pager"><?php echo \InboxAI\Support\Format::pagination_links( $total, $page, $per_page ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Format::pagination_links() escapes every dynamic piece internally; $page/$per_page are plain ints, never echoed raw. ?></div>
			</div>
		<?php endif; ?>
	</div>
<?php endif; ?>
