<?php
/**
 * The privacy hardening bulk action.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Puts the whole site into a defensible default state, in one go.
 *
 * Everything this does can be done by hand across five settings screens and
 * a few hundred posts. Doing it by hand is how sites end up with fourteen of
 * fifteen switches flipped, so it is one routine with one report.
 *
 * It is idempotent: every step compares before it writes and says whether it
 * changed anything, so running it twice is safe and the second run tells you
 * that nothing was left to do.
 *
 * It is also blunt. It closes comments on every post, tells search engines to
 * stay away, and empties the credentials on the Connectors screen. That is
 * what it is for, and it is why the button asks first.
 */
final class Hardening {

	/**
	 * Core options set to a fixed value.
	 *
	 * @var array<string, string|int>
	 */
	private const OPTIONS = array(
		// Settings → General.
		'users_can_register'           => 0,

		// Settings → Reading.
		'blog_public'                  => 0,

		// Settings → Discussion, every box.
		'default_pingback_flag'        => 0,
		'default_ping_status'          => 'closed',
		'default_comment_status'       => 'closed',
		'require_name_email'           => 0,
		'comment_registration'         => 0,
		'close_comments_for_old_posts' => 0,
		'show_comments_cookies_opt_in' => 0,
		'thread_comments'              => 0,
		'page_comments'                => 0,
		'comments_notify'              => 0,
		'moderation_notify'            => 0,
		'comment_moderation'           => 0,
		'comment_previously_approved'  => 0,
		'show_avatars'                 => 0,
	);

	/**
	 * Plugin settings the routine switches on.
	 *
	 * @var string[]
	 */
	private const MEASURES = array(
		'block_scripts',
		'block_styles',
		'block_script_modules',
		'block_resource_hints',
		'block_oembed',
		'block_gravatar',
		'block_emoji',
		'block_comment_ip',
		'block_speculative_loading',
		'block_dashboard_requests',
		'warn_insecure_urls',
	);

	/**
	 * Runs every step and returns what happened.
	 *
	 * @return array<int, array{label: string, changed: bool, detail: string}> Report rows.
	 */
	public static function run_privacy_hardening_bulk_action(): array {
		return array(
			self::close_comments_on_all_posts(),
			self::set_core_options(),
			self::disconnect_connectors(),
			self::enable_measures(),
		);
	}

	/**
	 * Closes comments and pings on every post that is not in the trash.
	 *
	 * One UPDATE rather than a loop over `wp_update_post()`: a site with fifty
	 * thousand posts would otherwise need fifty thousand queries and a lot of
	 * patience. The post cache is cleaned afterwards, since a direct write
	 * leaves it stale.
	 *
	 * @return array{label: string, changed: bool, detail: string} Report row.
	 */
	private static function close_comments_on_all_posts(): array {
		global $wpdb;

		$label = __( 'Comments and pings on existing content', 'baukasten-consent-blocking-engine' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_status != 'trash'
			AND ( comment_status != 'closed' OR ping_status != 'closed' )"
		);

		$ids = array_map( 'intval', (array) $ids );

		if ( array() === $ids ) {
			return self::row( $label, false, __( 'Already closed everywhere.', 'baukasten-consent-blocking-engine' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"UPDATE {$wpdb->posts}
			SET comment_status = 'closed', ping_status = 'closed'
			WHERE post_status != 'trash'"
		);

		foreach ( $ids as $id ) {
			clean_post_cache( $id );
		}

		return self::row(
			$label,
			true,
			sprintf(
				/* translators: %s: number of entries. */
				_n( '%s entry closed.', '%s entries closed.', count( $ids ), 'baukasten-consent-blocking-engine' ),
				number_format_i18n( count( $ids ) )
			)
		);
	}

	/**
	 * Writes the core options listed above.
	 *
	 * @return array{label: string, changed: bool, detail: string} Report row.
	 */
	private static function set_core_options(): array {
		$label   = __( 'WordPress settings', 'baukasten-consent-blocking-engine' );
		$changed = array();

		foreach ( self::OPTIONS as $option => $value ) {
			// Compared as strings: WordPress stores these as '0' and 'closed',
			// while the table above holds integers, and comparing an int 0 to
			// the string '0' strictly would rewrite every option on every run.
			if ( (string) get_option( $option ) === (string) $value ) {
				continue;
			}

			update_option( $option, $value );

			$changed[] = $option;
		}

		if ( array() === $changed ) {
			return self::row( $label, false, __( 'All already set.', 'baukasten-consent-blocking-engine' ) );
		}

		return self::row( $label, true, implode( ', ', $changed ) );
	}

	/**
	 * Empties the credentials stored on Settings → Connectors.
	 *
	 * WordPress 7.0 added that screen for AI provider connections; every
	 * configured connector is an API key in the options table and an outbound
	 * connection waiting to happen. Each connector declares where it keeps its
	 * credentials, so they can be cleared without knowing any of them.
	 *
	 * Keys supplied through a PHP constant or an environment variable are out
	 * of reach of a database routine. Those are named in the report instead of
	 * being silently missed.
	 *
	 * @return array{label: string, changed: bool, detail: string} Report row.
	 */
	private static function disconnect_connectors(): array {
		$label = __( 'Connectors', 'baukasten-consent-blocking-engine' );

		if ( ! function_exists( 'wp_get_connectors' ) ) {
			return self::row( $label, false, __( 'This WordPress version has no connectors.', 'baukasten-consent-blocking-engine' ) );
		}

		$cleared  = array();
		$external = array();

		foreach ( (array) wp_get_connectors() as $id => $connector ) {
			$auth = isset( $connector['auth'] ) && is_array( $connector['auth'] ) ? $connector['auth'] : array();

			if ( empty( $auth['setting_name'] ) ) {
				continue;
			}

			$setting = (string) $auth['setting_name'];
			$method  = isset( $auth['method'] ) ? (string) $auth['method'] : 'api_key';

			foreach ( array( 'env_var_name', 'constant_name' ) as $source ) {
				if ( ! empty( $auth[ $source ] ) ) {
					$external[] = (string) $id . ' (' . (string) $auth[ $source ] . ')';
				}
			}

			$empty = 'application_password' === $method
				? array(
					'username' => '',
					'password' => '',
				)
				: '';

			if ( get_option( $setting, $empty ) === $empty ) {
				continue;
			}

			update_option( $setting, $empty );

			$cleared[] = (string) $id;
		}

		$detail = array() === $cleared
			? __( 'Nothing was connected.', 'baukasten-consent-blocking-engine' )
			: implode( ', ', $cleared );

		if ( array() !== $external ) {
			$detail .= ' — ' . sprintf(
				/* translators: %s: comma separated list of connector names. */
				__( 'still configured outside the database and untouched: %s', 'baukasten-consent-blocking-engine' ),
				implode( ', ', array_unique( $external ) )
			);
		}

		return self::row( $label, array() !== $cleared, $detail );
	}

	/**
	 * Switches on every blocking measure this plugin offers.
	 *
	 * The emoji hooks and the s.w.org resource hints are what `block_emoji`
	 * does, so switching every measure on covers those too.
	 *
	 * @return array{label: string, changed: bool, detail: string} Report row.
	 */
	private static function enable_measures(): array {
		$label    = __( 'Blocking measures', 'baukasten-consent-blocking-engine' );
		$settings = Settings::all();
		$changed  = array();

		foreach ( self::MEASURES as $measure ) {
			if ( ! empty( $settings[ $measure ] ) ) {
				continue;
			}

			$settings[ $measure ] = true;
			$changed[]            = $measure;
		}

		if ( array() === $changed ) {
			return self::row( $label, false, __( 'All already active.', 'baukasten-consent-blocking-engine' ) );
		}

		update_option( Settings::OPTION, Settings::sanitize( $settings ), false );

		return self::row( $label, true, implode( ', ', $changed ) );
	}

	/**
	 * Builds a report row.
	 *
	 * @param string $label   What the step did.
	 * @param bool   $changed Whether anything was written.
	 * @param string $detail  Human readable detail.
	 * @return array{label: string, changed: bool, detail: string} Report row.
	 */
	private static function row( string $label, bool $changed, string $detail ): array {
		return array(
			'label'   => $label,
			'changed' => $changed,
			'detail'  => $detail,
		);
	}
}
