<?php
/**
 * The Two Factor provider.
 *
 * @package Baukasten\TwoFactor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Asks a second, already signed-in browser session to approve the login.
 *
 * Deliberately not namespaced, unlike the rest of this plugin. Two Factor
 * resolves providers by calling `class_exists()` on the array key handed to the
 * `two_factor_providers` filter, and that same string is then written to user
 * meta, to session meta and to the site-wide allowlist option — it is a stored
 * data format, not just an identifier. A namespaced class would work only with
 * the fully qualified name as the key, which reads badly next to core's own
 * `Two_Factor_Email` and `Two_Factor_Totp`.
 *
 * The name is `Baukasten_Two_Factor_Approve` rather than the more natural
 * `Two_Factor_Baukasten_Approve` because PHPCS's `PrefixAllGlobals` sniff is
 * configured for this repository with the prefixes `baukasten`/`Baukasten`, and
 * a global class not starting with one of them fails the build.
 *
 * For the same sniff, and because `Two_Factor_Core::uninstall()` includes this
 * file at arbitrary times, the file holds this one class declaration and
 * nothing else: no constants of its own outside the class, no functions, no
 * globals.
 */
class Baukasten_Two_Factor_Approve extends Two_Factor_Provider {

	/**
	 * Hidden field carrying the challenge id back with the form.
	 */
	const INPUT_NAME_CHALLENGE = 'baukasten-2fa-challenge';

	/**
	 * Submit button name carrying which button was pressed.
	 */
	const INPUT_NAME_ACTION = 'baukasten-2fa-action';

	/**
	 * Registers the provider's own hooks.
	 *
	 * Runs on every request: Two Factor calls `get_providers()` on `init` at
	 * priority 10 purely so providers can hook themselves up. Kept inert for
	 * that reason — no translations (too early would trip the 6.7 notice), no
	 * database access, nothing that costs anything on a request that will never
	 * show a login form.
	 */
	protected function __construct() {
		add_action( 'two_factor_user_options_' . __CLASS__, array( $this, 'user_options' ) );

		parent::__construct();
	}

	/**
	 * Returns the provider's name.
	 *
	 * @return string Label.
	 */
	public function get_label() {
		return _x( 'Confirmation in another session', 'Provider Label', 'baukasten-2fa' );
	}

	/**
	 * Returns the label used to offer this as an alternative method.
	 *
	 * @return string Label.
	 */
	public function get_alternative_provider_label() {
		return __( 'Confirm in another signed-in session', 'baukasten-2fa' );
	}

	/**
	 * Whether this method is set up for the user.
	 *
	 * Always true, and it has to be.
	 *
	 * The tempting reading of this method is "does the user have a second
	 * session open right now", and answering it would be a critical bug. Two
	 * Factor drops every provider that reports unavailable, and if that empties
	 * the list, `is_user_using_two_factor()` turns false and the user is signed
	 * in on the password alone. Its fail-open rescue does not help here,
	 * because that only fires when the user has no enabled provider *keys* at
	 * all, and ours would be there.
	 *
	 * There is also nothing to enrol: any account can be asked to confirm from
	 * another session. Whether one is actually open is a runtime condition, and
	 * it belongs in the waiting screen, which says so and offers the user's
	 * other methods.
	 *
	 * @param WP_User $user User to check.
	 * @return bool Always true.
	 */
	public function is_available_for_user( $user ) {
		unset( $user );

		return true;
	}

	/**
	 * Prints the waiting screen into Two Factor's login form.
	 *
	 * @param WP_User $user User signing in.
	 * @return void
	 */
	public function authentication_page( $user ) {
		\Baukasten\TwoFactor\Login_Screen::render( $user );
	}

	/**
	 * Handles the form's own buttons before validation runs.
	 *
	 * @param WP_User $user User signing in.
	 * @return bool True to re-render the page without validating.
	 */
	public function pre_process_authentication( $user ) {
		return \Baukasten\TwoFactor\Login_Screen::pre_process( $user );
	}

	/**
	 * Decides whether the login may finish.
	 *
	 * Must return exactly `true`: Two Factor compares with `true !==`, and
	 * anything else counts as a failed attempt.
	 *
	 * @param WP_User $user User signing in.
	 * @return bool True when an approved challenge was spent.
	 */
	public function validate_authentication( $user ) {
		return \Baukasten\TwoFactor\Login_Screen::validate( $user );
	}

	/**
	 * Prints the explanation on the user's profile screen.
	 *
	 * @param WP_User $user User whose profile is shown.
	 * @return void
	 */
	public function user_options( $user ) {
		unset( $user );

		echo '<p class="description">';
		echo esc_html__(
			'When you sign in, any other browser where you are already signed in shows a confirmation in the admin bar. Approving it there completes the sign-in.',
			'baukasten-2fa'
		);
		echo '</p>';
	}

	/**
	 * User meta keys to delete when Two Factor is uninstalled.
	 *
	 * Empty on purpose. Two Factor calls this while *it* is being deleted, and
	 * this plugin's data is not its to remove — that is what our own
	 * `uninstall.php` is for.
	 *
	 * @return array<int, string> Always empty.
	 */
	public static function uninstall_user_meta_keys() {
		return array();
	}

	/**
	 * Options to delete when Two Factor is uninstalled.
	 *
	 * Empty for the same reason as `uninstall_user_meta_keys()`.
	 *
	 * @return array<int, string> Always empty.
	 */
	public static function uninstall_options() {
		return array();
	}
}
