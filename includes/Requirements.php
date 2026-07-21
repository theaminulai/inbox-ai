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
	 * Whether every requirement is currently met.
	 *
	 * @return bool
	 */
	public static function are_met(): bool {
		return array() === self::get_errors();
	}

	/**
	 * Returns a list of human-readable error messages for every unmet
	 * requirement. An empty array means all requirements are satisfied.
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
			$errors[] = __( 'CF7 AI Inbox requires Contact Form 7 to be installed and active.', 'cf7-ai-inbox' );
		}

		return $errors;
	}

	/**
	 * Whether Contact Form 7 is installed and active.
	 *
	 * @return bool
	 */
	public static function is_cf7_active(): bool {
		return defined( 'WPCF7_VERSION' );
	}
}
