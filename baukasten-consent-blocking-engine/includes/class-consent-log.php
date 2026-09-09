<?php
/**
 * The consent log.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * An append-only record of the decisions visitors made.
 *
 * Article 7(1) GDPR asks the controller to be able to demonstrate that consent
 * was given, so a decision is never updated in place: every change writes a
 * new row and the previous one stays. Reading the table top to bottom for one
 * anonymous id gives the full history.
 *
 * There is no personal data in it. No IP address, no user id, no user agent —
 * only a random id generated in the visitor's browser cookie, the categories,
 * the policy version those categories were agreed against, and the time.
 */
final class Consent_Log {

	/**
	 * Option holding the installed schema version.
	 */
	const OPTION_DB_VERSION = 'baukasten_consent_blocking_engine_db_version';

	/**
	 * Current schema version.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Returns the table name including the site prefix.
	 *
	 * @return string Table name.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'baukasten_consent_log';
	}

	/**
	 * Creates or migrates the table.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			anonymous_id varchar(32) NOT NULL DEFAULT '',
			categories text NOT NULL,
			policy_version varchar(50) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY anonymous_id (anonymous_id),
			KEY created_at (created_at)
		) {$collate};";

		dbDelta( $sql );

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
	}

	/**
	 * Creates or migrates the table when the stored version is behind.
	 *
	 * @return void
	 */
	public static function maybe_install(): void {
		if ( self::DB_VERSION === get_option( self::OPTION_DB_VERSION, '' ) ) {
			return;
		}

		self::install();
	}

	/**
	 * Appends a decision.
	 *
	 * @param string   $anonymous_id Anonymous id from the visitor's cookie.
	 * @param string[] $categories   Categories the visitor allowed.
	 * @param string   $policy       Policy version in force.
	 * @return bool True when the row was written.
	 */
	public static function record( string $anonymous_id, array $categories, string $policy ): bool {
		global $wpdb;

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			self::table(),
			array(
				'anonymous_id'   => substr( sanitize_key( $anonymous_id ), 0, 32 ),
				'categories'     => (string) wp_json_encode( array_values( $categories ) ),
				'policy_version' => substr( $policy, 0, 50 ),
				'created_at'     => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		return false !== $inserted;
	}

	/**
	 * Returns the number of rows.
	 *
	 * @return int Row count.
	 */
	public static function count(): int {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Returns a page of rows, newest first.
	 *
	 * @param int $limit  Maximum rows.
	 * @param int $offset Rows to skip.
	 * @return array<int, array<string, mixed>> Rows.
	 */
	public static function rows( int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, anonymous_id, categories, policy_version, created_at FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
				max( 1, $limit ),
				max( 0, $offset )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Streams the whole log as CSV.
	 *
	 * @return void
	 */
	public static function export_csv(): void {
		$out = fopen( 'php://output', 'w' );

		if ( false === $out ) {
			return;
		}

		fputcsv( $out, array( 'id', 'anonymous_id', 'categories', 'policy_version', 'created_at' ) );

		foreach ( self::batches() as $row ) {
			fputcsv(
				$out,
				array(
					$row['id'],
					$row['anonymous_id'],
					implode( ' ', (array) json_decode( (string) $row['categories'], true ) ),
					$row['policy_version'],
					$row['created_at'],
				)
			);
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * Streams the whole log as JSON.
	 *
	 * @return void
	 */
	public static function export_json(): void {
		echo '[';

		$first = true;

		foreach ( self::batches() as $row ) {
			$row['categories'] = (array) json_decode( (string) $row['categories'], true );

			echo $first ? '' : ',';
			echo wp_json_encode( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			$first = false;
		}

		echo ']';
	}

	/**
	 * Yields every row in batches, so a large log does not exhaust memory.
	 *
	 * @return \Generator<array<string, mixed>> Rows.
	 */
	private static function batches(): \Generator {
		$offset = 0;
		$size   = 500;

		do {
			$rows  = self::rows( $size, $offset );
			$found = count( $rows );

			foreach ( $rows as $row ) {
				yield $row;
			}

			$offset += $size;
		} while ( $found === $size );
	}

	/**
	 * Drops the table.
	 *
	 * @return void
	 */
	public static function drop(): void {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

		delete_option( self::OPTION_DB_VERSION );
	}
}
