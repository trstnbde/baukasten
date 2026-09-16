<?php
/**
 * Plugin settings.
 *
 * @package Baukasten\TwoFactor
 */

namespace Baukasten\TwoFactor;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, sanitises and exposes the settings.
 *
 * Every value is readable through a filter as well, so a site can override one
 * without touching the stored option. The filter runs last, so it always has
 * the final word.
 */
final class Settings {

	/**
	 * Option holding the settings array.
	 */
	const OPTION = 'baukasten_2fa_settings';

	/**
	 * Shortest challenge lifetime the tab accepts, in seconds.
	 */
	const MIN_EXPIRY = 60;

	/**
	 * Longest challenge lifetime the tab accepts, in seconds.
	 */
	const MAX_EXPIRY = 300;

	/**
	 * Returns the defaults.
	 *
	 * @return array<string, mixed> Default settings.
	 */
	public static function defaults(): array {
		return array(
			// Two minutes. Heartbeat ticks once a minute by default, so this is
			// roughly one tick to notice plus one minute to react.
			'expiry'              => 120,

			// The fast poll only runs while a challenge is actually open.
			'poll_interval'       => 3,

			// Challenges a user may create within the window.
			'rate_limit'          => 10,

			// Rate limit window in seconds.
			'rate_window'         => 600,

			// Hours a decided or expired row is kept before it is purged.
			'retention_hours'     => 24,

			// Show the anonymised address and browser next to the prompt.
			'show_context'        => true,

			// Keep Heartbeat ticking in wp-admin even while the tab is idle.
			// Deliberately not offered for the front end: that would put a
			// permanent request on every logged-in page view on the site.
			'heartbeat_keepalive' => true,
		);
	}

	/**
	 * Returns the stored settings merged over the defaults.
	 *
	 * @return array<string, mixed> Settings.
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		return self::sanitize( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Sanitises a settings array.
	 *
	 * Starts from the defaults and walks their keys, so nothing unknown can
	 * ever end up in the option.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Clean settings.
	 */
	public static function sanitize( array $input ): array {
		$clean = self::defaults();

		foreach ( array_keys( $clean ) as $key ) {
			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}

			switch ( $key ) {
				case 'expiry':
					$clean[ $key ] = self::clamp( (int) $input[ $key ], self::MIN_EXPIRY, self::MAX_EXPIRY );
					break;

				case 'poll_interval':
					$clean[ $key ] = self::clamp( (int) $input[ $key ], 2, 30 );
					break;

				case 'rate_limit':
					$clean[ $key ] = self::clamp( (int) $input[ $key ], 1, 100 );
					break;

				case 'rate_window':
					$clean[ $key ] = self::clamp( (int) $input[ $key ], 60, DAY_IN_SECONDS );
					break;

				case 'retention_hours':
					$clean[ $key ] = self::clamp( (int) $input[ $key ], 1, 720 );
					break;

				default:
					$clean[ $key ] = (bool) $input[ $key ];
					break;
			}
		}

		return $clean;
	}

	/**
	 * Returns how long a challenge stays open, in seconds.
	 *
	 * @return int Seconds.
	 */
	public static function expiry(): int {
		$settings = self::all();

		/**
		 * Filters the challenge lifetime.
		 *
		 * @since 1.0.0
		 *
		 * @param int $expiry Lifetime in seconds.
		 */
		$expiry = (int) apply_filters( 'baukasten/2fa/expiry', (int) $settings['expiry'] );

		return self::clamp( $expiry, self::MIN_EXPIRY, self::MAX_EXPIRY );
	}

	/**
	 * Returns the fast poll interval in seconds.
	 *
	 * @return int Seconds.
	 */
	public static function poll_interval(): int {
		$settings = self::all();

		/**
		 * Filters the fast poll interval.
		 *
		 * @since 1.0.0
		 *
		 * @param int $interval Interval in seconds.
		 */
		$interval = (int) apply_filters( 'baukasten/2fa/poll_interval', (int) $settings['poll_interval'] );

		return self::clamp( $interval, 2, 30 );
	}

	/**
	 * Returns how many challenges a user may create per window.
	 *
	 * @return int Challenges.
	 */
	public static function rate_limit(): int {
		$settings = self::all();

		/**
		 * Filters the number of challenges allowed per window.
		 *
		 * @since 1.0.0
		 *
		 * @param int $limit Challenges allowed.
		 */
		return max( 1, (int) apply_filters( 'baukasten/2fa/rate_limit', (int) $settings['rate_limit'] ) );
	}

	/**
	 * Returns the rate limit window in seconds.
	 *
	 * @return int Seconds.
	 */
	public static function rate_window(): int {
		$settings = self::all();

		/**
		 * Filters the rate limit window.
		 *
		 * @since 1.0.0
		 *
		 * @param int $window Window in seconds.
		 */
		return max( 60, (int) apply_filters( 'baukasten/2fa/rate_window', (int) $settings['rate_window'] ) );
	}

	/**
	 * Returns how long decided rows are kept, in seconds.
	 *
	 * @return int Seconds.
	 */
	public static function retention(): int {
		$settings = self::all();

		return max( HOUR_IN_SECONDS, (int) $settings['retention_hours'] * HOUR_IN_SECONDS );
	}

	/**
	 * Whether the prompt shows where the login attempt came from.
	 *
	 * @return bool True when the context is shown.
	 */
	public static function show_context(): bool {
		$settings = self::all();

		/**
		 * Filters whether the address and browser are shown with the prompt.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $show Whether to show the context.
		 */
		return (bool) apply_filters( 'baukasten/2fa/show_context', (bool) $settings['show_context'] );
	}

	/**
	 * Whether Heartbeat should keep ticking in an idle admin tab.
	 *
	 * @return bool True when suspension is disabled in wp-admin.
	 */
	public static function heartbeat_keepalive(): bool {
		$settings = self::all();

		return (bool) $settings['heartbeat_keepalive'];
	}

	/**
	 * Clamps an integer into a range.
	 *
	 * @param int $value Value.
	 * @param int $min   Lower bound.
	 * @param int $max   Upper bound.
	 * @return int Clamped value.
	 */
	private static function clamp( int $value, int $min, int $max ): int {
		return max( $min, min( $max, $value ) );
	}
}
