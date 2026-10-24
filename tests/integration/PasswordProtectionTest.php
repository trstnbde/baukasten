<?php
/**
 * Passwords on business cards and on the new tab page, and the link in the
 * admin bar.
 *
 * @package Baukasten\Tests
 */

use Baukasten\BusinessCards\Forms;
use Baukasten\BusinessCards\Post_Type;
use Baukasten\BusinessCards\Renderer as Card_Renderer;
use Baukasten\BusinessCards\VCard;
use Baukasten\ContentVisibility\Visibility;
use Baukasten\LoginLegalPages\Login_URL;
use Baukasten\LoginLegalPages\Rate_Limit;
use Baukasten\NewTab\Admin_Bar;
use Baukasten\NewTab\Renderer;
use Baukasten\NewTab\Settings;

/**
 * A locked card or page shows a password form and nothing else.
 */
class PasswordProtectionTest extends WP_UnitTestCase {

	/**
	 * The password used throughout.
	 */
	const PASSWORD = 'correct horse';

	/**
	 * A card with a password.
	 *
	 * @var int
	 */
	private int $card_id;

	/**
	 * The new tab page, with a password.
	 *
	 * @var int
	 */
	private int $page_id;

	/**
	 * Creates both and starts logged out.
	 */
	public function set_up(): void {
		parent::set_up();

		$GLOBALS['wp_rest_server'] = null;

		$this->card_id = self::factory()->post->create(
			array(
				'post_type'     => Post_Type::POST_TYPE,
				'post_status'   => 'publish',
				'post_title'    => 'Ada Lovelace card',
				'post_password' => self::PASSWORD,
			)
		);

		foreach ( array(
			'first_name' => 'Ada',
			'last_name'  => 'Lovelace',
			'email'      => 'ada@example.org',
			'phone'      => '+49 30 1234567',
			'enable_vcf' => '1',
		) as $key => $value ) {
			update_post_meta( $this->card_id, '_baukasten_card_' . $key, $value );
		}

		$this->page_id = self::factory()->post->create(
			array(
				'post_type'     => 'page',
				'post_title'    => 'Neuer Tab',
				'post_status'   => 'publish',
				'post_password' => self::PASSWORD,
			)
		);

		update_option( Settings::OPTION_PAGE, $this->page_id );
		Visibility::set( $this->page_id, Visibility::VISIBILITY_PUBLIC );

		$settings              = Settings::all();
		$settings['favorites'] = array(
			array(
				'title' => 'Intranet',
				'url'   => 'https://intranet.example.org/secret',
				'icon'  => '',
			),
		);

		Settings::save( $settings );

		$this->reset_renderer();

		$_SERVER['REMOTE_ADDR'] = '203.0.113.' . wp_rand( 1, 254 );
		$_GET                   = array();
		$_POST                  = array();

		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );
		wp_set_current_user( 0 );
	}

	/**
	 * Clears the request state the new tab renderer keeps.
	 */
	private function reset_renderer(): void {
		foreach ( array(
			'view'    => null,
			'address' => '',
		) as $property => $value ) {
			$reflection = new ReflectionProperty( Renderer::class, $property );
			$reflection->setAccessible( true );
			$reflection->setValue( null, $value );
		}
	}

	/**
	 * Puts the cookie of a correctly entered password into the request.
	 *
	 * @param string $password Password the visitor typed.
	 */
	private function enter_password( string $password = self::PASSWORD ): void {
		require_once ABSPATH . WPINC . '/class-phpass.php';

		$hasher = new PasswordHash( 8, true );

		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = $hasher->HashPassword( $password );
	}

	/**
	 * Renders a template file and returns what it printed.
	 *
	 * @param string $file Absolute path.
	 * @return string Output.
	 */
	private function render( string $file ): string {
		ob_start();
		require $file;

		return (string) ob_get_clean();
	}

	/* ------------------------------------------------------------------ Cards */

	/**
	 * A locked card prints the form and none of the card.
	 */
	public function test_locked_card_shows_no_fields(): void {
		$this->go_to( get_permalink( $this->card_id ) );

		$this->assertTrue( Card_Renderer::is_locked() );
		$this->assertStringEndsWith( 'templates/card-locked.php', Card_Renderer::use_card_template( 'theme.php' ) );

		$html = $this->render( \Baukasten\BusinessCards\PLUGIN_DIR . 'templates/card-locked.php' );

		$this->assertStringContainsString( 'post_password', $html );
		$this->assertStringContainsString( 'name="post_password"', $html );

		foreach ( array( 'Lovelace', 'ada@example.org', '1234567', 'mailto:', 'tel:', 'action=vcf', 'bkbc-avatar' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $html, $needle );
		}

		$this->assertStringNotContainsString( 'card.js', $html );
		$this->assertStringNotContainsString( 'qrcode', $html );
	}

	/**
	 * The title of the lock page is not the card's.
	 */
	public function test_locked_card_has_a_generic_title(): void {
		$this->go_to( get_permalink( $this->card_id ) );
		Card_Renderer::detach();

		$parts = apply_filters( 'document_title_parts', array( 'title' => 'Ada Lovelace card' ) );

		$this->assertSame( array( 'title' => 'Protected card' ), $parts );
		$this->assertArrayHasKey( 'X-Robots-Tag', Card_Renderer::lock_headers( array() ) );
		$this->assertSame( 'no-store, private', Card_Renderer::lock_headers( array() )['Cache-Control'] );
	}

	/**
	 * With the right cookie the card is shown as before.
	 */
	public function test_unlocked_card_is_shown(): void {
		$this->enter_password();
		$this->go_to( get_permalink( $this->card_id ) );

		$this->assertFalse( Card_Renderer::is_locked() );
		$this->assertStringEndsWith( 'templates/card-view.php', Card_Renderer::use_card_template( 'theme.php' ) );
	}

	/**
	 * A wrong cookie does not unlock it.
	 */
	public function test_wrong_password_stays_locked(): void {
		$this->enter_password( 'wrong' );
		$this->go_to( get_permalink( $this->card_id ) );

		$this->assertTrue( Card_Renderer::is_locked() );
	}

	/**
	 * A signed-in user, whatever the role, passes — for cards only.
	 */
	public function test_signed_in_users_pass_for_cards_only(): void {
		$other = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_password' => self::PASSWORD,
			)
		);

		foreach ( array( 'subscriber', 'editor', 'administrator' ) as $role ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );

			$this->go_to( get_permalink( $this->card_id ) );

			$this->assertFalse( Card_Renderer::is_locked(), $role );
			$this->assertTrue( post_password_required( $other ), $role . ' is not let past an ordinary post.' );
		}
	}

	/**
	 * A locked card hands out no vCard.
	 */
	public function test_locked_card_serves_no_vcard(): void {
		$this->go_to( get_permalink( $this->card_id ) . '?action=vcf' );

		$_GET['action'] = 'vcf';

		// An unlocked card would send the file and end the request; falling
		// through to the assertion is the proof that nothing was sent.
		VCard::maybe_serve();

		$this->assertTrue( post_password_required( $this->card_id ) );
	}

	/**
	 * A form submission from a locked card is refused.
	 */
	public function test_form_submission_from_a_locked_card_is_refused(): void {
		if ( ! class_exists( 'WPCF7_Validation' ) || ! class_exists( 'WPCF7_FormTag' ) ) {
			$this->markTestSkipped( 'Contact Form 7 is not loaded.' );
		}

		$tags            = array( new WPCF7_FormTag( array( 'type' => 'text', 'name' => 'your-name' ) ) );
		$_POST['_wpcf7_container_post'] = (string) $this->card_id;

		$refused = Forms::refuse_locked_card( new WPCF7_Validation(), $tags );

		$this->assertFalse( $refused->is_valid() );

		$this->enter_password();

		$allowed = Forms::refuse_locked_card( new WPCF7_Validation(), $tags );

		$this->assertTrue( $allowed->is_valid() );
	}

	/* ------------------------------------------------------------- New tab page */

	/**
	 * The locked page has no favourites, no search and no redirect.
	 */
	public function test_locked_page_shows_nothing_of_itself(): void {
		add_filter(
			'wp_redirect',
			static function () {
				throw new RuntimeException( 'redirect' );
			}
		);

		$this->go_to( get_permalink( $this->page_id ) . '&q=evil.example.com&e=brave' );

		$this->assertTrue( Renderer::is_locked() );

		Renderer::take_over();

		$this->assertSame( '', Renderer::address() );
		$this->assertStringEndsWith( 'templates/locked.php', Renderer::use_template( 'theme.php' ) );

		$html = $this->render( \Baukasten\NewTab\PLUGIN_DIR . 'templates/locked.php' );

		$this->assertStringContainsString( 'name="post_password"', $html );

		foreach ( array( 'intranet.example.org', 'Intranet', 'bk-config', 'new-tab.js', 'evil.example.com', 'bk-search' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $html, $needle );
		}

		$policy = Renderer::content_security_policy( Renderer::view(), true );

		$this->assertStringNotContainsString( 'unsafe-inline', $policy );
		$this->assertStringNotContainsString( 'sha256-47DEQpj8HBSa', $policy, 'No hash of an empty style.' );
	}

	/**
	 * With the cookie the page is the page.
	 */
	public function test_unlocked_page_is_the_page(): void {
		$this->enter_password();
		$this->go_to( get_permalink( $this->page_id ) );

		$this->assertFalse( Renderer::is_locked() );

		Renderer::take_over();

		$this->assertStringEndsWith( 'templates/new-tab.php', Renderer::use_template( 'theme.php' ) );
	}

	/**
	 * Signed-in users pass on the page, every role.
	 */
	public function test_signed_in_users_pass_on_the_page(): void {
		foreach ( array( 'subscriber', 'editor', 'administrator' ) as $role ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );

			$this->go_to( get_permalink( $this->page_id ) );
			$this->reset_renderer();

			$this->assertFalse( Renderer::is_locked(), $role );
		}
	}

	/**
	 * The routes answer 401 without the cookie and 200 with it.
	 */
	public function test_routes_follow_the_password(): void {
		add_filter(
			'pre_http_request',
			static fn() => array(
				'response' => array( 'code' => 200 ),
				'body'     => '["q",["one"]]',
				'headers'  => array(),
			)
		);

		foreach ( array( '/baukasten-new-tab/v1/suggest', '/baukasten-new-tab/v1/backgrounds' ) as $route ) {
			$request = new WP_REST_Request( 'GET', $route );
			$request->set_query_params( array( 'q' => 'abc' ) );

			$this->assertSame( 401, rest_get_server()->dispatch( $request )->get_status(), $route );
		}

		$this->enter_password();

		foreach ( array( '/baukasten-new-tab/v1/suggest', '/baukasten-new-tab/v1/backgrounds' ) as $route ) {
			$request = new WP_REST_Request( 'GET', $route );
			$request->set_query_params( array( 'q' => 'abc' ) );

			$this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status(), $route );
		}
	}

	/* --------------------------------------------------------------- Admin bar */

	/**
	 * Every signed-in role gets the node; nobody gets it without a page.
	 */
	public function test_admin_bar_node(): void {
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';

		foreach ( array( 'subscriber', 'contributor', 'editor', 'administrator' ) as $role ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );

			$bar = new WP_Admin_Bar();
			Admin_Bar::add_node( $bar );

			$node = $bar->get_node( Admin_Bar::NODE );

			$this->assertNotNull( $node, $role );
			$this->assertSame( get_permalink( $this->page_id ), $node->href, $role );
		}

		update_option( Settings::OPTION_PAGE, 0 );

		$bar = new WP_Admin_Bar();
		Admin_Bar::add_node( $bar );

		$this->assertNull( $bar->get_node( Admin_Bar::NODE ) );

		wp_set_current_user( 0 );
		update_option( Settings::OPTION_PAGE, $this->page_id );

		$bar = new WP_Admin_Bar();
		Admin_Bar::add_node( $bar );

		$this->assertNull( $bar->get_node( Admin_Bar::NODE ) );
	}

	/* -------------------------------------------------------------- Rate limit */

	/**
	 * The password form posts to the login address, which keeps the action.
	 */
	public function test_password_form_posts_to_the_login_address(): void {
		$this->set_permalink_structure( '/%postname%/' );

		$url = site_url( 'wp-login.php?action=postpass', 'login_post' );

		$this->assertSame( home_url( '/' . Login_URL::slug() . '/?action=postpass' ), $url );
	}

	/**
	 * Attempts past the limit end in a 429, every one of them counted.
	 */
	public function test_post_password_limit(): void {
		$_POST['post_password'] = 'guess';
		$limit                  = Rate_Limit::postpass_settings()['threshold'];

		for ( $i = 0; $i < $limit; $i++ ) {
			Rate_Limit::limit_post_passwords();
		}

		$this->expectException( WPDieException::class );

		Rate_Limit::limit_post_passwords();
	}

	/**
	 * The lockout is kept under a hashed address and ends.
	 */
	public function test_post_password_lockout_is_hashed_and_expires(): void {
		$_POST['post_password'] = 'guess';
		$limit                  = Rate_Limit::postpass_settings()['threshold'];

		for ( $i = 0; $i < $limit; $i++ ) {
			Rate_Limit::limit_post_passwords();
		}

		try {
			Rate_Limit::limit_post_passwords();
		} catch ( WPDieException $exception ) {
			unset( $exception );
		}

		$key = Rate_Limit::key();

		$this->assertGreaterThan( time(), Rate_Limit::postpass_locked_until( $key ) );
		$this->assertStringNotContainsString( $_SERVER['REMOTE_ADDR'], $key );

		delete_transient( Rate_Limit::PREFIX_POSTPASS_LOCK . $key );

		// Over: the next submission is counted again, not refused.
		Rate_Limit::limit_post_passwords();

		$this->assertSame( 1, (int) get_transient( Rate_Limit::PREFIX_POSTPASS . $key ) );
	}

	/**
	 * Opening the form is not an attempt.
	 */
	public function test_opening_the_form_does_not_count(): void {
		for ( $i = 0; $i < 50; $i++ ) {
			Rate_Limit::limit_post_passwords();
		}

		$this->assertFalse( get_transient( Rate_Limit::PREFIX_POSTPASS . Rate_Limit::key() ) );
	}
}
