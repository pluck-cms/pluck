<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Support\Path;

final class PathTest extends TestCase
{
	public function run(): void
	{
		$this->group('a rewritten php file is not served from cache', fn () => $this->invalidates());
		$this->group('windows separators', fn () => $this->windows());

		$base = $this->tempDir('pluck-path');
		mkdir($base . '/content', 0o755, true);

		$this->assertSame(
			realpath($base) . '/content/page.json',
			Path::within($base, 'content/page.json'),
			'a normal relative path resolves inside the base',
		);
		$this->assertSame(realpath($base), Path::within($base, ''), 'an empty path is the base itself');
		$this->assertSame(realpath($base) . '/a/b', Path::within($base, './a/./b'), 'single dots are ignored');

		$this->expectFailure(static fn () => Path::within($base, '../etc/passwd'), 'parent traversal is refused');
		$this->expectFailure(static fn () => Path::within($base, 'content/../../x'), 'traversal in the middle is refused');
		$this->expectFailure(static fn () => Path::within($base, '/etc/passwd'), 'absolute paths are refused');
		$this->expectFailure(static fn () => Path::within($base, "content/\0.json"), 'null bytes are refused');

		/*
		 * The link points at a second temporary directory, not at a real system
		 * path. What is being tested is that Path::within() refuses a link
		 * leading out of the base, and any directory outside the base proves
		 * that. Aiming it at /etc proved nothing extra and made every careless
		 * recursive delete in this suite a loaded weapon.
		 */
		if (function_exists('symlink')) {
			$outside = $this->tempDir('pluck-path-outside');
			file_put_contents($outside . '/secret.txt', 'not yours');
			@symlink($outside, $base . '/escape');
			$this->expectFailure(
				static fn () => Path::within($base, 'escape/secret.txt'),
				'a symlink pointing outside the base is refused',
			);
		}

		Path::writeAtomic($base . '/content/atomic.txt', 'hello');
		$this->assertSame('hello', file_get_contents($base . '/content/atomic.txt'), 'atomic write lands the content');
		Path::writeAtomic($base . '/content/atomic.txt', 'replaced');
		$this->assertSame('replaced', file_get_contents($base . '/content/atomic.txt'), 'atomic write replaces cleanly');
		$this->assertSame(
			[],
			glob($base . '/content/*.tmp') ?: [],
			'no temp files are left behind',
		);

		$this->assertSame('0644', substr(sprintf('%o', fileperms($base . '/content/atomic.txt')), -4), 'written files are not world-writable');

		Path::ensureDir($base . '/deep/deeper');
		$this->assertTrue(is_dir($base . '/deep/deeper'), 'ensureDir creates nested directories');
		$this->assertSame('0755', substr(sprintf('%o', fileperms($base . '/deep')), -4), 'created directories are not 0777');

		Path::deleteTree($base, 'deep');
		$this->assertFalse(is_dir($base . '/deep'), 'deleteTree removes the tree');
		$this->expectFailure(static fn () => Path::deleteTree($base, '../outside'), 'deleteTree refuses to leave the base');
	}

	/**
	 * Writing a .php tells OPcache the file changed.
	 *
	 * Asserted against the source, which is unsatisfying and honest: OPcache in
	 * CLI is per-process, so a test here starts with an empty cache and cannot
	 * reproduce the failure. The failure lives where the cache is shared between
	 * requests — every mod_php and php-fpm server — so a save, a redirect, and a
	 * request landing within opcache.revalidate_freq gets the file as it was.
	 *
	 * That is what made a saved timezone read back as the old one, and read
	 * correctly again after going somewhere else and coming back.
	 */
	private function invalidates(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/src/Support/Path.php');

		$this->assertTrue(
			str_contains($source, 'opcache_invalidate'),
			'writeAtomic invalidates the compiled copy',
		);
		$this->assertTrue(
			str_contains($source, ".php'"),
			'and only for files that have one',
		);
		$this->assertTrue(
			str_contains($source, "function_exists('opcache_invalidate')"),
			'without assuming the extension is loaded',
		);
	}

	/**
	 * Paths are compared with one separator, whatever the platform uses.
	 *
	 * realpath() returns backslashes on Windows, so a base of
	 * C:\\xampp\\htdocs\\pluck\\data never started with itself plus "/" and every
	 * path was refused as an escape. Pluck did not run on Windows at all — which
	 * is where a great many people try something first, and it was reported by
	 * somebody who had to patch it before he could look at anything else.
	 *
	 * Asserted against the source, because this machine is not Windows and a test
	 * that only runs where the fault cannot happen proves nothing about it.
	 */
	private function windows(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/src/Support/Path.php');

		$this->assertFalse(
			str_contains($source, "str_starts_with(\$probeReal, \$baseReal . '/')"),
			'the comparison no longer assumes a forward slash came out of realpath',
		);
		$this->assertTrue(
			str_contains($source, 'private static function normalise'),
			'separators are normalised before comparing',
		);

		// Case is folded on Windows and not on Linux, and that difference is the
		// point: NTFS treats Data and data as one directory, ext4 does not, and
		// folding case there would let a path escape into a different one.
		$this->assertTrue(
			str_contains($source, "DIRECTORY_SEPARATOR === '\\\\'"),
			'and case is folded only where the filesystem folds it',
		);

		// The check still refuses what it always refused.
		$dir = $this->tempDir('pluck-within');
		@mkdir($dir . '/data', 0o755, true);

		$this->expectFailure(
			fn () => Path::within($dir . '/data', '../outside'),
			'a traversal is still refused',
		);

		$this->assertTrue(
			str_starts_with(
				str_replace('\\', '/', Path::within($dir . '/data', 'settings/config.php')),
				str_replace('\\', '/', realpath($dir . '/data')),
			),
			'and an ordinary path still resolves inside the base',
		);
	}
}
