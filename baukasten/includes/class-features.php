<?php
/**
 * The features built into the core.
 *
 * @package Baukasten
 */

namespace Baukasten;

defined( 'ABSPATH' ) || exit;

/**
 * Loads Consent Blocking Engine, Content Visibility and Login Legal Pages.
 *
 * Until they were merged into the core these were three separate plugins.
 * Their code moved unchanged into `features/<slug>/`, and each keeps its own
 * namespace, options and hooks. What this class adds is the handover from a
 * site that still has one of the old plugins installed:
 *
 * - WordPress loads `baukasten-consent-blocking-engine/…` before
 *   `baukasten/baukasten.php`, because `-` sorts before `/`. If an old plugin
 *   is still active, its classes already exist by the time the core loads,
 *   and loading the feature a second time would be a fatal error. So the
 *   feature is skipped for that request, and the old plugin keeps working.
 * - On the next admin request the old plugin is deactivated, and from then
 *   on the feature takes over.
 * - The old plugin must not be deleted through the Plugins screen: its own
 *   `uninstall.php` would delete the very data the feature now uses. A
 *   notice says so, and its delete link is removed.
 */
final class Features {

	/**
	 * The built-in features: slug => namespace.
	 *
	 * The slug is the directory under `features/` and, prefixed with
	 * `baukasten-`, the slug of the plugin it used to be.
	 *
	 * @var array<string, string>
	 */
	const FEATURES = array(
		'content-visibility'      => 'ContentVisibility',
		'login-legal-pages'       => 'LoginLegalPages',
		'consent-blocking-engine' => 'ConsentBlockingEngine',
	);

	/**
	 * Features that are running this request.
	 *
	 * @var string[]
	 */
	private static array $loaded = array();

	/**
	 * Old plugins whose code was loaded instead of the feature.
	 *
	 * @var string[]
	 */
	private static array $superseded = array();

	/**
	 * Includes every feature whose old plugin has not already been loaded.
	 *
	 * @return void
	 */
	public static function load(): void {
		foreach ( self::FEATURES as $slug => $ns ) {
			if ( defined( 'Baukasten\\' . $ns . '\\PLUGIN_FILE' ) ) {
				self::$superseded[] = $slug;
				continue;
			}

			require_once PLUGIN_DIR . 'features/' . $slug . '/bootstrap.php';

			self::$loaded[] = $slug;
		}

		add_action( 'admin_init', array( __CLASS__, 'retire_legacy_plugins' ), 1 );
		add_action( 'admin_notices', array( __CLASS__, 'render_legacy_notice' ) );

		foreach ( array_keys( self::FEATURES ) as $slug ) {
			add_filter( 'plugin_action_links_' . self::legacy_file( $slug ), array( __CLASS__, 'remove_delete_link' ) );
		}
	}

	/**
	 * Whether a feature is running this request.
	 *
	 * @param string $slug Feature slug.
	 * @return bool True if loaded.
	 */
	public static function is_loaded( string $slug ): bool {
		return in_array( $slug, self::$loaded, true );
	}

	/**
	 * Whether every feature is running this request.
	 *
	 * @return bool False while an old plugin stands in for one of them.
	 */
	public static function all_loaded(): bool {
		return array() === self::$superseded;
	}

	/**
	 * Runs `install()` of every loaded feature.
	 *
	 * @return void
	 */
	public static function install(): void {
		foreach ( self::FEATURES as $slug => $ns ) {
			$callback = 'Baukasten\\' . $ns . '\\install';

			if ( self::is_loaded( $slug ) && function_exists( $callback ) ) {
				$callback();
			}
		}
	}

	/**
	 * Runs `activate()` of every loaded feature that has one.
	 *
	 * @return void
	 */
	public static function activate(): void {
		foreach ( self::FEATURES as $slug => $ns ) {
			$callback = 'Baukasten\\' . $ns . '\\activate';

			if ( self::is_loaded( $slug ) && function_exists( $callback ) ) {
				$callback();
			}
		}
	}

	/**
	 * Runs `deactivate()` of every loaded feature that has one.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		foreach ( self::FEATURES as $slug => $ns ) {
			$callback = 'Baukasten\\' . $ns . '\\deactivate';

			if ( self::is_loaded( $slug ) && function_exists( $callback ) ) {
				$callback();
			}
		}
	}

	/**
	 * Plugin file of the separate plugin a feature used to be.
	 *
	 * @param string $slug Feature slug.
	 * @return string Plugin file relative to the plugins directory.
	 */
	public static function legacy_file( string $slug ): string {
		return 'baukasten-' . $slug . '/baukasten-' . $slug . '.php';
	}

	/**
	 * Old plugins still present in the plugins directory.
	 *
	 * @return string[] Plugin files.
	 */
	public static function legacy_installed(): array {
		$found = array();

		foreach ( array_keys( self::FEATURES ) as $slug ) {
			if ( file_exists( WP_PLUGIN_DIR . '/' . self::legacy_file( $slug ) ) ) {
				$found[] = self::legacy_file( $slug );
			}
		}

		return $found;
	}

	/**
	 * Deactivates old plugins the core has replaced.
	 *
	 * Silent, so their deactivation hooks do not run: nothing they would
	 * undo should be undone, because the feature carries on with it.
	 *
	 * @return void
	 */
	public static function retire_legacy_plugins(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$active = array_filter(
			self::legacy_installed(),
			static fn ( string $file ): bool => is_plugin_active( $file )
		);

		if ( array() === $active ) {
			return;
		}

		deactivate_plugins( $active, true );
	}

	/**
	 * Tells an administrator to remove the old plugin folders by hand.
	 *
	 * @return void
	 */
	public static function render_legacy_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$files = self::legacy_installed();

		if ( array() === $files ) {
			return;
		}

		$names = array_map( static fn ( string $file ): string => dirname( $file ), $files );

		printf(
			'<div class="notice notice-warning"><p>%s</p><p><code>%s</code></p></div>',
			esc_html__( 'These plugins are now part of Baukasten - Privacy Toolkit and have been deactivated. Remove their folders from wp-content/plugins by FTP or SSH. Do not delete them on the Plugins screen: their uninstall routine would delete the settings and data the toolkit now uses.', 'baukasten' ),
			esc_html( implode( ', ', $names ) )
		);
	}

	/**
	 * Removes the delete link of an old plugin.
	 *
	 * @param array<string, string> $links Action links.
	 * @return array<string, string> Links without "delete".
	 */
	public static function remove_delete_link( array $links ): array {
		unset( $links['delete'] );

		return $links;
	}
}
