<?php
/**
 * The waiting screen on wp-login.php.
 *
 * @package Baukasten\TwoFactor
 */

namespace Baukasten\TwoFactor;

defined( 'ABSPATH' ) || exit;

/**
 * Everything the login form does: opening a challenge, waiting for it, spending it.
 *
 * The provider class delegates here so that the one unnamespaced file in this
 * plugin stays a thin shell.
 */
final class Login_Screen {

	/**
	 * Script handle for the poller.
	 */
	const SCRIPT = 'baukasten-2fa-login';

	/**
	 * Style handle for the waiting screen.
	 */
	const STYLE = 'baukasten-2fa-login';

	/**
	 * Registers the login screen hooks.
	 *
	 * Only *registers* the script here. `login_enqueue_scripts` fires inside
	 * `login_header()`, which Two Factor calls before it reaches the provider,
	 * so enqueuing at this point would ship the poller to the password form and
	 * the lost-password screen as well. The enqueue happens in `render()`.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'register_script' ) );
	}

	/**
	 * Registers the poller script.
	 *
	 * No dependencies: jQuery is not loaded on wp-login.php.
	 *
	 * @return void
	 */
	public static function register_script(): void {
		wp_register_style( self::STYLE, PLUGIN_URL . 'assets/css/login.css', array(), VERSION );

		wp_register_script(
			self::SCRIPT,
			PLUGIN_URL . 'assets/js/login.js',
			array(),
			VERSION,
			true
		);
	}

	/**
	 * Prints the waiting screen inside Two Factor's form.
	 *
	 * @param \WP_User|null $user User signing in.
	 * @return void
	 */
	public static function render( $user ): void {
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		wp_enqueue_style( self::STYLE );

		$challenge = Challenges::create(
			$user->ID,
			self::is_revalidation() ? 'revalidate' : 'login',
			self::nonce_hash( $user->ID ),
			self::origin_token()
		);

		require_once ABSPATH . 'wp-admin/includes/template.php';

		echo '<p class="two-factor-prompt">';
		echo esc_html__(
			'Open one of your other signed-in browsers. A confirmation is waiting in its admin bar.',
			'baukasten-2fa'
		);
		echo '</p>';

		if ( null === $challenge ) {
			// Rate limited, or the row could not be written. Say so and offer
			// nothing to poll — but still print the buttons, so that Two
			// Factor's own list of alternative methods stays reachable below
			// the form. Never `wp_die()` here: that would strand the user on a
			// dead end with no way to pick another method.
			echo '<p class="baukasten-2fa-error" role="alert">';
			echo esc_html__(
				'Too many confirmation requests were sent recently. Please wait a few minutes, or use one of the other methods below.',
				'baukasten-2fa'
			);
			echo '</p>';

			self::render_buttons();

			return;
		}

		printf(
			'<input type="hidden" name="%1$s" id="%1$s" value="%2$s" />',
			esc_attr( \Baukasten_Two_Factor_Approve::INPUT_NAME_CHALLENGE ),
			esc_attr( $challenge['id'] )
		);

		echo '<p class="baukasten-2fa-status" id="baukasten-2fa-status" role="status" aria-live="polite">';
		echo esc_html__( 'Waiting for confirmation…', 'baukasten-2fa' );
		echo '</p>';

		echo '<noscript><p>';
		echo esc_html__(
			'JavaScript is switched off, so this page cannot notice the confirmation on its own. Approve the request in your other browser, then press the button below.',
			'baukasten-2fa'
		);
		echo '</p></noscript>';

		self::render_buttons();

		wp_enqueue_script( self::SCRIPT );

		wp_localize_script(
			self::SCRIPT,
			'baukasten2faLogin',
			array(
				'endpoint'  => esc_url_raw( rest_url( REST_Controller::NAMESPACE . '/challenge/' . $challenge['id'] ) ),
				'interval'  => Settings::poll_interval() * 1000,
				'expiresAt' => $challenge['expires_at'] * 1000,
				'i18n'      => array(
					'waiting'  => __( 'Waiting for confirmation…', 'baukasten-2fa' ),
					'approved' => __( 'Confirmed. Signing you in…', 'baukasten-2fa' ),
					'denied'   => __( 'The request was declined. This sign-in will not continue.', 'baukasten-2fa' ),
					'expired'  => __( 'The request expired. Send a new one, or use another method below.', 'baukasten-2fa' ),
					'offline'  => __( 'Cannot reach the site right now. Still trying…', 'baukasten-2fa' ),
				),
			)
		);
	}

	/**
	 * Prints the three submit buttons, in the order they have to be in.
	 *
	 * "I have confirmed" comes first because implicit form submission — the
	 * user pressing Enter — activates the first submit button in the form. With
	 * cancel first, Enter would abandon the login. It doubles as the way this
	 * screen works without JavaScript.
	 *
	 * @return void
	 */
	private static function render_buttons(): void {
		$name = \Baukasten_Two_Factor_Approve::INPUT_NAME_ACTION;

		printf(
			'<p class="baukasten-2fa-actions">
				<button type="submit" class="button button-primary button-large" name="%1$s" value="check">%2$s</button>
				<button type="submit" class="button" name="%1$s" value="renew">%3$s</button>
				<button type="submit" class="button" name="%1$s" value="cancel">%4$s</button>
			</p>',
			esc_attr( $name ),
			esc_html__( 'I have confirmed', 'baukasten-2fa' ),
			esc_html__( 'Send again', 'baukasten-2fa' ),
			esc_html__( 'Cancel', 'baukasten-2fa' )
		);
	}

	/**
	 * Handles this form's own buttons before Two Factor validates anything.
	 *
	 * Returning true makes `process_provider()` re-render the page without
	 * touching the failure counter and without calling `validate_authentication()`.
	 *
	 * POST only, and `$_POST` only. Two Factor's revalidation route does not
	 * verify a nonce on GET requests at all, so reading `$_REQUEST` here would
	 * mean a prefetched or previewed link could open challenges.
	 *
	 * @param \WP_User|null $user User signing in.
	 * @return bool True to re-render without validating.
	 */
	public static function pre_process( $user ): bool {
		if ( ! $user instanceof \WP_User || ! self::is_post() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Two Factor verified its login nonce in `_login_form_validate_2fa()` before reaching the provider.
		$action = isset( $_POST[ \Baukasten_Two_Factor_Approve::INPUT_NAME_ACTION ] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- See above.
			? sanitize_key( wp_unslash( (string) $_POST[ \Baukasten_Two_Factor_Approve::INPUT_NAME_ACTION ] ) )
			: '';

		if ( 'cancel' === $action ) {
			Challenges::cancel( self::posted_challenge(), $user->ID );

			// Drop the login nonce too, so the abandoned attempt cannot be
			// resumed from a stale tab, then leave for a clean login form.
			// Returning true instead would re-render the identical page and
			// look like the button did nothing.
			\Two_Factor_Core::delete_login_nonce( $user->ID );

			wp_safe_redirect( wp_login_url() );
			exit;
		}

		if ( 'renew' === $action ) {
			// `render()` supersedes and creates, so simply re-rendering is a
			// new request.
			return true;
		}

		return false;
	}

	/**
	 * Decides whether the login may finish.
	 *
	 * @param \WP_User|null $user User signing in.
	 * @return bool True when an approved challenge was spent.
	 */
	public static function validate( $user ): bool {
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		return Challenges::consume(
			self::posted_challenge(),
			$user->ID,
			self::nonce_hash( $user->ID )
		);
	}

	/**
	 * Returns the hash of the user's current Two Factor login nonce.
	 *
	 * Two Factor keeps exactly one login nonce per user and rotates it on every
	 * render of the two-factor form. Recording which nonce a challenge was
	 * raised against lets both sides notice when a second login attempt has
	 * overtaken the first — the approving browser gets a clear message instead
	 * of the waiting one silently landing on the front page.
	 *
	 * The stored value is already a hash, so nothing secret is copied here.
	 *
	 * @param int $user_id User id.
	 * @return string Hash, or an empty string.
	 */
	public static function nonce_hash( int $user_id ): string {
		$nonce = get_user_meta( $user_id, \Two_Factor_Core::USER_META_NONCE_KEY, true );

		if ( ! is_array( $nonce ) || empty( $nonce['key'] ) ) {
			return '';
		}

		return (string) $nonce['key'];
	}

	/**
	 * Returns a hash of the current session token, when there is one.
	 *
	 * Identifies the browser a challenge was raised *in*, so that browser can
	 * be kept from confirming it. Empty for a fresh sign-in, where Two Factor
	 * has already cleared the auth cookie.
	 *
	 * @return string Hashed token, or an empty string.
	 */
	public static function origin_token(): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$token = wp_get_session_token();

		return '' === $token ? '' : wp_hash( $token );
	}

	/**
	 * Returns the challenge id that came back with the form.
	 *
	 * @return string Plaintext challenge id, or an empty string.
	 */
	private static function posted_challenge(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Two Factor verified its login nonce in `_login_form_validate_2fa()` before reaching the provider.
		if ( ! isset( $_POST[ \Baukasten_Two_Factor_Approve::INPUT_NAME_CHALLENGE ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- See above.
		$id = sanitize_text_field( wp_unslash( (string) $_POST[ \Baukasten_Two_Factor_Approve::INPUT_NAME_CHALLENGE ] ) );

		return Challenges::is_valid_id( $id ) ? $id : '';
	}

	/**
	 * Whether the current request is a POST.
	 *
	 * @return bool True on POST.
	 */
	private static function is_post(): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) )
			: '';

		return 'POST' === $method;
	}

	/**
	 * Whether this is Two Factor's revalidation flow rather than a sign-in.
	 *
	 * @return bool True during revalidation.
	 */
	private static function is_revalidation(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the action only, to label the challenge.
		$action = isset( $_REQUEST['action'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above.
			? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) )
			: '';

		return 'revalidate_2fa' === $action;
	}
}
