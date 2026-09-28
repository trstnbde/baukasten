<?php
/**
 * No Flamingo address book (F3).
 *
 * @package Baukasten\FormPrivacy
 */

namespace Baukasten\FormPrivacy;

defined( 'ABSPATH' ) || exit;

/**
 * Switches Flamingo's address book off. There is no setting for this.
 *
 * Flamingo fills its address book on its own: from every Contact Form 7
 * submission that was mailed, from every WordPress user on registration and
 * profile update, and from approved comments — and, on activation, from all
 * users and the last twenty comments at once. That is a contact database
 * without a purpose or a retention period.
 *
 * Every one of those paths goes through `Flamingo_Contact::add()`, which gives
 * up when the email address is empty. So the address is emptied on
 * `flamingo_add_contact`, and no contact is ever written. Contact Form 7
 * already copes with the empty result.
 *
 * The screen goes too: editing contacts is mapped to `do_not_allow`, which
 * removes "Address Book" from the menu and makes "Flamingo" open the inbound
 * messages, because WordPress sends a top-level menu to its first allowed
 * submenu. Deleting contacts stays allowed, or Flamingo's own privacy eraser
 * would refuse to remove what is left.
 *
 * Existing contacts are removed by the cleanup, never automatically.
 */
final class Address_Book {

	/**
	 * Registers the filters.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'flamingo_add_contact', array( __CLASS__, 'refuse_contact' ), PHP_INT_MAX );
		add_filter( 'flamingo_map_meta_cap', array( __CLASS__, 'hide_address_book' ), PHP_INT_MAX );
	}

	/**
	 * Empties the address, so Flamingo writes no contact.
	 *
	 * @param mixed $args Contact arguments.
	 * @return mixed Arguments without an email address.
	 */
	public static function refuse_contact( $args ) {
		if ( is_array( $args ) ) {
			$args['email'] = '';
		}

		return $args;
	}

	/**
	 * Takes the right to view or edit contacts away from everybody.
	 *
	 * @param mixed $meta_caps Flamingo's capability map.
	 * @return mixed The map with contact editing disallowed.
	 */
	public static function hide_address_book( $meta_caps ) {
		if ( is_array( $meta_caps ) ) {
			$meta_caps['flamingo_edit_contacts'] = 'do_not_allow';
			$meta_caps['flamingo_edit_contact']  = 'do_not_allow';
		}

		return $meta_caps;
	}

	/**
	 * Number of contacts still in the address book.
	 *
	 * @return int Contacts in any status.
	 */
	public static function count(): int {
		$counts = wp_count_posts( 'flamingo_contact' );
		$total  = 0;

		foreach ( (array) $counts as $count ) {
			$total += (int) $count;
		}

		return $total;
	}
}
