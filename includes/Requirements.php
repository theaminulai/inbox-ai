<?php
/**
 * Runtime environment/dependency checks.
 *
 * @package CF7AIInbox
 */

namespace CF7AIInbox;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Requirements
 *
 * Centralizes the checks that decide whether the plugin is safe to run.
 * WordPress core already enforces the "Requires at least" / "Requires PHP" /
 * "Requires Plugins" plugin-header fields before activation on WP 6.5+, but
 * this class provides a defense-in-depth runtime check for two cases core
 * doesn't cover: sites running an older WordPress core, and installs where
 * Contact Form 7 was deactivated *after* this plugin was already active.
 */
final class Requirements {

	/**
	 * Minimum supported PHP version.
	 *
	 * @var string
	 */
	public const MIN_PHP_VERSION = '8.1';

	/**
	 * Minimum supported WordPress version.
	 *
	 * @var string
	 */
	public const MIN_WP_VERSION = '6.7';

	/**
	 * Contact Form 7's own plugin file, relative to the plugins directory —
	 * the standard slug/file WordPress.org hosts it under. Used to build
	 * the Install/Activate action links in {@see \CF7AIInbox\Plugin}'s
	 * dependency notice, and to tell "not installed at all" apart from
	 * "installed but not active" for that notice.
	 *
	 * @var string
	 */
	public const CF7_PLUGIN_FILE = 'contact-form-7/wp-contact-form-7.php';

	/**
	 * Whether every requirement is currently met.
	 *
	 * Deliberately does none of the string-building {@see self::get_errors()}
	 * does — just the bare `version_compare()`/`defined()` checks — so it's
	 * safe to call as early as `plugins_loaded` (where {@see \CF7AIInbox\Plugin::init()}
	 * calls it). `get_errors()` calls `__()`, and WordPress 6.7+ logs a
	 * "translation loading triggered too early" notice if any `__()` call
	 * for this plugin's text domain happens before the `init` hook — so
	 * that method must only ever be called from code that itself runs at
	 * `init` or later (in practice, from inside an `admin_notices` callback).
	 *
	 * @return bool
	 */
	public static function are_met(): bool {
		if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
			return false;
		}

		global $wp_version;

		if ( ! empty( $wp_version ) && version_compare( $wp_version, self::MIN_WP_VERSION, '<' ) ) {
			return false;
		}

		return self::is_cf7_active();
	}

	/**
	 * Returns a list of human-readable error messages for every unmet
	 * requirement. An empty array means all requirements are satisfied.
	 *
	 * The Contact Form 7 message (if present) is always exactly
	 * {@see self::cf7_missing_message()} — {@see \CF7AIInbox\Plugin}'s notice
	 * renderer compares against that constant (rather than guessing from
	 * substring matching) to swap it out for an actionable Install/Activate
	 * button instead of plain text.
	 *
	 * Calls `__()` internally — only ever call this from `init` or later
	 * (see {@see self::are_met()}'s docblock). {@see \CF7AIInbox\Plugin} only
	 * calls it from inside its `admin_notices` callback for exactly this
	 * reason.
	 *
	 * @return string[]
	 */
	public static function get_errors(): array {
		$errors = array();

		if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'CF7 AI Inbox requires PHP %1$s or newer. This site is running PHP %2$s.', 'cf7-ai-inbox' ),
				self::MIN_PHP_VERSION,
				PHP_VERSION
			);
		}

		global $wp_version;

		if ( ! empty( $wp_version ) && version_compare( $wp_version, self::MIN_WP_VERSION, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required WordPress version, 2: current WordPress version. */
				__( 'CF7 AI Inbox requires WordPress %1$s or newer. This site is running WordPress %2$s.', 'cf7-ai-inbox' ),
				self::MIN_WP_VERSION,
				$wp_version
			);
		}

		if ( ! self::is_cf7_active() ) {
			$errors[] = self::cf7_missing_message();
		}

		return $errors;
	}

	/**
	 * The exact message {@see self::get_errors()} uses for a missing/inactive
	 * Contact Form 7 — a stable string {@see \CF7AIInbox\Plugin} can compare
	 * against instead of fragile substring matching.
	 *
	 * @return string
	 */
	public static function cf7_missing_message(): string {
		return __( 'CF7 AI Inbox requires Contact Form 7 to be installed and active.', 'cf7-ai-inbox' );
	}

	/**
	 * Whether Contact Form 7 is installed and active.
	 *
	 * @return bool
	 */
	public static function is_cf7_active(): bool {
		return defined( 'WPCF7_VERSION' );
	}

	/**
	 * Whether Contact Form 7 is present in the plugins directory at all
	 * (installed), regardless of whether it's currently active. Distinct
	 * from {@see self::is_cf7_active()} so the dependency notice can offer
	 * an "Activate" button instead of an "Install" button when it's already
	 * sitting there deactivated.
	 *
	 * @return bool
	 */
	public static function is_cf7_installed(): bool {
		if ( self::is_cf7_active() ) {
			return true;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return array_key_exists( self::CF7_PLUGIN_FILE, get_plugins() );
	}
}
