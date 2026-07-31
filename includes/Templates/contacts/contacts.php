<?php
/**
 * Contacts List page shell.
 *
 * Mirrors `includes/Templates/inbox/inbox.php`'s split: page chrome (the
 * `.wrap`/`#main` wrapper, the shared row-menu element, the toast container)
 * lives here, while the actual page-header/toolbar/table markup lives in
 * `contacts/list.php`. Contacts has only one view — no list/detail routing
 * like the AI Inbox List's shell does — so this file always renders exactly
 * one thing.
 *
 * Expects, via {@see \InboxAI\Support\Template::render()}:
 *
 * @var array<int, array<string, mixed>> $contacts   Current page's rows —
 *                                                     see `ContactsListPage::render()`.
 * @var int                              $total      Total matching contacts.
 * @var int                              $page       1-indexed current page.
 * @var int                              $per_page   Rows per page.
 * @var array<string, mixed>             $filters    Current filter values.
 * @var string[]                         $categories Every AI category that exists site-wide.
 * @var bool                             $can_delete Whether the current user holds `DELETE_MESSAGES`.
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap inboxai-wrap">
	<div class="inboxai-main" id="main" data-page="contacts" data-can-delete="<?php echo esc_attr( $can_delete ? '1' : '0' ); ?>">
		<?php
		\InboxAI\Support\Template::render(
			'contacts/list',
			array(
				'contacts'   => $contacts,
				'total'      => $total,
				'page'       => $page,
				'per_page'   => $per_page,
				'filters'    => $filters,
				'categories' => $categories,
				'can_delete' => $can_delete,
			)
		);
		?>
	</div>
</div>

<div class="inboxai-row-menu" id="row-menu" data-open-for=""></div>

<div id="toast-container"></div>
