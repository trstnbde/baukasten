<?php
/**
 * The consent REST endpoints.
 *
 * @package Baukasten\ConsentBlockingEngine
 */

namespace Baukasten\ConsentBlockingEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and records the visitor's decision.
 *
 * `POST /consent` has no capability check on purpose. The people whose consent
 * matters most are not logged in, and requiring a capability would make the
 * endpoint useless for exactly them. What guards it instead:
 *
 * - the `wp_rest` nonce, which WordPress issues to anonymous visitors too;
 * - a whitelist: only categories that are actually registered are accepted,
 *   never free text;
 * - a rate limit, because this is a public endpoint that writes rows.
 *
 * The rate limit keys a transient by a hash of the client address. The address
 * itself is neither stored nor logged, and the transient expires on its own.
 */
final class REST_Controller {

	/**
	 * REST namespace.
	 */
	const NAMESPACE = 'baukasten-consent/v1';

	/**
	 * Writes allowed per address within the window.
	 */
	private const RATE_LIMIT = 30;

	/**
	 * Rate limit window in seconds.
	 */
	private const RATE_WINDOW = 300;

	/**
	 * Registers the routes.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registers the two routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/consent-state',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_state' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/consent',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'set_consent' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'categories' => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'string' ),
					),
				),
			)
		);
	}

	/**
	 * Returns the categories and the visitor's current decision.
	 *
	 * @return \WP_REST_Response The current state.
	 */
	public static function get_state(): \WP_REST_Response {
		return new \WP_REST_Response( self::state(), 200 );
	}

	/**
	 * Records a decision.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error The new state, or an error.
	 */
	public static function set_consent( \WP_REST_Request $request ) {
		if ( ! self::within_rate_limit() ) {
			return new \WP_Error(
				'baukasten_consent_rate_limited',
				__( 'Too many consent updates. Please try again in a few minutes.', 'baukasten-consent-blocking-engine' ),
				array( 'status' => 429 )
			);
		}

		$requested = (array) $request->get_param( 'categories' );
		$requested = array_map( 'sanitize_key', array_map( 'strval', $requested ) );

		// Whitelist, not free text: only categories a visitor may actually
		// decide on are taken from the request. Required ones are added by
		// Consent_State::write() whether they were sent or not.
		$accepted = array_values( array_intersect( Categories::optional_slugs(), $requested ) );

		$existing = Consent_State::read();
		$payload  = Consent_State::write( $accepted, null === $existing ? '' : $existing['id'] );

		Consent_Log::record( $payload['id'], $payload['categories'], $payload['policy'] );

		/**
		 * Fires after a visitor's decision was recorded.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $categories Categories the visitor allows.
		 * @param string   $policy     Policy version in force.
		 */
		do_action( 'baukasten/consent/recorded', $payload['categories'], $payload['policy'] );

		return new \WP_REST_Response( self::state(), 200 );
	}

	/**
	 * Builds the state payload.
	 *
	 * @return array<string, mixed> Categories, the decision, and the policy version.
	 */
	public static function state(): array {
		$stored = Consent_State::read();

		$categories = array();

		foreach ( Categories::all() as $slug => $definition ) {
			$categories[] = array(
				'slug'        => $slug,
				'label'       => $definition['label'],
				'description' => $definition['description'],
				'required'    => (bool) $definition['required'],
			);
		}

		return array(
			'categories'     => $categories,
			'allowed'        => Consent_State::allowed(),
			'decided'        => null !== $stored,
			'policyVersion'  => Settings::policy_version(),
			'decidedAgainst' => null === $stored ? '' : $stored['policy'],
		);
	}

	/**
	 * Whether this client may write another row right now.
	 *
	 * @return bool True when within the limit.
	 */
	private static function within_rate_limit(): bool {
		$address = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: '';

		if ( '' === $address ) {
			return true;
		}

		$key   = 'baukasten_consent_rl_' . wp_hash( $address );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}

		set_transient( $key, $count + 1, self::RATE_WINDOW );

		return true;
	}
}
