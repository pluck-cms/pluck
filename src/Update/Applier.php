<?php
declare(strict_types=1);

namespace Pluck\Update;

use Pluck\Backup\Tar;
use Pluck\Support\Path;
use RuntimeException;
use Throwable;

/**
 * Putting a downloaded release in place.
 *
 * The most dangerous thing in Pluck, so it is written to fail rather than to
 * half-succeed. Nothing is overwritten until the whole archive has been unpacked
 * and checked, every file about to be replaced is copied aside first, and any
 * failure puts the copies back.
 *
 * What is worth being clear about, because a checksum is easy to mistake for
 * more than it is: the SHA-256 shown next to a download proves the bytes arrived
 * as GitHub sent them. It does not prove GitHub sent something legitimate — a
 * compromised release comes with a matching hash. The defence against that is a
 * signature over the archive, which this project decided it could not keep up on
 * every release, and a check nobody sustains is worse than none because it gets
 * switched off the first time it is inconvenient.
 *
 * So the risk taken here is deliberate and recorded: pressing the button trusts
 * the release. Automatic updating is not offered for the same reason — one
 * compromised release should not reach every site before anyone has looked at it.
 *
 * What is never touched: `data/`, `media/`, and `data/settings/config.php` inside
 * it. Those are the site; the rest is the program.
 */
final class Applier
{
	/** Directories the release owns outright, where a file that has gone should go. */
	private const OWNED = ['src', 'views', 'lang', 'assets', 'bin', 'docs'];

	/** Never replaced, whatever the archive holds. */
	/*
	 * modules/ is here because a module of your own had nowhere to live that
	 * survived an update: src/ is replaced wholesale, so anything added there was
	 * gone the first time somebody pressed the button.
	 *
	 * 'instances' is a leftover from an idea that was never built. It costs
	 * nothing and removing it from a list that protects things is the kind of
	 * tidying that goes wrong once.
	 */
	private const KEEP = ['data', 'media', 'modules', 'instances'];

	public function __construct(
		private readonly string $rootDir,
		private readonly Updates $updates,
	) {
	}

	/**
	 * Unpack $name over the install.
	 *
	 * @return array{replaced:int,removed:int,from:string,to:string}
	 * @throws RuntimeException with nothing changed, or with everything put back
	 */
	public function apply(string $name): array
	{
		$archive = $this->updates->pathOf($name);
		$staging = $this->stagingDir();

		// Read before anything is replaced. composer.json is one of the files the
		// release overwrites, so asking afterwards reports the version we just
		// installed as the one we came from.
		$from = $this->currentVersion();

		try {
			$this->unpack($archive, $staging);
			$release = $this->rootOf($staging);
			$this->check($release);

			$files = $this->filesIn($release);
			$rollback = $this->stagingDir('rollback');

			$replaced = $this->swap($release, $files, $rollback);
			$removed = $this->removeObsolete($files, $rollback);

			return [
				'replaced' => $replaced,
				'removed' => $removed,
				'from' => $from,
				'to' => $this->versionIn($release),
			];
		} catch (Throwable $e) {
			$this->rollBack();

			throw $e;
		} finally {
			$this->clean();
		}
	}

	// ---- unpacking ------------------------------------------------------

	/**
	 * Read the archive into a staging directory.
	 *
	 * Every path is checked against the staging root before anything is written.
	 * A release tarball is a file fetched over the network, which makes it
	 * untrusted input in exactly the way an upload is — `../../` in an entry name
	 * is the oldest trick there is.
	 */
	private function unpack(string $archive, string $staging): void
	{
		Path::ensureDir($staging);

		$handle = str_ends_with($archive, '.gz') ? @gzopen($archive, 'rb') : @fopen($archive, 'rb');
		if ($handle === false) {
			throw new RuntimeException('That download could not be read.');
		}

		$written = 0;

		try {
			foreach (Tar::entries($handle) as $entry) {
				if ($entry['mode'] === 0 || $entry['size'] < 0) {
					continue;
				}

				$relative = $entry['path'];
				if (str_contains($relative, "\0") || str_starts_with($relative, '/')) {
					throw new RuntimeException('This archive contains a path that leads outside itself.');
				}

				try {
					$target = Path::within($staging, $relative);
				} catch (Throwable) {
					throw new RuntimeException('This archive contains a path that leads outside itself.');
				}

				Path::ensureDir(dirname($target));

				fseek($handle, $entry['offset']);
				$out = fopen($target, 'wb');
				if ($out === false) {
					throw new RuntimeException('Could not unpack the release. Is there room on the disk?');
				}

				$left = $entry['size'];
				while ($left > 0) {
					$chunk = fread($handle, min(262144, $left));
					if ($chunk === false || $chunk === '') {
						break;
					}
					fwrite($out, $chunk);
					$left -= strlen($chunk);
				}
				fclose($out);

				if ($left > 0) {
					throw new RuntimeException('The archive ended sooner than it said it would.');
				}

				$written++;
			}
		} finally {
			fclose($handle);
		}

		if ($written === 0) {
			throw new RuntimeException('That archive is empty.');
		}
	}

	/**
	 * The directory inside the staging area that actually holds Pluck.
	 *
	 * GitHub wraps a tarball in a folder named after the repository and the
	 * commit, so what was unpacked is one level down from where it looks.
	 */
	private function rootOf(string $staging): string
	{
		if (is_file($staging . '/index.php')) {
			return $staging;
		}

		$dirs = [];
		foreach (scandir($staging) ?: [] as $entry) {
			if ($entry !== '.' && $entry !== '..' && is_dir($staging . '/' . $entry)) {
				$dirs[] = $staging . '/' . $entry;
			}
		}

		if (count($dirs) === 1 && is_file($dirs[0] . '/index.php')) {
			return $dirs[0];
		}

		throw new RuntimeException('This does not look like a Pluck release: there is no index.php in it.');
	}

	/**
	 * Refuse an archive that is not a Pluck.
	 *
	 * Cheap, and it is the difference between a failed update and a document root
	 * full of somebody else's project.
	 */
	private function check(string $release): void
	{
		foreach (['index.php', 'admin.php', 'src/autoload.php', 'src/Bootstrap.php'] as $expected) {
			if (!is_file($release . '/' . $expected)) {
				throw new RuntimeException(sprintf('This does not look like a Pluck release: %s is missing.', $expected));
			}
		}

		if (!is_dir($release . '/views') || !is_dir($release . '/lang')) {
			throw new RuntimeException('This does not look like a Pluck release: views or lang is missing.');
		}
	}

	// ---- swapping -------------------------------------------------------

	/**
	 * Every file in the release, as paths relative to its root.
	 *
	 * `data/` and `media/` are skipped even when the archive carries them: a
	 * release ships empty placeholders, and copying those over a live site would
	 * be the one mistake nobody recovers from.
	 *
	 * @return list<string>
	 */
	private function filesIn(string $release, string $prefix = '', int $depth = 0): array
	{
		if ($depth > 12) {
			return [];
		}

		$files = [];

		foreach (scandir($release . ($prefix === '' ? '' : '/' . $prefix)) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..' || $entry === '.git') {
				continue;
			}

			$relative = $prefix === '' ? $entry : $prefix . '/' . $entry;

			if ($prefix === '' && in_array($entry, self::KEEP, true)) {
				continue;
			}

			$full = $release . '/' . $relative;

			if (is_link($full)) {
				continue;
			}

			if (is_dir($full)) {
				foreach ($this->filesIn($release, $relative, $depth + 1) as $nested) {
					$files[] = $nested;
				}
				continue;
			}

			$files[] = $relative;
		}

		return $files;
	}

	/**
	 * Copy each file into place, keeping the old one first.
	 *
	 * @param list<string> $files
	 */
	private function swap(string $release, array $files, string $rollback): int
	{
		Path::ensureDir($rollback);
		$replaced = 0;

		foreach ($files as $relative) {
			$target = Path::within($this->rootDir, $relative);

			// The old file goes aside before the new one lands, so a failure in the
			// middle of two hundred files is recoverable rather than a site running
			// half of one version and half of another.
			if (is_file($target)) {
				$kept = Path::within($rollback, $relative);
				Path::ensureDir(dirname($kept));
				if (!@copy($target, $kept)) {
					throw new RuntimeException(sprintf('Could not set aside %s before replacing it.', $relative));
				}
			}

			Path::ensureDir(dirname($target));

			if (!@copy($release . '/' . $relative, $target)) {
				throw new RuntimeException(sprintf('Could not write %s. Is the folder writable by the web server?', $relative));
			}

			@chmod($target, 0o644);
			$replaced++;
		}

		return $replaced;
	}

	/**
	 * Remove files the release no longer has.
	 *
	 * Only inside the directories the release owns outright. A version that drops
	 * a file with a flaw in it has not fixed anything if the file stays on disk —
	 * but sweeping the whole document root would take things that were never ours.
	 *
	 * @param list<string> $files
	 */
	private function removeObsolete(array $files, string $rollback): int
	{
		$keep = array_flip($files);
		$removed = 0;

		foreach (self::OWNED as $owned) {
			$dir = $this->rootDir . '/' . $owned;
			if (!is_dir($dir)) {
				continue;
			}

			foreach ($this->existingUnder($owned) as $relative) {
				if (isset($keep[$relative])) {
					continue;
				}

				$target = Path::within($this->rootDir, $relative);
				$kept = Path::within($rollback, $relative);
				Path::ensureDir(dirname($kept));
				@copy($target, $kept);

				if (@unlink($target)) {
					$removed++;
				}
			}
		}

		return $removed;
	}

	/** @return list<string> */
	private function existingUnder(string $prefix, int $depth = 0): array
	{
		$dir = $this->rootDir . '/' . $prefix;
		if ($depth > 12 || !is_dir($dir)) {
			return [];
		}

		$found = [];
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$relative = $prefix . '/' . $entry;
			$full = $this->rootDir . '/' . $relative;

			if (is_link($full)) {
				continue;
			}

			if (is_dir($full)) {
				foreach ($this->existingUnder($relative, $depth + 1) as $nested) {
					$found[] = $nested;
				}
				continue;
			}

			$found[] = $relative;
		}

		return $found;
	}

	/**
	 * Put back everything that was set aside.
	 *
	 * Best effort by necessity: if this fails too, the backup taken before the
	 * update is what is left, and the screen says so.
	 */
	private function rollBack(): void
	{
		$rollback = $this->stagingDir('rollback');
		if (!is_dir($rollback)) {
			return;
		}

		foreach ($this->allUnder($rollback) as $relative) {
			try {
				$target = Path::within($this->rootDir, $relative);
				Path::ensureDir(dirname($target));
				@copy($rollback . '/' . $relative, $target);
			} catch (Throwable) {
				continue;
			}
		}
	}

	/** @return list<string> */
	private function allUnder(string $dir, string $prefix = '', int $depth = 0): array
	{
		if ($depth > 12) {
			return [];
		}

		$found = [];
		$base = $dir . ($prefix === '' ? '' : '/' . $prefix);

		foreach (scandir($base) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$relative = $prefix === '' ? $entry : $prefix . '/' . $entry;

			if (is_dir($base . '/' . $entry)) {
				foreach ($this->allUnder($dir, $relative, $depth + 1) as $nested) {
					$found[] = $nested;
				}
				continue;
			}

			$found[] = $relative;
		}

		return $found;
	}

	// ---- plumbing -------------------------------------------------------

	private function stagingDir(string $which = 'staging'): string
	{
		return $this->updates->downloadDir() . '/' . $which;
	}

	private function clean(): void
	{
		foreach (['staging', 'rollback'] as $which) {
			$this->removeTree($this->stagingDir($which));
		}
	}

	/**
	 * Delete a directory tree.
	 *
	 * is_link() before is_dir(), always. is_dir() follows a symlink, so a routine
	 * that trusts it walks through the link and deletes what it points at — which
	 * is how this project's own test suite once emptied /etc.
	 */
	private function removeTree(string $dir): void
	{
		if (is_link($dir)) {
			@unlink($dir);

			return;
		}
		if (!is_dir($dir)) {
			return;
		}

		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$path = $dir . '/' . $entry;

			if (is_link($path)) {
				@unlink($path);
				continue;
			}

			is_dir($path) ? $this->removeTree($path) : @unlink($path);
		}

		@rmdir($dir);
	}

	private function currentVersion(): string
	{
		return $this->versionIn($this->rootDir);
	}

	/** Read a version out of composer.json, which is where the release records it. */
	private function versionIn(string $root): string
	{
		$file = $root . '/composer.json';
		if (!is_file($file)) {
			return '';
		}

		$data = json_decode((string) file_get_contents($file), true);

		return is_array($data) && isset($data['version']) && is_string($data['version']) ? $data['version'] : '';
	}
}
