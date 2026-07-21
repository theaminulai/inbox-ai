<?php
/**
 * PSR-4-style autoloader for the CF7AIInbox namespace.
 *
 * Maps the `CF7AIInbox\` namespace root to the `includes/` directory. Every
 * sub-namespace segment corresponds directly to a subdirectory, and the
 * final segment to a file of the same name — matching the `psr-4` mapping
 * declared in composer.json, so the plugin works identically whether or not
 * `composer install` has been run.
 *
 * Example:
 *   CF7AIInbox\Database\Migrator     => includes/Database/Migrator.php
 *   CF7AIInbox\Security\Capabilities => includes/Security/Capabilities.php
 *
 * @package CF7AIInbox
 */

namespace CF7AIInbox;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Autoloader
 *
 * Registers a PSR-4-compatible autoloader for the CF7AIInbox namespace. No
 * third-party dependency manager is required to run the plugin.
 */
final class Autoloader {

	/**
	 * The root namespace prefix this autoloader is responsible for.
	 *
	 * @var string
	 */
	private const NAMESPACE_PREFIX = 'CF7AIInbox\\';

	/**
	 * Absolute path to the `includes/` directory the namespace maps to.
	 *
	 * @var string
	 */
	private static string $base_dir = '';

	/**
	 * Registers this autoloader with PHP's SPL autoloader stack.
	 *
	 * Must be called exactly once, from the main plugin bootstrap file.
	 *
	 * @return void
	 */
	public static function register(): void {
		self::$base_dir = __DIR__ . DIRECTORY_SEPARATOR;

		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Loads the class file corresponding to a fully-qualified class name.
	 *
	 * Called automatically by PHP whenever an undefined class, interface,
	 * or trait belonging to the `CF7AIInbox\` namespace is referenced.
	 *
	 * @param string $class_name Fully-qualified class name.
	 *
	 * @return void
	 */
	public static function load( string $class_name ): void {
		$prefix_length = strlen( self::NAMESPACE_PREFIX );

		// Ignore classes outside our namespace — let other autoloaders handle them.
		if ( strncmp( self::NAMESPACE_PREFIX, $class_name, $prefix_length ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class_name, $prefix_length );
		$relative_path  = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
		$file           = self::$base_dir . $relative_path;

		// Never throw from within the autoloader; let PHP raise the
		// standard "class not found" error if the file is missing.
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
}
