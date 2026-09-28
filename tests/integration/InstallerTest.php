<?php
/**
 * Activation and update leave WordPress settings alone (Recommendation 11).
 *
 * @package Baukasten\Tests
 */

use Baukasten\Installer;

/**
 * The hardening routine only runs from its button.
 */
class InstallerTest extends WP_UnitTestCase {

	/**
	 * Settings the hardening routine would change.
	 *
	 * @var array<string, string>
	 */
	private const WATCHED = array(
		'blog_public'            => '1',
		'users_can_register'     => '1',
		'default_comment_status' => 'open',
	);

	/**
	 * Sets the watched options to what hardening would undo.
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( self::WATCHED as $option => $value ) {
			update_option( $option, $value );
		}
	}

	/**
	 * Asserts the watched options kept their values.
	 */
	private function assert_untouched(): void {
		foreach ( self::WATCHED as $option => $value ) {
			$this->assertSame( $value, (string) get_option( $option ), $option );
		}
	}

	/**
	 * Activation installs, and hardens nothing.
	 */
	public function test_activation_changes_no_core_option(): void {
		Installer::activate();

		$this->assert_untouched();
	}

	/**
	 * A version change hardens nothing.
	 */
	public function test_upgrade_changes_no_core_option(): void {
		update_option( Installer::OPTION_VERSION, '0.9.0' );
		Installer::maybe_upgrade();

		$this->assert_untouched();
	}

	/**
	 * The structure migration from the separate plugins hardens nothing.
	 */
	public function test_structure_migration_changes_no_core_option(): void {
		delete_option( Installer::OPTION_STRUCTURE );
		Installer::maybe_install_features();

		$this->assert_untouched();
		$this->assertSame( Installer::STRUCTURE, (int) get_option( Installer::OPTION_STRUCTURE ) );
	}

	/**
	 * The structure migration does not re-run the visibility migration.
	 *
	 * A site coming from the separate plugin already ran it; running it again
	 * would publish what has gone without a flag since.
	 */
	public function test_structure_migration_keeps_unflagged_content(): void {
		update_option( 'baukasten_content_visibility_migration', array( 'time' => 1, 'count' => 0 ) );
		$post = self::factory()->post->create();
		delete_post_meta( $post, '_baukasten_visibility' );

		delete_option( Installer::OPTION_STRUCTURE );
		Installer::maybe_install_features();

		$this->assertFalse( metadata_exists( 'post', $post, '_baukasten_visibility' ) );
	}
}
