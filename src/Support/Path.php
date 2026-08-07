<?php
declare(strict_types=1);

namespace Pluck\Support;

use RuntimeException;

/**
 * Traversal-safe path handling.
 *
 * Every filesystem path derived from request input must pass through
 * Path::within(). Pluck 4 resolved paths by string concatenation, which is how
 * "../" reached the page loader; there is deliberately no unchecked variant here.
 */
final class Path
{
	/**
	 * Resolve $relative inside $base and guarantee the result stays inside it.
	 * The target does not need to exist yet; the deepest existing ancestor is
	 * what gets verified, so this is safe for paths we are about to create.
	 */
	public static function within(string $base, string $relative): string
	{
		$baseReal = realpath($base);
		if ($baseReal === false) {
			throw new RuntimeException("Base directory does not exist: {$base}");
		}

		$relative = str_replace('\\', '/', $relative);
		if ($relative !== '' && ($relative[0] === '/' || preg_match('#^[a-zA-Z]:#', $relative) === 1)) {
			throw new RuntimeException('Absolute paths are not accepted here.');
		}

		$segments = [];
		foreach (explode('/', $relative) as $segment) {
			if ($segment === '' || $segment === '.') {
				continue;
			}
			if ($segment === '..') {
				throw new RuntimeException('Parent directory traversal is not allowed.');
			}
			if (str_contains($segment, "\0")) {
				throw new RuntimeException('Null byte in path.');
			}
			$segments[] = $segment;
		}

		$target = $baseReal . ($segments === [] ? '' : DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments));

		// Verify the deepest existing ancestor really lives under the base, so a
		// pre-existing symlink cannot point the path out of the data directory.
		$probe = $target;
		while (!file_exists($probe)) {
			$parent = dirname($probe);
			if ($parent === $probe) {
				break;
			}
			$probe = $parent;
		}
		$probeReal = realpath($probe);
		if ($probeReal === false || !self::isInside(self::normalise($probeReal), self::normalise($baseReal))) {
			throw new RuntimeException('Resolved path escapes its base directory.');
		}

		return $target;
	}

	/**
	 * One separator, so two paths can be compared.
	 *
	 * realpath() returns backslashes on Windows, so a base of
	 * C:\xampp\htdocs\pluck\data never started with itself plus "/" and every
	 * path was refused as an escape. Pluck did not run on Windows at all, which
	 * is where a great many people try things first.
	 */
	private static function normalise(string $path): string
	{
		return str_replace('\\', '/', $path);
	}

	/**
	 * Is $probe the base, or inside it?
	 *
	 * Case is folded on Windows only. NTFS treats Data and data as one directory,
	 * so a comparison that does not would refuse a path the filesystem considers
	 * identical. On Linux they are two different directories and folding case
	 * would let a path escape into one of them — which is why this is decided by
	 * the platform rather than made uniform for tidiness.
	 */
	private static function isInside(string $probe, string $base): bool
	{
		if (DIRECTORY_SEPARATOR === '\\') {
			$probe = mb_strtolower($probe);
			$base = mb_strtolower($base);
		}

		return $probe === $base || str_starts_with($probe, rtrim($base, '/') . '/');
	}

	/** Create a directory (recursively) with sane, non-world-writable permissions. */
	public static function ensureDir(string $dir): void
	{
		if (is_dir($dir)) {
			return;
		}
		// Suppressed on purpose: the exception below carries everything the caller
		// needs, and the warning does not. On a server with display_errors on it
		// would be printed before anything could catch it, and once output has
		// started PHP will not send a 500 — so the warning was the reason a broken
		// install answered "200 OK" with a stack trace on it.
		if (!@mkdir($dir, 0o755, true) && !is_dir($dir)) {
			throw new RuntimeException("Could not create directory: {$dir}");
		}
	}

	/**
	 * Write a file atomically: temp file in the same directory, then rename.
	 * Prevents half-written pages when two admins save at the same moment.
	 */
	public static function writeAtomic(string $file, string $contents): void
	{
		self::ensureDir(dirname($file));
		$tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';

		if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
			throw new RuntimeException("Could not write: {$tmp}");
		}
		@chmod($tmp, 0o644);

		if (!rename($tmp, $file)) {
			@unlink($tmp);
			throw new RuntimeException("Could not move temp file into place: {$file}");
		}


		/*
		 * A PHP file that was just replaced is still cached as it was.
		 *
		 * OPcache holds the compiled form of every .php it has seen and, by
		 * default, only asks whether the file changed every couple of seconds.
		 * Write one and read it back inside that window and you get the previous
		 * contents: right on disk, stale in the process. The atomic write makes it
		 * worse rather than better, because rename() gives the new file a
		 * different inode while OPcache is keyed on the path.
		 *
		 * This is what made a saved timezone read back as the old one until you
		 * navigated away and returned — a reading fault that looked exactly like a
		 * saving fault.
		 */
		if (function_exists('opcache_invalidate') && str_ends_with($file, '.php')) {
			@opcache_invalidate($file, true);
		}
	}

	/** Recursively delete a directory. Refuses to act outside $base. */
	public static function deleteTree(string $base, string $relative): void
	{
		$target = self::within($base, $relative);
		if (!file_exists($target)) {
			return;
		}
		if (is_file($target) || is_link($target)) {
			@unlink($target);
			return;
		}
		foreach (scandir($target) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			self::deleteTree($base, ltrim($relative, '/') . '/' . $entry);
		}
		@rmdir($target);
	}
}
