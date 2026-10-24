<?php
/**
 * Stored settings.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, completes and validates the addon's options.
 *
 * Three options, kept apart on purpose:
 *
 * - `baukasten_new_tab_page`: which page it is. Registered in the `reading`
 *   group by `Page_Setting`, next to the homepage and the posts page.
 * - `baukasten_new_tab_settings`: everything else, autoloaded, because the
 *   page reads it on every request.
 * - `baukasten_new_tab_unsplash_key`: the Unsplash access key, not autoloaded,
 *   so it is not in memory on every request of the site and is only ever read
 *   by the one request that talks to Unsplash.
 */
final class Settings {

	/**
	 * Option holding the page ID.
	 */
	const OPTION_PAGE = 'baukasten_new_tab_page';

	/**
	 * Option holding everything else.
	 */
	const OPTION = 'baukasten_new_tab_settings';

	/**
	 * Option holding the Unsplash access key.
	 */
	const OPTION_KEY = 'baukasten_new_tab_unsplash_key';

	/**
	 * Background modes.
	 */
	const BACKGROUNDS = array( 'media', 'unsplash', 'off' );

	/**
	 * Orientations the Unsplash API accepts, plus `any`.
	 */
	const ORIENTATIONS = array( 'landscape', 'portrait', 'squarish', 'any' );

	/**
	 * The format of an Unsplash access key.
	 */
	const KEY_PATTERN = '/^[A-Za-z0-9_-]{20,100}$/';

	/**
	 * Most favourites the bar takes.
	 */
	const MAX_FAVORITES = 40;

	/**
	 * Settings of this request, completed with the defaults.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * The engines that ship, in the order of the menu.
	 *
	 * `{q}` stands for the search terms. Each was checked on 24 October 2026
	 * for whether its language parameter actually changes the interface; see
	 * the plugin README.
	 *
	 * @return array<string, array{badge: string, label: string, shortcut: string, template: string, enabled: bool}> Engines keyed by id.
	 */
	public static function default_engines(): array {
		return array(
			'startpage'  => array(
				'badge'    => 'S',
				'label'    => 'Startpage',
				'shortcut' => 'sp',
				'template' => 'https://www.startpage.com/sp/search?query={q}&cat=web&language=deutsch&lui=deutsch',
				'enabled'  => true,
			),
			'brave'      => array(
				'badge'    => 'B',
				'label'    => 'Brave',
				'shortcut' => 'b',
				'template' => 'https://search.brave.com/search?q={q}&source=web&lang=de&country=de',
				'enabled'  => true,
			),
			'google_ai'  => array(
				'badge'    => 'G',
				'label'    => 'Google KI-Modus',
				'shortcut' => 'g',
				'template' => 'https://www.google.com/search?q={q}&udm=50&hl=de&gl=de',
				'enabled'  => true,
			),
			'perplexity' => array(
				'badge'    => 'P',
				'label'    => 'Perplexity',
				'shortcut' => 'p',
				'template' => 'https://www.perplexity.ai/search?q={q}',
				'enabled'  => true,
			),
			'yandex'     => array(
				'badge'    => 'Y',
				'label'    => 'Yandex',
				'shortcut' => 'y',
				'template' => 'https://yandex.com/search/?text={q}&lang=de',
				'enabled'  => true,
			),
		);
	}

	/**
	 * Defaults for the settings option.
	 *
	 * @return array<string, mixed> Defaults.
	 */
	public static function defaults(): array {
		return array(
			'default_engine'       => 'startpage',
			'engines'              => self::default_engines(),
			'background'           => 'media',
			'media_ids'            => array(),
			'unsplash_query'       => 'wallpapers,nature',
			'unsplash_orientation' => 'landscape',
			'unsplash_app_name'    => '',
			'interval'             => 10,
			'favorites'            => array(),
			'greeting'             => true,
			'greeting_holidays'    => true,
			'greeting_custom'      => '',
		);
	}

	/**
	 * All settings, completed with the defaults.
	 *
	 * @return array<string, mixed> Settings.
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION, array() );
			$stored = is_array( $stored ) ? $stored : array();

			$settings            = array_merge( self::defaults(), array_intersect_key( $stored, self::defaults() ) );
			$settings['engines'] = self::complete_engines( is_array( $settings['engines'] ) ? $settings['engines'] : array() );

			self::$cache = $settings;
		}

		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed Value, or null for an unknown name.
	 */
	public static function get( string $key ) {
		$all = self::all();

		return $all[ $key ] ?? null;
	}

	/**
	 * Stores the settings.
	 *
	 * @param array<string, mixed> $settings Validated settings.
	 * @return void
	 */
	public static function save( array $settings ): void {
		update_option( self::OPTION, array_intersect_key( $settings, self::defaults() ), true );

		self::$cache = null;
	}

	/**
	 * Forgets what this request has read, after something else wrote the option.
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * The selected page.
	 *
	 * @return int Page ID, or 0.
	 */
	public static function page_id(): int {
		return absint( get_option( self::OPTION_PAGE, 0 ) );
	}

	/**
	 * The Unsplash access key.
	 *
	 * @return string Key, or an empty string.
	 */
	public static function unsplash_key(): string {
		$key = get_option( self::OPTION_KEY, '' );

		return is_string( $key ) ? $key : '';
	}

	/**
	 * The engines that are switched on, in menu order.
	 *
	 * @return array<string, array{badge: string, label: string, shortcut: string, template: string, enabled: bool}> Engines keyed by id.
	 */
	public static function active_engines(): array {
		$active = array_filter(
			(array) self::get( 'engines' ),
			static fn( $engine ): bool => ! empty( $engine['enabled'] )
		);

		if ( array() === $active ) {
			// Nothing switched on is a misconfiguration, not a wish for a
			// search box that cannot search.
			$defaults = self::default_engines();

			return array( 'startpage' => $defaults['startpage'] );
		}

		return $active;
	}

	/**
	 * The engine a search goes to when nothing else is chosen.
	 *
	 * @return string Engine id.
	 */
	public static function default_engine(): string {
		$active = self::active_engines();
		$chosen = (string) self::get( 'default_engine' );

		return isset( $active[ $chosen ] ) ? $chosen : (string) array_key_first( $active );
	}

	/**
	 * Puts every shipped engine into a stored list, keeping what was edited.
	 *
	 * Engines are edited, never added or removed, so the list always has the
	 * same ids in the same order.
	 *
	 * @param array<string, mixed> $stored Stored engines.
	 * @return array<string, array{badge: string, label: string, shortcut: string, template: string, enabled: bool}> Complete list.
	 */
	private static function complete_engines( array $stored ): array {
		$engines = array();

		foreach ( self::default_engines() as $id => $default ) {
			$engine = isset( $stored[ $id ] ) && is_array( $stored[ $id ] ) ? $stored[ $id ] : array();

			$engines[ $id ] = array(
				'badge'    => $default['badge'],
				'label'    => isset( $engine['label'] ) && '' !== $engine['label'] ? (string) $engine['label'] : $default['label'],
				'shortcut' => isset( $engine['shortcut'] ) ? (string) $engine['shortcut'] : $default['shortcut'],
				'template' => isset( $engine['template'] ) && '' !== $engine['template'] ? (string) $engine['template'] : $default['template'],
				'enabled'  => isset( $engine['enabled'] ) ? (bool) $engine['enabled'] : $default['enabled'],
			);
		}

		return $engines;
	}

	/**
	 * Whether a search template is acceptable.
	 *
	 * HTTPS only, and the placeholder has to be there: a template without
	 * `{q}` sends every search to the same page.
	 *
	 * @param string $template Template.
	 * @return bool True when it can be used.
	 */
	public static function is_valid_template( string $template ): bool {
		if ( ! str_contains( $template, '{q}' ) ) {
			return false;
		}

		$probe = str_replace( '{q}', 'probe', $template );

		return 'https' === wp_parse_url( $probe, PHP_URL_SCHEME )
			&& '' !== (string) wp_parse_url( $probe, PHP_URL_HOST )
			&& esc_url_raw( $probe, array( 'https' ) ) === $probe;
	}

	/**
	 * Cleans an address for the favourites bar.
	 *
	 * @param string $url Address as entered.
	 * @return string Address, or an empty string when it is not http(s).
	 */
	public static function clean_url( string $url ): string {
		$url = trim( $url );

		if ( '' === $url ) {
			return '';
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . $url;
		}

		$clean = esc_url_raw( $url, array( 'http', 'https' ) );

		return '' !== (string) wp_parse_url( $clean, PHP_URL_HOST ) ? $clean : '';
	}
}
