<?php
declare(strict_types=1);

namespace Pluck\Backup;

use RuntimeException;

/**
 * Tar archives, written and read by hand.
 *
 * Written rather than reached for, because the obvious alternatives are not
 * available where Pluck runs. `ext-zip` is absent from every image in the test
 * bed and from a great deal of shared hosting. `PharData` needs the Phar
 * extension, which hosts routinely disable outright. What is left is a format
 * simple enough to implement in one readable file, and tar from 1979 is exactly
 * that: fixed 512-byte headers, no compression, no index, no cleverness.
 *
 * Only what a backup needs is supported — regular files, and directories
 * implicitly. No symlinks, no hard links, no device nodes, no sparse files. A
 * reader that understands fewer things is a reader with fewer ways to be talked
 * into something.
 */
final class Tar
{
	private const BLOCK = 512;

	/** Regular file, in both the old and the ustar spelling. */
	private const REGULAR = ['0', "\0", ''];

	/**
	 * A header for one file.
	 *
	 * @throws RuntimeException when the path will not fit. Failing loudly beats
	 *         writing a truncated name, which produces an archive that restores
	 *         into the wrong place.
	 */
	public static function header(string $path, int $size, int $mtime, int $mode = 0o644): string
	{
		[$prefix, $name] = self::splitPath($path);

		$header = str_pad($name, 100, "\0");
		$header .= str_pad(sprintf('%06o ', $mode & 0o777), 8, "\0");
		$header .= str_pad(sprintf('%06o ', 0), 8, "\0");
		$header .= str_pad(sprintf('%06o ', 0), 8, "\0");
		$header .= str_pad(sprintf('%011o ', $size), 12, "\0");
		$header .= str_pad(sprintf('%011o ', $mtime), 12, "\0");
		$header .= '        '; // checksum, filled in below
		$header .= '0';        // regular file
		$header .= str_repeat("\0", 100); // linkname
		$header .= "ustar\0" . '00';
		$header .= str_pad('pluck', 32, "\0");
		$header .= str_pad('pluck', 32, "\0");
		$header .= str_repeat("\0", 8) . str_repeat("\0", 8); // dev major/minor
		$header .= str_pad($prefix, 155, "\0");
		$header .= str_repeat("\0", 12);

		// The checksum is the sum of every byte, computed with the checksum field
		// itself read as eight spaces. Written as six octal digits, a NUL and a
		// space — the odd spelling every tar implementation agrees on.
		$sum = 0;
		for ($i = 0; $i < self::BLOCK; $i++) {
			$sum += ord($header[$i]);
		}

		return substr_replace($header, sprintf('%06o', $sum) . "\0 ", 148, 8);
	}

	/** Pad to a whole number of blocks. */
	public static function pad(int $size): string
	{
		$remainder = $size % self::BLOCK;

		return $remainder === 0 ? '' : str_repeat("\0", self::BLOCK - $remainder);
	}

	/** Two empty blocks, which is how a tar says it has finished. */
	public static function end(): string
	{
		return str_repeat("\0", self::BLOCK * 2);
	}

	/**
	 * Walk the entries in an archive.
	 *
	 * A generator, so a large archive is read a file at a time rather than being
	 * held in memory twice over.
	 *
	 * @param resource $handle
	 * @return \Generator<array{path:string,size:int,mtime:int,mode:int,offset:int}>
	 */
	public static function entries($handle): \Generator
	{
		$at = 0;

		while (true) {
			// Seek absolutely rather than trusting where the pointer was left. The
			// whole point of yielding an offset is that the caller reads the
			// contents — and a caller that does moves the pointer, which quietly
			// turned the next header into garbage.
			if (fseek($handle, $at) !== 0) {
				return;
			}

			$block = fread($handle, self::BLOCK);
			if ($block === false || strlen($block) < self::BLOCK) {
				return;
			}

			// Two empty blocks end the archive; one is enough to stop reading.
			if (trim($block, "\0") === '') {
				return;
			}

			$entry = self::parse($block);
			if ($entry === null) {
				throw new RuntimeException('This archive is damaged: a header did not add up.');
			}

			$entry['offset'] = $at + self::BLOCK;
			$at = $entry['offset'] + $entry['size'] + strlen(self::pad($entry['size']));

			yield $entry;
		}
	}

	/**
	 * Read one header block.
	 *
	 * @return array{path:string,size:int,mtime:int,mode:int,offset:int}|null null
	 *         when the checksum does not match, which means the file is damaged
	 *         or is not a tar at all
	 */
	private static function parse(string $block): ?array
	{
		$stored = trim(substr($block, 148, 8), "\0 ");
		if ($stored === '' || !preg_match('/^[0-7]+$/', $stored)) {
			return null;
		}

		$blanked = substr_replace($block, '        ', 148, 8);
		$sum = 0;
		for ($i = 0; $i < self::BLOCK; $i++) {
			$sum += ord($blanked[$i]);
		}

		if ($sum !== octdec($stored)) {
			return null;
		}

		$type = substr($block, 156, 1);
		if (!in_array($type, self::REGULAR, true)) {
			// A link, a device, a directory entry. Skipped rather than refused:
			// the size field still says how far to seek, so the archive stays
			// readable, and nothing this project writes produces one.
			$type = 'skip';
		}

		$name = rtrim(substr($block, 0, 100), "\0");
		$prefix = rtrim(substr($block, 345, 155), "\0");

		return [
			'path' => $prefix === '' ? $name : $prefix . '/' . $name,
			'size' => (int) octdec(trim(substr($block, 124, 12), "\0 ") ?: '0'),
			'mtime' => (int) octdec(trim(substr($block, 136, 12), "\0 ") ?: '0'),
			'mode' => $type === 'skip' ? 0 : (int) octdec(trim(substr($block, 100, 8), "\0 ") ?: '644'),
			'offset' => 0,
		];
	}

	/**
	 * Split a path into ustar's prefix and name fields.
	 *
	 * The name field holds 100 characters and the prefix another 155, joined by a
	 * slash. Splitting has to happen *at* a slash, which is why a very long single
	 * filename cannot be represented however much room is left over.
	 *
	 * @return array{0:string,1:string}
	 * @throws RuntimeException when no split works
	 */
	private static function splitPath(string $path): array
	{
		$path = ltrim(str_replace('\\', '/', $path), '/');

		if (strlen($path) <= 100) {
			return ['', $path];
		}

		// The longest prefix that fits, so the name field keeps as much as it can.
		for ($at = min(strlen($path) - 1, 155); $at > 0; $at--) {
			if ($path[$at] !== '/') {
				continue;
			}

			$prefix = substr($path, 0, $at);
			$name = substr($path, $at + 1);

			if (strlen($name) <= 100 && strlen($prefix) <= 155) {
				return [$prefix, $name];
			}
		}

		throw new RuntimeException(sprintf('This path is too long to put in a tar archive: %s', $path));
	}
}
