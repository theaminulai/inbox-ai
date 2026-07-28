<?php
/**
 * Shared nonce + capability gate for every per-page AJAX controller.
 *
 * @package InboxAI\Admin\Ajax
 */

namespace InboxAI\Admin\Ajax;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BaseAjaxController
 *
 * Originally one shared `AjaxController` handled every admin page's
 * `admin-ajax.php` actions in a single, ever-growing file. Split into one
 * controller class per admin page (`SettingsAjaxController`,
 * `InboxAjaxController`, `ContactsAjaxController` — one more join per future
 * page, e.g. Analytics) once that file became unwieldy. Two things every one
 * of them still shares live here instead of being copy-pasted three times
 * over: the nonce + capability check every action opens with
 * ({@see self::check()}), and the same handful of "read one `$_POST` field,
 * sanitize it, fall back to a default" one-liners every action's first few
 * lines were otherwise repeating verbatim (`post_int()`, `post_string()`,
 * `post_key()`, `post_email()`, `post_html()`, `post_bool()`,
 * `post_json_array()`, plus the `page`/`per_page` pagination pair every list
 * action reads identically).
 *
 * Every `post_*()` helper below is only ever called from an action method
 * that already ran {@see self::check()} first — that's what actually
 * verifies the request's nonce. phpcs can't trace that verification through
 * a method call, hence the one `NonceVerification` ignore comment per helper
 * below, instead of one on every single `$_POST` read site across three
 * files.
 */
abstract class BaseAjaxController {

	/**
	 * Shared nonce + capability gate. Sends a JSON error and stops execution
	 * if either check fails.
	 *
	 * @param string $capability   Required capability.
	 * @param string $nonce_action Which page's nonce this request must
	 *                             carry — e.g.
	 *                             {@see SettingsAjaxController::SETTINGS_NONCE_ACTION},
	 *                             {@see InboxAjaxController::INBOX_NONCE_ACTION},
	 *                             {@see ContactsAjaxController::CONTACTS_NONCE_ACTION}.
	 *
	 * @return void
	 */
	protected function check( string $capability, string $nonce_action ): void {
		check_ajax_referer( $nonce_action, 'nonce' );

		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to do this.', 'inbox-ai' ) ),
				403
			);
		}
	}

	/**
	 * Reads an integer `$_POST` field via `absint()` (never negative),
	 * optionally clamped to a minimum — the `page`/`per_page`/`id`/`offset`
	 * idiom every list and single-row action needs.
	 *
	 * @param string   $key     `$_POST` key.
	 * @param int      $default Value when the key is missing.
	 * @param int|null $min     Clamp the result to at least this value, or
	 *                          `null` (default) for no clamp.
	 *
	 * @return int
	 */
	protected function post_int( string $key, int $default = 0, ?int $min = null ): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see class docblock: every post_*() helper is only ever called after self::check() has already verified the request's nonce.
		$value = isset( $_POST[ $key ] ) ? absint( wp_unslash( $_POST[ $key ] ) ) : $default;

		return null === $min ? $value : max( $min, $value );
	}

	/**
	 * Reads an integer `$_POST` field the same way as {@see self::post_int()},
	 * except a missing or empty-string value is preserved as `''` rather than
	 * coerced to `0` — the one filter (`confidence_below`) where "not
	 * provided" and "provided as zero" need to stay distinguishable all the
	 * way into {@see \InboxAI\Database\MessageRepository::build_where()}.
	 *
	 * @param string $key `$_POST` key.
	 *
	 * @return int|string
	 */
	protected function post_int_or_empty( string $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see class docblock.
		return isset( $_POST[ $key ] ) && '' !== $_POST[ $key ] ? absint( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	/**
	 * Reads a free-text `$_POST` field via `sanitize_text_field()` — search
	 * boxes, names, and similar. Pass `$default = null` for the one place
	 * (`InboxAjaxController::send_reply()`) that needs to tell "not provided"
	 * apart from "provided as an empty string".
	 *
	 * @param string      $key     `$_POST` key.
	 * @param string|null $default Value when the key is missing.
	 *
	 * @return string|null
	 */
	protected function post_string( string $key, ?string $default = '' ): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see class docblock.
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $default;
	}

	/**
	 * Reads a `$_POST` field via `sanitize_key()` — status/priority/period/tab
	 * values and other fixed-vocabulary slugs.
	 *
	 * @param string $key     `$_POST` key.
	 * @param string $default Value when the key is missing.
	 *
	 * @return string
	 */
	protected function post_key( string $key, string $default = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see class docblock.
		return isset( $_POST[ $key ] ) ? sanitize_key( wp_unslash( $_POST[ $key ] ) ) : $default;
	}

	/**
	 * Reads a `$_POST` field via `sanitize_email()`.
	 *
	 * @param string $key     `$_POST` key.
	 * @param string $default Value when the key is missing.
	 *
	 * @return string
	 */
	protected function post_email( string $key, string $default = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see class docblock.
		return isset( $_POST[ $key ] ) ? sanitize_email( wp_unslash( $_POST[ $key ] ) ) : $default;
	}

	/**
	 * Reads a rich-text `$_POST` field via `wp_kses_post()` — reply subjects'
	 * bodies, the only HTML this plugin ever accepts from an admin request.
	 * Pass `$default = null` where "not provided" must stay distinguishable
	 * from "provided as an empty string" (again, `send_reply()`).
	 *
	 * @param string      $key     `$_POST` key.
	 * @param string|null $default Value when the key is missing.
	 *
	 * @return string|null
	 */
	protected function post_html( string $key, ?string $default = '' ): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- see class docblock; wp_unslash() is applied explicitly below, then wp_kses_post() sanitizes the HTML immediately after.
		return isset( $_POST[ $key ] ) ? wp_kses_post( wp_unslash( $_POST[ $key ] ) ) : $default;
	}

	/**
	 * Reads a checkbox-style `$_POST` field (present + truthy = `true`) —
	 * e.g. the Import wizard's `run_ai` flag.
	 *
	 * @param string $key `$_POST` key.
	 *
	 * @return bool
	 */
	protected function post_bool( string $key ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see class docblock.
		return ! empty( $_POST[ $key ] );
	}

	/**
	 * Reads a JSON-encoded object `$_POST` field, decoded to a plain array
	 * (never trusted or echoed as raw JSON) — `save_settings()`'s `values`
	 * field. Sanitizing each field inside the decoded array is still the
	 * caller's job (see `SettingsAjaxController::save_settings()`); this only
	 * guarantees an array shape instead of `null`/a scalar.
	 *
	 * @param string $key `$_POST` key.
	 *
	 * @return array<string, mixed>
	 */
	protected function post_json_array( string $key ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see class docblock; wp_unslash() is applied explicitly before decoding, and every field of the decoded array is sanitized individually by the caller.
		$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';

		$values = json_decode( (string) $raw, true );

		return is_array( $values ) ? $values : array();
	}

	/**
	 * The 1-indexed `page` `$_POST` field every list action reads
	 * identically.
	 *
	 * @return int
	 */
	protected function post_page(): int {
		return $this->post_int( 'page', 1, 1 );
	}

	/**
	 * The `per_page` `$_POST` field every list action reads identically,
	 * aside from `InboxAjaxController::list_messages()`'s own default (also
	 * `20`, so this is only a parameter for clarity, not because the two
	 * pages actually disagree).
	 *
	 * @param int $default Value when the key is missing.
	 *
	 * @return int
	 */
	protected function post_per_page( int $default = 20 ): int {
		return $this->post_int( 'per_page', $default, 1 );
	}
}
