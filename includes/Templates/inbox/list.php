<?php
/**
 * AI Inbox List page — message list screen.
 *
 * Fully server-rendered: `$messages` is already the current page's rows
 * (from {@see \CF7AIInbox\Database\MessageRepository::get_filtered()}), the
 * filter controls are a plain GET form (`src/admin/componets/inbox/list.js`
 * only auto-submits it on change — a small interactivity layer on top, not
 * what fetches the data), and pagination is a set of real `<a href>` links
 * (see {@see \CF7AIInbox\Support\Format::pagination_links()}). This matters
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
 * @package CF7AIInbox\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cf7ai_has_active_filters = array() !== array_filter(
	$filters,
	static function ( $value ) {
		return '' !== $value && null !== $value;
	}
);

$cf7ai_base_url  = remove_query_arg( array( 'paged' ) );
$cf7ai_clear_url = add_query_arg( array( 'page' => 'cf7ai-inbox' ), admin_url( 'admin.php' ) );

// Matches InboxListPage::PERIODS — '' (the default) means every message,
// same behavior as before this control existed. Selecting a range is a
// real GET param (`period`), so it's just as bookmarkable/shareable as
// every other filter on this page; `inbox-period-select` lives outside
// `#inbox-filter-form` (it's in the page header, not the filter toolbar)
// so `src/admin/componets/inbox/list.js` wires its own change handler for
// it rather than reusing the generic `.cf7-ai-inbox-filter-select` one.
$cf7ai_period_labels = array(
	''           => __( 'All time', 'cf7-ai-inbox' ),
	'7_days'     => __( 'Last 7 days', 'cf7-ai-inbox' ),
	'30_days'    => __( 'Last 30 days', 'cf7-ai-inbox' ),
	'90_days'    => __( 'Last 90 days', 'cf7-ai-inbox' ),
	'this_month' => __( 'This month', 'cf7-ai-inbox' ),
	'1_year'     => __( 'Last 1 year', 'cf7-ai-inbox' ),
	'2_years'    => __( 'Last 2 years', 'cf7-ai-inbox' ),
	'3_years'    => __( 'Last 3 years', 'cf7-ai-inbox' ),
	'5_years'    => __( 'Last 5 years', 'cf7-ai-inbox' ),
);

/**
 * @param int $id
 * @return string
 */
$cf7ai_detail_url = static function ( int $id ) {
	return add_query_arg(
		array(
			'page' => 'cf7ai-inbox',
			'id'   => $id,
		),
		admin_url( 'admin.php' )
	);
};

?>
<section class="cf7-ai-inbox-screen cf7-ai-inbox-is-active" id="screen-inbox">
	<div class="cf7-ai-inbox-page-header">
		<div>
			<h1><?php esc_html_e( 'AI Inbox', 'cf7-ai-inbox' ); ?></h1>
			<p><?php esc_html_e( 'All Contact Form 7 submissions, sorted by priority and confidence.', 'cf7-ai-inbox' ); ?></p>
		</div>
		<div class="cf7-ai-inbox-page-header__controls">
			<select class="cf7-ai-inbox-control" id="inbox-period-select">
				<?php foreach ( $cf7ai_period_labels as $cf7ai_period_value => $cf7ai_period_label ) : ?>
					<option value="<?php echo esc_attr( $cf7ai_period_value ); ?>" <?php selected( $filters['period'], $cf7ai_period_value ); ?>><?php echo esc_html( $cf7ai_period_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<a class="cf7-ai-inbox-btn--icon" href="<?php echo esc_url( $cf7ai_base_url ); ?>" id="inbox-refresh-btn" title="<?php esc_attr_e( 'Refresh', 'cf7-ai-inbox' ); ?>">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
			</a>
			<button class="cf7-ai-inbox-btn--secondary" id="inbox-export-btn">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;vertical-align:-2px;margin-right:5px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
				<?php esc_html_e( 'Export', 'cf7-ai-inbox' ); ?>
			</button>
		</div>
	</div>

	<?php if ( 0 === $total && ! $cf7ai_has_active_filters ) : ?>
		<div class="cf7-ai-inbox-state">
			<svg width="120" height="90" viewBox="0 0 120 90" fill="none">
				<rect x="10" y="20" width="100" height="60" rx="8" fill="#EEF1FF" stroke="#D8DFFE" stroke-width="2"/>
				<path d="M10 30l50 30 50-30" stroke="#3A5CF6" stroke-width="2" fill="none"/>
				<circle cx="94" cy="18" r="14" fill="#3A5CF6"/><path d="M88 18h12M94 12v12" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>
			</svg>
			<h2><?php esc_html_e( 'No submissions yet', 'cf7-ai-inbox' ); ?></h2>
			<p><?php esc_html_e( 'Contact Form 7 submissions will appear here once a form is monitored. Turn a form back on in General Settings to start receiving messages.', 'cf7-ai-inbox' ); ?></p>
			<div class="cf7-ai-inbox-state__actions">
				<a class="cf7-ai-inbox-btn--primary" href="<?php echo esc_url( \CF7AIInbox\Admin\Menu::url( 'cf7ai-settings' ) . '&tab=general-settings' ); ?>"><?php esc_html_e( 'Select Forms', 'cf7-ai-inbox' ); ?></a>
				<a class="cf7-ai-inbox-btn--secondary" href="<?php echo esc_url( \CF7AIInbox\Admin\Menu::url( 'cf7ai-settings' ) ); ?>"><?php esc_html_e( 'Configure AI Provider', 'cf7-ai-inbox' ); ?></a>
			</div>
		</div>
	<?php else : ?>
		<div class="cf7-ai-inbox-card">
			<form class="cf7-ai-inbox-table__toolbar" id="inbox-filter-form" method="get">
				<input type="hidden" name="page" value="cf7ai-inbox">
				<div class="cf7-ai-inbox-search">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
					<input type="text" name="search" id="inbox-search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search messages, customers, emails…', 'cf7-ai-inbox' ); ?>">
				</div>
				<select class="cf7-ai-inbox-filter-select" id="filter-form" name="form">
					<option value=""><?php esc_html_e( 'All forms', 'cf7-ai-inbox' ); ?></option>
					<?php foreach ( $form_titles as $cf7ai_form_title ) : ?>
						<option value="<?php echo esc_attr( $cf7ai_form_title ); ?>" <?php selected( $filters['form'], $cf7ai_form_title ); ?>><?php echo esc_html( $cf7ai_form_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<select class="cf7-ai-inbox-filter-select" id="filter-status" name="status">
					<option value=""><?php esc_html_e( 'All statuses', 'cf7-ai-inbox' ); ?></option>
					<?php
					$cf7ai_status_options = array(
						'new'      => __( 'New', 'cf7-ai-inbox' ),
						'review'   => __( 'Needs Review', 'cf7-ai-inbox' ),
						'reviewed' => __( 'Reviewed', 'cf7-ai-inbox' ),
						'drafted'  => __( 'Drafted', 'cf7-ai-inbox' ),
						'replied'  => __( 'Replied', 'cf7-ai-inbox' ),
						'failed'   => __( 'Failed', 'cf7-ai-inbox' ),
						'archived' => __( 'Archived', 'cf7-ai-inbox' ),
					);
					foreach ( $cf7ai_status_options as $cf7ai_status_value => $cf7ai_status_label ) :
						?>
						<option value="<?php echo esc_attr( $cf7ai_status_value ); ?>" <?php selected( $filters['status'], $cf7ai_status_value ); ?>><?php echo esc_html( $cf7ai_status_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select class="cf7-ai-inbox-filter-select" id="filter-priority" name="priority">
					<option value=""><?php esc_html_e( 'All priorities', 'cf7-ai-inbox' ); ?></option>
					<?php
					$cf7ai_priority_options = array(
						'urgent' => __( 'Urgent', 'cf7-ai-inbox' ),
						'high'   => __( 'High', 'cf7-ai-inbox' ),
						'normal' => __( 'Normal', 'cf7-ai-inbox' ),
						'low'    => __( 'Low', 'cf7-ai-inbox' ),
					);
					foreach ( $cf7ai_priority_options as $cf7ai_priority_value => $cf7ai_priority_label ) :
						?>
						<option value="<?php echo esc_attr( $cf7ai_priority_value ); ?>" <?php selected( $filters['priority'], $cf7ai_priority_value ); ?>><?php echo esc_html( $cf7ai_priority_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select class="cf7-ai-inbox-filter-select" id="filter-category" name="category">
					<option value=""><?php esc_html_e( 'All categories', 'cf7-ai-inbox' ); ?></option>
					<?php foreach ( $categories as $cf7ai_category_name ) : ?>
						<option value="<?php echo esc_attr( $cf7ai_category_name ); ?>" <?php selected( $filters['category'], $cf7ai_category_name ); ?>><?php echo esc_html( $cf7ai_category_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="cf7-ai-inbox-btn--secondary"><?php esc_html_e( 'Filter', 'cf7-ai-inbox' ); ?></button>
				<?php if ( $cf7ai_has_active_filters ) : ?>
					<a class="cf7-ai-inbox-btn--secondary" href="<?php echo esc_url( $cf7ai_clear_url ); ?>"><?php esc_html_e( 'Clear filters', 'cf7-ai-inbox' ); ?></a>
				<?php endif; ?>
			</form>
			<?php if ( 0 === $total ) : ?>
				<div class="cf7-ai-inbox-grid-table__cell cf7-ai-inbox-grid-table__cell--empty">
					<?php esc_html_e( 'No messages match your filters.', 'cf7-ai-inbox' ); ?>
					<a class="cf7-ai-inbox-btn--secondary" href="<?php echo esc_url( $cf7ai_clear_url ); ?>"><?php esc_html_e( 'Clear filters', 'cf7-ai-inbox' ); ?></a>
				</div>
			<?php else : ?>
				<div style="overflow-x:auto;">
					<div class="cf7-ai-inbox-grid-table cf7-ai-inbox-grid-table--messages" role="table">
						<div class="cf7-ai-inbox-grid-table__row cf7-ai-inbox-grid-table__row--head" role="row">
							<div class="cf7-ai-inbox-grid-table__cell" role="columnheader"><?php esc_html_e( 'Customer', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-grid-table__cell" role="columnheader"><?php esc_html_e( 'Message', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-grid-table__cell" role="columnheader"><?php esc_html_e( 'Form', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-grid-table__cell" role="columnheader"><?php esc_html_e( 'Priority', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-grid-table__cell" role="columnheader"><?php esc_html_e( 'Category', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-grid-table__cell" role="columnheader"><?php esc_html_e( 'AI Confidence', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-grid-table__cell" role="columnheader"><?php esc_html_e( 'Status', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-grid-table__cell" role="columnheader"><?php esc_html_e( 'Received', 'cf7-ai-inbox' ); ?></div>
							<div class="cf7-ai-inbox-grid-table__cell" role="columnheader"></div>
						</div>
						<div id="inbox-table-body" class="cf7-ai-inbox-grid-table__body" role="rowgroup">
							<?php foreach ( $messages as $cf7ai_m ) : ?>
								<?php
								$cf7ai_name    = $cf7ai_m['sender_name'] ?: __( '(no name)', 'cf7-ai-inbox' );
								$cf7ai_preview = mb_substr( (string) ( $cf7ai_m['subject'] ?: $cf7ai_m['message'] ), 0, 120 );
								$cf7ai_url     = $cf7ai_detail_url( $cf7ai_m['id'] );
								?>
								<div class="cf7-ai-inbox-grid-table__row<?php echo 'archived' === $cf7ai_m['workflow_status'] ? ' cf7-ai-inbox-is-archived' : ''; ?>" role="row">
									<div class="cf7-ai-inbox-grid-table__cell cf7-ai-inbox-customer__cell" role="cell">
										<div class="cf7-ai-inbox-avatar" style="background:<?php echo esc_attr( \CF7AIInbox\Support\Format::avatar_color( $cf7ai_m['sender_email'] ) ); ?>;"><?php echo esc_html( \CF7AIInbox\Support\Format::avatar_initials( $cf7ai_name ) ); ?></div>
										<div>
											<a class="cf7-ai-inbox-customer__name cf7-ai-inbox-customer__link" href="<?php echo esc_url( $cf7ai_url ); ?>" style="display:block;"><?php echo esc_html( $cf7ai_name ); ?></a>
											<a class="cf7-ai-inbox-customer__email cf7-ai-inbox-customer__link" href="<?php echo esc_url( $cf7ai_url ); ?>" style="display:block;"><?php echo esc_html( $cf7ai_m['sender_email'] ); ?></a>
										</div>
									</div>
									<div class="cf7-ai-inbox-grid-table__cell" role="cell"><span class="cf7-ai-inbox-message-preview"><?php echo esc_html( $cf7ai_preview ); ?></span></div>
									<div class="cf7-ai-inbox-grid-table__cell" role="cell"><?php echo esc_html( $cf7ai_m['form_title'] ); ?></div>
									<div class="cf7-ai-inbox-grid-table__cell" role="cell"><?php echo \CF7AIInbox\Support\Format::priority_badge_html( (string) $cf7ai_m['priority'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Format::priority_badge_html() escapes every dynamic piece internally; see includes/Support/Format.php. ?></div>
									<div class="cf7-ai-inbox-grid-table__cell" role="cell"><?php echo esc_html( $cf7ai_m['category'] ?: '—' ); ?></div>
									<div class="cf7-ai-inbox-grid-table__cell" role="cell"><?php echo \CF7AIInbox\Support\Format::confidence_cell_html( null === $cf7ai_m['confidence'] ? null : (int) $cf7ai_m['confidence'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Format::confidence_cell_html() escapes internally; the only dynamic input is already cast to int. ?></div>
									<div class="cf7-ai-inbox-grid-table__cell" role="cell"><?php echo \CF7AIInbox\Support\Format::status_badge_html( (string) $cf7ai_m['workflow_status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see Format::status_badge_html(), escapes internally. ?></div>
									<div class="cf7-ai-inbox-grid-table__cell" role="cell"><span style="font-family:var(--mono);color:var(--text-secondary);"><?php echo esc_html( \CF7AIInbox\Support\Format::time_ago( (string) $cf7ai_m['created_at'] ) ); ?></span></div>
									<div class="cf7-ai-inbox-grid-table__cell" role="cell">
										<div class="cf7-ai-inbox-row-actions">
											<a class="cf7-ai-inbox-btn--icon" href="<?php echo esc_url( $cf7ai_url ); ?>" title="<?php esc_attr_e( 'View', 'cf7-ai-inbox' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg></a>
											<a class="cf7-ai-inbox-btn--icon" href="<?php echo esc_url( $cf7ai_url . '#reply-composer' ); ?>" title="<?php esc_attr_e( 'Reply', 'cf7-ai-inbox' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v14H4z"/><path d="M4 6l8 7 8-7"/></svg></a>
											<div class="cf7-ai-inbox-btn--icon" data-action="more" data-id="<?php echo (int) $cf7ai_m['id']; ?>" data-status="<?php echo esc_attr( $cf7ai_m['workflow_status'] ); ?>" title="<?php esc_attr_e( 'More actions', 'cf7-ai-inbox' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg></div>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<div class="cf7-ai-inbox-table__footer">
					<?php
					$cf7ai_start = 0 === $total ? 0 : ( $page - 1 ) * $per_page + 1;
					$cf7ai_end   = min( $page * $per_page, $total );
					?>
					<span id="inbox-count-label">
						<?php
						printf(
							/* translators: 1: first row number, 2: last row number, 3: total rows */
							esc_html__( 'Showing %1$d to %2$d of %3$d messages', 'cf7-ai-inbox' ),
							(int) $cf7ai_start,
							(int) $cf7ai_end,
							(int) $total
						);
						?>
					</span>
					<div id="inbox-pager"><?php echo \CF7AIInbox\Support\Format::pagination_links( $total, $page, $per_page ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Format::pagination_links() escapes every dynamic piece internally (esc_url()/esc_attr()); $page/$per_page are plain ints, never echoed raw. ?></div>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
