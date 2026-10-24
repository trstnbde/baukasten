<?php
/**
 * The New Tab Page addon: the choice of page, the takeover, the greetings and
 * what must never leak.
 *
 * @package Baukasten\Tests
 */

use Baukasten\ContentVisibility\Frontend_Guard;
use Baukasten\ContentVisibility\Visibility;
use Baukasten\NewTab\Backgrounds;
use Baukasten\NewTab\Greetings;
use Baukasten\NewTab\Holidays;
use Baukasten\NewTab\Page_Setting;
use Baukasten\NewTab\Renderer;
use Baukasten\NewTab\REST_Controller;
use Baukasten\NewTab\Search;
use Baukasten\NewTab\Settings;
use Baukasten\NewTab\Suggest;

/**
 * Baukasten Addon: New Tab Page.
 */
class NewTabTest extends WP_UnitTestCase {

	/**
	 * A key that is easy to find in output.
	 */
	const KEY = 'TESTKEYabcdefghijklmnopqrstuvwx-_';

	/**
	 * The selected page.
	 *
	 * @var int
	 */
	private int $page_id;

	/**
	 * Creates a published page and starts from a clean state.
	 */
	public function set_up(): void {
		parent::set_up();

		$GLOBALS['wp_rest_server'] = null;

		$this->page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Neuer Tab',
				'post_name'   => 'start',
				'post_status' => 'publish',
			)
		);

		update_option( Settings::OPTION_PAGE, $this->page_id );
		Visibility::set( $this->page_id, Visibility::VISIBILITY_PUBLIC );
		Settings::flush();

		$this->reset_renderer();

		$_SERVER['REMOTE_ADDR'] = '192.0.2.77';
		$_GET                   = array();

		wp_set_current_user( 0 );
	}

	/**
	 * Clears the request state the renderer keeps.
	 */
	private function reset_renderer(): void {
		foreach ( array( 'view' => null, 'address' => '' ) as $property => $value ) {
			$reflection = new ReflectionProperty( Renderer::class, $property );
			$reflection->setAccessible( true );
			$reflection->setValue( null, $value );
		}
	}

	/**
	 * Any published page that is not the posts page is accepted.
	 */
	public function test_published_page_is_accepted(): void {
		$other = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame( array( $other, '' ), Page_Setting::validate( (string) $other ) );
		$this->assertSame( array( 0, '' ), Page_Setting::validate( '0' ) );
	}

	/**
	 * The posts page is refused and the old value stays.
	 */
	public function test_posts_page_is_refused(): void {
		$posts_page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		update_option( 'page_for_posts', $posts_page );

		$result = Page_Setting::validate( (string) $posts_page );

		$this->assertSame( $this->page_id, $result[0] );
		$this->assertNotSame( '', $result[1] );
	}

	/**
	 * A draft, a post and a missing page are refused.
	 */
	public function test_unpublished_and_foreign_ids_are_refused(): void {
		$draft = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		$post  = self::factory()->post->create();

		foreach ( array( $draft, $post, 999999 ) as $id ) {
			$result = Page_Setting::validate( (string) $id );

			$this->assertSame( $this->page_id, $result[0] );
			$this->assertNotSame( '', $result[1] );
		}
	}

	/**
	 * The homepage may be the new tab page.
	 */
	public function test_homepage_is_allowed(): void {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $this->page_id );

		$this->assertSame( array( $this->page_id, '' ), Page_Setting::validate( (string) $this->page_id ) );
	}

	/**
	 * Trashing, deleting or unpublishing the page resets the option.
	 */
	public function test_option_is_reset_when_the_page_goes_away(): void {
		wp_trash_post( $this->page_id );
		$this->assertSame( 0, Settings::page_id() );

		update_option( Settings::OPTION_PAGE, $this->page_id );
		wp_untrash_post( $this->page_id );
		wp_publish_post( $this->page_id );
		update_option( Settings::OPTION_PAGE, $this->page_id );

		wp_update_post(
			array(
				'ID'          => $this->page_id,
				'post_status' => 'draft',
			)
		);
		$this->assertSame( 0, Settings::page_id() );

		$deleted = self::factory()->post->create( array( 'post_type' => 'page' ) );

		update_option( Settings::OPTION_PAGE, $deleted );
		wp_delete_post( $deleted, true );
		$this->assertSame( 0, Settings::page_id() );
	}

	/**
	 * The page list flags the new tab page and no other.
	 */
	public function test_post_state(): void {
		$states = Page_Setting::post_state( array(), get_post( $this->page_id ) );
		$other  = Page_Setting::post_state( array(), get_post( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );

		$this->assertArrayHasKey( 'baukasten_new_tab', $states );
		$this->assertSame( array(), $other );
	}

	/**
	 * The template is replaced on the selected page only.
	 */
	public function test_takeover_only_on_the_selected_page(): void {
		$other = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->go_to( get_permalink( $other ) );
		$this->assertFalse( Renderer::is_new_tab() );
		$this->assertSame( 'x.php', Renderer::use_template( 'x.php' ) );

		$this->go_to( get_permalink( $this->page_id ) );
		$this->assertTrue( Renderer::is_new_tab() );

		Renderer::take_over();

		$this->assertStringEndsWith( 'templates/new-tab.php', Renderer::use_template( 'x.php' ) );
	}

	/**
	 * Served as the homepage it is taken over as well.
	 */
	public function test_takeover_as_homepage(): void {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $this->page_id );

		$this->go_to( home_url( '/' ) );

		$this->assertTrue( Renderer::is_new_tab() );
	}

	/**
	 * Content Visibility's guard runs before the addon does anything.
	 */
	public function test_content_visibility_runs_first(): void {
		$guard    = has_action( 'template_redirect', array( Frontend_Guard::class, 'guard_singular' ) );
		$renderer = has_action( 'template_redirect', array( Renderer::class, 'take_over' ) );

		$this->assertSame( 0, $guard );
		$this->assertGreaterThan( $guard, $renderer );

		Visibility::set( $this->page_id, Visibility::VISIBILITY_PRIVATE );

		$this->assertTrue( Frontend_Guard::is_blocked( $this->page_id ) );
		$this->assertWPError( REST_Controller::may_read() );
	}

	/**
	 * An address typed without JavaScript is shown, not forwarded.
	 */
	public function test_no_open_redirect(): void {
		$redirected = array();

		add_filter(
			'wp_redirect',
			static function ( $location ) use ( &$redirected ) {
				$redirected[] = $location;

				throw new RuntimeException( 'redirect' );
			}
		);

		foreach ( array( 'evil.example.com', 'https://evil.example.com/login', 'localhost:8080', '192.0.2.1' ) as $entry ) {
			$this->reset_renderer();

			$this->go_to( get_permalink( $this->page_id ) . '&q=' . rawurlencode( $entry ) );

			Renderer::take_over();

			$this->assertNotSame( '', Renderer::address(), $entry );
			$this->assertStringEndsWith( 'templates/address.php', Renderer::use_template( 'x.php' ), $entry );
		}

		$this->assertSame( array(), $redirected );
	}

	/**
	 * A search is forwarded, and only to an engine's host.
	 */
	public function test_search_goes_to_the_engine(): void {
		$target = '';

		add_filter(
			'wp_redirect',
			static function ( $location ) use ( &$target ) {
				$target = $location;

				throw new RuntimeException( 'redirect' );
			}
		);

		$this->go_to( get_permalink( $this->page_id ) . '&q=Mondlandung+1969&e=brave' );

		try {
			Renderer::take_over();
		} catch ( Throwable $exception ) {
			$this->assertSame( 'redirect', $exception->getMessage() );
		}

		$this->assertSame( 'search.brave.com', wp_parse_url( $target, PHP_URL_HOST ) );
		$this->assertStringContainsString( 'q=Mondlandung%201969', $target );
	}

	/**
	 * The at-shortcuts, and what counts as an address.
	 */
	public function test_search_parsing(): void {
		$this->assertSame( 'brave', Search::parse( '@b linux', 'startpage' )['engine'] );
		$this->assertSame( 'linux', Search::parse( '@b linux', 'startpage' )['terms'] );
		$this->assertSame( 'startpage', Search::parse( '@zz linux', 'startpage' )['engine'] );

		$this->assertSame( 'https://heise.de', Search::address( 'heise.de' ) );
		$this->assertSame( 'http://localhost:8080', Search::address( 'localhost:8080' ) );
		$this->assertSame( 'http://192.168.0.1', Search::address( '192.168.0.1' ) );
		$this->assertSame( '', Search::address( 'app.js' ) );
		$this->assertSame( '', Search::address( 'two words.de' ) );
		$this->assertSame( '', Search::address( 'javascript:alert(1)' ) );
		$this->assertSame( '', Search::address( '3.14' ) );
	}

	/**
	 * Easter against known dates.
	 *
	 * @dataProvider easter_dates
	 *
	 * @param int    $year Year.
	 * @param string $date Easter Sunday.
	 */
	public function test_easter( int $year, string $date ): void {
		$this->assertSame( $date, Holidays::easter( $year )->format( 'Y-m-d' ) );
	}

	/**
	 * Known Easter Sundays.
	 *
	 * @return array<string, array{int, string}> Cases.
	 */
	public static function easter_dates(): array {
		return array(
			'2024' => array( 2024, '2024-03-31' ),
			'2025' => array( 2025, '2025-04-20' ),
			'2026' => array( 2026, '2026-04-05' ),
			'2027' => array( 2027, '2027-03-28' ),
			'2028' => array( 2028, '2028-04-16' ),
			'2038' => array( 2038, '2038-04-25' ),
		);
	}

	/**
	 * Every anchor of 2026 to 2028 finds its own holiday in its own week,
	 * except where two share a week.
	 */
	public function test_every_anchor_has_its_week(): void {
		foreach ( array( 2026, 2027, 2028 ) as $year ) {
			foreach ( Holidays::anchors( $year ) as $anchor ) {
				$found = Holidays::for_week( $anchor['date'] );

				$this->assertNotNull( $found, $anchor['key'] . ' ' . $year );
				$this->assertSame( $anchor['key'], $found['key'], $anchor['key'] . ' ' . $year );
			}
		}
	}

	/**
	 * Two anchors in one week: the nearer wins.
	 */
	public function test_the_nearer_anchor_wins_in_a_shared_week(): void {
		$timezone = wp_timezone();

		$monday    = Holidays::for_week( new DateTimeImmutable( '2027-09-27', $timezone ) );
		$saturday  = Holidays::for_week( new DateTimeImmutable( '2027-10-02', $timezone ) );
		$sunday    = Holidays::for_week( new DateTimeImmutable( '2027-10-03', $timezone ) );
		$next_week = Holidays::for_week( new DateTimeImmutable( '2027-10-04', $timezone ) );

		$this->assertSame( 'rosh_hashanah', $monday['key'] );
		$this->assertSame( 'rosh_hashanah', $saturday['key'] );
		$this->assertSame( 'german_unity', $sunday['key'] );
		$this->assertNull( $next_week );
	}

	/**
	 * New Year's Eve can reach into the next year's week 1.
	 */
	public function test_new_years_eve_reaches_into_january(): void {
		$found = Holidays::for_week( new DateTimeImmutable( '2027-01-01', wp_timezone() ) );

		$this->assertSame( 'new_years_eve', $found['key'] );
		$this->assertStringContainsString( '2027', Greetings::for_day( new DateTimeImmutable( '2027-01-01', wp_timezone() ) )['text'] );
	}

	/**
	 * Both tables reach at least two years ahead.
	 */
	public function test_tables_reach_two_years_ahead(): void {
		$limit = ( (int) gmdate( 'Y' ) + 2 ) . '-01-01';

		foreach ( array( 'islamic', 'jewish' ) as $table ) {
			foreach ( Holidays::table( $table ) as $key => $dates ) {
				$this->assertNotSame( array(), $dates, $key );
				$this->assertGreaterThanOrEqual( $limit, max( $dates ), $table . ' ' . $key );
			}
		}
	}

	/**
	 * The Jewish table agrees with the calendar extension.
	 */
	public function test_jewish_table_matches_the_calendar(): void {
		if ( ! function_exists( 'jewishtojd' ) ) {
			$this->markTestSkipped( 'ext-calendar is not loaded.' );
		}

		foreach ( range( 2026, 2040 ) as $year ) {
			foreach ( Holidays::anchors( $year ) as $anchor ) {
				if ( ! in_array( $anchor['key'], array( 'rosh_hashanah', 'yom_kippur' ), true ) ) {
					continue;
				}

				$this->assertContains( $anchor['date']->format( 'Y-m-d' ), Holidays::table( 'jewish' )[ $anchor['key'] ], $anchor['key'] . ' ' . $year );
			}
		}
	}

	/**
	 * Without the extension the table gives the same dates.
	 */
	public function test_jewish_dates_without_the_extension(): void {
		$with = array();

		foreach ( Holidays::anchors( 2027 ) as $anchor ) {
			$with[ $anchor['key'] ][] = $anchor['date']->format( 'Y-m-d' );
		}

		add_filter( 'baukasten/new_tab/compute_jewish_dates', '__return_false' );

		$without = array();

		foreach ( Holidays::anchors( 2027 ) as $anchor ) {
			$without[ $anchor['key'] ][] = $anchor['date']->format( 'Y-m-d' );
		}

		$this->assertSame( $with['rosh_hashanah'], $without['rosh_hashanah'] );
		$this->assertSame( array( '2027-10-02' ), $without['rosh_hashanah'] );
		$this->assertSame( array( '2027-10-11' ), $without['yom_kippur'] );
	}

	/**
	 * The random pool: 52 greetings, no duplicates, none with a weekday.
	 */
	public function test_random_pool(): void {
		$pool = Greetings::pool()['random'];

		$this->assertCount( 52, $pool );
		$this->assertSame( $pool, array_values( array_unique( $pool ) ) );
		$this->assertCount( 10, Greetings::pool()['holidays'] );

		foreach ( $pool as $line ) {
			$this->assertDoesNotMatchRegularExpression( '/\b(Montag|Dienstag|Mittwoch|Donnerstag|Freitag|Samstag|Sonntag|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\b/', $line );
		}
	}

	/**
	 * A holiday week shows the holiday's greeting with its placeholders filled.
	 */
	public function test_holiday_greeting(): void {
		$greeting = Greetings::for_day( new DateTimeImmutable( '2027-10-02', wp_timezone() ) );

		$this->assertSame( 'rosh_hashanah', $greeting['holiday'] );
		$this->assertStringContainsString( '5788', $greeting['text'] );
		$this->assertStringNotContainsString( '{', $greeting['text'] );
	}

	/**
	 * The override date only works for administrators.
	 */
	public function test_date_override_needs_manage_options(): void {
		$_GET['bk_date'] = '2027-10-02';

		$this->assertSame( '', \Baukasten\NewTab\Clock::override() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( '2027-10-02', \Baukasten\NewTab\Clock::override() );
		$this->assertSame( '2027-10-02', \Baukasten\NewTab\Clock::now()->format( 'Y-m-d' ) );
	}

	/**
	 * The Unsplash access key is in no page and no REST answer.
	 */
	public function test_access_key_is_never_printed(): void {
		update_option( Settings::OPTION_KEY, self::KEY );

		$settings               = Settings::all();
		$settings['background'] = 'unsplash';

		Settings::save( $settings );

		$this->seed_pool();

		$this->go_to( get_permalink( $this->page_id ) );
		Renderer::take_over();

		ob_start();
		require \Baukasten\NewTab\PLUGIN_DIR . 'templates/new-tab.php';
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="bk-config"', $html );
		$this->assertStringContainsString( 'images.unsplash.com', $html );
		$this->assertStringNotContainsString( self::KEY, $html );
		$this->assertStringNotContainsString( self::KEY, (string) wp_json_encode( Renderer::view() ) );

		$server = rest_get_server();

		add_filter(
			'pre_http_request',
			static fn() => array(
				'response' => array( 'code' => 200 ),
				'body'     => '["abc",["abcd"]]',
				'headers'  => array(),
			)
		);

		foreach ( array( '/baukasten-new-tab/v1/backgrounds', '/baukasten-new-tab/v1/suggest' ) as $route ) {
			$request = new WP_REST_Request( 'GET', $route );
			$request->set_query_params( array( 'q' => 'abc' ) );

			$response = $server->dispatch( $request );

			$this->assertSame( 200, $response->get_status(), $route );
			$this->assertStringNotContainsString( self::KEY, (string) wp_json_encode( $response->get_data() ), $route );
		}
	}

	/**
	 * Puts one photo into the Unsplash pool.
	 */
	private function seed_pool(): void {
		update_option(
			\Baukasten\NewTab\Unsplash::OPTION_POOL,
			array(
				'fetched' => time(),
				'query'   => 'wallpapers,nature|landscape',
				'photos'  => array(
					array(
						'id'      => 'abc',
						'raw'     => 'https://images.unsplash.com/photo-1?ixid=x',
						'width'   => 100,
						'height'  => 100,
						'color'   => '#336699',
						'alt'     => '',
						'name'    => 'Ada',
						'profile' => 'https://unsplash.com/@ada',
						'link'    => 'https://unsplash.com/photos/abc',
					),
				),
			)
		);
	}

	/**
	 * Unsplash is only allowed by the policy, and only fetched, in its own mode.
	 */
	public function test_unsplash_only_in_its_mode(): void {
		update_option( Settings::OPTION_KEY, self::KEY );
		$this->seed_pool();

		$settings               = Settings::all();
		$settings['background'] = 'unsplash';

		Settings::save( $settings );

		$background = Backgrounds::initial();

		$this->assertSame( 'unsplash', $background['mode'] );
		$this->assertNotNull( $background['photo'] );
		$this->assertCount( 1, Backgrounds::for_rest()['items'] );

		$this->go_to( get_permalink( $this->page_id ) );
		Renderer::take_over();

		$view = Renderer::view();

		$this->assertStringContainsString( 'images.unsplash.com', Renderer::content_security_policy( $view ) );
		$this->assertStringNotContainsString( 'images.unsplash.com', Renderer::content_security_policy( array_merge( $view, array( 'background' => array( 'mode' => 'media' ) ) ) ) );
	}

	/**
	 * The greeting can be switched off, and its holidays separately.
	 */
	public function test_greeting_settings(): void {
		$day      = new DateTimeImmutable( '2027-10-02', wp_timezone() );
		$settings = Settings::all();

		$this->assertSame( 'rosh_hashanah', Greetings::for_day( $day )['holiday'] );

		$settings['greeting_holidays'] = false;
		Settings::save( $settings );

		$this->assertSame( '', Greetings::for_day( $day )['holiday'] );
		$this->assertNotSame( '', Greetings::for_day( $day )['text'] );

		$settings['greeting'] = false;
		Settings::save( $settings );

		$this->assertSame( '', Greetings::for_day( $day )['text'] );
	}

	/**
	 * Own greetings replace the built-in pool, but not the holidays.
	 */
	public function test_custom_greetings(): void {
		$settings                    = Settings::all();
		$settings['greeting_custom'] = "Eins\n\n  Zwei  \nEins";

		Settings::save( $settings );

		$this->assertSame( array( 'Eins', 'Zwei' ), Greetings::pool()['random'] );
		$this->assertContains( Greetings::for_day( new DateTimeImmutable( '2027-06-15', wp_timezone() ) )['text'], array( 'Eins', 'Zwei' ) );
		$this->assertSame( 'rosh_hashanah', Greetings::for_day( new DateTimeImmutable( '2027-10-02', wp_timezone() ) )['holiday'] );
	}

	/**
	 * Suggestions always come from Startpage.
	 */
	public function test_suggestions_come_from_startpage(): void {
		$this->assertSame( 'www.startpage.com', wp_parse_url( Suggest::provider_url( 'linux' ), PHP_URL_HOST ) );
		$this->assertStringContainsString( 'q=linux', Suggest::provider_url( 'linux' ) );
	}

	/**
	 * The page's icon is the site's, or WordPress's own.
	 */
	public function test_icon_is_the_site_icon(): void {
		$this->go_to( get_permalink( $this->page_id ) );
		Renderer::take_over();

		$this->assertStringEndsWith( '/favicon.ico', Renderer::view()['icon'] );
	}

	/**
	 * The content policy has no unsafe-inline and hashes the inline styles.
	 */
	public function test_content_security_policy(): void {
		$this->go_to( get_permalink( $this->page_id ) );
		Renderer::take_over();

		$policy = Renderer::content_security_policy( Renderer::view() );

		$this->assertStringNotContainsString( 'unsafe-inline', $policy );
		$this->assertStringContainsString( "frame-ancestors 'none'", $policy );
		$this->assertStringContainsString( "base-uri 'none'", $policy );
		$this->assertStringContainsString( "script-src 'self'", $policy );
		$this->assertStringContainsString( Renderer::hash( Renderer::view()['style'] ), $policy );
	}

	/**
	 * The suggestion route is limited, and talks to nobody in the test.
	 */
	public function test_suggest_rate_limit(): void {
		$calls = 0;

		add_filter(
			'pre_http_request',
			static function () use ( &$calls ) {
				++$calls;

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '["q",["one","two"]]',
					'headers'  => array(),
				);
			}
		);

		$last = null;

		for ( $i = 0; $i <= Suggest::RATE_LIMIT; $i++ ) {
			$request = new WP_REST_Request( 'GET', '/baukasten-new-tab/v1/suggest' );
			$request->set_query_params( array( 'q' => 'query' . $i ) );

			$last = rest_get_server()->dispatch( $request );
		}

		$this->assertSame( 429, $last->get_status() );
		$this->assertSame( Suggest::RATE_LIMIT, $calls );
	}

	/**
	 * A cached answer does not ask the provider again.
	 */
	public function test_suggest_is_cached(): void {
		$calls = 0;

		add_filter(
			'pre_http_request',
			static function () use ( &$calls ) {
				++$calls;

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '["linux",["linux mint","linux kernel"]]',
					'headers'  => array(),
				);
			}
		);

		$this->assertSame( array( 'linux mint', 'linux kernel' ), Suggest::lookup( 'linux' ) );
		$this->assertSame( array( 'linux mint', 'linux kernel' ), Suggest::lookup( 'linux' ) );
		$this->assertSame( 1, $calls );
	}

	/**
	 * Over-long and empty queries get nothing.
	 */
	public function test_suggest_input_is_bounded(): void {
		add_filter(
			'pre_http_request',
			static fn() => array(
				'response' => array( 'code' => 200 ),
				'body'     => '["q",[]]',
				'headers'  => array(),
			)
		);

		$request = new WP_REST_Request( 'GET', '/baukasten-new-tab/v1/suggest' );
		$request->set_query_params( array( 'q' => str_repeat( 'a', 500 ) ) );

		$response = rest_get_server()->dispatch( $request );

		// Either refused outright or cut to the limit; never passed on whole.
		if ( 200 === $response->get_status() ) {
			$this->assertLessThanOrEqual( Search::MAX_LENGTH, mb_strlen( (string) $response->get_data()[0] ) );
		} else {
			$this->assertSame( 400, $response->get_status() );
		}
	}

	/**
	 * Icons accept raster formats only.
	 */
	public function test_icons_refuse_svg(): void {
		$this->assertSame( 'png', \Baukasten\NewTab\Favicons::sniff( "\x89PNG\r\n\x1a\n...." ) );
		$this->assertSame( 'ico', \Baukasten\NewTab\Favicons::sniff( "\x00\x00\x01\x00...." ) );
		$this->assertSame( '', \Baukasten\NewTab\Favicons::sniff( '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>' ) );
		$this->assertSame( '', \Baukasten\NewTab\Favicons::sniff( '<?xml version="1.0"?><svg/>' ) );
	}

	/**
	 * Search templates need https and the placeholder.
	 */
	public function test_template_validation(): void {
		$this->assertTrue( Settings::is_valid_template( 'https://example.com/?q={q}' ) );
		$this->assertFalse( Settings::is_valid_template( 'http://example.com/?q={q}' ) );
		$this->assertFalse( Settings::is_valid_template( 'https://example.com/' ) );
		$this->assertFalse( Settings::is_valid_template( 'javascript:alert({q})' ) );
		$this->assertSame( '', Settings::clean_url( 'javascript:alert(1)' ) );
		$this->assertSame( 'https://example.com', Settings::clean_url( 'example.com' ) );
	}
}
