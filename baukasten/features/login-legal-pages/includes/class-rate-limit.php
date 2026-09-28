<?php
/**
 * Rate limit for sign-ins and password resets.
 *
 * @package Baukasten\LoginLegalPages
 */

namespace Baukasten\LoginLegalPages;

defined( 'ABSPATH' ) || exit;

/**
 * Locks an IP address out after repeated failed sign-ins.
 *
 * The limit sits on authentication, not on a URL. `/login/`, `wp-login.php`,
 * XML-RPC and application passwords all end up there, so the limit cannot be
 * sidestepped by posting to a different address. Two Factor 0.16 reports a
 * wrong second-factor code through `wp_login_failed` as well, so those count
 * towards the same limit.
 *
 * - Five failures within fifteen minutes lock the address out for fifteen
 *   minutes. Every further lockout within 24 hours doubles that, up to a day.
 * - While locked, even the correct password is refused. `authenticate` runs
 *   at priority 100 for that: `wp_authenticate_username_password()` on 20
 *   ignores an earlier error and returns the user when the password matches.
 * - Only per address, never per user name. A lock on the account would let
 *   anybody lock the administrator out by typing their name.
 * - The message is the same whether the user name exists or not.
 * - The address comes from `REMOTE_ADDR` only. Behind a reverse proxy the
 *   `baukasten/login/client_ip` filter can supply the real one.
 * - It is stored hashed, as part of a transient's name, and forgotten when
 *   the transient expires. There is no `sleep()`: a sleeping request ties up
 *   a PHP worker, which helps an attacker more than it hurts them.
 *
 * Password reset requests are limited separately, to three per hour and
 * address, because each one sends an email.
 */
final class Rate_Limit {

	/**
	 * Option holding the settings.
	 */
	const OPTION = 'baukasten_login_rate_limit';

	/**
	 * Transient prefix of the failure counter.
	 */
	const PREFIX_FAILURES = 'baukasten_login_rl_';

	/**
	 * Transient prefix of an active lockout.
	 */
	const PREFIX_LOCK = 'baukasten_login_lock_';

	/**
	 * Transient prefix of the number of lockouts in the last 24 hours.
	 */
	const PREFIX_STRIKES = 'baukasten_login_strikes_';

	/**
	 * Transient prefix of the password reset counter.
	 */
	const PREFIX_RESET = 'baukasten_login_reset_';

	/**
	 * Password reset requests allowed per address and hour.
	 */
	const RESET_LIMIT = 3;

	/**
	 * Error code of a refused attempt.
	 */
	const ERROR_CODE = 'baukasten_login_locked';

	/**
	 * Returns the default settings.
	 *
	 * @return array{enabled: bool, threshold: int, window: int, lockout: int} Defaults, times in minutes.
	 */
	public static function defaults(): array {
		return array(
			'enabled'   => true,
			'threshold' => 5,
			'window'    => 15,
			'lockout'   => 15,
		);
	}

	/**
	 * Returns the stored settings, merged over the defaults.
	 *
	 * @return array{enabled: bool, threshold: int, window: int, lockout: int} Settings.
	 */
	public static function settings(): array {
		$stored = get_option( self::OPTION, array() );

		return self::sanitize( array_merge( self::defaults(), is_array( $stored ) ? $stored : array() ) );
	}

	/**
	 * Normalises a settings array.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array{enabled: bool, threshold: int, window: int, lockout: int} Settings.
	 */
	public static function sanitize( array $input ): array {
		return array(
			'enabled'   => ! empty( $input['enabled'] ),
			'threshold' => min( 100, max( 1, absint( $input['threshold'] ?? 5 ) ) ),
			'window'    => min( 1440, max( 1, absint( $input['window'] ?? 15 ) ) ),
			'lockout'   => min( 1440, max( 1, absint( $input['lockout'] ?? 15 ) ) ),
		);
	}

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_login_failed', array( __CLASS__, 'record_failure' ) );
		add_action( 'application_password_failed_authentication', array( __CLASS__, 'record_failure' ) );
		add_filter( 'authenticate', array( __CLASS__, 'refuse_when_locked' ), 100, 3 );
		add_action( 'wp_authenticate_application_password_errors', array( __CLASS__, 'refuse_application_password' ) );
		add_action( 'wp_login', array( __CLASS__, 'forget_failures' ) );
		add_action( 'lostpassword_post', array( __CLASS__, 'limit_password_resets' ) );
	}

	/**
	 * Counts a failed sign-in, and locks the address out at the threshold.
	 *
	 * @return void
	 */
	public static function record_failure(): void {
		$settings = self::settings();
		$key      = self::key();

		if ( ! $settings['enabled'] || '' === $key || self::locked_until( $key ) > 0 ) {
			return;
		}

		$failures = (int) get_transient( self::PREFIX_FAILURES . $key ) + 1;

		if ( $failures < $settings['threshold'] ) {
			// The window runs from the latest failure, which is stricter than
			// a fixed window and needs no second timestamp.
			set_transient( self::PREFIX_FAILURES . $key, $failures, $settings['window'] * MINUTE_IN_SECONDS );

			return;
		}

		delete_transient( self::PREFIX_FAILURES . $key );

		$strikes  = (int) get_transient( self::PREFIX_STRIKES . $key ) + 1;
		$duration = (int) min( DAY_IN_SECONDS, $settings['lockout'] * MINUTE_IN_SECONDS * ( 2 ** ( $strikes - 1 ) ) );

		set_transient( self::PREFIX_STRIKES . $key, $strikes, DAY_IN_SECONDS );
		set_transient( self::PREFIX_LOCK . $key, time() + $duration, $duration );
	}

	/**
	 * Refuses every sign-in from a locked address, the correct one included.
	 *
	 * @param \WP_User|\WP_Error|null $user     Result so far.
	 * @param string                  $username Submitted user name.
	 * @param string                  $password Submitted password.
	 * @return \WP_User|\WP_Error|null The result, or the lockout error.
	 */
	public static function refuse_when_locked( $user, $username, $password ) {
		// wp-login.php runs the authentication chain on every visit, even
		// with nothing submitted. There is nothing to refuse then.
		if ( '' === (string) $username && '' === (string) $password ) {
			return $user;
		}

		$error = self::lock_error();

		return null === $error ? $user : $error;
	}

	/**
	 * Refuses an application password from a locked address.
	 *
	 * Application passwords are checked by calling the authentication
	 * function directly, past the `authenticate` filter.
	 *
	 * @param \WP_Error $error Errors collected so far.
	 * @return void
	 */
	public static function refuse_application_password( $error ): void {
		$lock = self::lock_error();

		if ( null !== $lock && $error instanceof \WP_Error ) {
			$error->add( self::ERROR_CODE, $lock->get_error_message(), $lock->get_error_data() );
		}
	}

	/**
	 * Clears the failure counter after a successful sign-in.
	 *
	 * The strike count stays, so a lockout shortly after still doubles.
	 *
	 * @return void
	 */
	public static function forget_failures(): void {
		$key = self::key();

		if ( '' !== $key ) {
			delete_transient( self::PREFIX_FAILURES . $key );
		}
	}

	/**
	 * Allows three password reset requests per address and hour.
	 *
	 * @param \WP_Error $errors Errors of the reset request.
	 * @return void
	 */
	public static function limit_password_resets( $errors ): void {
		$key = self::key();

		if ( ! self::settings()['enabled'] || '' === $key || ! $errors instanceof \WP_Error ) {
			return;
		}

		$count = (int) get_transient( self::PREFIX_RESET . $key );

		if ( $count >= self::RESET_LIMIT ) {
			$errors->add(
				self::ERROR_CODE,
				__( 'Too many password reset requests. Please try again later.', 'baukasten' ),
				array( 'status' => 429 )
			);

			return;
		}

		set_transient( self::PREFIX_RESET . $key, $count + 1, HOUR_IN_SECONDS );
	}

	/**
	 * The lockout error for this address, if it is locked out.
	 *
	 * @return \WP_Error|null Error, or null when the address may sign in.
	 */
	public static function lock_error(): ?\WP_Error {
		$key = self::key();

		if ( ! self::settings()['enabled'] || '' === $key || 0 === self::locked_until( $key ) ) {
			return null;
		}

		return new \WP_Error(
			self::ERROR_CODE,
			__( 'Too many failed login attempts. Please try again later.', 'baukasten' ),
			array( 'status' => 429 )
		);
	}

	/**
	 * When the lockout of an address ends.
	 *
	 * @param string $key Hashed address.
	 * @return int Unix timestamp, or 0 when it is not locked out.
	 */
	public static function locked_until( string $key ): int {
		$until = (int) get_transient( self::PREFIX_LOCK . $key );

		return $until > time() ? $until : 0;
	}

	/**
	 * Hashed client address, used in transient names.
	 *
	 * @return string Hash, or an empty string when there is no address.
	 */
	public static function key(): string {
		$ip = self::client_ip();

		return '' === $ip ? '' : substr( wp_hash( $ip ), 0, 32 );
	}

	/**
	 * The client address.
	 *
	 * @return string IP address, or an empty string.
	 */
	public static function client_ip(): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: '';

		/**
		 * Filters the address the rate limit counts against.
		 *
		 * Only for a site behind a reverse proxy you control: return the
		 * address from its forwarding header when `$remote` is the proxy.
		 * Trusting `X-Forwarded-For` from anybody would let every request
		 * choose its own address.
		 *
		 * @since 1.0.0
		 *
		 * @param string $ip     Address the limit uses.
		 * @param string $remote `REMOTE_ADDR` as received.
		 */
		$ip = (string) apply_filters( 'baukasten/login/client_ip', $remote, $remote );

		return false === filter_var( $ip, FILTER_VALIDATE_IP ) ? '' : $ip;
	}
}
