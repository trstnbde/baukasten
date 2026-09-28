<?php
/**
 * WP-CLI command.
 *
 * @package Baukasten\FormPrivacy
 */

namespace Baukasten\FormPrivacy;

defined( 'ABSPATH' ) || exit;

/**
 * `wp baukasten form-privacy clean`.
 */
final class CLI {

	/**
	 * Registers the command when WP-CLI is running.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! class_exists( '\\WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'baukasten form-privacy clean', array( __CLASS__, 'clean' ) );
	}

	/**
	 * Cleans up what Flamingo stored before this plugin was active.
	 *
	 * Deletes every address book contact and contact tag for good, reduces the
	 * metadata of every stored message to the whitelist, and applies the
	 * retention period right away. The same routine as the button on the
	 * settings tab. Deleted data cannot be brought back.
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
	 *     wp baukasten form-privacy clean
	 *     wp baukasten form-privacy clean --yes
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public static function clean( array $args, array $assoc_args ): void {
		unset( $args );

		\WP_CLI::confirm(
			'This deletes every Flamingo contact and every message older than the retention period, and strips metadata from the rest. It cannot be undone. Continue?',
			$assoc_args
		);

		$rows = Cleanup::run();

		\WP_CLI\Utils\format_items(
			'table',
			array_map(
				static fn ( array $row ): array => array(
					'step'   => $row['label'],
					'result' => $row['changed'] ? 'changed' : 'no change',
					'detail' => $row['detail'],
				),
				$rows
			),
			array( 'step', 'result', 'detail' )
		);

		$changed = count( array_filter( array_column( $rows, 'changed' ) ) );

		if ( 0 === $changed ) {
			\WP_CLI::success( 'Nothing to do — every step reported no change.' );

			return;
		}

		\WP_CLI::success( sprintf( '%d of %d steps changed something.', $changed, count( $rows ) ) );
	}
}
