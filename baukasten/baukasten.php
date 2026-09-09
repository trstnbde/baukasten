<?php
/**
 * Plugin Name:       Baukasten - Privacy Toolkit
 * Plugin URI:        https://github.com/trstnbde/baukasten
 * Description:       A settings hub for the Baukasten addons. Install the addons you need as separate plugins and configure them all in one place.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      8.0
 * Author:            Torsten B.
 * Author URI:        https://github.com/trstnbde
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       baukasten
 * Domain Path:       /languages
 *
 * @package Baukasten
 */

namespace Baukasten;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.0.0';

/**
 * Absolute path to the plugin directory, with trailing slash.
 */
define( 'Baukasten\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL to the plugin directory, with trailing slash.
 */
define( 'Baukasten\PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Absolute path to the main plugin file.
 */
define( 'Baukasten\PLUGIN_FILE', __FILE__ );

/**
 * Capability required to reach the Baukasten settings screen.
 */
const CAPABILITY = 'manage_baukasten';

require_once PLUGIN_DIR . 'includes/class-autoloader.php';

Autoloader::register();

register_activation_hook( __FILE__, array( Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Installer::class, 'deactivate' ) );

/**
 * Returns the shared plugin loader instance.
 *
 * @return Loader The loader singleton.
 */
function plugin(): Loader {
	static $loader = null;

	if ( null === $loader ) {
		$loader = new Loader();
	}

	return $loader;
}

plugin()->run();
