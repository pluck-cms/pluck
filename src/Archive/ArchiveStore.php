<?php
declare(strict_types=1);

namespace Pluck\Archive;

use Pluck\Support\Path;
use RuntimeException;
use Throwable;

/**
 * A folder of archives, and the rules for naming one.
 *
 * Backups and downloaded releases are two of these. They had four identical
 * methods each — `pathOf`, `exists`, `delete`, and a listing that differed only
 * in what it built from the names — and identical is how it started. A rule kept
 * in two places is one that will be fixed in one of them.
 *
 * What differs is the folder, the shape of a name, and what to say when a name
 * is not one. Those are arguments; the rest is here.
 *
 * ## The name check is the security boundary
 *
 * These names arrive from a form: "install this download", "delete this backup".
 * A name is therefore never trusted — it is reduced to its basename, matched
 * against a pattern the caller supplied, and then resolved with `Path::within`,
 * which refuses anything that resolves outside the folder even if the pattern
 * let it through. Two checks for one job, because this is the one place where
 * being wrong means somebody's `../../index.php`.
 */
final class ArchiveStore
{
	/**
	 * @param string $directory where the archives live
	 * @param string $pattern a full-match regex a valid name satisfies
	 * @param string $refusal what to say when a name is not one
	 */
	public function __construct(
		private readonly string $directory,
		private readonly string $pattern,
		private readonly string $refusal,
	) {
	}

	public function directory(): string
	{
		return $this->directory;
	}

	/**
	 * The full path of an archive, refusing anything that is not one.
	 *
	 * @throws RuntimeException when the name is not one this store accepts
	 */
	public function pathOf(string $name): string
	{
		$name = basename($name);

		if (preg_match($this->pattern, $name) !== 1) {
			throw new RuntimeException($this->refusal);
		}

		return Path::within($this->directory, $name);
	}

	public function exists(string $name): bool
	{
		try {
			return is_file($this->pathOf($name));
		} catch (Throwable) {
			// A name that is not one cannot exist, which is the answer a caller
			// wants here — asking whether a file is there should not throw.
			return false;
		}
	}

	public function delete(string $name): bool
	{
		return $this->exists($name) && @unlink($this->pathOf($name));
	}

	/**
	 * Every name in the folder that this store accepts.
	 *
	 * Names rather than objects: a backup and a download describe themselves
	 * differently, and that is the part that genuinely belongs to each of them.
	 *
	 * @return list<string>
	 */
	public function names(): array
	{
		if (!is_dir($this->directory)) {
			return [];
		}

		$found = [];

		foreach (scandir($this->directory) ?: [] as $entry) {
			if (preg_match($this->pattern, $entry) === 1) {
				$found[] = $entry;
			}
		}

		return $found;
	}
}
