<?php
/**
 * The Wishlist addon: fields, reported purchases, the routes and the page.
 *
 * @package Baukasten\Tests
 */

use Baukasten\Wishlist\Claims;
use Baukasten\Wishlist\Fields;
use Baukasten\Wishlist\Post_Types;
use Baukasten\Wishlist\Renderer;
use Baukasten\Wishlist\REST_Controller;
use Baukasten\Wishlist\Settings;
use Baukasten\Wishlist\Shortcode;

/**
 * Baukasten Addon: Wishlist.
 */
class WishlistTest extends WP_UnitTestCase {

	/**
	 * A published list.
	 *
	 * @var int
	 */
	private int $list_id;

	/**
	 * A published wish on it, wanted twice.
	 *
	 * @var int
	 */
	private int $item_id;

	/**
	 * Creates a list with one wish and starts logged out.
	 */
	public function set_up(): void {
		parent::set_up();

		$GLOBALS['wp_rest_server'] = null;
		$_SERVER['REMOTE_ADDR']    = '192.0.2.10';

		update_option( Settings::OPTION_RATE_LIMIT, 20 );

		$this->list_id = self::factory()->post->create(
			array(
				'post_type'   => Post_Types::LIST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Leni turns two',
			)
		);

		update_post_meta( $this->list_id, Fields::meta_key( 'person' ), 'Leni' );
		update_post_meta( $this->list_id, Fields::meta_key( 'date' ), '2026-12-11' );

		$this->item_id = $this->wish( 'Wooden <b>train</b>', 2, '24.90', 1 );

		wp_set_current_user( 0 );
	}

	/**
	 * Clears the rate limit between tests.
	 */
	public function tear_down(): void {
		delete_transient( REST_Controller::RATE_PREFIX . substr( wp_hash( '192.0.2.10' ), 0, 32 ) );
		unset( $_SERVER['REMOTE_ADDR'] );

		parent::tear_down();
	}

	/**
	 * Creates a wish on the list.
	 *
	 * @param string $title    Title.
	 * @param int    $quantity Quantity.
	 * @param string $price    Price.
	 * @param int    $priority Priority.
	 * @param string $status   Post status.
	 * @return int Wish ID.
	 */
	private function wish( string $title, int $quantity, string $price, int $priority, string $status = 'publish' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => Post_Types::ITEM_TYPE,
				'post_status' => $status,
				'post_title'  => $title,
			)
		);

		update_post_meta( $id, Fields::meta_key( 'list' ), (string) $this->list_id );
		update_post_meta( $id, Fields::meta_key( 'quantity' ), (string) $quantity );
		update_post_meta( $id, Fields::meta_key( 'price' ), $price );
		update_post_meta( $id, Fields::meta_key( 'priority' ), (string) $priority );

		return $id;
	}

	/**
	 * Dispatches a request to the addon's routes.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $path   Path below the namespace.
	 * @param array<string, mixed> $query  Query parameters.
	 * @return WP_REST_Response Response.
	 */
	private function call( string $method, string $path, array $query = array() ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/' . REST_Controller::NAMESPACE . $path );

		foreach ( $query as $key => $value ) {
			$request->set_query_params( array_merge( $request->get_query_params(), array( $key => $value ) ) );
		}

		return rest_get_server()->dispatch( $request );
	}

	public function test_prices_are_normalised_from_either_notation(): void {
		$this->assertSame( '12.99', Fields::sanitize( 'price', '12,99' ) );
		$this->assertSame( '12.99', Fields::sanitize( 'price', '12.99 €' ) );
		$this->assertSame( '1299.00', Fields::sanitize( 'price', '1.299,00' ) );
		$this->assertSame( '1299.00', Fields::sanitize( 'price', '1,299.00' ) );
		$this->assertSame( '1299.00', Fields::sanitize( 'price', '1.299' ) );
		$this->assertSame( '5.00', Fields::sanitize( 'price', '5' ) );
		$this->assertSame( '', Fields::sanitize( 'price', 'free' ) );
	}

	public function test_fields_are_clamped_and_cleaned(): void {
		$this->assertSame( '1', Fields::sanitize( 'quantity', '-3' ) );
		$this->assertSame( (string) Fields::MAX_QUANTITY, Fields::sanitize( 'quantity', '5000' ) );
		$this->assertSame( (string) Fields::PRIORITY_MEDIUM, Fields::sanitize( 'priority', '7' ) );
		$this->assertSame( '', Fields::sanitize( 'url', 'javascript:alert(1)' ) );
		$this->assertSame( 'https://shop.example/train', Fields::sanitize( 'url', 'https://shop.example/train' ) );
		$this->assertSame( '', Fields::sanitize( 'date', '2026-02-30' ) );
		$this->assertSame( Fields::DEFAULT_ACCENT, Fields::sanitize( 'color', 'red' ) );
		$this->assertSame( '0', Fields::sanitize( 'list', (string) $this->item_id ), 'A wish is not a list.' );
	}

	public function test_a_purchase_counts_until_the_quantity_is_reached(): void {
		$first = $this->call( 'POST', '/items/' . $this->item_id . '/claim' );

		$this->assertSame( 201, $first->get_status() );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $first->get_data()['token'] );
		$this->assertSame( 1, $first->get_data()['remaining'] );

		$this->assertSame( 201, $this->call( 'POST', '/items/' . $this->item_id . '/claim' )->get_status() );

		$third = $this->call( 'POST', '/items/' . $this->item_id . '/claim' );

		$this->assertSame( 409, $third->get_status() );
		$this->assertSame( 2, Claims::count( $this->item_id ) );
		$this->assertSame( 0, Claims::remaining( $this->item_id ) );
	}

	public function test_only_the_token_takes_a_purchase_back(): void {
		$token = $this->call( 'POST', '/items/' . $this->item_id . '/claim' )->get_data()['token'];

		$this->assertSame( 404, $this->call( 'DELETE', '/items/' . $this->item_id . '/claim', array( 'token' => str_repeat( 'a', 32 ) ) )->get_status() );
		$this->assertSame( 400, $this->call( 'DELETE', '/items/' . $this->item_id . '/claim', array( 'token' => 'nope' ) )->get_status() );
		$this->assertSame( 1, Claims::count( $this->item_id ) );

		$undo = $this->call( 'DELETE', '/items/' . $this->item_id . '/claim', array( 'token' => $token ) );

		$this->assertSame( 200, $undo->get_status() );
		$this->assertSame( 2, $undo->get_data()['remaining'] );
		$this->assertSame( 404, $this->call( 'DELETE', '/items/' . $this->item_id . '/claim', array( 'token' => $token ) )->get_status(), 'A token works once.' );
	}

	public function test_no_token_is_stored_in_the_clear(): void {
		$token = $this->call( 'POST', '/items/' . $this->item_id . '/claim' )->get_data()['token'];

		$stored = get_post_meta( $this->item_id, Claims::META, false );

		$this->assertCount( 1, $stored );
		$this->assertNotSame( $token, $stored[0] );
		$this->assertStringNotContainsString( '192.0.2.10', (string) $stored[0] );
	}

	public function test_reset_clears_every_purchase(): void {
		$this->call( 'POST', '/items/' . $this->item_id . '/claim' );
		$this->call( 'POST', '/items/' . $this->item_id . '/claim' );

		Claims::reset( $this->item_id );

		$this->assertSame( 0, Claims::count( $this->item_id ) );
	}

	public function test_drafts_and_strangers_take_no_reports(): void {
		$draft = $this->wish( 'Draft wish', 1, '', 2, 'draft' );
		$page  = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame( 404, $this->call( 'POST', '/items/' . $draft . '/claim' )->get_status() );
		$this->assertSame( 404, $this->call( 'POST', '/items/' . $page . '/claim' )->get_status() );

		wp_update_post(
			array(
				'ID'          => $this->list_id,
				'post_status' => 'draft',
			)
		);

		$this->assertSame( 404, $this->call( 'POST', '/items/' . $this->item_id . '/claim' )->get_status(), 'A wish on an unpublished list.' );
		$this->assertSame( 404, $this->call( 'GET', '/lists/' . $this->list_id )->get_status() );
	}

	public function test_a_closed_list_takes_no_reports(): void {
		update_post_meta( $this->list_id, Fields::meta_key( 'date' ), '2020-01-01' );
		update_post_meta( $this->list_id, Fields::meta_key( 'close_after' ), '1' );

		$this->assertFalse( Claims::is_open( $this->list_id ) );
		$this->assertSame( 403, $this->call( 'POST', '/items/' . $this->item_id . '/claim' )->get_status() );

		update_post_meta( $this->list_id, Fields::meta_key( 'close_after' ), '0' );

		$this->assertTrue( Claims::is_open( $this->list_id ), 'A past date alone does not close a list.' );
	}

	public function test_reports_are_rate_limited_per_address(): void {
		update_option( Settings::OPTION_RATE_LIMIT, 2 );

		$big = $this->wish( 'Blocks', 10, '', 2 );

		$this->assertSame( 201, $this->call( 'POST', '/items/' . $big . '/claim' )->get_status() );
		$this->assertSame( 201, $this->call( 'POST', '/items/' . $big . '/claim' )->get_status() );
		$this->assertSame( 429, $this->call( 'POST', '/items/' . $big . '/claim' )->get_status() );
		$this->assertSame( 2, Claims::count( $big ) );

		$_SERVER['REMOTE_ADDR'] = '192.0.2.11';

		$this->assertSame( 201, $this->call( 'POST', '/items/' . $big . '/claim' )->get_status(), 'Another address has its own window.' );

		delete_transient( REST_Controller::RATE_PREFIX . substr( wp_hash( '192.0.2.11' ), 0, 32 ) );
	}

	public function test_the_state_route_gives_counts_and_no_titles(): void {
		$this->call( 'POST', '/items/' . $this->item_id . '/claim' );

		$response = $this->call( 'GET', '/lists/' . $this->list_id );
		$data     = json_decode( (string) wp_json_encode( $response->get_data() ), true );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['open'] );
		$this->assertSame( 1, $data['items'][ (string) $this->item_id ]['bought'] );
		$this->assertStringNotContainsString( 'train', (string) wp_json_encode( $data ) );
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
	}

	public function test_the_shortcode_prints_escaped_wishes_in_order(): void {
		$this->wish( 'Picture book', 1, '9.99', 3 );

		$html = do_shortcode( Shortcode::for_list( $this->list_id ) );

		$this->assertStringContainsString( 'Leni turns two', $html );
		$this->assertStringContainsString( 'For Leni', $html );
		$this->assertStringContainsString( 'Wooden &lt;b&gt;train&lt;/b&gt;', $html );
		$this->assertStringNotContainsString( '<b>train</b>', $html );
		$this->assertLessThan( strpos( $html, 'Picture book' ), strpos( $html, 'Wooden' ), 'Higher priority first.' );
		$this->assertStringContainsString( 'data-remaining="2"', $html );
		$this->assertTrue( wp_style_is( 'baukasten-wishlist', 'enqueued' ) );
	}

	public function test_sorting_by_price_puts_wishes_without_a_price_last(): void {
		$items = array(
			array( 'title' => 'A', 'price' => '', 'priority' => 2, 'remaining' => 1, 'order' => 0 ),
			array( 'title' => 'B', 'price' => '30.00', 'priority' => 2, 'remaining' => 1, 'order' => 0 ),
			array( 'title' => 'C', 'price' => '5.00', 'priority' => 2, 'remaining' => 1, 'order' => 0 ),
		);

		$this->assertSame( array( 'C', 'B', 'A' ), array_column( Renderer::sorted( $items, 'price-asc' ), 'title' ) );
		$this->assertSame( array( 'B', 'C', 'A' ), array_column( Renderer::sorted( $items, 'price-desc' ), 'title' ) );
	}

	public function test_nothing_is_shown_inside_a_locked_post(): void {
		$page = self::factory()->post->create(
			array(
				'post_type'     => 'page',
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);

		$GLOBALS['post'] = get_post( $page );

		$this->assertSame( '', do_shortcode( Shortcode::for_list( $this->list_id ) ) );

		unset( $GLOBALS['post'] );
	}

	public function test_unpublished_lists_show_nothing_to_visitors(): void {
		wp_update_post(
			array(
				'ID'          => $this->list_id,
				'post_status' => 'draft',
			)
		);

		$this->assertSame( '', do_shortcode( Shortcode::for_list( $this->list_id ) ) );
	}
}
