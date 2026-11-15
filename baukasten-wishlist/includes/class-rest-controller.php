<?php
/**
 * The routes the page talks to.
 *
 * @package Baukasten\Wishlist
 */

namespace Baukasten\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * `GET  /baukasten-wishlist/v1/lists/<id>`        what is bought, per wish
 * `POST /baukasten-wishlist/v1/items/<id>/claim`   report one purchase
 * `DELETE /baukasten-wishlist/v1/items/<id>/claim` take one back, with its token
 *
 * Public, because guests are not logged in, and without a nonce, because a
 * cached page would carry an expired one. What stands in for it: reports
 * count against a rate limit per address, a purchase can only be taken back
 * with the token the reporting browser was given, and nothing is ever
 * reported beyond the quantity wished for. The routes answer nothing a list
 * on a page would not show — no titles, only counts.
 */
final class REST_Controller {

	/**
	 * Route namespace.
	 */
	const NAMESPACE = 'baukasten-wishlist/v1';

	/**
	 * Prefix of the rate limit transients.
	 */
	const RATE_PREFIX = 'baukasten_wishlist_rl_';

	/**
	 * Registers the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registers the routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		$id = array(
			'type'              => 'integer',
			'required'          => true,
			'minimum'           => 1,
			'sanitize_callback' => 'absint',
		);

		register_rest_route(
			self::NAMESPACE,
			'/lists/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_state' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'id' => $id ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/items/(?P<id>\d+)/claim',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'claim' ),
					'permission_callback' => '__return_true',
					'args'                => array( 'id' => $id ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'unclaim' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'id'    => $id,
						'token' => array(
							'type'     => 'string',
							'required' => true,
							'pattern'  => '^[a-f0-9]{32}$',
						),
					),
				),
			)
		);
	}

	/**
	 * Answers with the state of a list.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error State, or an error.
	 */
	public static function list_state( \WP_REST_Request $request ) {
		$list_id = (int) $request->get_param( 'id' );

		if ( ! Items::list_is_published( $list_id ) ) {
			return self::not_found();
		}

		return self::fresh( Items::state( $list_id ) );
	}

	/**
	 * Reports one purchase.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error The token and the new state, or an error.
	 */
	public static function claim( \WP_REST_Request $request ) {
		$item = self::item( (int) $request->get_param( 'id' ) );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		if ( ! self::within_rate_limit() ) {
			return self::rate_limited();
		}

		$token = Claims::claim( $item['id'] );

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		return self::fresh(
			array_merge(
				array( 'token' => $token ),
				self::item_state( $item['id'] )
			),
			201
		);
	}

	/**
	 * Takes back one purchase.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error The new state, or an error.
	 */
	public static function unclaim( \WP_REST_Request $request ) {
		$item = self::item( (int) $request->get_param( 'id' ) );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		if ( ! self::within_rate_limit() ) {
			return self::rate_limited();
		}

		if ( ! Claims::unclaim( $item['id'], (string) $request->get_param( 'token' ) ) ) {
			return new \WP_Error(
				'baukasten_wishlist_unknown_report',
				__( 'This report does not exist any more. Perhaps it was already taken back.', 'baukasten-wishlist' ),
				array( 'status' => 404 )
			);
		}

		return self::fresh( self::item_state( $item['id'] ) );
	}

	/**
	 * A wish that takes reports, or why not.
	 *
	 * @param int $item_id Wish post ID.
	 * @return array{id: int, list: int}|\WP_Error Wish and list IDs, or an error.
	 */
	private static function item( int $item_id ) {
		$item = get_post( $item_id );

		if ( ! $item instanceof \WP_Post || Post_Types::ITEM_TYPE !== $item->post_type || 'publish' !== $item->post_status ) {
			return self::not_found();
		}

		$list_id = (int) Fields::load_item( $item_id )['list'];

		if ( ! Items::list_is_published( $list_id ) ) {
			return self::not_found();
		}

		if ( ! Claims::is_open( $list_id ) ) {
			return new \WP_Error(
				'baukasten_wishlist_closed',
				__( 'This list no longer takes reports.', 'baukasten-wishlist' ),
				array( 'status' => 403 )
			);
		}

		return array(
			'id'   => $item_id,
			'list' => $list_id,
		);
	}

	/**
	 * What is bought of one wish.
	 *
	 * @param int $item_id Wish post ID.
	 * @return array<string, int> Quantity, bought and remaining.
	 */
	private static function item_state( int $item_id ): array {
		$quantity = (int) Fields::load_item( $item_id )['quantity'];
		$bought   = Claims::count( $item_id );

		return array(
			'id'        => $item_id,
			'quantity'  => $quantity,
			'bought'    => min( $bought, $quantity ),
			'remaining' => max( 0, $quantity - $bought ),
		);
	}

	/**
	 * Counts one report against the visitor's address.
	 *
	 * A fixed window per hashed address, like the New Tab Page's suggestions.
	 * The address itself is never stored.
	 *
	 * @return bool Whether the report may go ahead.
	 */
	public static function within_rate_limit(): bool {
		$limit = Settings::rate_limit();

		if ( 0 === $limit ) {
			return true;
		}

		$key = self::address_key();

		if ( '' === $key ) {
			return true;
		}

		$key    = self::RATE_PREFIX . $key;
		$bucket = get_transient( $key );
		$now    = time();
		$window = Settings::RATE_WINDOW;

		if ( ! is_array( $bucket ) || ! isset( $bucket['start'], $bucket['count'] ) || $now - (int) $bucket['start'] >= $window ) {
			$bucket = array(
				'start' => $now,
				'count' => 0,
			);
		}

		if ( (int) $bucket['count'] >= $limit ) {
			return false;
		}

		++$bucket['count'];

		set_transient( $key, $bucket, max( 1, $window - ( $now - (int) $bucket['start'] ) ) );

		return true;
	}

	/**
	 * The hashed visitor address.
	 *
	 * Taken from Login & Legal's rate limit where the core has it, so a site
	 * behind a proxy configures the address once for both.
	 *
	 * @return string Hash, or an empty string without an address.
	 */
	private static function address_key(): string {
		if ( class_exists( '\Baukasten\LoginLegalPages\Rate_Limit' ) && method_exists( '\Baukasten\LoginLegalPages\Rate_Limit', 'key' ) ) {
			return (string) \Baukasten\LoginLegalPages\Rate_Limit::key();
		}

		$address = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: '';

		return false === filter_var( $address, FILTER_VALIDATE_IP ) ? '' : substr( wp_hash( $address ), 0, 32 );
	}

	/**
	 * A response no cache keeps.
	 *
	 * @param array<string, mixed> $data   Body.
	 * @param int                  $status HTTP status.
	 * @return \WP_REST_Response Response.
	 */
	private static function fresh( array $data, int $status = 200 ): \WP_REST_Response {
		$response = new \WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * The error for a wish or list that cannot be reported on.
	 *
	 * @return \WP_Error Error with status 404.
	 */
	private static function not_found(): \WP_Error {
		return new \WP_Error( 'baukasten_wishlist_not_found', __( 'This wish does not exist.', 'baukasten-wishlist' ), array( 'status' => 404 ) );
	}

	/**
	 * The error for too many reports.
	 *
	 * @return \WP_Error Error with status 429.
	 */
	private static function rate_limited(): \WP_Error {
		return new \WP_Error(
			'baukasten_wishlist_rate_limited',
			__( 'Too many reports from this connection. Please wait a few minutes.', 'baukasten-wishlist' ),
			array( 'status' => 429 )
		);
	}
}
