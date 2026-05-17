<?php
/**
 * Minimal PSR-4 style autoloader for the plugin.
 *
 * @package DatRooster\PartialPayments
 */

namespace DatRooster\PartialPayments;

defined( 'ABSPATH' ) || exit;

final class Autoloader {
	/**
	 * Namespace prefix handled by this autoloader.
	 */
	private const PREFIX = 'DatRooster\\PartialPayments\\';

	/**
	 * Registers the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'autoload' ) );
	}

	/**
	 * Loads plugin classes from the src directory.
	 *
	 * @param string $class Fully qualified class name.
	 */
	private static function autoload( string $class ): void {
		if ( ! str_starts_with( $class, self::PREFIX ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( self::PREFIX ) );
		$class_file     = DATROOSTER_PP_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $class_file ) ) {
			require_once $class_file;
		}
	}
}
