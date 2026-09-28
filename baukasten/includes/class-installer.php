<?php
/**
 * Activation, deactivation and upgrade routines.
 *
 * @package Baukasten
 */

namespace Baukasten;

defined( 'ABSPATH' ) || exit;

/**
 * Sets up options and capabilities, and clears out the old module system.
 */
final class Installer {

	/**
	 * Option holding plugin settings.
	 */
	const OPTION_SETTINGS = 'baukasten_settings';

	/**
	 * Option holding the installed schema version.
	 */
	const OPTION_VERSION = 'baukasten_version';

	/**
	 * Option holding the structure version.
	 *
	 * Bumped when the shape of the plugin changes without its version number
	 * changing, as when the three addons were merged into the core. Checked on
	 * every request (on `wp_loaded`), not only in the admin, because the
	 * features need their options and table before the first visitor arrives.
	 */
	const OPTION_STRUCTURE = 'baukasten_structure';

	/**
	 * Current structure version. 2: features merged into the core.
	 */
	const STRUCTURE = 2;

	/**
	 * Capability used before 1.0.0, when addons were uploaded as modules.
	 */
	const LEGACY_CAPABILITY = 'manage_baukasten_modules';

	/**
	 * Options written by the module system that 1.0.0 replaced.
	 *
	 * @var string[]
	 */
	private const LEGACY_OPTIONS = array(
		'baukasten_active_modules',
		'baukasten_paused_modules',
	);

	/**
	 * Directory the module system unpacked uploads into, below the uploads dir.
	 */
	private const LEGACY_MODULES_DIRNAME = 'baukasten-modules';

	/**
	 * Roles that receive the capability on activation.
	 *
	 * @var string[]
	 */
	private const PRIVILEGED_ROLES = array( 'administrator' );

	/**
	 * Runs on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		add_option( self::OPTION_SETTINGS, self::default_settings(), '', false );

		update_option( self::OPTION_VERSION, VERSION, false );

		self::add_capabilities();
		self::remove_legacy_data();

		Features::activate();
		Features::install();

		if ( Features::all_loaded() ) {
			update_option( self::OPTION_STRUCTURE, self::STRUCTURE, true );
		}
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * Settings are left untouched, so deactivating and reactivating the plugin
	 * restores the previous state. Only deletion cleans up.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		self::remove_capabilities();

		Features::deactivate();
	}

	/**
	 * Returns the default settings.
	 *
	 * @return array<string, mixed> Default settings.
	 */
	public static function default_settings(): array {
		return array();
	}

	/**
	 * Grants the capability to the privileged roles.
	 *
	 * @return void
	 */
	public static function add_capabilities(): void {
		foreach ( self::PRIVILEGED_ROLES as $role_name ) {
			$role = get_role( $role_name );

			if ( $role instanceof \WP_Role ) {
				$role->add_cap( CAPABILITY );
			}
		}
	}

	/**
	 * Revokes the capability from all roles.
	 *
	 * @return void
	 */
	public static function remove_capabilities(): void {
		$roles = wp_roles();

		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = get_role( $role_name );

			if ( $role instanceof \WP_Role ) {
				$role->remove_cap( CAPABILITY );
				$role->remove_cap( self::LEGACY_CAPABILITY );
			}
		}
	}

	/**
	 * Makes sure the environment is in shape on every load.
	 *
	 * Handles the case where a site was updated without running the activation
	 * hook, for example after a manual file upload.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$installed = get_option( self::OPTION_VERSION, '' );

		if ( VERSION === $installed ) {
			return;
		}

		self::add_capabilities();
		self::remove_legacy_data();

		update_option( self::OPTION_VERSION, VERSION, false );
	}

	/**
	 * Installs the features when the structure version is behind.
	 *
	 * Runs on `wp_loaded`. A site updated by replacing the plugin files
	 * never fires the activation hook, and the version number did not change
	 * when the features were merged in, so this is what brings a site that
	 * had the separate plugins over. Every step is idempotent.
	 *
	 * Not earlier: Content Visibility's migration asks which post types exist,
	 * and before `init` there are none — it would migrate nothing, and cache
	 * an empty list of managed types for the rest of the request.
	 *
	 * @return void
	 */
	public static function maybe_install_features(): void {
		if ( (int) get_option( self::OPTION_STRUCTURE, 0 ) >= self::STRUCTURE ) {
			return;
		}

		Features::install();
		self::add_capabilities();

		// A feature skipped because its old plugin is still active has not
		// been installed yet. Leave the version behind so it is next time.
		if ( Features::all_loaded() ) {
			update_option( self::OPTION_STRUCTURE, self::STRUCTURE, true );
		}
	}

	/**
	 * Removes everything the pre 1.0.0 module system left behind.
	 *
	 * Modules became ordinary plugins in 1.0.0. The old capability, the two
	 * options tracking active and paused modules, and the upload directory
	 * under `wp-content/uploads/` have no meaning any more. The files are
	 * deleted last and only after the options are gone, so a failure to write
	 * cannot leave the site pointing at modules that are no longer loaded.
	 *
	 * @return void
	 */
	public static function remove_legacy_data(): void {
		foreach ( self::LEGACY_OPTIONS as $option ) {
			delete_option( $option );
		}

		foreach ( array_keys( wp_roles()->roles ) as $role_name ) {
			$role = get_role( $role_name );

			if ( $role instanceof \WP_Role ) {
				$role->remove_cap( self::LEGACY_CAPABILITY );
			}
		}

		self::remove_legacy_modules_dir();
	}

	/**
	 * Deletes the old module upload directory, if it is still there.
	 *
	 * @return void
	 */
	private static function remove_legacy_modules_dir(): void {
		$uploads = wp_get_upload_dir();

		if ( empty( $uploads['basedir'] ) ) {
			return;
		}

		$dir = trailingslashit( (string) $uploads['basedir'] ) . self::LEGACY_MODULES_DIRNAME;

		if ( ! is_dir( $dir ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		global $wp_filesystem;

		if ( WP_Filesystem() && $wp_filesystem ) {
			$wp_filesystem->delete( $dir, true );
		}
	}
}
