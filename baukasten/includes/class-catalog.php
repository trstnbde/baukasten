<?php
/**
 * Known addon catalog.
 *
 * @package Baukasten
 */

namespace Baukasten;

defined( 'ABSPATH' ) || exit;

/**
 * The addons that exist, whether or not this site has them.
 *
 * `Addons` only knows the addons whose PHP is running, which is the right
 * answer for building tabs and the wrong one for the overview: an addon that
 * would solve the reader's problem is invisible precisely when they most need
 * to be told about it. So the overview reads from here instead, and asks
 * `Addons` only whether an entry has a tab to link to.
 *
 * The list is hard-coded on purpose. Reading it from the plugin directory API
 * would mean an outbound request from a plugin whose whole selling point is
 * that it makes none, and the guidelines are right to ask for consent before
 * one. Six names in a PHP array cost a release note when a seventh appears,
 * which is the cheaper of the two.
 *
 * Keyed by tab id, so an entry lines up with what the addon registers.
 */
final class Catalog {

	/**
	 * An addon that is installed and running.
	 */
	public const ACTIVE = 'active';

	/**
	 * An addon that is installed but switched off.
	 */
	public const INACTIVE = 'inactive';

	/**
	 * An addon this site does not have.
	 */
	public const MISSING = 'missing';

	/**
	 * The addons, keyed by the tab id each one registers.
	 *
	 * Titles are plugin names and stay as they are in every locale, the way
	 * they read in the plugin directory. Descriptions are the short
	 * description from each addon's readme, translated here because a site
	 * that has not installed the addon has no other copy of it.
	 *
	 * @return array<string, array{slug: string, title: string, description: string, position: int}>
	 */
	private static function entries(): array {
		return array(
			'content-visibility' => array(
				'slug'        => 'baukasten-content-visibility',
				'title'       => 'Baukasten Addon: Content Visibility',
				'description' => __( 'A public/private switch on every post and page. Private content is readable by logged-in users only, whatever their role.', 'baukasten' ),
				'position'    => 10,
			),
			'login-legal-pages'  => array(
				'slug'        => 'baukasten-login-legal-pages',
				'title'       => 'Baukasten Addon: Login Legal Pages',
				'description' => __( 'Privacy policy, terms and imprint under the login form, no WordPress header on the screen, and a login address people can read.', 'baukasten' ),
				'position'    => 20,
			),
			'consent'            => array(
				'slug'        => 'baukasten-consent-blocking-engine',
				'title'       => 'Baukasten Addon: Consent Blocking Engine',
				'description' => __( 'Blocks third-party scripts, styles, embeds, resource hints, Gravatar and emoji until the visitor consents, and keeps an auditable log.', 'baukasten' ),
				'position'    => 30,
			),
			'multi-domain'       => array(
				'slug'        => 'baukasten-multi-domain',
				'title'       => 'Baukasten Addon: Multi-Domain Landingpage',
				'description' => __( 'Give a page its own domain. One WordPress install serves a different front page under each domain you point at it.', 'baukasten' ),
				'position'    => 40,
			),
			'business-cards'     => array(
				'slug'        => 'baukasten-business-cards',
				'title'       => 'Baukasten Addon: Business Cards',
				'description' => __( 'A digital business card on its own short link, built for a phone and detached from your theme. With vCard download and a QR code.', 'baukasten' ),
				'position'    => 50,
			),
			'2fa'                => array(
				'slug'        => 'baukasten-2fa',
				'title'       => 'Baukasten Addon: Two-Factor Approval',
				'description' => __( 'Adds a two-factor method that asks a second, already signed-in browser session to approve the login from the WordPress admin bar.', 'baukasten' ),
				'position'    => 60,
			),
		);
	}

	/**
	 * Returns every known addon with its state on this site.
	 *
	 * Each entry gains `file` (the plugin basename WordPress knows it by),
	 * `status`, `version` and `has_tab`. Sorted by position, like
	 * `Addons::all()`.
	 *
	 * Only call this on an admin screen: it reads the installed plugin list,
	 * which lives in wp-admin.
	 *
	 * @return array<string, array<string, mixed>> Addons keyed by tab id.
	 */
	public static function all(): array {
		$installed = self::installed();
		$addons    = array();

		foreach ( self::entries() as $id => $entry ) {
			$file   = $entry['slug'] . '/' . $entry['slug'] . '.php';
			$header = $installed[ $file ] ?? null;

			if ( null === $header ) {
				$status = self::MISSING;
			} elseif ( is_plugin_active( $file ) ) {
				$status = self::ACTIVE;
			} else {
				$status = self::INACTIVE;
			}

			$addons[ $id ] = array(
				'id'          => $id,
				'slug'        => $entry['slug'],
				'file'        => $file,
				'title'       => $entry['title'],
				'description' => $entry['description'],
				'position'    => $entry['position'],
				'status'      => $status,
				'version'     => isset( $header['Version'] ) ? (string) $header['Version'] : '',
				'has_tab'     => Addons::has( $id ),
			);
		}

		uasort(
			$addons,
			static fn( array $a, array $b ): int => $a['position'] <=> $b['position']
		);

		return $addons;
	}

	/**
	 * The plugins this site has, whether active or not.
	 *
	 * @return array<string, array<string, string>> Plugin headers keyed by basename.
	 */
	private static function installed(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return (array) get_plugins();
	}

	/**
	 * Where to send someone who wants an addon this site does not have.
	 *
	 * A search rather than the plugin's own information screen: the directory
	 * answers a search for something it has not listed with an empty result,
	 * and answers a request for an unknown slug with an error. An empty result
	 * is the better of the two while a release is still in review.
	 *
	 * @param array<string, mixed> $addon Addon from `all()`.
	 * @return string Admin URL.
	 */
	public static function install_url( array $addon ): string {
		return add_query_arg(
			array(
				'tab'  => 'search',
				'type' => 'term',
				's'    => rawurlencode( (string) $addon['title'] ),
			),
			admin_url( 'plugin-install.php' )
		);
	}

	/**
	 * Where to send someone who wants to switch an installed addon on.
	 *
	 * @param array<string, mixed> $addon Addon from `all()`.
	 * @return string Nonced admin URL.
	 */
	public static function activate_url( array $addon ): string {
		$file = (string) $addon['file'];

		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'activate',
					'plugin' => rawurlencode( $file ),
				),
				admin_url( 'plugins.php' )
			),
			'activate-plugin_' . $file
		);
	}

	/**
	 * The label shown for a status.
	 *
	 * @param string $status One of the class constants.
	 * @return string Translated label.
	 */
	public static function status_label( string $status ): string {
		switch ( $status ) {
			case self::ACTIVE:
				return __( 'Active', 'baukasten' );

			case self::INACTIVE:
				return __( 'Installed, not active', 'baukasten' );

			default:
				return __( 'Not installed', 'baukasten' );
		}
	}
}
