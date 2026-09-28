<?php
/**
 * The sign-in rate limit (Recommendation 14).
 *
 * @package Baukasten\Tests
 */

use Baukasten\LoginLegalPages\Rate_Limit;

/**
 * Failures lock the address out, even for the correct password.
 */
class RateLimitTest extends WP_UnitTestCase {

	/**
	 * A user with a known password.
	 *
	 * @var int
	 */
	private int $user_id;

	/**
	 * Creates the user and picks a documentation address.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->user_id          = self::factory()->user->create(
			array(
				'user_login' => 'ratelimit',
				'user_pass'  => 'correct horse',
			)
		);
		$_SERVER['REMOTE_ADDR'] = '203.0.113.' . wp_rand( 1, 254 );
	}

	/**
	 * Five failures, on any login address, lock the sixth attempt out.
	 */
	public function test_lock_after_threshold_even_with_correct_password(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertWPError( wp_authenticate( 'ratelimit', 'wrong' ) );
		}

		$result = wp_authenticate( 'ratelimit', 'correct horse' );

		$this->assertWPError( $result );
		$this->assertSame( Rate_Limit::ERROR_CODE, $result->get_error_code() );
		$this->assertSame( 429, $result->get_error_data()['status'] );
	}

	/**
	 * Below the threshold, the correct password works and resets the counter.
	 */
	public function test_success_below_threshold(): void {
		for ( $i = 0; $i < 4; $i++ ) {
			wp_authenticate( 'ratelimit', 'wrong' );
		}

		$user = wp_authenticate( 'ratelimit', 'correct horse' );

		$this->assertInstanceOf( WP_User::class, $user );

		do_action( 'wp_login', 'ratelimit', $user );

		$this->assertFalse( get_transient( Rate_Limit::PREFIX_FAILURES . Rate_Limit::key() ) );
	}

	/**
	 * The lock follows the address, not the user name.
	 */
	public function test_other_address_is_not_locked(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			wp_authenticate( 'ratelimit', 'wrong' );
		}

		$_SERVER['REMOTE_ADDR'] = '198.51.100.7';

		$this->assertInstanceOf( WP_User::class, wp_authenticate( 'ratelimit', 'correct horse' ) );
	}

	/**
	 * The same message for an existing and an unknown user name.
	 */
	public function test_message_does_not_reveal_the_user(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			wp_authenticate( 'nobody-' . $i, 'wrong' );
		}

		$known   = wp_authenticate( 'ratelimit', 'correct horse' );
		$unknown = wp_authenticate( 'nobody', 'wrong' );

		$this->assertSame( $known->get_error_message(), $unknown->get_error_message() );
	}

	/**
	 * A second lockout within a day lasts twice as long.
	 */
	public function test_second_lockout_doubles(): void {
		$key = Rate_Limit::key();

		for ( $i = 0; $i < 5; $i++ ) {
			wp_authenticate( 'ratelimit', 'wrong' );
		}

		$first = Rate_Limit::locked_until( $key ) - time();

		delete_transient( Rate_Limit::PREFIX_LOCK . $key );

		for ( $i = 0; $i < 5; $i++ ) {
			wp_authenticate( 'ratelimit', 'wrong' );
		}

		$second = Rate_Limit::locked_until( $key ) - time();

		$this->assertEqualsWithDelta( 2 * $first, $second, 5 );
	}
}
