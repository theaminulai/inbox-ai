<?php
/**
 * Renderer for the AI Inbox List admin page (`cf7ai-inbox`).
 *
 * @package CF7AIInbox\Admin\Pages
 */

namespace CF7AIInbox\Admin\Pages;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIInbox\Admin\AjaxController;
use CF7AIInbox\CF7\CategoryTaxonomy;
use CF7AIInbox\Database\ActivityRepository;
use CF7AIInbox\Database\MessageRepository;
use CF7AIInbox\Security\Capabilities;
use CF7AIInbox\Support\Template;

/**
 * Class InboxListPage
 *
 * Both of this page's views — the message list and a single submission's
 * detail (which folds in the "AI analysis failed" state — see
 * {@see self::render_detail()}) — are rendered with real data server-side,
 * exactly like the Settings page. This matters at scale: a site with a large,
 * ongoing volume of submissions gets real `LIMIT`/`OFFSET` SQL pagination and
 * a real, bookmarkable/shareable URL per filtered page or per submission,
 * instead of ever holding the whole (or a 10,000-row "export" slice of the)
 * table in the browser. `src/admin/componets/inbox/*.js` is only responsible
 * for interactivity on top of that already-rendered HTML: the row "more
 * actions" menu, auto-submitting the filter form, the reply composer's rich
 * text toolbar and save/send calls, and re-queuing AI analysis — never for
 * building the table or detail screen's markup itself.
 *
 * `$_GET['id']` is what decides which view renders: no `id` shows the list,
 * a valid `id` shows that submission's detail — the same one-page-per-slug
 * pattern WordPress's own `edit.php` / `post.php?action=edit` uses.
 */
final class InboxListPage {

	/**
	 * Values the "Received" date-range control accepts — anything else in
	 * the request is treated as no filter at all (every message, matching
	 * this page's long-standing default). Same period vocabulary as the
	 * Settings page's Usage & Billing tab (see
	 * `AjaxController::USAGE_PERIODS`) for a consistent set of choices
	 * across the plugin, resolved the same way by
	 * {@see \CF7AIInbox\Database\MessageRepository::period_to_datetime()}.
	 * Public: also read by {@see \CF7AIInbox\Admin\AjaxController::list_messages()}
	 * so the CSV export AJAX call validates against the same whitelist.
	 *
	 * @var string[]
	 */
	public const PERIODS = array( '7_days', '30_days', '90_days', 'this_month', '1_year', '2_years', '3_years', '5_years' );

	/**
	 * Hooks this page's data into {@see \CF7AIInbox\Admin\Menu::enqueue_assets()}.
	 */
	public function __construct() {
		add_filter( 'cf7ai_inbox_localize_data', array( $this, 'localize_data' ), 10, 2 );
	}

	/**
	 * Renders the page. Registered as the `add_submenu_page()` callback for
	 * `cf7ai-inbox` by {@see \CF7AIInbox\Admin\Menu}.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::VIEW_MESSAGES ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cf7-ai-inbox' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing decision (which view to render), not a state-changing request.
		$id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;

		if ( $id > 0 ) {
			$this->render_detail( $id );
			return;
		}

		$this->render_list();
	}

	/**
	 * Renders the message list: reads filters + pagination straight from
	 * `$_GET` (a plain GET form — see `includes/Templates/inbox/list.php` —
	 * so every filtered/paginated state is its own real URL) and queries
	 * {@see \CF7AIInbox\Database\MessageRepository::get_filtered()} once,
	 * server-side.
	 *
	 * @return void
	 */
	private function render_list(): void {
		$filters  = $this->get_filters_from_request();
		$page     = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter/pagination, not a state-changing request.
		$per_page = 20;

		$result = MessageRepository::get_filtered( $filters, $page, $per_page );

		Template::render(
			'inbox/inbox',
			array(
				'view'        => 'list',
				'messages'    => $result['items'],
				'total'       => $result['total'],
				'page'        => $page,
				'per_page'    => $per_page,
				'filters'     => $filters,
				'form_titles' => $this->get_form_titles(),
				'categories'  => $this->get_category_names(),
				'can_reply'   => current_user_can( Capabilities::SEND_REPLIES ),
				'can_edit'    => current_user_can( Capabilities::EDIT_MESSAGES ),
				'can_delete'  => current_user_can( Capabilities::DELETE_MESSAGES ),
			)
		);
	}

	/**
	 * Renders one submission's detail screen (folding in the "AI analysis
	 * failed" state — see `includes/Templates/inbox/detail.php` — rather than
	 * a separate screen/URL, since it's the same submission either way).
	 *
	 * @param int $id Message row id.
	 *
	 * @return void
	 */
	private function render_detail( int $id ): void {
		$message = MessageRepository::find( $id );

		if ( null === $message ) {
			Template::render(
				'inbox/inbox',
				array(
					'view'       => 'not-found',
					'can_reply'  => current_user_can( Capabilities::SEND_REPLIES ),
					'can_edit'   => current_user_can( Capabilities::EDIT_MESSAGES ),
					'can_delete' => current_user_can( Capabilities::DELETE_MESSAGES ),
				)
			);
			return;
		}

		Template::render(
			'inbox/inbox',
			array(
				'view'       => 'detail',
				'message'    => $message,
				'activities' => ActivityRepository::get_for_message( $id ),
				'can_reply'  => current_user_can( Capabilities::SEND_REPLIES ),
				'can_edit'   => current_user_can( Capabilities::EDIT_MESSAGES ),
				'can_delete' => current_user_can( Capabilities::DELETE_MESSAGES ),
			)
		);
	}

	/**
	 * Reads and sanitizes the list screen's filters from `$_GET`, matching
	 * {@see \CF7AIInbox\Admin\AjaxController::list_messages()}'s own
	 * sanitization exactly (that AJAX action still exists, for the CSV
	 * export's "current filters, no pagination cap" request).
	 *
	 * @return array<string, mixed>
	 */
	private function get_filters_from_request(): array {
		$period = isset( $_GET['period'] ) ? sanitize_key( wp_unslash( $_GET['period'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter, not a state-changing request.

		if ( ! in_array( $period, self::PERIODS, true ) ) {
			$period = '';
		}

		return array(
			'status'           => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter, not a state-changing request.
			'priority'         => isset( $_GET['priority'] ) ? sanitize_key( wp_unslash( $_GET['priority'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'category'         => isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'form'             => isset( $_GET['form'] ) ? sanitize_text_field( wp_unslash( $_GET['form'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'confidence_below' => isset( $_GET['confidence_below'] ) && '' !== $_GET['confidence_below'] ? absint( wp_unslash( $_GET['confidence_below'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'search'           => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'period'           => $period,
		);
	}

	/**
	 * Adds this page's AJAX nonce to the shared `cf7aiInboxAdmin` JS object.
	 *
	 * @param array<string, mixed> $data Data collected so far (at least `ajaxUrl`).
	 * @param string               $slug Slug of the admin page currently being enqueued for.
	 *
	 * @return array<string, mixed>
	 */
	public function localize_data( array $data, string $slug ): array {
		if ( 'cf7ai-inbox' !== $slug ) {
			return $data;
		}

		$data['nonce'] = wp_create_nonce( AjaxController::INBOX_NONCE_ACTION );

		return $data;
	}

	/**
	 * Every AI category that exists anywhere on this site — the union of
	 * every {@see \CF7AIInbox\CF7\CategoryTaxonomy} term any form has ever
	 * been assigned — for the "Category" filter dropdown. A stored
	 * message's `category` column is a plain string set at analysis time,
	 * independent of a form's current category list, so the filter needs
	 * to offer every category that could possibly appear across every
	 * message, not just one form's current set.
	 *
	 * No fallback list: a site where no form has had a category added yet
	 * genuinely has none to filter by, so this returns an empty array and
	 * the dropdown just offers "All categories".
	 *
	 * @return string[]
	 */
	private function get_category_names(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => CategoryTaxonomy::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'names',
			)
		);

		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * Every real Contact Form 7 form's title, for the "Form" filter dropdown
	 * (matches the mockup's hardcoded `<option>` list, but built from the
	 * forms that actually exist on this site).
	 *
	 * @return string[]
	 */
	private function get_form_titles(): array {
		if ( ! class_exists( '\WPCF7_ContactForm' ) ) {
			return array();
		}

		return array_map(
			static function ( $form ) {
				return $form->title();
			},
			\WPCF7_ContactForm::find()
		);
	}
}
