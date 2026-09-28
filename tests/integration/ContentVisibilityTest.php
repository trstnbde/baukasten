<?php
/**
 * Private content stays private through embed, oEmbed and REST (Recommendation 1).
 *
 * @package Baukasten\Tests
 */

use Baukasten\ContentVisibility\Frontend_Guard;
use Baukasten\ContentVisibility\Visibility;

/**
 * Anonymous requests for a private post.
 */
class ContentVisibilityTest extends WP_UnitTestCase {

	/**
	 * A published post flagged private.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Creates the post and logs out.
	 */
	public function set_up(): void {
		parent::set_up();

		// Hooks are restored after every test, the REST server is not: start a
		// fresh one so rest_api_init runs again and the guards are registered.
		$GLOBALS['wp_rest_server'] = null;

		$this->post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Secret title',
				'post_excerpt' => 'Secret excerpt',
				'post_status'  => 'publish',
			)
		);

		Visibility::set( $this->post_id, Visibility::VISIBILITY_PRIVATE );
		wp_set_current_user( 0 );
	}

	/**
	 * The embed view answers 404 and has no post left to render.
	 */
	public function test_embed_view_is_not_found(): void {
		$this->go_to( get_post_embed_url( $this->post_id ) );

		$this->assertTrue( is_embed() );

		Frontend_Guard::guard_singular();

		$this->assertTrue( is_404() );
		$this->assertFalse( have_posts() );
	}

	/**
	 * A private posts page is guarded like any private page.
	 */
	public function test_private_posts_page_is_guarded(): void {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', self::factory()->post->create( array( 'post_type' => 'page' ) ) );
		update_option( 'page_for_posts', self::factory()->post->create( array( 'post_type' => 'page' ) ) );
		Visibility::set( (int) get_option( 'page_for_posts' ), Visibility::VISIBILITY_PRIVATE );

		// A 403 instead of the login redirect, which would end the test run.
		add_filter( 'baukasten/content_visibility/login_redirect', '__return_empty_string' );

		$this->go_to( get_permalink( (int) get_option( 'page_for_posts' ) ) );

		$this->assertTrue( is_home() );
		$this->expectException( WPDieException::class );

		Frontend_Guard::guard_singular();
	}

	/**
	 * The oEmbed endpoint treats the URL as unknown.
	 */
	public function test_oembed_endpoint_refuses(): void {
		$request = new WP_REST_Request( 'GET', '/oembed/1.0/embed' );
		$request->set_param( 'url', get_permalink( $this->post_id ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
		$this->assertStringNotContainsString( 'Secret title', wp_json_encode( $response->get_data() ) );
	}

	/**
	 * The REST item route refuses the post.
	 */
	public function test_rest_item_refuses(): void {
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $this->post_id ) );

		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
		$this->assertStringNotContainsString( 'Secret title', wp_json_encode( $response->get_data() ) );
	}

	/**
	 * A logged-in visitor still gets the oEmbed data.
	 */
	public function test_logged_in_visitor_sees_oembed(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( $this->post_id, Frontend_Guard::guard_oembed( $this->post_id ) );
	}
}
