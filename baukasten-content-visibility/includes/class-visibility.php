<?php
/**
 * Data model: meta key, defaults, supported post types and migration.
 *
 * @package Baukasten\ContentVisibility
 */

namespace Baukasten\ContentVisibility;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the per post visibility flag.
 */
final class Visibility {

	/**
	 * Post meta key. The leading underscore keeps it out of the custom
	 * fields UI.
	 */
	const META_KEY = '_baukasten_visibility';

	/**
	 * Visible to everyone.
	 */
	const VISIBILITY_PUBLIC = 'public';

	/**
	 * Visible to logged-in users only.
	 */
	const VISIBILITY_PRIVATE = 'private';

	/**
	 * Option recording the last activation migration.
	 */
	const OPTION_MIGRATION = 'baukasten_content_visibility_migration';

	/**
	 * Cached list of supported post types.
	 *
	 * @var string[]|null
	 */
	private static ?array $post_types = null;

	/**
	 * Registers the hooks the data model needs.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'wp_insert_post', array( __CLASS__, 'set_default_on_insert' ), 10, 2 );
		add_action( 'registered_post_type', array( __CLASS__, 'flush_post_types' ) );
	}

	/**
	 * Returns the accepted visibility values.
	 *
	 * @return string[] Allowed values.
	 */
	public static function values(): array {
		return array( self::VISIBILITY_PUBLIC, self::VISIBILITY_PRIVATE );
	}

	/**
	 * Reduces any input to one of the two accepted values.
	 *
	 * Anything unrecognised falls back to the safe side, private.
	 *
	 * @param mixed $value Raw value.
	 * @return string Either `public` or `private`.
	 */
	public static function sanitize( $value ): string {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';

		return in_array( $value, self::values(), true ) ? $value : self::VISIBILITY_PRIVATE;
	}

	/**
	 * Registers the post meta so it is sanitised and permission checked.
	 *
	 * The meta is deliberately kept out of REST: exposing it would tell an
	 * anonymous caller which content is protected.
	 *
	 * @return void
	 */
	public static function register_meta(): void {
		foreach ( self::get_post_types() as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => array( __CLASS__, 'sanitize' ),
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', (int) $post_id );
					},
				)
			);
		}
	}

	/**
	 * Returns every post type the plugin could manage.
	 *
	 * Public, with a UI, and not attachments. This is the list before any
	 * filtering, so the settings screen can offer the full choice.
	 *
	 * @return string[] Post type names.
	 */
	public static function detect_post_types(): array {
		$post_types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			)
		);

		unset( $post_types['attachment'] );

		return array_values( $post_types );
	}

	/**
	 * Returns the post types the plugin manages.
	 *
	 * @return string[] Post type names.
	 */
	public static function get_post_types(): array {
		if ( null !== self::$post_types ) {
			return self::$post_types;
		}

		$post_types = self::detect_post_types();

		/**
		 * Filters the post types Content Visibility applies to.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $post_types Post type names.
		 */
		$post_types = (array) apply_filters( 'baukasten/content_visibility/post_types', $post_types );

		self::$post_types = array_values( array_unique( array_filter( array_map( 'strval', $post_types ) ) ) );

		return self::$post_types;
	}

	/**
	 * Clears the cached post type list.
	 *
	 * @return void
	 */
	public static function flush_post_types(): void {
		self::$post_types = null;
	}

	/**
	 * Whether a post type is managed by this module.
	 *
	 * @param string $post_type Post type name.
	 * @return bool True when supported.
	 */
	public static function is_supported( string $post_type ): bool {
		return in_array( $post_type, self::get_post_types(), true );
	}

	/**
	 * Returns the visibility of a post.
	 *
	 * Falls back to private when no value is stored, which is the documented
	 * default for content created after the module was activated.
	 *
	 * @param int $post_id Post ID.
	 * @return string Either `public` or `private`.
	 */
	public static function get( int $post_id ): string {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || ! self::is_supported( $post->post_type ) ) {
			return self::VISIBILITY_PUBLIC;
		}

		$stored = get_post_meta( $post->ID, self::META_KEY, true );

		if ( ! is_string( $stored ) || '' === $stored ) {
			return self::VISIBILITY_PRIVATE;
		}

		return self::sanitize( $stored );
	}

	/**
	 * Whether a post is restricted to logged-in users.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True when private.
	 */
	public static function is_private( int $post_id ): bool {
		return self::VISIBILITY_PRIVATE === self::get( $post_id );
	}

	/**
	 * Stores the visibility of a post.
	 *
	 * Capability checks are the caller's responsibility.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $value   Desired visibility.
	 * @return string The value that was stored.
	 */
	public static function set( int $post_id, string $value ): string {
		$value  = self::sanitize( $value );
		$before = self::get( $post_id );

		update_post_meta( $post_id, self::META_KEY, $value );

		if ( $before !== $value ) {
			/**
			 * Fires after the visibility of a post has changed.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $post_id Post ID.
			 * @param string $value   New visibility.
			 * @param string $before  Previous visibility.
			 */
			do_action( 'baukasten/content_visibility/changed', $post_id, $value, $before );
		}

		return $value;
	}

	/**
	 * Gives newly created content the documented default.
	 *
	 * Writing the value explicitly keeps the front end queries simple and
	 * makes the state visible in the admin column straight away.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public static function set_default_on_insert( int $post_id, $post ): void {
		if ( ! $post instanceof \WP_Post || ! self::is_supported( $post->post_type ) ) {
			return;
		}

		if ( 'auto-draft' === $post->post_status || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// metadata_exists() rather than get_post_meta(), so a registered
		// default could never make an absent row look like a stored choice.
		if ( metadata_exists( 'post', $post_id, self::META_KEY ) ) {
			return;
		}

		update_post_meta( $post_id, self::META_KEY, self::VISIBILITY_PRIVATE );
	}

	/**
	 * Marks every existing post without a stored value as public.
	 *
	 * Runs on activation. It is idempotent: only rows that have no value yet
	 * are touched, so reactivating the module never overwrites a choice an
	 * editor has made, and content created while the module was switched off
	 * is picked up too.
	 *
	 * @return int Number of posts migrated.
	 */
	public static function migrate_existing_to_public(): int {
		global $wpdb;

		$post_types = self::get_post_types();

		if ( empty( $post_types ) ) {
			return 0;
		}

		$migrated  = 0;
		$chunk     = 1000;
		$type_list = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );

		do {
			// The placeholder list is built from a count, so the sniff cannot
			// match placeholders against the flattened argument array.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p
					 LEFT JOIN {$wpdb->postmeta} m
					        ON m.post_id = p.ID AND m.meta_key = %s
					 WHERE p.post_type IN ( {$type_list} )
					   AND p.post_status NOT IN ( 'auto-draft', 'inherit', 'trash' )
					   AND m.meta_id IS NULL
					 LIMIT %d",
					array_merge( array( self::META_KEY ), $post_types, array( $chunk ) )
				)
			);
			// phpcs:enable

			$ids   = array_map( 'intval', (array) $ids );
			$found = count( $ids );

			if ( 0 === $found ) {
				break;
			}

			foreach ( $ids as $id ) {
				add_post_meta( $id, self::META_KEY, self::VISIBILITY_PUBLIC, true );
				++$migrated;
			}
		} while ( $found === $chunk );

		/**
		 * Fires after the activation migration has run.
		 *
		 * @since 1.0.0
		 *
		 * @param int $migrated Number of posts marked public.
		 */
		do_action( 'baukasten/content_visibility/migrated', $migrated );

		return $migrated;
	}

	/**
	 * Returns the meta query clause that keeps public content only.
	 *
	 * Requiring an explicit `public` value also excludes posts that carry no
	 * value at all, which mirrors the private fallback in `get()`.
	 *
	 * @return array<int, array<string, string>> Meta query clauses.
	 */
	public static function public_only_meta_query(): array {
		return array(
			array(
				'key'     => self::META_KEY,
				'value'   => self::VISIBILITY_PUBLIC,
				'compare' => '=',
			),
		);
	}
}
