<?php
/**
 * Addon registry.
 *
 * @package Baukasten
 */

namespace Baukasten;

defined( 'ABSPATH' ) || exit;

/**
 * Collects the addons that want a tab on the Baukasten settings screen.
 *
 * Addons are ordinary WordPress plugins. WordPress installs, updates,
 * activates and deletes them; this class does nothing but remember which of
 * them asked for a tab, and with what.
 *
 * An addon registers itself on the `baukasten/register_addons` action:
 *
 *     add_action( 'baukasten/register_addons', function () {
 *         Baukasten\Addons::register( array(
 *             'id'          => 'my-addon',
 *             'title'       => __( 'My Addon', 'my-addon' ),
 *             'plugin_file' => MY_ADDON_FILE,
 *             'render'      => 'my_addon_render_tab',
 *         ) );
 *     } );
 *
 * Nothing has to be inherited or implemented. An addon that only checks
 * `class_exists( 'Baukasten\Addons' )` before calling this keeps working when
 * the core plugin is not installed.
 */
final class Addons {

	/**
	 * Registered addons, keyed by id.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $addons = array();

	/**
	 * Whether the registration action has already fired.
	 *
	 * @var bool
	 */
	private static bool $collected = false;

	/**
	 * Fires the registration action, at most once per request.
	 *
	 * Called on `init` at priority 20: late enough that every addon has booted
	 * and loaded its translations, so a tab title can be translated without
	 * tripping the "translation loading was triggered too early" notice
	 * WordPress 6.7 added. Also called defensively from every reader below, so
	 * the order can never matter.
	 *
	 * @return void
	 */
	public static function collect(): void {
		if ( self::$collected ) {
			return;
		}

		self::$collected = true;

		/**
		 * Fires when Baukasten collects the addons that want a settings tab.
		 *
		 * @since 1.0.0
		 */
		do_action( 'baukasten/register_addons' );
	}

	/**
	 * Registers an addon.
	 *
	 * Accepted arguments:
	 *
	 * - `id`          string   Required. Tab slug, run through `sanitize_key()`.
	 * - `title`       string   Required. Tab label, already translated.
	 * - `render`      callable Required. Prints the tab contents.
	 * - `plugin_file` string   Optional. Main plugin file, used for the version
	 *                          and description shown on the overview tab.
	 * - `description` string   Optional. Overrides the plugin header description.
	 * - `capability`  string   Optional. Checked in addition to the capability
	 *                          guarding the settings page itself, so it can only
	 *                          narrow access, never widen it. Defaults to the
	 *                          core capability.
	 * - `position`    int      Optional. Sort order, default 10.
	 *
	 * @param array<string, mixed> $args Addon arguments.
	 * @return bool True when the addon was registered.
	 */
	public static function register( array $args ): bool {
		$id     = isset( $args['id'] ) ? sanitize_key( (string) $args['id'] ) : '';
		$title  = isset( $args['title'] ) ? (string) $args['title'] : '';
		$render = $args['render'] ?? null;

		if ( '' === $id || '' === $title || ! is_callable( $render ) ) {
			return false;
		}

		self::$addons[ $id ] = array(
			'id'          => $id,
			'title'       => $title,
			'render'      => $render,
			'plugin_file' => isset( $args['plugin_file'] ) ? (string) $args['plugin_file'] : '',
			'description' => isset( $args['description'] ) ? (string) $args['description'] : '',
			'capability'  => isset( $args['capability'] ) ? (string) $args['capability'] : CAPABILITY,
			'position'    => isset( $args['position'] ) ? (int) $args['position'] : 10,
		);

		return true;
	}

	/**
	 * Returns all registered addons, sorted by position and then title.
	 *
	 * @return array<string, array<string, mixed>> Addons keyed by id.
	 */
	public static function all(): array {
		self::collect();

		$addons = self::$addons;

		uasort(
			$addons,
			static function ( array $a, array $b ): int {
				if ( $a['position'] === $b['position'] ) {
					return strnatcasecmp( (string) $a['title'], (string) $b['title'] );
				}

				return $a['position'] <=> $b['position'];
			}
		);

		return $addons;
	}

	/**
	 * Returns a single addon.
	 *
	 * @param string $id Addon id.
	 * @return array<string, mixed>|null Addon data, or null when unknown.
	 */
	public static function get( string $id ): ?array {
		self::collect();

		return self::$addons[ sanitize_key( $id ) ] ?? null;
	}

	/**
	 * Whether an addon is registered.
	 *
	 * @param string $id Addon id.
	 * @return bool True when registered.
	 */
	public static function has( string $id ): bool {
		return null !== self::get( $id );
	}

	/**
	 * Reads version and description from an addon's plugin header.
	 *
	 * @param array<string, mixed> $addon Addon data.
	 * @return array{version: string, description: string} Header values, possibly empty.
	 */
	public static function plugin_data( array $addon ): array {
		$data = array(
			'version'     => '',
			'description' => isset( $addon['description'] ) ? (string) $addon['description'] : '',
		);

		$file = isset( $addon['plugin_file'] ) ? (string) $addon['plugin_file'] : '';

		if ( '' === $file || ! is_readable( $file ) ) {
			return $data;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Translated, not marked up: the description shown here should follow
		// the dashboard language like every other string on the screen.
		$header = get_plugin_data( $file, false, true );

		$data['version'] = isset( $header['Version'] ) ? (string) $header['Version'] : '';

		if ( '' === $data['description'] && ! empty( $header['Description'] ) ) {
			$data['description'] = (string) $header['Description'];
		}

		return $data;
	}
}
