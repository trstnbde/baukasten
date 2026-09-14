<?php
/**
 * The confirmation prompt in the admin bar.
 *
 * @package Baukasten\TwoFactor
 */

namespace Baukasten\TwoFactor;

defined( 'ABSPATH' ) || exit;

/**
 * Puts the prompt in front of the signed-in user, wherever they happen to be.
 *
 * Two polling speeds, on purpose. Heartbeat runs anyway for every signed-in
 * user and costs nothing extra to ride along on, but it ticks about once a
 * minute — fine for noticing that something is waiting, too slow to watch it.
 * So the fast poll, which is a real request every few seconds, only starts once
 * Heartbeat has said there is something to watch, and stops the moment the
 * challenge is answered or runs out.
 *
 * The node itself is always in the markup, hidden. Building it from JavaScript
 * instead would mean fighting every other plugin over where in the bar it
 * lands, and a node that appears at a different position each time is worse
 * than one that is simply empty most of the time.
 */
final class Admin_Bar {

	/**
	 * Script handle.
	 */
	const SCRIPT = 'baukasten-2fa-admin-bar';

	/**
	 * Style handle.
	 */
	const STYLE = 'baukasten-2fa-admin-bar';

	/**
	 * Admin bar node id.
	 */
	const NODE = 'baukasten-2fa';

	/**
	 * Registers the admin bar, its assets and the Heartbeat handler.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_node' ), 100 );

		// The admin bar shows on the front end too, so the assets have to be
		// enqueued on both sides.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		add_filter( 'heartbeat_received', array( __CLASS__, 'heartbeat' ), 10, 2 );
		add_filter( 'heartbeat_settings', array( __CLASS__, 'heartbeat_settings' ) );
	}

	/**
	 * Returns the current user's open challenge, looked up at most once.
	 *
	 * One indexed lookup per page that shows the admin bar. That is cheaper
	 * than the alternative — an extra HTTP round trip from the browser on every
	 * page load — and it is what lets a freshly opened page be correct
	 * immediately instead of waiting for a heartbeat that may be a minute away.
	 *
	 * @return array<string, mixed>|null The challenge, or null.
	 */
	private static function current_challenge(): ?array {
		static $looked_up = false;
		static $challenge = null;

		if ( $looked_up ) {
			return $challenge;
		}

		$looked_up = true;

		if ( is_user_logged_in() ) {
			$challenge = Challenges::pending_for_user( get_current_user_id(), Login_Screen::origin_token() );
		}

		return $challenge;
	}

	/**
	 * Adds the prompt node, already filled in when something is waiting.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The admin bar.
	 * @return void
	 */
	public static function add_node( $wp_admin_bar ): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$waiting = null !== self::current_challenge();

		$wp_admin_bar->add_node(
			array(
				'id'    => self::NODE,
				'title' => '<span class="ab-icon dashicons dashicons-shield-alt" aria-hidden="true"></span>'
					. '<span class="ab-label">' . esc_html__( 'Confirm sign-in', 'baukasten-2fa' ) . '</span>',
				'href'  => false,
				'meta'  => array(
					// Shown straight away when a challenge is already waiting,
					// so the prompt does not have to wait for a heartbeat.
					'class'      => $waiting ? 'baukasten-2fa-node baukasten-2fa-visible' : 'baukasten-2fa-node',
					'tabindex'   => 0,
					'aria-label' => esc_attr__( 'A sign-in is waiting for your confirmation', 'baukasten-2fa' ),
				),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => self::NODE . '-panel',
				'parent' => self::NODE,
				'title'  => self::panel(),
				'meta'   => array( 'class' => 'baukasten-2fa-panel-item' ),
			)
		);
	}

	/**
	 * Returns the panel markup.
	 *
	 * Rendered empty: the node is printed on every page, most of which have no
	 * challenge to describe. JavaScript fills in the details when there is one.
	 *
	 * @return string Panel HTML.
	 */
	private static function panel(): string {
		return sprintf(
			'<div class="baukasten-2fa-panel">
				<p class="baukasten-2fa-question">%1$s</p>
				<p class="baukasten-2fa-context" data-baukasten-2fa="context"></p>
				<p class="baukasten-2fa-buttons">
					<button type="button" class="button button-primary" data-baukasten-2fa="approve">%2$s</button>
					<button type="button" class="button" data-baukasten-2fa="deny">%3$s</button>
				</p>
				<p class="baukasten-2fa-result" data-baukasten-2fa="result" role="status" aria-live="polite"></p>
			</div>',
			esc_html__( 'Someone is signing in to your account. Was that you?', 'baukasten-2fa' ),
			esc_html__( 'Yes, it was me', 'baukasten-2fa' ),
			esc_html__( 'No', 'baukasten-2fa' )
		);
	}

	/**
	 * Enqueues the script and style when the admin bar is actually showing.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		if ( ! is_user_logged_in() || ! is_admin_bar_showing() ) {
			return;
		}

		wp_enqueue_style( self::STYLE, PLUGIN_URL . 'assets/css/admin-bar.css', array(), VERSION );

		// Heartbeat pulls in jQuery itself, but the dependency is named here as
		// well because this script binds the `heartbeat-send` event directly.
		wp_enqueue_script( 'heartbeat' );
		wp_enqueue_script( self::SCRIPT, PLUGIN_URL . 'assets/js/admin-bar.js', array( 'jquery', 'heartbeat' ), VERSION, true );

		wp_localize_script(
			self::SCRIPT,
			'baukasten2faBar',
			array(
				'pending'  => esc_url_raw( rest_url( REST_Controller::NAMESPACE . '/pending' ) ),
				'decide'   => esc_url_raw( rest_url( REST_Controller::NAMESPACE . '/decide' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'interval' => Settings::poll_interval() * 1000,
				// Whether something was already waiting when this page was
				// built. Lets the script start its fast poll at once instead of
				// idling until the first heartbeat.
				'waiting'  => null !== self::current_challenge(),
				'i18n'     => array(
					'approved' => __( 'Confirmed.', 'baukasten-2fa' ),
					'denied'   => __( 'Declined. The sign-in will not continue.', 'baukasten-2fa' ),
					'already'  => __( 'This was already answered.', 'baukasten-2fa' ),
					'stale'    => __( 'This sign-in request is no longer valid.', 'baukasten-2fa' ),
					'failed'   => __( 'That did not work. Please try again.', 'baukasten-2fa' ),
					/* translators: %s: coarse description of where the sign-in came from. */
					'from'     => __( 'From %s', 'baukasten-2fa' ),
				),
			)
		);
	}

	/**
	 * Answers whether a challenge is waiting for the current user.
	 *
	 * Only runs when the browser asked. That keeps the query off every
	 * Heartbeat tick on pages where this plugin's script is not loaded — the
	 * Heartbeat itself is shared with core and other plugins, and a query on
	 * every tick for every signed-in user is exactly the kind of permanent cost
	 * this design is trying to avoid.
	 *
	 * @param array<string, mixed> $response Response to send back.
	 * @param array<string, mixed> $data     Data the browser sent.
	 * @return array<string, mixed> Response.
	 */
	public static function heartbeat( $response, $data ): array {
		$response = is_array( $response ) ? $response : array();

		if ( ! isset( $data['baukasten_2fa'] ) || ! is_user_logged_in() ) {
			return $response;
		}

		$challenge = Challenges::pending_for_user( get_current_user_id(), Login_Screen::origin_token() );

		$response['baukasten_2fa'] = array(
			'pending' => null !== $challenge,
			// The `wp_rest` nonce is good for a day and WordPress does not
			// refresh it on the front end. The tab holding this prompt is by
			// design a long-lived one, so it gets a fresh nonce on every tick;
			// otherwise the button quietly starts returning 403 overnight.
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		);

		return $response;
	}

	/**
	 * Keeps Heartbeat ticking in an idle admin tab.
	 *
	 * Heartbeat stops after a few minutes without interaction, and the browser
	 * holding this prompt is by definition the one the user is not looking at.
	 * Only in wp-admin: switching suspension off for the front end would put a
	 * standing request behind every signed-in page view on the site, which is
	 * far more than this feature is worth.
	 *
	 * @param array<string, mixed> $settings Heartbeat settings.
	 * @return array<string, mixed> Settings.
	 */
	public static function heartbeat_settings( $settings ): array {
		$settings = is_array( $settings ) ? $settings : array();

		if ( is_admin() && Settings::heartbeat_keepalive() ) {
			$settings['suspension'] = 'disable';
		}

		return $settings;
	}
}
