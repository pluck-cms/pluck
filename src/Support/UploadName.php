<?php
declare(strict_types=1);

namespace Pluck\Support;

/**
 * The stored name of an uploaded file.
 *
 * An uploaded name is rebuilt, never cleaned: the stem is slugged and the one
 * validated extension is put back. That is what stops CVE-2018-19420 (the
 * filename itself as a stored XSS payload) and the whole double-extension family
 * (.php.txt, .phar.png) at once, because no dot, quote, angle bracket or slash
 * from the original can reach the disk.
 *
 * It lives here rather than inside MediaController so the tests can exercise the
 * function the uploader actually calls instead of a copy of it that can drift.
 */
final class UploadName
{
	/** Long enough to stay recognisable, short enough for any filesystem. */
	public const MAX_STEM = 80;

	/** Give up on counting and take a random suffix after this many collisions. */
	private const MAX_COLLISIONS = 999;

	/**
	 * @param string $original   the name as the browser sent it, untrusted
	 * @param string $extension  an extension that has already been validated
	 */
	public static function build(string $original, string $extension): string
	{
		return self::stem($original) . '.' . $extension;
	}

	public static function stem(string $original): string
	{
		return substr(Slug::make(pathinfo($original, PATHINFO_FILENAME), 'file'), 0, self::MAX_STEM);
	}

	/**
	 * The stored name, with a counter appended if that name is taken.
	 *
	 * @param callable(string):bool $taken tells whether a candidate name exists
	 */
	public static function unique(string $original, string $extension, callable $taken): string
	{
		$stem = self::stem($original);
		$name = $stem . '.' . $extension;

		$counter = 2;
		while ($taken($name)) {
			$name = $stem . '-' . $counter . '.' . $extension;
			$counter++;
			if ($counter > self::MAX_COLLISIONS) {
				return $stem . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
			}
		}

		return $name;
	}
}
