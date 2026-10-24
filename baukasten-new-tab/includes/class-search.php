<?php
/**
 * What a search box entry means.
 *
 * @package Baukasten\NewTab
 */

namespace Baukasten\NewTab;

defined( 'ABSPATH' ) || exit;

/**
 * Tells an address from a search, and builds the engine URL.
 *
 * The same rules run in `new-tab.js`; this copy serves the search without
 * JavaScript, where the form is a GET on the page itself with `q` and `e`.
 *
 * That GET is also why an address is never redirected to. A page that
 * forwards `?q=<anything that looks like a host>` is an open redirect on the
 * site's own domain, which is exactly what a phishing mail wants to link to.
 * A search engine is fine — its host comes from the settings, not from the
 * request — so a search is forwarded and an address gets a page with a link.
 */
final class Search {

	/**
	 * Longest entry accepted, in characters.
	 */
	const MAX_LENGTH = 120;

	/**
	 * Endings that make `name.ending` a file rather than a host.
	 *
	 * `app.js` is a search for a file name. Some of these are real top-level
	 * domains too (`.sh`, `.md`, `.rs`, `.py`, `.zip`), but someone who types
	 * `deploy.sh` into a search box means the script.
	 */
	const FILE_ENDINGS = array(
		'bat',
		'c',
		'cfg',
		'conf',
		'cpp',
		'cs',
		'css',
		'csv',
		'dll',
		'doc',
		'docx',
		'exe',
		'gif',
		'go',
		'h',
		'htm',
		'html',
		'ini',
		'java',
		'jpeg',
		'jpg',
		'js',
		'json',
		'jsx',
		'kt',
		'lock',
		'log',
		'lua',
		'md',
		'mjs',
		'mp3',
		'mp4',
		'pdf',
		'php',
		'pl',
		'png',
		'ps1',
		'py',
		'rb',
		'rs',
		'scss',
		'sh',
		'sql',
		'svg',
		'swift',
		'tar',
		'toml',
		'ts',
		'tsx',
		'txt',
		'vue',
		'wav',
		'xls',
		'xlsx',
		'xml',
		'yaml',
		'yml',
		'zip',
	);

	/**
	 * Splits an entry into engine and terms.
	 *
	 * `@b quantum` searches Brave once, whatever the menu says. An unknown or
	 * switched-off shortcut is left in the terms.
	 *
	 * @param string $entry  What was typed.
	 * @param string $engine Engine chosen in the menu.
	 * @return array{engine: string, terms: string} Engine id and search terms.
	 */
	public static function parse( string $entry, string $engine ): array {
		$entry  = trim( $entry );
		$active = Settings::active_engines();

		if ( ! isset( $active[ $engine ] ) ) {
			$engine = Settings::default_engine();
		}

		if ( preg_match( '/^@(\S+)\s+(.+)$/u', $entry, $match ) ) {
			foreach ( $active as $id => $definition ) {
				if ( '' !== $definition['shortcut'] && strtolower( $match[1] ) === strtolower( $definition['shortcut'] ) ) {
					return array(
						'engine' => $id,
						'terms'  => trim( $match[2] ),
					);
				}
			}
		}

		return array(
			'engine' => $engine,
			'terms'  => $entry,
		);
	}

	/**
	 * The engine URL for a search.
	 *
	 * @param string $engine Engine id.
	 * @param string $terms  Search terms.
	 * @return string URL, or an empty string for an unknown engine.
	 */
	public static function url( string $engine, string $terms ): string {
		$active = Settings::active_engines();

		if ( ! isset( $active[ $engine ] ) ) {
			return '';
		}

		return str_replace( '{q}', rawurlencode( $terms ), $active[ $engine ]['template'] );
	}

	/**
	 * The address an entry stands for, if it is one.
	 *
	 * `heise.de`, `localhost:8080`, `192.168.0.1`, `[::1]:3000` and anything
	 * with a scheme are addresses. A space anywhere makes it a search, and so
	 * does a single dot before a file ending.
	 *
	 * @param string $entry What was typed.
	 * @return string URL with scheme, or an empty string for a search.
	 */
	public static function address( string $entry ): string {
		$entry = trim( $entry );

		if ( '' === $entry || preg_match( '/\s/u', $entry ) ) {
			return '';
		}

		if ( preg_match( '#^https?://[^/?\#]+#i', $entry ) ) {
			return $entry;
		}

		if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $entry ) ) {
			// Some other scheme. Not something to link to from here.
			return '';
		}

		$authority = (string) preg_replace( '#[/?\#].*$#s', '', $entry );
		$rest      = substr( $entry, strlen( $authority ) );
		$host      = $authority;
		$port      = '';

		if ( preg_match( '/^(\[[0-9a-f:.]+\])(?::(\d{1,5}))?$/i', $authority, $match ) ) {
			$host = $match[1];
			$port = $match[2] ?? '';

			if ( false === filter_var( trim( $host, '[]' ), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
				return '';
			}
		} else {
			if ( preg_match( '/^(.+):(\d{1,5})$/', $authority, $match ) ) {
				$host = $match[1];
				$port = $match[2];
			}

			$host = strtolower( $host );

			if ( ! self::is_host( $host, '' !== $port || '' !== $rest ) ) {
				return '';
			}
		}

		if ( '' !== $port && ( (int) $port < 1 || (int) $port > 65535 ) ) {
			return '';
		}

		$scheme = ( 'localhost' === $host || str_ends_with( $host, '.localhost' ) || str_starts_with( $host, '[' ) || false !== filter_var( $host, FILTER_VALIDATE_IP ) ) ? 'http' : 'https';

		return $scheme . '://' . $host . ( '' !== $port ? ':' . $port : '' ) . $rest;
	}

	/**
	 * Whether a host name looks like one.
	 *
	 * @param string $host       Lower case host.
	 * @param bool   $has_suffix Whether a port or a path follows.
	 * @return bool True for a host.
	 */
	private static function is_host( string $host, bool $has_suffix ): bool {
		if ( 'localhost' === $host || str_ends_with( $host, '.localhost' ) ) {
			return true;
		}

		if ( false !== filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return true;
		}

		if ( preg_match( '/^\d+(\.\d+)*$/', $host ) ) {
			// 3.14 is a number, not an address.
			return false;
		}

		$labels = explode( '.', $host );

		if ( count( $labels ) < 2 ) {
			return false;
		}

		foreach ( $labels as $label ) {
			if ( ! preg_match( '/^(?!-)[\p{L}\p{N}-]{1,63}(?<!-)$/u', $label ) ) {
				return false;
			}
		}

		$tld = (string) end( $labels );

		if ( ! preg_match( '/^(\p{L}{2,63}|xn--[a-z0-9-]+)$/u', $tld ) ) {
			return false;
		}

		// `app.js` is a file; `app.js/docs` or `app.js:3000` are not.
		return ! ( 2 === count( $labels ) && ! $has_suffix && in_array( $tld, self::FILE_ENDINGS, true ) );
	}

	/**
	 * Reads and bounds the `q` parameter of a request.
	 *
	 * @param mixed $value Unslashed raw value.
	 * @return string Entry, at most `MAX_LENGTH` characters.
	 */
	public static function clean_entry( $value ): string {
		$entry = sanitize_text_field( is_string( $value ) ? $value : '' );

		return mb_substr( $entry, 0, self::MAX_LENGTH );
	}

	/**
	 * The engines as the page's script sees them.
	 *
	 * @return array<int, array{id: string, label: string, shortcut: string, template: string}> Engines in menu order.
	 */
	public static function for_script(): array {
		$engines = array();

		foreach ( Settings::active_engines() as $id => $engine ) {
			$engines[] = array(
				'id'       => $id,
				'label'    => $engine['label'],
				'shortcut' => $engine['shortcut'],
				'template' => $engine['template'],
			);
		}

		return $engines;
	}
}
