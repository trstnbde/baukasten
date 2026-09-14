<?php
/**
 * The challenge store.
 *
 * @package Baukasten\TwoFactor
 */

namespace Baukasten\TwoFactor;

defined( 'ABSPATH' ) || exit;

/**
 * Every challenge, and every state change one can undergo.
 *
 * A challenge is a question — "is this login you?" — waiting for an answer from
 * a second browser. Two things make a table the right home for it rather than a
 * transient:
 *
 * - A transient offers no compare-and-swap. Two tabs answering at the same
 *   moment, or a double click, would both succeed and the last writer would
 *   win. Here every transition is a single `UPDATE ... WHERE status = <old>`
 *   and the row count decides: the first writer wins, everyone else sees zero
 *   affected rows and is told the question was already answered.
 * - The rate limit needs to count recent challenges, which means keeping them
 *   past their decision. They are purged, not deleted on the spot.
 *
 * The plaintext challenge id exists in exactly two places: the response that
 * created it, and the hidden field on the login form. The table stores only its
 * SHA-256 hash, so read access to the database does not reveal which logins are
 * in flight.
 */
final class Challenges {

	/**
	 * Option holding the installed schema version.
	 */
	const OPTION_DB_VERSION = 'baukasten_2fa_db_version';

	/**
	 * Current schema version.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Waiting for an answer.
	 */
	const STATUS_PENDING = 'pending';

	/**
	 * Answered yes, not yet used to finish the login.
	 */
	const STATUS_APPROVED = 'approved';

	/**
	 * Answered no.
	 */
	const STATUS_DENIED = 'denied';

	/**
	 * Used to finish a login. Terminal, and what makes a replay fail.
	 */
	const STATUS_CONSUMED = 'consumed';

	/**
	 * Replaced by a newer challenge for the same user.
	 */
	const STATUS_SUPERSEDED = 'superseded';

	/**
	 * Returns the table name.
	 *
	 * `base_prefix`, not `prefix`: users are network-wide and the admin bar
	 * lives on whichever site the approving tab happens to be on. A per-site
	 * table would hide a login to site B from a tab sitting on site A.
	 *
	 * @return string Table name.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->base_prefix . 'baukasten_2fa_challenges';
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
			challenge_hash char(64) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			blog_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			context varchar(20) NOT NULL DEFAULT 'login',
			nonce_hash varchar(64) NOT NULL DEFAULT '',
			origin_token varchar(64) NOT NULL DEFAULT '',
			ip_label varchar(45) NOT NULL DEFAULT '',
			agent_label varchar(100) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			expires_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			decided_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY challenge_hash (challenge_hash),
			KEY user_status (user_id,status,expires_at),
			KEY user_created (user_id,created_at),
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
	 * Opens a challenge for a user.
	 *
	 * Any challenge the user already had open is superseded first. Reusing an
	 * open one instead would be wrong: every render of the two-factor form
	 * rotates Two Factor's login nonce, so a reused challenge would be bound to
	 * a nonce that no longer exists. The invariant this keeps is that a user has
	 * at most one open challenge and it always belongs to the newest render.
	 *
	 * @param int    $user_id      User the login belongs to.
	 * @param string $context      Either `login` or `revalidate`.
	 * @param string $nonce_hash   Hash of Two Factor's current login nonce.
	 * @param string $origin_token Hashed session token of the browser being challenged, if any.
	 * @return array{id: string, expires_at: int}|null The plaintext id and expiry, or null when refused.
	 */
	public static function create( int $user_id, string $context, string $nonce_hash, string $origin_token ): ?array {
		global $wpdb;

		if ( $user_id <= 0 ) {
			return null;
		}

		self::purge();

		if ( ! self::within_rate_limit( $user_id ) ) {
			return null;
		}

		self::supersede_pending( $user_id );

		// No fallback on purpose. Two Factor degrades to a hash of the user id
		// and microtime when `random_bytes()` throws; that is a guessable
		// secret, and this id is the only thing guarding the status endpoint.
		try {
			$plaintext = bin2hex( random_bytes( 32 ) );
		} catch ( \Exception $e ) {
			return null;
		}

		$now     = time();
		$expires = $now + Settings::expiry();

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			self::table(),
			array(
				'challenge_hash' => self::hash( $plaintext ),
				'user_id'        => $user_id,
				'blog_id'        => get_current_blog_id(),
				'status'         => self::STATUS_PENDING,
				'context'        => 'revalidate' === $context ? 'revalidate' : 'login',
				'nonce_hash'     => substr( $nonce_hash, 0, 64 ),
				'origin_token'   => substr( $origin_token, 0, 64 ),
				'ip_label'       => Context::ip_label(),
				'agent_label'    => Context::agent_label(),
				'created_at'     => gmdate( 'Y-m-d H:i:s', $now ),
				'expires_at'     => gmdate( 'Y-m-d H:i:s', $expires ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return null;
		}

		return array(
			'id'         => $plaintext,
			'expires_at' => $expires,
		);
	}

	/**
	 * Returns a challenge by its plaintext id.
	 *
	 * Expiry is applied on read, so a row nobody has purged yet still reports
	 * as expired rather than as open.
	 *
	 * @param string $plaintext Plaintext challenge id.
	 * @return array<string, mixed>|null The row with its effective status, or null.
	 */
	public static function find( string $plaintext ): ?array {
		global $wpdb;

		if ( ! self::is_valid_id( $plaintext ) ) {
			return null;
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE challenge_hash = %s",
				self::hash( $plaintext )
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		return self::with_effective_status( $row );
	}

	/**
	 * Returns the user's open challenge, if there is one.
	 *
	 * A challenge raised by the very session that is asking is left out. That
	 * is what stops a signed-in session from approving its own revalidation
	 * request from the tab next door, which would make the second factor
	 * meaningless.
	 *
	 * @param int    $user_id      User to look up.
	 * @param string $own_token    Hashed session token of the asking session.
	 * @return array<string, mixed>|null The row, or null when nothing is open.
	 */
	public static function pending_for_user( int $user_id, string $own_token ): ?array {
		global $wpdb;

		if ( $user_id <= 0 ) {
			return null;
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE user_id = %d AND status = %s AND expires_at > %s ORDER BY id DESC LIMIT 5",
				$user_id,
				self::STATUS_PENDING,
				gmdate( 'Y-m-d H:i:s' )
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return null;
		}

		foreach ( $rows as $row ) {
			if ( '' !== $own_token && '' !== (string) $row['origin_token']
				&& hash_equals( (string) $row['origin_token'], $own_token ) ) {
				continue;
			}

			return $row;
		}

		return null;
	}

	/**
	 * Answers a challenge.
	 *
	 * The single valid way from `pending` to a decision. `status = 'pending'`
	 * sits in the WHERE clause, so two simultaneous answers cannot both win:
	 * the first changes one row, the second changes none. The caller reads the
	 * return value to tell "you decided this" from "somebody already had".
	 *
	 * Takes the row id rather than the challenge id because the browser doing
	 * the answering is not the one signing in and never sees the challenge id —
	 * that secret belongs to the waiting login page alone.
	 *
	 * @param int  $row_id  Row to answer.
	 * @param int  $user_id User who is answering.
	 * @param bool $approve True to approve, false to deny.
	 * @return bool True when this call was the one that decided it.
	 */
	public static function decide( int $row_id, int $user_id, bool $approve ): bool {
		global $wpdb;

		if ( $row_id <= 0 || $user_id <= 0 ) {
			return false;
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$changed = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$table} SET status = %s, decided_at = %s
				 WHERE id = %d AND user_id = %d AND status = %s AND expires_at > %s",
				$approve ? self::STATUS_APPROVED : self::STATUS_DENIED,
				gmdate( 'Y-m-d H:i:s' ),
				$row_id,
				$user_id,
				self::STATUS_PENDING,
				gmdate( 'Y-m-d H:i:s' )
			)
		);

		return 1 === (int) $changed;
	}

	/**
	 * Spends an approved challenge to finish a login.
	 *
	 * Binds the id that came back with the login form, never "any approved row
	 * for this user": an approved challenge the user abandoned when they
	 * switched to another method must not be able to satisfy a later attempt.
	 *
	 * The row moves to `consumed` rather than being deleted, so replaying the
	 * same POST finds a row that no longer matches `status = 'approved'`.
	 *
	 * @param string $plaintext  Plaintext challenge id from the form.
	 * @param int    $user_id    User logging in.
	 * @param string $nonce_hash Hash of Two Factor's current login nonce.
	 * @return bool True when the login may proceed.
	 */
	public static function consume( string $plaintext, int $user_id, string $nonce_hash ): bool {
		global $wpdb;

		if ( ! self::is_valid_id( $plaintext ) || $user_id <= 0 || '' === $nonce_hash ) {
			return false;
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$changed = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$table} SET status = %s
				 WHERE challenge_hash = %s AND user_id = %d AND status = %s
				   AND nonce_hash = %s AND expires_at > %s",
				self::STATUS_CONSUMED,
				self::hash( $plaintext ),
				$user_id,
				self::STATUS_APPROVED,
				substr( $nonce_hash, 0, 64 ),
				gmdate( 'Y-m-d H:i:s' )
			)
		);

		return 1 === (int) $changed;
	}

	/**
	 * Marks the user's open challenges as replaced.
	 *
	 * @param int $user_id User whose challenges to supersede.
	 * @return void
	 */
	public static function supersede_pending( int $user_id ): void {
		global $wpdb;

		if ( $user_id <= 0 ) {
			return;
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$table} SET status = %s WHERE user_id = %d AND status = %s",
				self::STATUS_SUPERSEDED,
				$user_id,
				self::STATUS_PENDING
			)
		);
	}

	/**
	 * Cancels one open challenge.
	 *
	 * @param string $plaintext Plaintext challenge id.
	 * @param int    $user_id   Owner.
	 * @return void
	 */
	public static function cancel( string $plaintext, int $user_id ): void {
		global $wpdb;

		if ( ! self::is_valid_id( $plaintext ) || $user_id <= 0 ) {
			return;
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$table} SET status = %s WHERE challenge_hash = %s AND user_id = %d AND status = %s",
				self::STATUS_SUPERSEDED,
				self::hash( $plaintext ),
				$user_id,
				self::STATUS_PENDING
			)
		);
	}

	/**
	 * Deletes rows that are past the retention window.
	 *
	 * Runs opportunistically whenever a challenge is created, and once a day
	 * from cron — the opportunistic path never runs for a user who stops
	 * signing in, and their rows would otherwise sit there indefinitely.
	 *
	 * @return int Rows deleted.
	 */
	public static function purge(): int {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$table} WHERE created_at < %s LIMIT 500",
				gmdate( 'Y-m-d H:i:s', time() - Settings::retention() )
			)
		);

		return (int) $deleted;
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

	/**
	 * Whether a string could be a challenge id at all.
	 *
	 * Checked before every query, so a malformed id never reaches the database.
	 *
	 * @param string $plaintext Candidate id.
	 * @return bool True when well formed.
	 */
	public static function is_valid_id( string $plaintext ): bool {
		return 1 === preg_match( '/^[0-9a-f]{64}$/', $plaintext );
	}

	/**
	 * Hashes a plaintext challenge id for storage and lookup.
	 *
	 * @param string $plaintext Plaintext id.
	 * @return string Hex digest.
	 */
	private static function hash( string $plaintext ): string {
		return hash( 'sha256', $plaintext );
	}

	/**
	 * Reports a row that has run out of time as expired.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed> Row with `status` reflecting the clock.
	 */
	private static function with_effective_status( array $row ): array {
		if ( self::STATUS_PENDING === $row['status'] && strtotime( (string) $row['expires_at'] . ' UTC' ) <= time() ) {
			$row['status'] = 'expired';
		}

		return $row;
	}

	/**
	 * Whether this user may open another challenge right now.
	 *
	 * Superseded rows are left out of the count: reloading the login form
	 * should not eat the user's allowance.
	 *
	 * @param int $user_id User to check.
	 * @return bool True when within the limit.
	 */
	private static function within_rate_limit( int $user_id ): bool {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND created_at > %s AND status <> %s",
				$user_id,
				gmdate( 'Y-m-d H:i:s', time() - Settings::rate_window() ),
				self::STATUS_SUPERSEDED
			)
		);

		return $count < Settings::rate_limit();
	}
}
