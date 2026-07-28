<?php
/**
 * AI Inbox List page — message list screen.
 *
 * Fully server-rendered: `$messages` is already the current page's rows
 * (from {@see \InboxAI\Database\MessageRepository::get_filtered()}), the
 * filter controls are a plain GET form (`src/admin/componets/inbox/list.js`
 * only auto-submits it on change — a small interactivity layer on top, not
 * what fetches the data), and pagination is a set of real `<a href>` links
 * (see {@see \InboxAI\Support\Format::pagination_links()}). This matters
 * for sites with a large, ever-growing submission volume: every filtered/
 * paginated state is its own real, LIMIT/OFFSET-backed URL rather than a
 * client-held dataset.
 *
 * @var array<int, array<string, mixed>> $messages    Current page's rows.
 * @var int                              $total       Total matching rows.
 * @var int                              $page        1-indexed current page.
 * @var int                              $per_page    Rows per page.
 * @var array<string, mixed>             $filters     Current filter values
 *                                                     (see `InboxListPage::get_filters_from_request()`).
 * @var string[]                         $form_titles Every real Contact Form 7 form's title.
 * @var string[]                         $categories  Every AI category that exists site-wide.
 * @var bool                             $can_delete  Whether the current user holds `DELETE_MESSAGES`.
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

$inboxai_base_url  = remove_query_arg( array( 'paged' ) );
$inboxai_clear_url = add_query_arg( array( 'page' => 'inboxai-inbox' ), admin_url( 'admin.php' ) );

// Matches InboxListPage::PERIODS — '' (the default) means every message,
// same behavior as before this control existed. Selecting a range is a
// real GET param (`period`), so it's just as bookmarkable/shareable as
// every other filter on this page; `inbox-period-select` lives outside
// `#inbox-filter-form` (it's in the page header, not the filter toolbar)
// so `src/admin/componets/inbox/list.js` wires its own change handler for
// it rather than reusing the generic `.inboxai-filter-select` one.
$inboxai_period_labels = array(
	''           => __( 'All time', 'inbox-ai' ),
	'7_days'     => __( 'Last 7 days', 'inbox-ai' ),
	'30_days'    => __( 'Last 30 days', 'inbox-ai' ),
	'90_days'    => __( 'Last 90 days', 'inbox-ai' ),
	'this_month' => __( 'This month', 'inbox-ai' ),
	'1_year'     => __( 'Last 1 year', 'inbox-ai' ),
	'2_years'    => __( 'Last 2 years', 'inbox-ai' ),
	'3_years'    => __( 'Last 3 years', 'inbox-ai' ),
	'5_years'    => __( 'Last 5 years', 'inbox-ai' ),
);

/**
 * @param int $id
 * @return string
 */
$inboxai_detail_url = static function ( int $id ) {
	return add_query_arg(
		array(
			'page' => 'inboxai-inbox',
			'id'   => $id,
		),
		admin_url( 'admin.php' )
	);
};

?>
<section class="inboxai-screen inboxai-is-active" id="screen-inbox">
	<div class="inboxai-page-header">
		<div>
			<h1><?php esc_html_e( 'AI Inbox', 'inbox-ai' ); ?></h1>
			<p><?php esc_html_e( 'All Contact Form 7 submissions, sorted by priority and confidence.', 'inbox-ai' ); ?></p>
		</div>
		<div class="inboxai-page-header__controls">
			<select class="inboxai-control" id="inbox-period-select">
				<?php foreach ( $inboxai_period_labels as $inboxai_period_value => $inboxai_period_label ) : ?>
					<option value="<?php echo esc_attr( $inboxai_period_value ); ?>" <?php selected( $filters['period'], $inboxai_period_value ); ?>><?php echo esc_html( $inboxai_period_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<a class="inboxai-btn--icon" href="<?php echo esc_url( $inboxai_base_url ); ?>" id="inbox-refresh-btn" title="<?php esc_attr_e( 'Refresh', 'inbox-ai' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
			</a>
			<button class="inboxai-btn--secondary" id="inbox-export-btn">
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
			<h2><?php esc_html_e( 'No submissions yet', 'inbox-ai' ); ?></h2>
			<p><?php esc_html_e( 'Contact Form 7 submissions will appear here once a form is monitored. Turn a form back on in General Settings to start receiving messages.', 'inbox-ai' ); ?></p>
			<div class="inboxai-state__actions">
				<a class="inboxai-btn--primary" href="<?php echo esc_url( \InboxAI\Admin\Menu::url( 'inboxai-settings' ) . '&tab=general-settings' ); ?>"><?php esc_html_e( 'Select Forms', 'inbox-ai' ); ?></a>
				<a class="inboxai-btn--secondary" href="<?php echo esc_url( \InboxAI\Admin\Menu::url( 'inboxai-settings' ) ); ?>"><?php esc_html_e( 'Configure AI Provider', 'inbox-ai' ); ?></a>
			</div>
		</div>
	<?php else : ?>
		<div class="inboxai-card">
			<form class="inboxai-table__toolbar" id="inbox-filter-form" method="get">
				<input type="hidden" name="page" value="inboxai-inbox">
				<div class="inboxai-search">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
					<input type="text" name="search" id="inbox-search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search messages, customers, emails…', 'inbox-ai' ); ?>">
				</div>
				<select class="inboxai-filter-select" id="filter-form" name="form">
					<option value=""><?php esc_html_e( 'All forms', 'inbox-ai' ); ?></option>
					<?php foreach ( $form_titles as $inboxai_form_title ) : ?>
						<option value="<?php echo esc_attr( $inboxai_form_title ); ?>" <?php selected( $filters['form'], $inboxai_form_title ); ?>><?php echo esc_html( $inboxai_form_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<select class="inboxai-filter-select" id="filter-status" name="status">
					<option value=""><?php esc_html_e( 'All statuses', 'inbox-ai' ); ?></option>
					<?php
					$inboxai_status_options = array(
						'new'      => __( 'New', 'inbox-ai' ),
						'review'   => __( 'Needs Review', 'inbox-ai' ),
						'reviewed' => __( 'Reviewed', 'inbox-ai' ),
						'drafted'  => __( 'Drafted', 'inbox-ai' ),
						'replied'  => __( 'Replied', 'inbox-ai' ),
						'failed'   => __( 'Failed', 'inbox-ai' ),
						'archived' => __( 'Archived', 'inbox-ai' ),
					);
					foreach ( $inboxai_status_options as $inboxai_status_value => $inboxai_status_label ) :
						?>
						<option value="<?php echo esc_attr( $inboxai_status_value ); ?>" <?php selected( $filters['status'], $inboxai_status_value ); ?>><?php echo esc_html( $inboxai_status_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select class="inboxai-filter-select" id="filter-priority" name="priority">
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
				<select class="inboxai-filter-select" id="filter-category" name="category">
					<option value=""><?php esc_html_e( 'All categories', 'inbox-ai' ); ?></option>
					<?php foreach ( $categories as $inboxai_category_name ) : ?>
						<option value="<?php echo esc_attr( $inboxai_category_name ); ?>" <?php selected( $filters['category'], $inboxai_category_name ); ?>><?php echo esc_html( $inboxai_category_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="inboxai-btn--secondary"><?php esc_html_e( 'Filter', 'inbox-ai' ); ?></button>
				<?php if ( $inboxai_has_active_filters ) : ?>
					<a class="inboxai-btn--secondary" href="<?php echo esc_url( $inboxai_clear_url ); ?>"><?php esc_html_e( 'Clear filters', 'inbox-ai' ); ?></a>
				<?php endif; ?>
			</form>
			<?php if ( 0 === $total ) : ?>
				<div class="inboxai-grid-table__cell inboxai-grid-table__cell--empty">
					<?php esc_html_e( 'No messages match your filters.', 'inbox-ai' ); ?>
					<a class="inboxai-btn--secondary" href="<?php echo esc_url( $inboxai_clear_url ); ?>"><?php esc_html_e( 'Clear filters', 'inbox-ai' ); ?></a>
				</div>
			<?php else : ?>
				<div style="overflow-x:auto;">
					<div class="inboxai-grid-table inboxai-grid-table--messages" role="table">
						<div class="inboxai-grid-table__row inboxai-grid-table__row--head" role="row">
							<div class="inboxai-grid-table__cell" role="columnheader"><?php esc_html_e( 'Customer', 'inbox-ai' ); ?></div>
							<div class="inboxai-grid-table__cell" role="columnheader"><?php esc_html_e( 'Message', 'inbox-ai' ); ?></div>
							<div class="inboxai-grid-table__cell" role="columnheader"><?php esc_html_e( 'Form', 'inbox-ai' ); ?></div>
							<div class="inboxai-grid-table__cell" role="columnheader"><?php esc_html_e( 'Priority', 'inbox-ai' ); ?></div>
							<div class="inboxai-grid-table__cell" role="columnheader"><?php esc_html_e( 'Category', 'inbox-ai' ); ?></div>
							<div class="inboxai-grid-table__cell" role="columnheader"><?php esc_html_e( 'AI Confidence', 'inbox-ai' ); ?></div>
							<div class="inboxai-grid-table__cell" role="columnheader"><?php esc_html_e( 'Status', 'inbox-ai' ); ?></div>
							<div class="inboxai-grid-table__cell" role="columnheader"><?php esc_html_e( 'Received', 'inbox-ai' ); ?></div>
							<div class="inboxai-grid-table__cell" role="columnheader"></div>
						</div>
						<div id="inbox-table-body" class="inboxai-grid-table__body" role="rowgroup">
							<?php foreach ( $messages as $inboxai_m ) : ?>
								<?php
								$inboxai_name    = $inboxai_m['sender_name'] ?: __( '(no name)', 'inbox-ai' );
								$inboxai_preview = mb_substr( (string) ( $inboxai_m['subject'] ?: $inboxai_m['message'] ), 0, 120 );
								$inboxai_url     = $inboxai_detail_url( $inboxai_m['id'] );
								?>
								<div class="inboxai-grid-table__row<?php echo 'archived' === $inboxai_m['workflow_status'] ? ' inboxai-is-archived' : ''; ?>" role="row">
									<div class="inboxai-grid-table__cell inboxai-customer__cell" role="cell">
										<div class="inboxai-avatar" style="background:<?php echo esc_attr( \InboxAI\Support\Format::avatar_color( $inboxai_m['sender_email'] ) ); ?>;"><?php echo esc_html( \InboxAI\Support\Format::avatar_initials( $inboxai_name ) ); ?></div>
										<div>
											<a class="inboxai-customer__name inboxai-customer__link" href="<?php echo esc_url( $inboxai_url ); ?>" style="display:block;"><?php echo esc_html( $inboxai_name ); ?></a>
											<a class="inboxai-customer__email inboxai-customer__link" href="<?php echo esc_url( $inboxai_url ); ?>" style="display:block;"><?php echo esc_html( $inboxai_m['sender_email'] ); ?></a>
										</div>
									</div>
									<div class="inboxai-grid-table__cell" role="cell"><span class="inboxai-message-preview"><?php echo esc_html( $inboxai_preview ); ?></span></div>
									<div class="inboxai-grid-table__cell" role="cell"><?php echo esc_html( $inboxai_m['form_title'] ); ?></div>
									<div class="inboxai-grid-table__cell" role="cell"><?php echo \InboxAI\Support\Format::priority_badge_html( (string) $inboxai_m['priority'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Format::priority_badge_html() escapes every dynamic piece internally; see includes/Support/Format.php. ?></div>
									<div class="inboxai-grid-table__cell" role="cell"><?php echo esc_html( $inboxai_m['category'] ?: '—' ); ?></div>
									<div class="inboxai-grid-table__cell" role="cell"><?php echo \InboxAI\Support\Format::confidence_cell_html( null === $inboxai_m['confidence'] ? null : (int) $inboxai_m['confidence'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Format::confidence_cell_html() escapes internally; the only dynamic input is already cast to int. ?></div>
									<div class="inboxai-grid-table__cell" role="cell"><?php echo \InboxAI\Support\Format::status_badge_html( (string) $inboxai_m['workflow_status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see Format::status_badge_html(), escapes internally. ?></div>
									<div class="inboxai-grid-table__cell" role="cell"><span style="font-family:var(--mono);color:var(--text-secondary);"><?php echo esc_html( \InboxAI\Support\Format::time_ago( (string) $inboxai_m['created_at'] ) ); ?></span></div>
									<div class="inboxai-grid-table__cell" role="cell">
										<div class="inboxai-row-actions">
											<a class="inboxai-btn--icon" href="<?php echo esc_url( $inboxai_url ); ?>" title="<?php esc_attr_e( 'View', 'inbox-ai' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg></a>
											<a class="inboxai-btn--icon" href="<?php echo esc_url( $inboxai_url . '#reply-composer' ); ?>" title="<?php esc_attr_e( 'Reply', 'inbox-ai' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v14H4z"/><path d="M4 6l8 7 8-7"/></svg></a>
											<div class="inboxai-btn--icon" data-action="more" data-id="<?php echo (int) $inboxai_m['id']; ?>" data-status="<?php echo esc_attr( $inboxai_m['workflow_status'] ); ?>" title="<?php esc_attr_e( 'More actions', 'inbox-ai' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg></div>
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
					<span id="inbox-count-label">
						<?php
						printf(
							/* translators: 1: first row number, 2: last row number, 3: total rows */
							esc_html__( 'Showing %1$d to %2$d of %3$d messages', 'inbox-ai' ),
							(int) $inboxai_start,
							(int) $inboxai_end,
							(int) $total
						);
						?>
					</span>
					<div id="inbox-pager"><?php echo \InboxAI\Support\Format::pagination_links( $total, $page, $per_page ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Format::pagination_links() escapes every dynamic piece internally (esc_url()/esc_attr()); $page/$per_page are plain ints, never echoed raw. ?></div>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
