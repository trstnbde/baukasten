<?php
/**
 * The challenge REST endpoints.
 *
 * @package Baukasten\TwoFactor
 */

namespace Baukasten\TwoFactor;

defined( 'ABSPATH' ) || exit;

/**
 * Three routes: one for the waiting login page, two for the admin bar.
 *
 * `GET /challenge/<id>` is the odd one out, because the browser polling it is
 * by definition not signed in yet. It is guarded by possession of the challenge
 * id alone — 256 bits from `random_bytes()`, stored only as a hash.
 *
 * The obvious alternative, Two Factor's own login nonce, would be worse:
 * `Two_Factor_Core::verify_login_nonce()` *deletes* the nonce when verification
 * fails, so a pollable endpoint built on it would hand anyone who can guess a
 * user id a way to kill other people's sign-ins. The id is a bearer token
 * instead, and the response is stripped to a single word so that holding it
 * reveals nothing but the answer to the question it already identifies: no user
 * name, no address, no browser.
 *
 * Everything a person might want to see before answering — the anonymised
 * address, the coarse browser — is on `/pending`, which is authenticated.
 *
 * Every parameter is read through `WP_REST_Request::get_param()` rather than a
 * superglobal, which is also why none of these callbacks needs a nonce
 * verification annotation.
 */
final class REST_Controller {

	/**
	 * REST namespace.
	 */
	const NAMESPACE = 'baukasten-2fa/v1';

	/**
	 * Status reads allowed per address within the window.
	 */
	private const RATE_LIMIT = 400;

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
	 * Registers the three routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/challenge/(?P<id>[0-9a-f]{64})',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_status' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => array( Challenges::class, 'is_valid_id' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/pending',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_pending' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/decide',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'decide' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					// The opaque handle from `/pending`, not a challenge id.
					'handle'   => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[0-9a-f]{32,128}$',
					),
					'decision' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'approve', 'deny' ),
					),
				),
			)
		);
	}

	/**
	 * Returns nothing but the status of one challenge.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error The status, or an error.
	 */
	public static function get_status( \WP_REST_Request $request ) {
		if ( ! self::within_rate_limit() ) {
			return new \WP_Error(
				'baukasten_2fa_rate_limited',
				__( 'Too many requests.', 'baukasten-2fa' ),
				array( 'status' => 429 )
			);
		}

		$challenge = Challenges::find( (string) $request->get_param( 'id' ) );

		if ( null === $challenge ) {
			return new \WP_Error(
				'baukasten_2fa_not_found',
				__( 'Unknown request.', 'baukasten-2fa' ),
				array( 'status' => 404 )
			);
		}

		$response = new \WP_REST_Response(
			array( 'status' => (string) $challenge['status'] ),
			200
		);

		// A REST cache in front of the site would otherwise be free to serve
		// "pending" for the rest of the challenge's life.
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

		return $response;
	}

	/**
	 * Returns the current user's open challenge, if any.
	 *
	 * @return \WP_REST_Response The challenge, or an empty payload.
	 */
	public static function get_pending(): \WP_REST_Response {
		$user_id   = get_current_user_id();
		$challenge = Challenges::pending_for_user( $user_id, Login_Screen::origin_token() );

		if ( null === $challenge ) {
			$response = new \WP_REST_Response( array( 'pending' => false ), 200 );
			$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

			return $response;
		}

		$payload = array(
			'pending'   => true,
			'expiresAt' => (int) strtotime( (string) $challenge['expires_at'] . ' UTC' ) * 1000,
			'context'   => (string) $challenge['context'],
		);

		if ( Settings::show_context() ) {
			$payload['ipLabel']    = (string) $challenge['ip_label'];
			$payload['agentLabel'] = (string) $challenge['agent_label'];
		}

		// The plaintext id is not stored, so the admin bar cannot be handed it.
		// It does not need one: `/decide` accepts the id it is given only after
		// checking it belongs to the caller, and the caller has exactly one
		// open challenge at a time. What goes out is a per-user handle the
		// admin bar echoes straight back.
		$payload['handle'] = self::handle_for( $user_id, (int) $challenge['id'] );
		$payload['nonce']  = wp_create_nonce( 'wp_rest' );

		$response = new \WP_REST_Response( $payload, 200 );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

		return $response;
	}

	/**
	 * Answers a challenge.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error The new status, or an error.
	 */
	public static function decide( \WP_REST_Request $request ) {
		$user_id   = get_current_user_id();
		$handle    = (string) $request->get_param( 'handle' );
		$challenge = Challenges::pending_for_user( $user_id, Login_Screen::origin_token() );

		if ( null === $challenge ) {
			return new \WP_Error(
				'baukasten_2fa_not_found',
				__( 'This request is no longer waiting for an answer.', 'baukasten-2fa' ),
				array( 'status' => 404 )
			);
		}

		if ( ! hash_equals( self::handle_for( $user_id, (int) $challenge['id'] ), $handle ) ) {
			return new \WP_Error(
				'baukasten_2fa_mismatch',
				__( 'This request is no longer waiting for an answer.', 'baukasten-2fa' ),
				array( 'status' => 404 )
			);
		}

		// The login this challenge belongs to may have been overtaken by a
		// newer one, which rotates Two Factor's login nonce. Answering it now
		// would leave the other browser waiting for something that can never
		// be spent, so say so here where somebody is actually looking.
		if ( Login_Screen::nonce_hash( $user_id ) !== (string) $challenge['nonce_hash'] ) {
			return new \WP_Error(
				'baukasten_2fa_stale',
				__( 'This sign-in request is no longer valid. Start the sign-in again.', 'baukasten-2fa' ),
				array( 'status' => 409 )
			);
		}

		$approve = 'approve' === $request->get_param( 'decision' );
		$decided = Challenges::decide( (int) $challenge['id'], $user_id, $approve );

		if ( ! $decided ) {
			// Somebody else got there first — another tab, or a double click.
			// Not an error worth a dialog; the admin bar just catches up.
			return new \WP_REST_Response( array( 'status' => 'already-decided' ), 200 );
		}

		if ( $approve ) {
			/**
			 * Fires when a user approves a sign-in from another session.
			 *
			 * @since 1.0.0
			 *
			 * @param int $user_id User who approved.
			 */
			do_action( 'baukasten/2fa/approved', $user_id );
		} else {
			/**
			 * Fires when a user declines a sign-in from another session.
			 *
			 * @since 1.0.0
			 *
			 * @param int $user_id User who declined.
			 */
			do_action( 'baukasten/2fa/denied', $user_id );
		}

		return new \WP_REST_Response(
			array( 'status' => $approve ? Challenges::STATUS_APPROVED : Challenges::STATUS_DENIED ),
			200
		);
	}

	/**
	 * Returns an opaque, user-bound handle for one challenge row.
	 *
	 * The admin bar never sees the plaintext challenge id — that belongs to the
	 * browser signing in. It gets this instead: a hash of the row id under the
	 * site's salt and the user's own id, so it cannot be transplanted to
	 * another account and reveals nothing about the challenge.
	 *
	 * @param int $user_id User id.
	 * @param int $row_id  Row id.
	 * @return string Handle.
	 */
	private static function handle_for( int $user_id, int $row_id ): string {
		return wp_hash( 'baukasten-2fa|' . $user_id . '|' . $row_id );
	}

	/**
	 * Whether this client may read another status right now.
	 *
	 * The status route is public, so a tab left open all night is 1200 requests
	 * an hour before anybody is malicious. The limit is generous enough that
	 * normal polling never reaches it, and the client backs off on its own.
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

		$key   = 'baukasten_2fa_rl_' . wp_hash( $address );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}

		set_transient( $key, $count + 1, self::RATE_WINDOW );

		return true;
	}
}
