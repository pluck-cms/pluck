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

			/*
			 * Everything the release will write, before anything is written.
			 *
			 * Half an update is the worst outcome. The rollback works — it has now
			 * been through a real failure on a real server — but it is a recovery,
			 * and a recovery that could have been a refusal is a bad trade.
			 *
			 * The case that found this: an install unpacked by hand as root, so
			 * some files belonged to root and the web server could not replace
			 * them. Nothing about that is visible until the write fails.
			 */
			$blocked = self::unwritable($this->rootDir, $files);

			if ($blocked !== []) {
				throw new RuntimeException(self::explainUnwritable($blocked, $this->rootDir));
			}

			$rollback = $this->stagingDir('rollback');

			$replaced = $this->swap($release, $files, $rollback);
			$removed = $this->removeObsolete($files, $rollback);

			/*
			 * Throw away every compiled file, because 251 of them just changed.
			 *
			 * OPcache holds the compiled form of each .php and, by default, only
			 * asks whether a file changed every couple of seconds. Immediately
			 * after an update that means old bytecode running against new files —
			 * and not evenly: some classes already reloaded, some not, in whatever
			 * combination the timing produced.
			 *
			 * What it looked like on the first real update was harmless and
			 * confusing: "Bijgewerkt naar rc40" above "Je draait rc39", because
			 * Bootstrap::VERSION was still the compiled old constant. What it
			 * could look like is a new class calling a method an old one does not
			 * have yet.
			 *
			 * A reset rather than invalidating each file: this is the one moment
			 * where throwing the whole cache away is proportionate, and a list of
			 * paths to invalidate is a list that can be incomplete.
			 */
			if (function_exists('opcache_reset')) {
				@opcache_reset();
			}

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

	/**
	 * Which of these the web server cannot replace.
	 *
	 * A file that exists has to be writable; one that does not has to have a
	 * writable directory to be created in, and that directory may not exist yet
	 * either — so the check walks up until it finds something that does.
	 *
	 * @param list<string> $files paths relative to the install
	 * @return list<string>
	 */
	public static function unwritable(string $rootDir, array $files): array
	{
		$blocked = [];

		foreach ($files as $relative) {
			$target = $rootDir . '/' . $relative;

			if (file_exists($target)) {
				if (!is_writable($target)) {
					$blocked[] = $relative;
				}

				continue;
			}

			$dir = dirname($target);

			while (!file_exists($dir) && strlen($dir) > strlen($rootDir)) {
				$dir = dirname($dir);
			}

			if (!is_writable($dir)) {
				$blocked[] = $relative;
			}
		}

		return $blocked;
	}

	/**
	 * What to do about it, which depends on why.
	 *
	 * Two situations look identical in the error and want opposite answers.
	 *
	 * A file whose directory is *also* unwritable is an ownership accident: an
	 * install unpacked by hand as somebody else. Handing it back to the account
	 * the site runs as is right.
	 *
	 * A file in a directory that *is* writable is the ordinary shared-hosting
	 * layout: PHP runs as one account, the files belong to another, directories
	 * are group-writable and files are not. Telling somebody to chown everything
	 * to the web server there is bad advice — it takes their own files away from
	 * them, and they will notice the next time they open FTP. The fix belongs on
	 * the server: run PHP as the account that owns the site.
	 *
	 * Names a handful and counts the rest: four hundred paths is not a message.
	 *
	 * @param list<string> $blocked
	 */
	public static function explainUnwritable(array $blocked, string $rootDir = ''): string
	{
		$shown = array_slice($blocked, 0, 5);
		$rest = count($blocked) - count($shown);

		// If the directories take writes, the owner is not the problem.
		$directoriesTakeWrites = $rootDir !== '' && is_writable($rootDir);

		$advice = $directoriesTakeWrites
			? 'The folders can be written but the files cannot, which means PHP runs as one account '
				. 'and the files belong to another. Chowning everything to the web server would take the '
				. 'files away from you — the fix belongs on the server: run PHP as the account that owns '
				. 'the site (a PHP-FPM pool of its own), or make the files group-writable with chmod -R g+w .'
			: 'This is almost always an install unpacked by hand as another user — give the files back to '
				. 'the account the site runs as, for example: chown -R <web user> .';

		return sprintf(
			'%d file%s cannot be replaced: %s%s. Nothing has been changed. %s',
			count($blocked),
			count($blocked) === 1 ? '' : 's',
			implode(', ', $shown),
			$rest > 0 ? sprintf(' and %d more', $rest) : '',
			$advice,
		);
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

		// The same reason as after a successful swap, and more pressing: a
		// rollback puts old files back under whatever bytecode the half-finished
		// update had already compiled.
		if (function_exists('opcache_reset')) {
			@opcache_reset();
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
