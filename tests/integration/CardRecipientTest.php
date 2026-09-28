<?php
/**
 * Where a card's contact form mail goes (Recommendation 5).
 *
 * @package Baukasten\Tests
 */

use Baukasten\BusinessCards\Forms;
use Baukasten\BusinessCards\Post_Type;

/**
 * `[_bkbc_card_email]`: valid, foreign, private and formless cards.
 */
class CardRecipientTest extends WP_UnitTestCase {

	/**
	 * Creates a card.
	 *
	 * @param array<string, mixed> $meta   Card meta, without the prefix.
	 * @param string               $status Post status.
	 * @return int Card ID.
	 */
	private function card( array $meta, string $status = 'publish' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => Post_Type::POST_TYPE,
				'post_status' => $status,
				'post_title'  => 'Test card',
			)
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, '_baukasten_card_' . $key, $value );
		}

		return $id;
	}

	/**
	 * A published card with this form and an address gets the mail.
	 */
	public function test_valid_card_gets_the_mail(): void {
		$card = $this->card(
			array(
				'email'       => 'owner@example.org',
				'cf7_form_id' => 42,
			)
		);

		$this->assertSame( $card, Forms::verified_card( $card, 42 ) );
		$this->assertSame( 'owner@example.org', Forms::recipient( Forms::verified_card( $card, 42 ) ) );
	}

	/**
	 * The second address is used when the first is empty.
	 */
	public function test_second_address_is_the_fallback(): void {
		$card = $this->card(
			array(
				'email_2'     => 'second@example.org',
				'cf7_form_id' => 42,
			)
		);

		$this->assertSame( 'second@example.org', Forms::recipient( Forms::verified_card( $card, 42 ) ) );
	}

	/**
	 * A card that embeds a different form is not trusted.
	 */
	public function test_card_with_other_form_is_refused(): void {
		$card = $this->card(
			array(
				'email'       => 'owner@example.org',
				'cf7_form_id' => 7,
			)
		);

		$this->assertSame( 0, Forms::verified_card( $card, 42 ) );
		$this->assertSame( get_bloginfo( 'admin_email' ), Forms::recipient( 0 ) );
	}

	/**
	 * A card without a form is not trusted.
	 */
	public function test_card_without_form_is_refused(): void {
		$card = $this->card( array( 'email' => 'owner@example.org' ) );

		$this->assertSame( 0, Forms::verified_card( $card, 42 ) );
	}

	/**
	 * A draft card is not trusted.
	 */
	public function test_draft_card_is_refused(): void {
		$card = $this->card(
			array(
				'email'       => 'owner@example.org',
				'cf7_form_id' => 42,
			),
			'draft'
		);

		$this->assertSame( 0, Forms::verified_card( $card, 42 ) );
	}

	/**
	 * Anything that is not a card is not trusted.
	 */
	public function test_foreign_post_is_refused(): void {
		$post = self::factory()->post->create();

		$this->assertSame( 0, Forms::verified_card( $post, 42 ) );
	}

	/**
	 * A card with no visibility flag, as cards normally are, is trusted.
	 *
	 * Content Visibility counts a missing flag as private, but cards are not
	 * managed by it unless a filter says so.
	 */
	public function test_unflagged_card_is_trusted_for_visitors(): void {
		$card = $this->card(
			array(
				'email'       => 'owner@example.org',
				'cf7_form_id' => 42,
			)
		);

		delete_post_meta( $card, '_baukasten_visibility' );
		wp_set_current_user( 0 );

		$this->assertSame( $card, Forms::verified_card( $card, 42 ) );
	}

	/**
	 * A private card only counts for a logged-in sender, when cards are managed.
	 */
	public function test_private_card_needs_a_logged_in_sender(): void {
		add_filter( 'baukasten/business_cards/respect_content_visibility', '__return_true' );
		$this->reset_managed_types();

		$card = $this->card(
			array(
				'email'       => 'owner@example.org',
				'cf7_form_id' => 42,
			)
		);

		update_post_meta( $card, '_baukasten_visibility', 'private' );
		wp_set_current_user( 0 );

		$this->assertSame( 0, Forms::verified_card( $card, 42 ) );

		wp_set_current_user( self::factory()->user->create() );

		$this->assertSame( $card, Forms::verified_card( $card, 42 ) );

		remove_filter( 'baukasten/business_cards/respect_content_visibility', '__return_true' );
		$this->reset_managed_types();
	}

	/**
	 * Forgets Content Visibility's cached list of managed post types.
	 */
	private function reset_managed_types(): void {
		$property = new ReflectionProperty( Baukasten\ContentVisibility\Visibility::class, 'post_types' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	/**
	 * A card without an address sends to the admin.
	 */
	public function test_card_without_address_sends_to_admin(): void {
		$card = $this->card( array( 'cf7_form_id' => 42 ) );

		$this->assertSame( get_bloginfo( 'admin_email' ), Forms::recipient( Forms::verified_card( $card, 42 ) ) );
	}
}
