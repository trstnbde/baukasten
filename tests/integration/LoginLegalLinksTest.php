<?php
/**
 * The legal links under the login form.
 *
 * @package Baukasten\Tests
 */

use Baukasten\LoginLegalPages\Legal_Pages;

/**
 * Each link is named after the page it opens.
 */
class LoginLegalLinksTest extends WP_UnitTestCase {

	/**
	 * A terms page with its own name is labelled with that name.
	 */
	public function test_labels_follow_the_page_titles(): void {
		$privacy = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Datenschutzerklärung',
			)
		);
		$terms   = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Haftungsausschluss',
			)
		);
		$imprint = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Impressum',
			)
		);

		update_option( 'wp_page_for_privacy_policy', $privacy );
		update_option( Legal_Pages::OPTION_TERMS, $terms );
		update_option( Legal_Pages::OPTION_IMPRINT, $imprint );

		$this->assertSame(
			array( 'Datenschutzerklärung', 'Haftungsausschluss', 'Impressum' ),
			wp_list_pluck( Legal_Pages::get_links(), 'label' )
		);
	}

	/**
	 * A page without a title falls back to the generic name.
	 */
	public function test_untitled_page_falls_back_to_the_generic_label(): void {
		$terms = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => '',
			)
		);

		update_option( Legal_Pages::OPTION_TERMS, $terms );

		$this->assertSame( array( 'Terms of Service' ), wp_list_pluck( Legal_Pages::get_links(), 'label' ) );
	}
}
