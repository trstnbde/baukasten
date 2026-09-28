<?php
/**
 * PHPUnit bootstrap for the integration tests.
 *
 * Runs inside the wp-env "tests-cli" container, where WP_TESTS_DIR points at
 * the WordPress test library and the plugins from .wp-env.json are installed.
 * The test suite starts every run from a fresh database, so the plugins are
 * loaded here rather than activated, and installed once by hand.
 *
 * @package Baukasten\Tests
 */

$baukasten_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $baukasten_tests_dir ) {
	$baukasten_tests_dir = '/wordpress-phpunit';
}

require_once __DIR__ . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $baukasten_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		$plugins = WP_CONTENT_DIR . '/plugins/';

		require_once $plugins . 'contact-form-7/wp-contact-form-7.php';
		require_once $plugins . 'flamingo/flamingo.php';
		require_once $plugins . 'baukasten/baukasten.php';
		require_once $plugins . 'baukasten-business-cards/baukasten-business-cards.php';
		require_once $plugins . 'baukasten-form-privacy/baukasten-form-privacy.php';
	}
);

tests_add_filter(
	'plugins_loaded',
	static function (): void {
		Baukasten\Installer::activate();
	},
	1
);

require $baukasten_tests_dir . '/includes/bootstrap.php';
