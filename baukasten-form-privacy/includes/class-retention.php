<?php
/**
 * Retention period for stored submissions (F5).
 *
 * @package Baukasten\FormPrivacy
 */

namespace Baukasten\FormPrivacy;

defined( 'ABSPATH' ) || exit;

/**
 * Deletes Flamingo messages older than the retention period, once a day.
 *
 * Flamingo keeps every message for ever; only spam moves to the trash after
 * `FLAMINGO_MOVE_TRASH_DAYS`. This deletes messages in every state for good,
 * with `wp_delete_post( $id, true )`, because a message's fields live in three
 * places — `_field_*` meta, the `_fields` array and the post content — and
 * only deleting the post removes all of them.
 *
 * The statuses are named explicitly: `any` leaves out Flamingo's spam status,
 * which is registered with `exclude_from_search`.
 *
 * Works in batches of 100 within a budget of about twenty seconds, and leaves
 * whatever is left for the next run. WP-Cron only runs when the site has
 * visitors, so the site needs a real server cron job for this to be punctual.
 */
final class Retention {

	/**
	 * Daily cron hook.
	 */
	const CRON_HOOK = 'baukasten/form_privacy/purge';

	/**
	 * Messages deleted per query.
	 */
	const BATCH = 100;

	/**
	 * Seconds one run may take.
	 */
	const BUDGET = 20;

	/**
	 * Flamingo's message post type.
	 */
	const POST_TYPE = 'flamingo_inbound';

	/**
	 * Every status a message can be in.
	 *
	 * @var string[]
	 */
	const STATUSES = array( 'publish', 'flamingo-spam', 'trash' );

	/**
	 * Registers the cron callback.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( self::CRON_HOOK, array( __CLASS__, 'purge' ) );

		// A site that had the plugin active before the hook existed, or whose
		// cron array was reset, gets it back without reactivating.
		add_action( 'admin_init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Schedules the daily run, unless it already is.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Removes the daily run.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Deletes every message older than the retention period.
	 *
	 * @return int Number of messages deleted.
	 */
	public static function purge(): int {
		if ( ! Settings::has_flamingo() ) {
			return 0;
		}

		$start   = microtime( true );
		$deleted = 0;

		do {
			$ids   = self::expired_ids();
			$found = count( $ids );

			foreach ( $ids as $id ) {
				if ( wp_delete_post( $id, true ) ) {
					++$deleted;
				}
			}

			$over_budget = ( microtime( true ) - $start ) > self::BUDGET;
		} while ( self::BATCH === $found && ! $over_budget );

		return $deleted;
	}

	/**
	 * The next batch of messages past the retention period.
	 *
	 * @return int[] Post IDs.
	 */
	public static function expired_ids(): array {
		$query = new \WP_Query(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => self::STATUSES,
				'posts_per_page'         => self::BATCH,
				'fields'                 => 'ids',
				'orderby'                => 'date',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					array(
						'column' => 'post_date_gmt',
						'before' => gmdate( 'Y-m-d H:i:s', time() - Settings::retention_days() * DAY_IN_SECONDS ),
					),
				),
			)
		);

		return array_map( 'intval', (array) $query->posts );
	}

	/**
	 * Number of stored messages, and the date of the oldest.
	 *
	 * @return array{count: int, oldest: string} Count, and the oldest message's GMT date or ''.
	 */
	public static function stats(): array {
		$query = new \WP_Query(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => self::STATUSES,
				'posts_per_page'         => 1,
				'orderby'                => 'date',
				'order'                  => 'ASC',
				'suppress_filters'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$oldest = $query->posts[0] ?? null;

		return array(
			'count'  => (int) $query->found_posts,
			'oldest' => $oldest instanceof \WP_Post ? (string) $oldest->post_date_gmt : '',
		);
	}
}
