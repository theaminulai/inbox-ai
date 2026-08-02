<?php
/**
 * Loads a plain-PHP view template from `includes/Templates/`.
 *
 * @package InboxAI\Support
 */

namespace InboxAI\Support;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Template
 *
 * Every admin page's markup lives in its own file under
 * `includes/Templates/` rather than inline inside a controller/page class
 * (see {@see \InboxAI\Admin\Menu}). This is the single, shared way any
 * class in the plugin loads one of those files.
 */
final class Template {

	/**
	 * Renders (echoes) a template file, with the given variables available
	 * to it by name.
	 *
	 * Templates are plain PHP files under `includes/Templates/` — no
	 * templating engine, matching the rest of this codebase. They are
	 * trusted, plugin-authored files (never user-uploaded or otherwise
	 * externally influenced), so extracting `$vars` into local scope here
	 * is safe: the only thing that varies between calls is the data being
	 * displayed, which the template is responsible for escaping at output
	 * (`esc_html()`/`esc_attr()`/`esc_url()`) exactly as it would if the
	 * markup were written inline.
	 *
	 * @param string               $template Template filename, without
	 *                                        the `.php` extension, relative
	 *                                        to `includes/Templates/`.
	 * @param array<string, mixed> $vars     Variables to expose to the
	 *                                        template, keyed by name.
	 *
	 * @return void
	 */
	public static function render( string $template, array $vars = array() ): void {
		$file = INBOXAI_PATH . 'includes/Templates/' . $template . '.php';

		if ( ! is_readable( $file ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- trusted, plugin-authored template files only; see class docblock.
		extract( $vars );

		include $file;
	}

	/**
	 * Same as {@see self::render()}, but captures and returns the output
	 * instead of echoing it — used where a template's markup is needed as an
	 * HTML string rather than printed directly, e.g. the AI Inbox List
	 * Submission Detail screen's AJAX polling (see
	 * {@see \InboxAI\Admin\Ajax\InboxAjaxController::get_message()}), which
	 * re-renders the AI Analysis card/timeline server-side and hands the
	 * markup back to `detail.js` to swap in, rather than re-implementing
	 * that formatting twice (once in PHP, once in JS).
	 *
	 * @param string               $template Same as {@see self::render()}.
	 * @param array<string, mixed> $vars     Same as {@see self::render()}.
	 *
	 * @return string
	 */
	public static function render_to_string( string $template, array $vars = array() ): string {
		ob_start();
		self::render( $template, $vars );

		return (string) ob_get_clean();
	}
}
