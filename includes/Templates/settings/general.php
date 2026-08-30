<?php
/**
 * Settings page — General tab.
 *
 * @var string $active_tab Currently visible tab key.
 * @var array  $general    {@see \InboxAI\Settings\Repository::get_general()}.
 * @var array  $cf7_forms  Real Contact Form 7 forms: array{id:int,title:string,monitored:bool,submissions_this_month:int}[].
 * @var array  $categories Every {@see \InboxAI\CF7\CategoryTaxonomy} term: array{term_id:int,name:string,count:int}[].
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$inboxai_retention_labels = array(
	'forever'   => __( 'Forever', 'inbox-ai' ),
	'24_months' => __( '24 months', 'inbox-ai' ),
	'12_months' => __( '12 months', 'inbox-ai' ),
	'6_months'  => __( '6 months', 'inbox-ai' ),
);

?>
<section class="inboxai-screen<?php echo 'general-settings' === $active_tab ? ' inboxai-is-active' : ''; ?>" id="screen-general-settings">
	<div class="inboxai-page-header">
		<div>
			<h1><?php esc_html_e( 'General Settings', 'inbox-ai' ); ?></h1>
			<p><?php esc_html_e( 'Choose which forms feed the AI Inbox and how new submissions are handled.', 'inbox-ai' ); ?></p>
		</div>
	</div>
	<div class="inboxai-settings__shell">
		<?php \InboxAI\Support\Template::render( 'settings/partials/subnav', array( 'active_tab' => $active_tab ) ); ?>
		<div class="inboxai-stack">

			<div class="inboxai-card">
				<div class="inboxai-card__header"><h2><?php esc_html_e( 'Monitored Forms', 'inbox-ai' ); ?></h2></div>
				<div class="inboxai-card__body">
					<?php if ( array() === $cf7_forms ) : ?>
						<p style="color:var(--text-tertiary);font-size:13px;"><?php esc_html_e( 'No Contact Form 7 forms found yet. Create a form to start monitoring submissions.', 'inbox-ai' ); ?></p>
					<?php else : ?>
						<?php foreach ( $cf7_forms as $inboxai_form ) : ?>
							<div class="inboxai-switch-row">
								<div>
									<div class="inboxai-switch-row__text"><?php echo esc_html( $inboxai_form['title'] ); ?></div>
									<div class="inboxai-switch-row__sub">
										<?php
										printf(
											/* translators: %d: number of submissions this calendar month. */
											esc_html( _n( '%d submission this month', '%d submissions this month', $inboxai_form['submissions_this_month'], 'inbox-ai' ) ),
											absint( $inboxai_form['submissions_this_month'] )
										);
										?>
									</div>
								</div>
								<div
									class="inboxai-switch<?php echo $inboxai_form['monitored'] ? ' inboxai-is-on' : ''; ?>"
									data-form-toggle="<?php echo esc_attr( $inboxai_form['title'] ); ?>"
									data-form-id="<?php echo esc_attr( (string) $inboxai_form['id'] ); ?>"
								></div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<div class="inboxai-card" id="manage-categories-card">
				<div class="inboxai-card__header">
					<h2><?php esc_html_e( 'Manage Categories', 'inbox-ai' ); ?></h2>
					<span class="inboxai-card__muted"><?php esc_html_e( 'Add, rename, or delete an AI category — renaming/deleting affects every form that uses it. Categories added here (or on any form\'s own edit screen) are available to every form.', 'inbox-ai' ); ?></span>
				</div>
				<div class="inboxai-card__body">
					<div class="inboxai-category-add">
						<input type="text" class="inboxai-category-add__input" id="category-add-input" placeholder="<?php esc_attr_e( 'New category name', 'inbox-ai' ); ?>">
						<button type="button" class="inboxai-btn--secondary" id="category-add-btn"><?php esc_html_e( 'Add category', 'inbox-ai' ); ?></button>
					</div>
					<div id="categories-list">
						<?php if ( array() === $categories ) : ?>
							<p id="categories-empty" style="color:var(--text-tertiary);font-size:13px;"><?php esc_html_e( 'No categories yet — add one above.', 'inbox-ai' ); ?></p>
						<?php else : ?>
							<?php foreach ( $categories as $inboxai_category ) : ?>
								<div class="inboxai-category-row" data-category-row data-term-id="<?php echo (int) $inboxai_category['term_id']; ?>">
									<div class="inboxai-category-row__main">
										<div class="inboxai-category-row__display" data-category-display><?php echo esc_html( $inboxai_category['name'] ); ?></div>
										<input type="text" class="inboxai-category-row__input" data-category-input style="display:none;" value="<?php echo esc_attr( $inboxai_category['name'] ); ?>">
										<div class="inboxai-category-row__sub">
											<?php
											printf(
												/* translators: %d: number of forms this category is assigned to. */
												esc_html( _n( 'Used by %d form', 'Used by %d forms', $inboxai_category['count'], 'inbox-ai' ) ),
												absint( $inboxai_category['count'] )
											);
											?>
										</div>
									</div>
									<div class="inboxai-category-row__actions">
										<button type="button" class="inboxai-btn--icon" data-category-edit title="<?php esc_attr_e( 'Rename', 'inbox-ai' ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4z"/></svg></button>
										<button type="button" class="inboxai-btn--icon" data-category-save title="<?php esc_attr_e( 'Save', 'inbox-ai' ); ?>" style="display:none;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg></button>
										<button type="button" class="inboxai-btn--icon" data-category-cancel title="<?php esc_attr_e( 'Cancel', 'inbox-ai' ); ?>" style="display:none;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
										<button type="button" class="inboxai-btn--icon" data-category-delete title="<?php esc_attr_e( 'Delete', 'inbox-ai' ); ?>" style="color:var(--urgent);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0l-1 14a2 2 0 01-2 2H7a2 2 0 01-2-2L4 6"/></svg></button>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="inboxai-card">
				<div class="inboxai-card__header"><h2><?php esc_html_e( 'Automatic Processing', 'inbox-ai' ); ?></h2></div>
				<div class="inboxai-card__body">
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Analyze new submissions automatically', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Runs AI analysis as soon as a message arrives', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $general['auto_analyze'] ? ' inboxai-is-on' : ''; ?>" data-field="auto_analyze"></div>
					</div>
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Auto-draft replies for high-confidence messages', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Drafts are never sent without approval', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $general['auto_draft_high_confidence'] ? ' inboxai-is-on' : ''; ?>" data-field="auto_draft_high_confidence"></div>
					</div>
					<div class="inboxai-switch-row">
						<div>
							<div class="inboxai-switch-row__text"><?php esc_html_e( 'Auto-archive detected spam', 'inbox-ai' ); ?></div>
							<div class="inboxai-switch-row__sub"><?php esc_html_e( 'Applies only above 95% spam confidence', 'inbox-ai' ); ?></div>
						</div>
						<div class="inboxai-switch<?php echo $general['auto_archive_spam'] ? ' inboxai-is-on' : ''; ?>" data-field="auto_archive_spam"></div>
					</div>
					<div class="inboxai-field" style="margin-top:16px;">
						<label><?php esc_html_e( 'Confidence threshold for "Needs Review"', 'inbox-ai' ); ?></label>
						<input class="inboxai-field__input" type="range" min="0" max="100" data-field="confidence_threshold" value="<?php echo esc_attr( (string) $general['confidence_threshold'] ); ?>">
						<div class="inboxai-field__hint">
							<?php
							printf(
								/* translators: %d: confidence percentage. */
								esc_html__( 'Messages analyzed below %d%% confidence are flagged for manual review.', 'inbox-ai' ),
								absint( $general['confidence_threshold'] )
							);
							?>
						</div>
					</div>
				</div>
			</div>

			<div class="inboxai-card">
				<div class="inboxai-card__header"><h2><?php esc_html_e( 'Data Retention', 'inbox-ai' ); ?></h2></div>
				<div class="inboxai-card__body">
					<div class="inboxai-field">
						<label><?php esc_html_e( 'Keep submissions for', 'inbox-ai' ); ?></label>
						<select class="inboxai-field__input" data-field="retention_period">
							<?php foreach ( $inboxai_retention_labels as $inboxai_value => $inboxai_label ) : ?>
								<option value="<?php echo esc_attr( $inboxai_value ); ?>" <?php selected( $general['retention_period'], $inboxai_value ); ?>><?php echo esc_html( $inboxai_label ); ?></option>
							<?php endforeach; ?>
						</select>
						<div class="inboxai-field__hint"><?php esc_html_e( 'Submissions older than this are permanently deleted by a daily background check. "Forever" never deletes anything.', 'inbox-ai' ); ?></div>
					</div>
				</div>
			</div>

			<div style="display:flex;gap:10px;justify-content:flex-end;">
				<button class="inboxai-btn--primary" id="general-settings-save-btn"><?php esc_html_e( 'Save Changes', 'inbox-ai' ); ?></button>
			</div>

		</div>
	</div>
</section>
