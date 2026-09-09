<?php
/**
 * Plugin bootstrap.
 *
 * @package Baukasten
 */

namespace Baukasten;

defined( 'ABSPATH' ) || exit;

/**
 * Wires up the plugin: translations, the addon registry and the admin surface.
 */
final class Loader {

	/**
	 * Admin controller.
	 *
	 * @var Admin|null
	 */
	private ?Admin $admin = null;

	/**
	 * Registers all hooks.
	 *
	 * @return void
	 */
	public function run(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( Addons::class, 'collect' ), 20 );

		if ( is_admin() ) {
			add_action( 'admin_init', array( Installer::class, 'maybe_upgrade' ) );

			$this->admin = new Admin();
			$this->admin->register();
		}
	}

	/**
	 * Loads the plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'baukasten',
			false,
			dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Returns the admin controller, if the request is an admin request.
	 *
	 * @return Admin|null Admin controller.
	 */
	public function admin(): ?Admin {
		return $this->admin;
	}
}
