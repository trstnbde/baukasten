<?php
/**
 * Class autoloader.
 *
 * @package Baukasten
 */

namespace Baukasten;

defined( 'ABSPATH' ) || exit;

/**
 * Maps namespaced class names to WordPress style file names.
 *
 * `Baukasten\Module_Registry` resolves to `includes/class-module-registry.php`,
 * interfaces to `includes/interface-*.php` and abstract classes to
 * `includes/abstract-*.php`.
 */
final class Autoloader {

	/**
	 * Registers the autoloader with SPL.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Loads a class file for the given fully qualified class name.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public static function load( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$relative = strtolower( str_replace( array( '_', '\\' ), array( '-', '/' ), $relative ) );

		foreach ( array( 'class-', 'interface-', 'abstract-' ) as $type ) {
			$path = __DIR__ . '/' . $type . $relative . '.php';

			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
}
