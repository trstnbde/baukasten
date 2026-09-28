<?php
/**
 * A consent decision cannot be forged from another site (Recommendation 4).
 *
 * @package Baukasten\Tests
 */

use Baukasten\ConsentBlockingEngine\Consent_Log;

/**
 * POST /baukasten-consent/v1/consent.
 */
class ConsentOriginTest extends WP_UnitTestCase {

	/**
	 * Builds a consent request.
	 *
	 * @param string               $content_type Content type header.
	 * @param array<string,string> $headers      Further headers.
	 * @return WP_REST_Request Request.
	 */
	private function request( string $content_type, array $headers ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/baukasten-consent/v1/consent' );
		$request->set_header( 'content-type', $content_type );

		foreach ( $headers as $name => $value ) {
			$request->set_header( $name, $value );
		}

		if ( 'application/json' === $content_type ) {
			$request->set_body( wp_json_encode( array( 'categories' => array( 'functional' ) ) ) );
		} else {
			$request->set_body_params( array( 'categories' => array( 'functional' ) ) );
		}

		return $request;
	}

	/**
	 * Resets the rate limit transient and the log count baseline.
	 */
	public function set_up(): void {
		parent::set_up();

		$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
		Consent_Log::install();
	}

	/**
	 * A form post from another site is refused and logs nothing.
	 */
	public function test_foreign_form_post_is_forbidden(): void {
		$before   = Consent_Log::count();
		$response = rest_get_server()->dispatch(
			$this->request( 'application/x-www-form-urlencoded', array( 'origin' => 'https://attacker.example' ) )
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( $before, Consent_Log::count() );
	}

	/**
	 * JSON from another origin is refused too.
	 */
	public function test_foreign_json_is_forbidden(): void {
		$response = rest_get_server()->dispatch(
			$this->request( 'application/json', array( 'origin' => 'https://attacker.example' ) )
		);

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * A form post from this site is still refused: not JSON.
	 */
	public function test_same_origin_form_post_is_forbidden(): void {
		$response = rest_get_server()->dispatch(
			$this->request( 'application/x-www-form-urlencoded', array( 'origin' => home_url() ) )
		);

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * A request with neither Origin nor Sec-Fetch-Site is refused.
	 */
	public function test_request_without_origin_is_forbidden(): void {
		$response = rest_get_server()->dispatch( $this->request( 'application/json', array() ) );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * JSON from this site is recorded.
	 */
	public function test_same_origin_json_is_recorded(): void {
		$before   = Consent_Log::count();
		$response = rest_get_server()->dispatch(
			$this->request( 'application/json', array( 'origin' => home_url() ) )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $before + 1, Consent_Log::count() );
	}

	/**
	 * Sec-Fetch-Site: same-origin stands in for a missing Origin.
	 */
	public function test_sec_fetch_site_is_accepted(): void {
		$response = rest_get_server()->dispatch(
			$this->request( 'application/json', array( 'sec_fetch_site' => 'same-origin' ) )
		);

		$this->assertSame( 200, $response->get_status() );
	}
}
