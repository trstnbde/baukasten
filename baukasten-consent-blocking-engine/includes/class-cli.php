<?php
/**
 * WP-CLI commands.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes the hardening routine to WP-CLI.
 *
 * The same routine the button in the settings tab calls, so a deployment
 * script and an administrator clicking around end up in the same state.
 */
final class CLI {

	/**
	 * Registers the commands.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! class_exists( '\\WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'baukasten harden', array( __CLASS__, 'harden' ) );
	}

	/**
	 * Applies the privacy hardening defaults.
	 *
	 * Closes comments and pings on all existing content, writes the privacy
	 * related core options, empties the credentials on Settings > Connectors
	 * and switches on every blocking measure.
	 *
	 * Safe to run repeatedly: each step reports whether it changed anything.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp baukasten harden
	 *     wp baukasten harden --yes
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public static function harden( array $args, array $assoc_args ): void {
		unset( $args );

		\WP_CLI::confirm(
			'This closes comments on every entry, tells search engines to stay away and empties all connector credentials. Continue?',
			$assoc_args
		);

		$rows = Hardening::run_privacy_hardening_bulk_action();

		\WP_CLI\Utils\format_items(
			'table',
			array_map(
				static function ( array $row ): array {
					return array(
						'step'   => $row['label'],
						'result' => $row['changed'] ? 'changed' : 'no change',
						'detail' => $row['detail'],
					);
				},
				$rows
			),
			array( 'step', 'result', 'detail' )
		);

		$changed = count(
			array_filter(
				$rows,
				static function ( array $row ): bool {
					return $row['changed'];
				}
			)
		);

		if ( 0 === $changed ) {
			\WP_CLI::success( 'Nothing to do, the site was already hardened.' );

			return;
		}

		\WP_CLI::success( sprintf( '%d of %d steps changed something.', $changed, count( $rows ) ) );
	}
}
