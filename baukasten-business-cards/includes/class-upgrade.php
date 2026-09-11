<?php
/**
 * The one-shot move from the 1.0 field schema.
 *
 * @package Baukasten\BusinessCards
 */

namespace Baukasten\BusinessCards;

defined( 'ABSPATH' ) || exit;

/**
 * Moves 1.0 cards onto the 1.1 schema, once, and says what it could not do.
 *
 * Stamped the way `Post_Type::maybe_flush()` is stamped: one autoloaded option
 * against a constant, so a site that is already current pays one option read on
 * an admin request and nothing else. It runs in the admin only — a write loop
 * over every card is not something to do on a visitor's request — and walks the
 * cards in id order behind a cursor, so a request that dies halfway resumes
 * rather than starting again.
 *
 * **Nothing is deleted.** Every old value with no new home is moved to a
 * `*_legacy` key and shown, read only, beside the field that replaced it. A
 * migration that quietly loses an address somebody typed is worse than one that
 * leaves a note.
 */
final class Upgrade {

	/**
	 * The schema version this build expects.
	 */
	const DATA_VERSION = 2;

	/**
	 * Option recording the schema version the stored data is on.
	 */
	const OPTION_VERSION = 'baukasten_business_cards_data_version';

	/**
	 * Option holding the id the last batch stopped at.
	 */
	const OPTION_CURSOR = 'baukasten_business_cards_upgrade_cursor';

	/**
	 * Option holding what the run did, for the overview tab.
	 */
	const OPTION_REPORT = 'baukasten_business_cards_upgrade_report';

	/**
	 * Cards converted per admin request.
	 */
	const BATCH = 250;

	/**
	 * Registers the hooks the upgrade needs.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_init', array( __CLASS__, 'maybe_run' ), 5 );
	}

	/**
	 * Whether the stored data still has to be moved.
	 *
	 * @return bool True while the upgrade has work left.
	 */
	public static function is_pending(): bool {
		return self::DATA_VERSION > absint( get_option( self::OPTION_VERSION, 1 ) );
	}

	/**
	 * Marks the data current without doing anything.
	 *
	 * Called on activation: a site installing 1.1 for the first time has no 1.0
	 * cards, and running a migration over nothing just to set a flag is a query
	 * nobody needs.
	 *
	 * @return void
	 */
	public static function mark_current(): void {
		update_option( self::OPTION_VERSION, self::DATA_VERSION, true );
	}

	/**
	 * Converts one batch, if there is anything left to convert.
	 *
	 * @return void
	 */
	public static function maybe_run(): void {
		if ( ! self::is_pending() ) {
			return;
		}

		// Not a permission check on the upgrade itself: a check that this is a
		// real admin request rather than a cron ping or a heartbeat poll.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$cursor = absint( get_option( self::OPTION_CURSOR, 0 ) );
		$ids    = self::next_batch( $cursor );

		$report = self::report();

		foreach ( $ids as $id ) {
			self::convert( $id, $report );

			$cursor = max( $cursor, $id );
		}

		$report['cards'] += count( $ids );

		update_option( self::OPTION_REPORT, $report, false );
		update_option( self::OPTION_CURSOR, $cursor, false );

		if ( count( $ids ) < self::BATCH ) {
			// A short batch means there is nothing after it.
			self::mark_current();
			delete_option( self::OPTION_CURSOR );
		}
	}

	/**
	 * The next cards to convert, in id order.
	 *
	 * A direct query rather than `get_posts()`: the cursor is "the id after the
	 * last one done", which `WP_Query` has no argument for. Doing it with
	 * `offset` instead would quietly skip a card if one were deleted between two
	 * batches, and `post__not_in` grows without bound. This is a primary-key
	 * range scan with a limit.
	 *
	 * @param int $cursor Highest id already converted.
	 * @return int[] Card post ids.
	 */
	private static function next_batch( int $cursor ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a one-off migration cursor over the primary key; caching a walk that mutates as it goes would be wrong.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d ORDER BY ID ASC LIMIT %d",
				Post_Type::POST_TYPE,
				$cursor,
				self::BATCH
			)
		);

		return array_map( 'absint', (array) $ids );
	}

	/**
	 * What the run did, with every key present.
	 *
	 * @return array<string, int> Report.
	 */
	public static function report(): array {
		$stored = get_option( self::OPTION_REPORT, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array_merge(
			array(
				'cards'            => 0,
				'skins'            => 0,
				'apple_converted'  => 0,
				'apple_kept'       => 0,
				'google_converted' => 0,
				'google_kept'      => 0,
				'legal_converted'  => 0,
				'legal_kept'       => 0,
			),
			array_map( 'absint', $stored )
		);
	}

	/**
	 * Converts one card.
	 *
	 * @param int                $post_id Card post ID.
	 * @param array<string, int> $report  Running tally, by reference.
	 * @return void
	 */
	private static function convert( int $post_id, array &$report ): void {
		self::convert_skin( $post_id, $report );
		self::convert_apple( $post_id, $report );
		self::convert_google( $post_id, $report );
		self::convert_legal( $post_id, $report );
	}

	/**
	 * Maps an old layout onto a skin.
	 *
	 * All three land on the same one, and the old value is kept. `classic`,
	 * `modern` and `bio` were three *structures* — a banner card, a borderless
	 * card, a stack of buttons. The three skins are one structure in three
	 * dresses, so no old value means Industry or Nocturne, and any one-to-one
	 * mapping would be inventing an intention nobody had. Everyone lands on the
	 * neutral one and the overview says so.
	 *
	 * @param int                $post_id Card post ID.
	 * @param array<string, int> $report  Running tally, by reference.
	 * @return void
	 */
	private static function convert_skin( int $post_id, array &$report ): void {
		$old = (string) get_post_meta( $post_id, Fields::meta_key( 'card_layout' ), true );

		if ( '' === $old || in_array( $old, Skins::ids(), true ) ) {
			return;
		}

		update_post_meta( $post_id, Fields::meta_key( 'card_layout_legacy' ), $old );
		update_post_meta( $post_id, Fields::meta_key( 'card_layout' ), Skins::DEFAULT_SKIN );

		++$report['skins'];
	}

	/**
	 * Moves the Apple Wallet link aside.
	 *
	 * A pass is now a file this site serves, and a link to a pass hosted
	 * elsewhere cannot become one. If it happens to point into this site's own
	 * media library it converts; otherwise it is parked, visibly, because it is
	 * the only record of where the pass was.
	 *
	 * @param int                $post_id Card post ID.
	 * @param array<string, int> $report  Running tally, by reference.
	 * @return void
	 */
	private static function convert_apple( int $post_id, array &$report ): void {
		$url = (string) get_post_meta( $post_id, Fields::meta_key( 'wallet_apple_url' ), true );

		if ( '' === $url ) {
			return;
		}

		$attachment_id = (int) attachment_url_to_postid( $url );

		if ( 0 < $attachment_id && Pass::MIME === get_post_mime_type( $attachment_id ) ) {
			update_post_meta( $post_id, Fields::meta_key( 'wallet_apple_pass_id' ), (string) $attachment_id );

			++$report['apple_converted'];
		} else {
			update_post_meta( $post_id, Fields::meta_key( 'wallet_apple_legacy' ), $url );

			++$report['apple_kept'];
		}

		delete_post_meta( $post_id, Fields::meta_key( 'wallet_apple_url' ) );
	}

	/**
	 * Pulls the token out of a Google Wallet save link.
	 *
	 * This one usually converts cleanly: the old field held the whole
	 * `https://pay.google.com/gp/v/save/<token>` address, and the new one holds
	 * the token off the end of it.
	 *
	 * @param int                $post_id Card post ID.
	 * @param array<string, int> $report  Running tally, by reference.
	 * @return void
	 */
	private static function convert_google( int $post_id, array &$report ): void {
		$url = (string) get_post_meta( $post_id, Fields::meta_key( 'wallet_google_url' ), true );

		if ( '' === $url ) {
			return;
		}

		$jwt = Fields::sanitize( 'jwt', $url );

		if ( '' !== $jwt ) {
			update_post_meta( $post_id, Fields::meta_key( 'wallet_google_jwt' ), $jwt );

			++$report['google_converted'];
		} else {
			update_post_meta( $post_id, Fields::meta_key( 'wallet_google_legacy' ), $url );

			++$report['google_kept'];
		}

		delete_post_meta( $post_id, Fields::meta_key( 'wallet_google_url' ) );
	}

	/**
	 * Turns the three footer URLs into page ids.
	 *
	 * `url_to_postid()` resolves a permalink on this site whatever its structure
	 * and returns 0 for anything it does not recognise — an external imprint, an
	 * address from before the site moved domain. Those are parked, not dropped.
	 *
	 * @param int                $post_id Card post ID.
	 * @param array<string, int> $report  Running tally, by reference.
	 * @return void
	 */
	private static function convert_legal( int $post_id, array &$report ): void {
		$pairs = array(
			'footer_privacy_url' => 'footer_privacy_page',
			'footer_terms_url'   => 'footer_terms_page',
			'footer_imprint_url' => 'footer_imprint_page',
		);

		foreach ( $pairs as $old => $new ) {
			$url = (string) get_post_meta( $post_id, Fields::meta_key( $old ), true );

			if ( '' === $url ) {
				continue;
			}

			$page_id = (int) url_to_postid( $url );
			$page    = 0 < $page_id ? get_post( $page_id ) : null;

			if ( $page instanceof \WP_Post && 'page' === $page->post_type ) {
				update_post_meta( $post_id, Fields::meta_key( $new ), (string) $page_id );

				++$report['legal_converted'];
			} else {
				update_post_meta( $post_id, Fields::meta_key( $old . '_legacy' ), $url );

				++$report['legal_kept'];
			}

			delete_post_meta( $post_id, Fields::meta_key( $old ) );
		}
	}
}
