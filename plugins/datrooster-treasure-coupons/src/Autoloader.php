<?php
/**
 * Minimal PSR-4 style autoloader for the plugin.
 *
 * @package DatRoosterTreasureCoupons
 */

namespace DatRoosterTreasureCoupons;

defined( 'ABSPATH' ) || exit;

final class Autoloader {
	/**
	 * Namespace prefix handled by this autoloader.
	 */
	private const PREFIX = 'DatRoosterTreasureCoupons\\';

	/**
	 * Registers the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'autoload' ) );
	}

	/**
	 * Loads plugin classes from the src directory.
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	private static function autoload( string $class_name ): void {
		if ( ! str_starts_with( $class_name, self::PREFIX ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( self::PREFIX ) );
		$class_file     = DATROOSTER_TREASURE_COUPONS_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $class_file ) ) {
			require_once $class_file;
		}
	}
}
