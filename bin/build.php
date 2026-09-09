<?php
/**
 * Builds a distributable plugin archive.
 *
 * Copies everything in a plugin directory that is not listed in its
 * .distignore into build/<slug>/ and zips it as build/<slug>-<version>.zip,
 * which is what goes into the WordPress.org SVN trunk and a GitHub release.
 *
 * Usage: php bin/build.php <plugin-directory>
 *        php bin/build.php --all
 *
 * The slug is the directory name; the main file is <slug>/<slug>.php.
 *
 * @package Baukasten
 */

declare( strict_types = 1 );

$repo = dirname( __DIR__ );
$args = array_slice( $argv, 1 );

if ( array() === $args ) {
	fwrite( STDERR, "Usage: php bin/build.php <plugin-directory>|--all\n" );
	exit( 1 );
}

if ( array( '--all' ) === $args ) {
	$args = array();

	foreach ( (array) scandir( $repo ) as $entry ) {
		if ( is_string( $entry ) && is_file( $repo . '/' . $entry . '/' . $entry . '.php' ) ) {
			$args[] = $entry;
		}
	}

	sort( $args );
}

/**
 * Reads the .distignore patterns of a plugin.
 *
 * @param string $dir Plugin directory.
 * @return string[] Patterns.
 */
function dist_patterns( string $dir ): array {
	$file = $dir . '/.distignore';

	if ( ! is_readable( $file ) ) {
		return array();
	}

	$lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

	return array_values(
		array_filter(
			array_map( 'trim', (array) $lines ),
			static fn( string $line ): bool => '' !== $line && '#' !== $line[0]
		)
	);
}

/**
 * Whether a relative path matches one of the .distignore patterns.
 *
 * A pattern matches the full relative path or any single path segment, so
 * `vendor` excludes both `vendor/` and `includes/vendor/`.
 *
 * @param string   $relative Path relative to the plugin directory.
 * @param string[] $patterns Patterns from .distignore.
 * @return bool True when the path is excluded.
 */
function is_ignored( string $relative, array $patterns ): bool {
	$segments = explode( '/', $relative );

	foreach ( $patterns as $pattern ) {
		if ( fnmatch( $pattern, $relative ) ) {
			return true;
		}

		foreach ( $segments as $segment ) {
			if ( fnmatch( $pattern, $segment ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Removes a directory and everything below it.
 *
 * @param string $path Directory to remove.
 * @return void
 */
function rmtree( string $path ): void {
	if ( ! is_dir( $path ) ) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $items as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() );
		} else {
			unlink( $item->getPathname() );
		}
	}

	rmdir( $path );
}

/**
 * Builds one plugin archive.
 *
 * @param string $repo Repository root.
 * @param string $slug Plugin directory name.
 * @return bool True on success.
 */
function build_plugin( string $repo, string $slug ): bool {
	$dir  = $repo . '/' . $slug;
	$main = $dir . '/' . $slug . '.php';

	if ( ! is_file( $main ) ) {
		fwrite( STDERR, "No main file at $slug/$slug.php\n" );

		return false;
	}

	$version = null;

	if ( preg_match( '/^\s*\*\s*Version:\s*(.+)$/mi', (string) file_get_contents( $main ), $matches ) ) {
		$version = trim( $matches[1] );
	}

	if ( null === $version ) {
		fwrite( STDERR, "Could not read the version from $slug/$slug.php\n" );

		return false;
	}

	$readme = $dir . '/readme.txt';

	if ( is_readable( $readme ) && preg_match( '/^Stable tag:\s*(.+)$/mi', (string) file_get_contents( $readme ), $matches ) ) {
		$stable = trim( $matches[1] );

		if ( $stable !== $version ) {
			fwrite( STDERR, "Version mismatch in $slug: plugin header says $version, readme.txt Stable tag says $stable\n" );

			return false;
		}
	}

	$patterns = dist_patterns( $dir );
	$build    = $repo . '/build';
	$staging  = $build . '/' . $slug;

	rmtree( $staging );
	mkdir( $staging, 0755, true );

	$copied   = 0;
	$iterator = new RecursiveIteratorIterator(
		new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			static function ( $current ) use ( $dir, $patterns ): bool {
				$relative = str_replace( '\\', '/', substr( $current->getPathname(), strlen( $dir ) + 1 ) );

				return ! is_ignored( $relative, $patterns );
			}
		)
	);

	foreach ( $iterator as $file ) {
		$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $dir ) + 1 ) );
		$target   = $staging . '/' . $relative;

		if ( $file->isDir() ) {
			if ( ! is_dir( $target ) ) {
				mkdir( $target, 0755, true );
			}
			continue;
		}

		if ( ! is_dir( dirname( $target ) ) ) {
			mkdir( dirname( $target ), 0755, true );
		}

		copy( $file->getPathname(), $target );
		++$copied;
	}

	$zip_path = $build . '/' . $slug . '-' . $version . '.zip';
	$zip      = new ZipArchive();

	if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		fwrite( STDERR, "Could not create $zip_path\n" );

		return false;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $staging, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $file ) {
		$relative = $slug . '/' . str_replace( '\\', '/', substr( $file->getPathname(), strlen( $staging ) + 1 ) );

		if ( $file->isDir() ) {
			$zip->addEmptyDir( $relative );
		} else {
			$zip->addFile( $file->getPathname(), $relative );
		}
	}

	$zip->close();

	printf(
		"Built %s (%d files, %s KB)%s",
		basename( $zip_path ),
		$copied,
		number_format( (float) filesize( $zip_path ) / 1024, 1 ),
		PHP_EOL
	);

	return true;
}

$failed = 0;

foreach ( $args as $arg ) {
	if ( ! build_plugin( $repo, basename( rtrim( str_replace( '\\', '/', $arg ), '/' ) ) ) ) {
		++$failed;
	}
}

exit( $failed > 0 ? 1 : 0 );
