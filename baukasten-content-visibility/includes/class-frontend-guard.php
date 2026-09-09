<?php
/**
 * Front end enforcement across templates, queries, REST, feeds and sitemaps.
 *
 * @package Baukasten\ContentVisibility
 */

namespace Baukasten\ContentVisibility;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Keeps private content away from logged-out visitors.
 *
 * Enforcement is layered on purpose. Blocking the single view alone is the
 * usual mistake: the same content is then still reachable through REST, the
 * feeds, the search results and the core sitemap.
 */
final class Frontend_Guard {

	/**
	 * Registers the enforcement hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( __CLASS__, 'guard_singular' ), 0 );
		add_action( 'pre_get_posts', array( __CLASS__, 'exclude_from_queries' ) );
		add_filter( 'posts_where', array( __CLASS__, 'filter_where' ), 10, 2 );

		add_filter( 'the_content', array( __CLASS__, 'guard_content' ), PHP_INT_MAX );
		add_filter( 'the_content_feed', array( __CLASS__, 'guard_feed_content' ), PHP_INT_MAX );
		add_filter( 'the_excerpt_rss', array( __CLASS__, 'guard_feed_content' ), PHP_INT_MAX );

		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'guard_sitemap' ), 10, 2 );

		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_guards' ) );
	}

	/**
	 * Whether the current visitor may see private content.
	 *
	 * Any logged-in user may, regardless of role. That is the whole point of
	 * this module and deliberately different from the core `private` post
	 * status, which requires `read_private_posts`.
	 *
	 * @return bool True when private content may be shown.
	 */
	public static function viewer_may_see_private(): bool {
		/**
		 * Filters whether the current visitor may see private content.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $allowed True when private content may be shown.
		 */
		return (bool) apply_filters( 'baukasten/content_visibility/viewer_may_see_private', is_user_logged_in() );
	}

	/**
	 * Whether a post must be hidden from the current visitor.
	 *
	 * @param int|WP_Post|null $post Post or post ID.
	 * @return bool True when the post is off limits.
	 */
	public static function is_blocked( $post ): bool {
		$post = get_post( $post );

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		if ( ! Visibility::is_supported( $post->post_type ) ) {
			return false;
		}

		if ( self::viewer_may_see_private() ) {
			return false;
		}

		return Visibility::is_private( $post->ID );
	}

	/**
	 * Blocks the single view of a private post.
	 *
	 * @return void
	 */
	public static function guard_singular(): void {
		if ( ! is_singular() || is_embed() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! self::is_blocked( $post ) ) {
			return;
		}

		$redirect = wp_login_url( self::current_url() );

		/**
		 * Filters where a blocked visitor is sent.
		 *
		 * Return an empty string to send a 403 through `wp_die()` instead of
		 * redirecting to the login form.
		 *
		 * @since 1.0.0
		 *
		 * @param string  $redirect Login URL including the redirect_to argument.
		 * @param WP_Post $post     The post that was blocked.
		 */
		$redirect = (string) apply_filters( 'baukasten/content_visibility/login_redirect', $redirect, $post );

		if ( '' === $redirect ) {
			wp_die(
				esc_html__( 'This content is available to logged-in users only.', 'baukasten-content-visibility' ),
				esc_html__( 'Login required', 'baukasten-content-visibility' ),
				array( 'response' => 403 )
			);
		}

		nocache_headers();
		wp_safe_redirect( $redirect, 302 );
		exit;
	}

	/**
	 * Flags front end, feed, search and archive queries for filtering.
	 *
	 * Single post queries are deliberately left alone, so that
	 * `guard_singular()` can send the visitor to the login form instead of
	 * the request ending in a bare 404.
	 *
	 * @param WP_Query $query The query being prepared.
	 * @return void
	 */
	public static function exclude_from_queries( $query ): void {
		if ( ! $query instanceof WP_Query || is_admin() ) {
			return;
		}

		if ( self::viewer_may_see_private() ) {
			return;
		}

		if ( $query->is_singular() ) {
			return;
		}

		// A query aimed at one specific post, for example a preview link or
		// `?p=123`, is handled by the singular guard.
		foreach ( array( 'p', 'page_id', 'name', 'pagename', 'attachment_id' ) as $single ) {
			if ( ! empty( $query->get( $single ) ) ) {
				return;
			}
		}

		$requested = $query->get( 'post_type' );
		$requested = is_array( $requested ) ? $requested : ( '' === $requested ? array() : array( $requested ) );
		$requested = array_values( array_filter( array_map( 'strval', $requested ) ) );

		// An empty post type is the default for search, home and most
		// archives. Those must be filtered, so only an explicit list of
		// unsupported types is allowed to opt out.
		if ( ! empty( $requested ) && ! in_array( 'any', $requested, true ) ) {
			if ( empty( array_intersect( $requested, Visibility::get_post_types() ) ) ) {
				return;
			}
		}

		$query->set( 'baukasten_content_visibility', true );
	}

	/**
	 * Restricts a flagged query to public content.
	 *
	 * A WHERE clause is used rather than a meta query because a single query
	 * can mix supported and unsupported post types, for example a search that
	 * also returns attachments. The clause leaves unsupported types alone.
	 *
	 * @param string   $where The WHERE clause.
	 * @param WP_Query $query The query being run.
	 * @return string Filtered WHERE clause.
	 */
	public static function filter_where( $where, $query ): string {
		global $wpdb;

		$where = (string) $where;

		if ( ! $query instanceof WP_Query || ! $query->get( 'baukasten_content_visibility' ) ) {
			return $where;
		}

		$post_types = Visibility::get_post_types();

		if ( empty( $post_types ) ) {
			return $where;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );

		// The placeholder list is built from a count, so the sniff cannot match
		// placeholders against the flattened argument array.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$clause = $wpdb->prepare(
			" AND ( {$wpdb->posts}.post_type NOT IN ( {$placeholders} )
				OR EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} AS bkcv
					WHERE bkcv.post_id = {$wpdb->posts}.ID
					  AND bkcv.meta_key = %s
					  AND bkcv.meta_value = %s
				) ) ",
			array_merge( $post_types, array( Visibility::META_KEY, Visibility::VISIBILITY_PUBLIC ) )
		);
		// phpcs:enable

		return $where . $clause;
	}

	/**
	 * Replaces the content of a private post as a last line of defence.
	 *
	 * Relevant for themes that render posts outside the main loop, where
	 * `template_redirect` never gets the chance to intervene.
	 *
	 * @param string $content Post content.
	 * @return string Filtered content.
	 */
	public static function guard_content( $content ): string {
		$content = (string) $content;

		if ( is_admin() || ! in_the_loop() ) {
			return $content;
		}

		if ( ! self::is_blocked( get_post() ) ) {
			return $content;
		}

		return self::notice_markup();
	}

	/**
	 * Empties the feed content and excerpt of a private post.
	 *
	 * @param string $content Feed content or excerpt.
	 * @return string Filtered value.
	 */
	public static function guard_feed_content( $content ): string {
		if ( ! self::is_blocked( get_post() ) ) {
			return (string) $content;
		}

		return esc_html__( 'This content is available to logged-in users only.', 'baukasten-content-visibility' );
	}

	/**
	 * Keeps private posts out of the core XML sitemap.
	 *
	 * The sitemap is generated for anonymous crawlers, so the visibility of
	 * the current visitor is not consulted here.
	 *
	 * @param array<string, mixed> $args      Query arguments.
	 * @param string               $post_type Post type being listed.
	 * @return array<string, mixed> Filtered query arguments.
	 */
	public static function guard_sitemap( $args, $post_type ): array {
		$args = is_array( $args ) ? $args : array();

		if ( ! Visibility::is_supported( (string) $post_type ) ) {
			return $args;
		}

		$meta_query = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the only lever this filter offers.
		$args['meta_query'] = array_merge( $meta_query, Visibility::public_only_meta_query() );

		return $args;
	}

	/**
	 * Registers the REST guards for every supported post type.
	 *
	 * @return void
	 */
	public static function register_rest_guards(): void {
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'guard_rest_item' ), 10, 3 );

		foreach ( Visibility::get_post_types() as $post_type ) {
			add_filter( "rest_{$post_type}_query", array( __CLASS__, 'guard_rest_query' ), 10, 2 );
		}
	}

	/**
	 * Returns the REST route prefixes that address a single post.
	 *
	 * @return array<string, string> Route prefix mapped to post type.
	 */
	private static function rest_route_prefixes(): array {
		$prefixes = array();

		foreach ( Visibility::get_post_types() as $post_type ) {
			$object = get_post_type_object( $post_type );

			if ( null === $object ) {
				continue;
			}

			$namespace = ! empty( $object->rest_namespace ) ? $object->rest_namespace : 'wp/v2';
			$base      = ! empty( $object->rest_base ) ? $object->rest_base : $post_type;

			$prefixes[ '/' . trim( $namespace, '/' ) . '/' . $base . '/' ] = $post_type;
		}

		return $prefixes;
	}

	// The request parameter is part of the filter signature.
	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter

	/**
	 * Removes private posts from REST collection responses.
	 *
	 * @param array<string, mixed> $args    Query arguments.
	 * @param WP_REST_Request      $request The request.
	 * @return array<string, mixed> Filtered query arguments.
	 */
	public static function guard_rest_query( $args, $request ): array {
		$args = is_array( $args ) ? $args : array();

		if ( self::viewer_may_see_private() ) {
			return $args;
		}

		$meta_query = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the only lever this filter offers.
		$args['meta_query'] = array_merge( $meta_query, Visibility::public_only_meta_query() );

		return $args;
	}

	// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter

	/**
	 * Denies REST access to a single private post.
	 *
	 * This runs before the route is dispatched rather than on
	 * `rest_prepare_{$post_type}`. The posts controller calls
	 * `link_header()` on whatever that filter returns, so handing it a
	 * WP_Error there ends the request in a fatal error instead of a 404.
	 *
	 * Collections are handled by `guard_rest_query()`.
	 *
	 * @param mixed           $result  Result to send, or null to continue.
	 * @param \WP_REST_Server $server  The REST server.
	 * @param WP_REST_Request $request The request.
	 * @return mixed The untouched result, or an error.
	 */
	public static function guard_rest_item( $result, $server, $request ) {
		if ( null !== $result || ! $request instanceof WP_REST_Request ) {
			return $result;
		}

		if ( self::viewer_may_see_private() ) {
			return $result;
		}

		$route = (string) $request->get_route();

		foreach ( self::rest_route_prefixes() as $prefix => $post_type ) {
			if ( 0 !== strpos( $route, $prefix ) ) {
				continue;
			}

			// Everything below `/<base>/<id>` is covered too, which includes
			// the revisions and autosaves sub routes.
			if ( ! preg_match( '#^' . preg_quote( $prefix, '#' ) . '(\d+)#', $route, $matches ) ) {
				continue;
			}

			$post = get_post( (int) $matches[1] );

			if ( $post instanceof WP_Post && $post->post_type === $post_type && self::is_blocked( $post ) ) {
				return new WP_Error(
					'rest_post_invalid_id',
					__( 'Invalid post ID.', 'baukasten-content-visibility' ),
					array( 'status' => 404 )
				);
			}
		}

		return $result;
	}

	/**
	 * Returns the markup shown in place of blocked content.
	 *
	 * @return string Notice markup.
	 */
	private static function notice_markup(): string {
		return sprintf(
			'<p class="baukasten-visibility-notice">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html__( 'This content is available to logged-in users only.', 'baukasten-content-visibility' ),
			esc_url( wp_login_url( self::current_url() ) ),
			esc_html__( 'Log in', 'baukasten-content-visibility' )
		);
	}

	/**
	 * Returns the URL of the current request.
	 *
	 * @return string Absolute URL.
	 */
	private static function current_url(): string {
		$requested = isset( $_SERVER['REQUEST_URI'] )
			? esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '';

		if ( '' === $requested ) {
			return home_url( '/' );
		}

		return home_url( $requested );
	}
}
