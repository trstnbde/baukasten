<?php
/**
 * The host to page lookup.
 *
 * @package Baukasten\MultiDomain
 */

namespace Baukasten\MultiDomain;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps one autoloaded option in step with the post meta.
 *
 * The meta is the source of truth — it is what an editor sees and edits — but
 * the front end must not query it. Answering "which page belongs to this
 * host" with a meta_query would put a database round trip in front of every
 * single request, cached or not. So the same information is mirrored into one
 * autoloaded option that is already in memory by the time routing happens.
 *
 * Every write to the meta syncs the map, whichever screen made it: the quick
 * edit, the meta box, or the REST API behind the block editor. Nothing writes
 * the map directly except this class.
 */
final class Domain_Map {

	/**
	 * Option holding the map.
	 */
	const OPTION = 'baukasten_domain_map';

	/**
	 * Post meta holding a page's domain.
	 */
	const META_KEY = '_baukasten_domain';

	/**
	 * Guards against the recursion of a sync that clears another page's meta.
	 *
	 * @var bool
	 */
	private static bool $syncing = false;

	/**
	 * Registers the hooks that keep the map in step.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta_change' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta_change' ), 10, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'on_meta_change' ), 10, 3 );

		// A page that is deleted or trashed must not keep answering for its
		// domain; a restored one gets it back.
		add_action( 'deleted_post', array( __CLASS__, 'on_post_gone' ) );
		add_action( 'trashed_post', array( __CLASS__, 'on_post_gone' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_post_restored' ) );
	}

	/**
	 * Returns the map.
	 *
	 * @return array<string, int> Host name to page ID.
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Returns the page a host is the front page of.
	 *
	 * @param string $host Sanitised host name.
	 * @return int Page ID, or 0.
	 */
	public static function page_for( string $host ): int {
		$map = self::all();

		return isset( $map[ $host ] ) ? (int) $map[ $host ] : 0;
	}

	/**
	 * Returns the domain stored on a page.
	 *
	 * @param int $page_id Page ID.
	 * @return string Host name, or an empty string.
	 */
	public static function domain_of( int $page_id ): string {
		return Domain::sanitize( (string) get_post_meta( $page_id, self::META_KEY, true ) );
	}

	/**
	 * Assigns a domain to a page, or clears it.
	 *
	 * The single entry point for every screen. Writing the meta is enough;
	 * the map follows from `on_meta_change()`.
	 *
	 * @param int    $page_id Page ID.
	 * @param string $domain  Sanitised host name, or an empty string to clear.
	 * @return void
	 */
	public static function assign( int $page_id, string $domain ): void {
		if ( '' === $domain ) {
			delete_post_meta( $page_id, self::META_KEY );

			return;
		}

		update_post_meta( $page_id, self::META_KEY, $domain );
	}

	/**
	 * Reacts to any write to the domain meta.
	 *
	 * @param int    $meta_id  Meta row ID, unused.
	 * @param int    $post_id  Post the meta belongs to.
	 * @param string $meta_key Meta key.
	 * @return void
	 */
	public static function on_meta_change( $meta_id, $post_id, $meta_key ): void {
		unset( $meta_id );

		if ( self::META_KEY !== $meta_key || self::$syncing ) {
			return;
		}

		self::sync_page( (int) $post_id );
	}

	/**
	 * Drops a page from the map when it is trashed or deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function on_post_gone( $post_id ): void {
		$map     = self::all();
		$post_id = (int) $post_id;
		$kept    = array();

		foreach ( $map as $host => $id ) {
			if ( (int) $id !== $post_id ) {
				$kept[ $host ] = (int) $id;
			}
		}

		if ( $kept !== $map ) {
			self::save( $kept );
		}
	}

	/**
	 * Puts a restored page back into the map.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function on_post_restored( $post_id ): void {
		self::sync_page( (int) $post_id );
	}

	/**
	 * Brings the map in line with one page's meta.
	 *
	 * Enforces the uniqueness rule on the way: a domain belongs to exactly one
	 * page, so assigning it here takes it away from whoever had it, meta and
	 * all. Otherwise the map and the meta would disagree, and the page list
	 * would show two pages claiming the same address.
	 *
	 * @param int $page_id Page ID.
	 * @return void
	 */
	public static function sync_page( int $page_id ): void {
		if ( self::$syncing ) {
			return;
		}

		self::$syncing = true;

		$domain  = self::domain_of( $page_id );
		$hosts   = self::hosts_for( $domain );
		$map     = self::all();
		$orphans = array();

		// Take these hosts off whoever else had them.
		foreach ( $hosts as $host ) {
			if ( isset( $map[ $host ] ) && (int) $map[ $host ] !== $page_id ) {
				$orphans[] = (int) $map[ $host ];
			}
		}

		// Drop every entry pointing at this page or at a page losing its host.
		$losers = array_merge( array( $page_id ), $orphans );
		$kept   = array();

		foreach ( $map as $host => $id ) {
			if ( ! in_array( (int) $id, $losers, true ) ) {
				$kept[ $host ] = (int) $id;
			}
		}

		foreach ( $hosts as $host ) {
			$kept[ $host ] = $page_id;
		}

		ksort( $kept );

		self::save( $kept );

		foreach ( array_unique( $orphans ) as $orphan ) {
			delete_post_meta( $orphan, self::META_KEY );
		}

		self::$syncing = false;
	}

	/**
	 * Rebuilds the whole map from the post meta.
	 *
	 * The map is derived data, and derived data drifts — a database import, a
	 * migration, a plugin writing meta directly. This is the way back.
	 *
	 * @return int Number of pages with a domain.
	 */
	public static function rebuild(): int {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'no_found_rows'  => true,
			)
		);

		$map = array();

		foreach ( (array) $pages as $page_id ) {
			$domain = self::domain_of( (int) $page_id );

			foreach ( self::hosts_for( $domain ) as $host ) {
				// First page wins, so a duplicate in the data cannot make the
				// rebuild depend on iteration order.
				if ( ! isset( $map[ $host ] ) ) {
					$map[ $host ] = (int) $page_id;
				}
			}
		}

		ksort( $map );

		self::save( $map );

		return count( array_unique( array_values( $map ) ) );
	}

	/**
	 * Returns every host a domain should answer on.
	 *
	 * @param string $domain Sanitised host name.
	 * @return string[] Host names.
	 */
	private static function hosts_for( string $domain ): array {
		if ( '' === $domain ) {
			return array();
		}

		$sibling = Domain::sibling( $domain );

		return '' === $sibling ? array( $domain ) : array( $domain, $sibling );
	}

	/**
	 * Writes the map.
	 *
	 * Autoloaded on purpose: the router reads it on every front end request,
	 * and the whole point of the option is to be in memory already.
	 *
	 * @param array<string, int> $map Host name to page ID.
	 * @return void
	 */
	private static function save( array $map ): void {
		update_option( self::OPTION, $map, true );
	}
}
