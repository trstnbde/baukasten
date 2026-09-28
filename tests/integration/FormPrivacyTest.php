<?php
/**
 * Form Privacy: address book, metadata, retention, honeypot.
 *
 * @package Baukasten\Tests
 */

use Baukasten\FormPrivacy\Honeypot;
use Baukasten\FormPrivacy\Retention;
use Baukasten\FormPrivacy\Settings;
use Baukasten\FormPrivacy\Submissions;

/**
 * F3, F4, F5, F9.
 */
class FormPrivacyTest extends WP_UnitTestCase {

	/**
	 * F3: Flamingo writes no contact, from any source.
	 */
	public function test_no_contact_is_written(): void {
		Flamingo_Contact::add(
			array(
				'email' => 'visitor@example.org',
				'name'  => 'Visitor',
			)
		);

		self::factory()->user->create( array( 'user_email' => 'user@example.org' ) );

		$this->assertSame( 0, (int) wp_count_posts( 'flamingo_contact' )->publish );
	}

	/**
	 * F3: nobody may open the address book.
	 */
	public function test_address_book_is_closed(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertFalse( current_user_can( 'flamingo_edit_contacts' ) );
		$this->assertTrue( current_user_can( 'flamingo_delete_contacts' ) );
	}

	/**
	 * F4: only whitelisted metadata reaches Flamingo.
	 */
	public function test_metadata_is_reduced(): void {
		$args = apply_filters(
			'wpcf7_flamingo_inbound_message_parameters',
			array(
				'meta'      => array(
					'remote_ip'  => '192.0.2.1',
					'user_agent' => 'Bot',
					'date'       => '2026-09-28',
					'url'        => 'https://example.org/',
					'turnstile'  => '{}',
				),
				'akismet'   => array( 'spam' => false ),
				'recaptcha' => array( 'score' => 1 ),
			)
		);

		$this->assertSame(
			array(
				'date' => '2026-09-28',
				'url'  => 'https://example.org/',
			),
			$args['meta']
		);
		$this->assertSame( array(), $args['akismet'] );
		$this->assertSame( array(), $args['recaptcha'] );
	}

	/**
	 * F4: spam is not stored by default, and nothing when storage is off.
	 */
	public function test_store_if(): void {
		$this->assertSame( array( 'mail_sent', 'mail_failed' ), Submissions::store_if( array( 'spam', 'mail_sent', 'mail_failed' ) ) );

		update_option( Settings::OPTION, array_merge( Settings::defaults(), array( 'store_submissions' => false ) ) );

		$this->assertSame( array(), Submissions::store_if( array( 'spam', 'mail_sent', 'mail_failed' ) ) );

		delete_option( Settings::OPTION );
	}

	/**
	 * F5: a message past the retention period is deleted with its meta.
	 */
	public function test_retention_deletes_old_messages(): void {
		$old = self::factory()->post->create(
			array(
				'post_type'     => 'flamingo_inbound',
				'post_status'   => 'flamingo-spam',
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 91 * DAY_IN_SECONDS ),
				'post_date'     => gmdate( 'Y-m-d H:i:s', time() - 91 * DAY_IN_SECONDS ),
			)
		);
		$new = self::factory()->post->create( array( 'post_type' => 'flamingo_inbound' ) );

		update_post_meta( $old, '_field_your-message', 'hello' );

		$this->assertSame( 1, Retention::purge() );
		$this->assertNull( get_post( $old ) );
		$this->assertSame( '', get_post_meta( $old, '_field_your-message', true ) );
		$this->assertNotNull( get_post( $new ) );
	}

	/**
	 * F9: honeypot, forged and early timestamps are spam, a normal one is not.
	 */
	public function test_honeypot_and_timestamp(): void {
		$now   = time();
		$stamp = Honeypot::stamp( $now - 10, 42 );

		$this->assertSame( 'honeypot', Honeypot::judge( 'filled', $stamp, 42, $now ) );
		$this->assertSame( 'timestamp missing', Honeypot::judge( '', '', 42, $now ) );
		$this->assertSame( 'timestamp invalid', Honeypot::judge( '', ( $now - 10 ) . '.forged', 42, $now ) );
		$this->assertSame( 'timestamp invalid', Honeypot::judge( '', $stamp, 43, $now ) );
		$this->assertSame( 'too fast', Honeypot::judge( '', Honeypot::stamp( $now - 1, 42 ), 42, $now ) );
		$this->assertSame( '', Honeypot::judge( '', $stamp, 42, $now ) );
	}
}
