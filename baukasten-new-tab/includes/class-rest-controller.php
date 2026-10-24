<?php
/**
 * The page's two REST routes.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * `GET /baukasten-new-tab/v1/suggest?q=` and `GET /baukasten-new-tab/v1/backgrounds`.
 *
 * Both are public, because the page is usually opened logged out — a browser
 * start page is a URL, not a session. They answer only what the page itself
 * shows anyway, and nothing at all while the page is private and the visitor
 * is not logged in: Content Visibility sends that visitor to the login
 * screen, and the routes should not tell them more than the page would.
 *
 * Neither ever includes the Unsplash access key; the integration test checks.
 */
final class REST_Controller {

	/**
	 * Route namespace.
	 */
	const NAMESPACE = 'baukasten-new-tab/v1';

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
		register_rest_route(
			self::NAMESPACE,
			'/suggest',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'suggest' ),
				'permission_callback' => array( __CLASS__, 'may_read' ),
				'args'                => array(
					'q' => array(
						'type'              => 'string',
						'required'          => true,
						'maxLength'         => Search::MAX_LENGTH,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/backgrounds',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'backgrounds' ),
				'permission_callback' => array( __CLASS__, 'may_read' ),
			)
		);
	}

	/**
	 * Whether the current visitor may see what the page shows.
	 *
	 * @return bool|\WP_Error True, or the reason not.
	 */
	public static function may_read() {
		$page_id = Settings::page_id();

		if ( 0 >= $page_id ) {
			return new \WP_Error( 'baukasten_new_tab_no_page', __( 'No new tab page is selected.', 'baukasten-new-tab' ), array( 'status' => 404 ) );
		}

		if ( class_exists( '\Baukasten\ContentVisibility\Frontend_Guard' ) && \Baukasten\ContentVisibility\Frontend_Guard::is_blocked( $page_id ) ) {
			return new \WP_Error( 'baukasten_new_tab_private', __( 'The new tab page is available to logged-in users only.', 'baukasten-new-tab' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Answers a suggestion request.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error OpenSearch suggestions, or an error.
	 */
	public static function suggest( \WP_REST_Request $request ) {
		$query = Search::clean_entry( (string) $request->get_param( 'q' ) );

		if ( ! Suggest::within_rate_limit() ) {
			return new \WP_Error(
				'baukasten_new_tab_rate_limited',
				__( 'Too many suggestion requests. Please wait a minute.', 'baukasten-new-tab' ),
				array( 'status' => 429 )
			);
		}

		$response = new \WP_REST_Response( array( $query, Suggest::lookup( $query ) ), 200 );
		$response->header( 'Cache-Control', 'private, max-age=300' );

		return $response;
	}

	/**
	 * Answers with the backgrounds the page can switch to.
	 *
	 * @return \WP_REST_Response Mode and items.
	 */
	public static function backgrounds(): \WP_REST_Response {
		$response = new \WP_REST_Response( Backgrounds::for_rest(), 200 );
		$response->header( 'Cache-Control', 'private, no-cache' );

		return $response;
	}
}
