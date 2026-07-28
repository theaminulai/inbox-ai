<?php
/**
 * admin-ajax.php handlers for every admin page's write/read actions.
 *
 * @package InboxAI\Admin
 */

namespace InboxAI\Admin;

use InboxAI\Admin\Ajax\ContactsAjaxController;
use InboxAI\Admin\Ajax\InboxAjaxController;
use InboxAI\Admin\Ajax\SettingsAjaxController;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AjaxController
 */
final class AjaxController {

	/**
	 * Registers every `wp_ajax_*` hook across every per-page controller.
	 *
	 * @return void
	 */
	public function init(): void {
		( new SettingsAjaxController() )->init();
		( new InboxAjaxController() )->init();
		( new ContactsAjaxController() )->init();
	}
}
