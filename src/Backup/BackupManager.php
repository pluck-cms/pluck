<?php
declare(strict_types=1);

namespace Pluck\Backup;

use Pluck\Bootstrap;

use Pluck\Archive\ArchiveStore;
use Pluck\Support\Path;
use RuntimeException;
use Throwable;

/**
 * Backups of everything a Pluck site cannot get back by downloading it again.
 *
 * That is `data/` and `media/`, and deliberately not the code: a release is
 * re-downloadable, it is the largest part of the install, and restoring it would
 * quietly roll the software back to whatever version the backup was made with.
 *
 * ## Where the archives live, and why that is not a mistake
 *
 * In `data/backups/`. The 4.x backup module wrote to the same sort of place and
 * that turned out to be a hole — but the hole was that a stock 4.7 shipped no
 * `.htaccess` under `data/`, so the archives were fetchable over HTTP by anyone
 * who guessed a name built from the hour and the minute.
 *
 * Pluck 5 denies the whole of `data/` on Apache and in the nginx rules, and the
 * test bed checks that on six servers. On top of that: every filename carries
 * eight random hex characters, so a server that ignores those rules still does
 * not hand the archive to somebody who merely knows what day it was made.
 *
 * Downloads go through an authenticated admin route that streams the file. There
 * is never a link to the archive itself.
 */
final class BackupManager
{
	public const DIRECTORY = 'backups';

	public const MANIFEST = 'pluck-backup.json';

	/** Refuse to start a second backup while one is running. */
	private const LOCK = 'backup.lock';

	/** A lock older than this is assumed to be from a request that died. */
	private const LOCK_STALE_SECONDS = 900;

	public function __construct(
		private readonly string $dataDir,
		private readonly string $mediaDir,
		private readonly string $version = Bootstrap::VERSION,
	) {
	}

	// ---- making one -----------------------------------------------------

	/**
	 * Write a backup and return what was written.
	 *
	 * @param string $reason free text recorded in the manifest — "manual",
	 *        "before update", "scheduled" — so a list of archives says why each
	 *        one exists
	 * @throws RuntimeException when another backup is already running, or the
	 *         archive cannot be written
	 */
	public function create(string $reason = 'manual'): Backup
	{
		$this->lock();

		try {
			Path::ensureDir($this->backupDir());

			$name = sprintf('pluck-%s-%s.tar%s', gmdate('Ymd-His'), bin2hex(random_bytes(4)), $this->gzip() ? '.gz' : '');
			$target = Path::within($this->backupDir(), $name);

			// Written beside the target and renamed, so a backup that is
			// interrupted never appears in the list as a complete one.
			$temporary = $target . '.part';

			$handle = fopen($temporary, 'wb');
			if ($handle === false) {
				throw new RuntimeException('Could not write to the backup folder.');
			}

			try {
				$files = $this->collect();
				$this->write($handle, $files, $reason);
			} finally {
				fclose($handle);
			}

			if ($this->gzip()) {
				$this->compress($temporary);
			}

			if (!rename($temporary, $target)) {
				@unlink($temporary);
				throw new RuntimeException('Could not finish writing the backup.');
			}

			@chmod($target, 0o600);

			return $this->describe($name);
		} finally {
			$this->unlock();
		}
	}

	/**
	 * @param resource $handle
	 * @param list<array{path:string,full:string}> $files
	 */
	private function write($handle, array $files, string $reason): void
	{
		$manifest = json_encode([
			'pluck' => $this->version,
			'created' => gmdate('c'),
			'reason' => $reason,
			'files' => count($files),
			'php' => PHP_VERSION,
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

		// The manifest goes first, so a reader knows what it is holding before it
		// has read a hundred megabytes of photographs.
		fwrite($handle, Tar::header(self::MANIFEST, strlen($manifest), time()));
		fwrite($handle, $manifest . Tar::pad(strlen($manifest)));

		foreach ($files as $file) {
			$size = (int) (filesize($file['full']) ?: 0);

			$source = fopen($file['full'], 'rb');
			if ($source === false) {
				// A file that cannot be read is reported by its absence rather than
				// aborting the whole backup — one unreadable file should not cost
				// somebody their only copy of everything else.
				continue;
			}

			fwrite($handle, Tar::header($file['path'], $size, (int) (filemtime($file['full']) ?: time())));

			$written = 0;
			while (!feof($source) && $written < $size) {
				$chunk = fread($source, 262144);
				if ($chunk === false || $chunk === '') {
					break;
				}
				// Never write more than the header promised: a file being appended
				// to while the backup runs would otherwise push every later header
				// out of alignment.
				$chunk = substr($chunk, 0, $size - $written);
				fwrite($handle, $chunk);
				$written += strlen($chunk);
			}
			fclose($source);

			// If the file shrank mid-read, pad to the size the header declared.
			if ($written < $size) {
				fwrite($handle, str_repeat("\0", $size - $written));
			}

			fwrite($handle, Tar::pad($size));
		}

		fwrite($handle, Tar::end());
	}

	/**
	 * Everything that goes in, as archive path => path on disk.
	 *
	 * Backups are excluded from backups. Including them turns every backup into a
	 * copy of all the previous ones, and the second one is already twice the size
	 * of the first.
	 *
	 * @return list<array{path:string,full:string}>
	 */
	/** Sessions and anything else transient. Rebuilt on demand, never restored. */
	private const CACHE = 'cache';

	private function collect(): array
	{
		$files = [];

		/*
		 * The lock is excluded as well as the backup folder: it is written before
		 * the files are collected, so it would travel inside every archive — and a
		 * restore would then put a lock file back and block backups for a quarter
		 * of an hour for no reason anybody could see.
		 *
		 * The cache goes too, and sessions are the reason. They are somebody's
		 * signed-in state, they are worthless an hour later, and on shared hosting
		 * they are the files most likely to belong to a different account than the
		 * one running the backup — which filled a server's error log with
		 * permission warnings for files that had no business being in an archive.
		 */
		$this->walk($this->dataDir, 'data', $files, [self::DIRECTORY, self::LOCK, self::CACHE]);
		$this->walk($this->mediaDir, 'media', $files, []);

		return $files;
	}

	/**
	 * @param list<array{path:string,full:string}> $files
	 * @param list<string> $skip directory names to leave out, at any depth
	 */
	private function walk(string $dir, string $prefix, array &$files, array $skip, int $depth = 0): void
	{
		if ($depth > 12 || !is_dir($dir)) {
			return;
		}

		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..' || in_array($entry, $skip, true)) {
				continue;
			}

			$full = $dir . '/' . $entry;

			// Never follow a link out of the tree. A backup that dereferences a
			// symlink is a backup that can be made to copy anything the web server
			// can read.
			if (is_link($full)) {
				continue;
			}

			if (is_dir($full)) {
				$this->walk($full, $prefix . '/' . $entry, $files, $skip, $depth + 1);
				continue;
			}

			if (is_file($full)) {
				$files[] = ['path' => $prefix . '/' . $entry, 'full' => $full];
			}
		}
	}

	// ---- looking at them ------------------------------------------------

	/** @return list<Backup> newest first */
	public function all(): array
	{
		// The store says which names are backups; describing one is this class's
		// own business, because a backup and a download describe themselves
		// differently and that is the part that genuinely belongs to each.
		$backups = array_map(
			fn (string $name): Backup => $this->describe($name),
			$this->store()->names(),
		);

		usort($backups, static fn (Backup $a, Backup $b): int => $b->createdAt <=> $a->createdAt);

		return $backups;
	}

	public function newest(): ?Backup
	{
		return $this->all()[0] ?? null;
	}

	public function describe(string $name): Backup
	{
		$path = $this->pathOf($name);

		return new Backup(
			name: $name,
			bytes: (int) (filesize($path) ?: 0),
			createdAt: (int) (filemtime($path) ?: 0),
			manifest: $this->manifestOf($path),
		);
	}

	/**
	 * The full path of a backup, refusing anything that is not one.
	 *
	 * Rebuilt from a basename and checked against the naming pattern, so a name
	 * arriving from a form cannot address a file elsewhere — the delete route in
	 * the 4.x module took `$_GET['delfile']` and unlinked it unchecked.
	 */
	/**
	 * The folder of backups, and the rules for naming one.
	 *
	 * Shared with the updater's download folder — see Archive\ArchiveStore. They
	 * had four identical methods each, which is how a rule ends up fixed in one
	 * place and not the other.
	 */
	private function store(): ArchiveStore
	{
		return new ArchiveStore(
			$this->backupDir(),
			'/^pluck-\d{8}-\d{6}-[0-9a-f]{8}\.tar(\.gz)?$/',
			'That is not the name of a backup.',
		);
	}

	public function pathOf(string $name): string
	{
		return $this->store()->pathOf($name);
	}

	public function exists(string $name): bool
	{
		return $this->store()->exists($name);
	}

	public function delete(string $name): bool
	{
		return $this->store()->delete($name);
	}

	/**
	 * Keep the newest $keep archives and remove the rest.
	 *
	 * Disk is the thing shared hosting runs out of, and a backup that fills the
	 * account is a backup that stops the site.
	 *
	 * @return int how many were removed
	 */
	public function prune(int $keep): int
	{
		$keep = max(1, $keep);
		$removed = 0;

		foreach (array_slice($this->all(), $keep) as $old) {
			if ($this->delete($old->name)) {
				$removed++;
			}
		}

		return $removed;
	}

	// ---- reading one back -----------------------------------------------

	/**
	 * What is inside an archive, without unpacking it.
	 *
	 * @return array{manifest:array<string,mixed>,files:int,bytes:int}
	 */
	public function inspect(string $name): array
	{
		$path = $this->pathOf($name);
		$handle = $this->open($path);

		try {
			$files = 0;
			$bytes = 0;
			foreach (Tar::entries($handle) as $entry) {
				if ($entry['path'] === self::MANIFEST) {
					continue;
				}
				$files++;
				$bytes += $entry['size'];
			}
		} finally {
			fclose($handle);
		}

		return ['manifest' => $this->manifestOf($path), 'files' => $files, 'bytes' => $bytes];
	}

	/**
	 * Put an archive back.
	 *
	 * The dangerous one, so it is deliberately narrow:
	 *
	 * - A safety copy is taken first, of what is about to be overwritten. Restore
	 *   is the one action nobody can undo by hand, so the undo is made for them.
	 * - Only paths under `data/` and `media/` are written, resolved and checked
	 *   against the folder they claim to be in. An archive is untrusted input in
	 *   exactly the way an upload is.
	 * - Files present now but absent from the archive are left alone. Deleting
	 *   them would be defensible, and it would also mean a restore from a partial
	 *   backup silently throws away everything added since.
	 * - Whatever the archive was written by, the data is brought up to the
	 *   current schema afterwards. Restoring an old backup otherwise leaves the
	 *   store at an older shape than the code expects — an SQLite install from
	 *   before per-user languages comes back without that column, and the next
	 *   query fails on a site that looked as though it had been restored fine.
	 *
	 * @param callable():void|null $settle brings the restored store up to date.
	 *        Supplied by the caller because it means re-reading config.php and
	 *        opening a driver, and the archive may have replaced config.php with
	 *        one naming a different driver.
	 * @return array{restored:int,skipped:list<string>,safety:?string,settled:bool}
	 */
	public function restore(string $name, bool $safetyCopy = true, ?callable $settle = null): array
	{
		$path = $this->pathOf($name);

		$manifest = $this->manifestOf($path);
		$from = (string) ($manifest['pluck'] ?? '');
		if ($from !== '' && version_compare($from, $this->version, '>')) {
			// Refused rather than attempted: a newer Pluck may have written data in
			// a shape this version cannot read, and a half-understood restore is
			// worse than none. Updating first and restoring afterwards works,
			// because the schema step below handles an archive that is older than
			// the code — never the other way round.
			throw new RuntimeException(sprintf(
				'This backup was made by Pluck %s and this is Pluck %s. Update Pluck to %s or newer first, '
				. 'then restore this backup — that way round works, this one does not.',
				$from,
				$this->version,
				$from,
			));
		}

		$safety = null;
		if ($safetyCopy) {
			$safety = $this->create('before restore')->name;
		}

		$handle = $this->open($path);
		$restored = 0;
		$skipped = [];

		try {
			foreach (Tar::entries($handle) as $entry) {
				if ($entry['path'] === self::MANIFEST || $entry['mode'] === 0) {
					continue;
				}

				$target = $this->targetFor($entry['path']);
				if ($target === null) {
					$skipped[] = $entry['path'];
					continue;
				}

				// Never restore a backup into the backup folder: an older archive
				// would reappear as a current one, and its own copies with it.
				if (str_starts_with($entry['path'], 'data/' . self::DIRECTORY . '/')) {
					$skipped[] = $entry['path'];
					continue;
				}

				Path::ensureDir(dirname($target));

				if (!$this->extract($handle, $entry, $target)) {
					$skipped[] = $entry['path'];
					continue;
				}

				$restored++;
			}
		} finally {
			fclose($handle);
		}

		$settled = false;
		if ($settle !== null && $restored > 0) {
			try {
				$settle();
				$settled = true;
			} catch (Throwable $e) {
				// The files are back either way; only the schema step failed. Saying
				// so beats pretending the restore was clean, because the next page
				// the person opens is where they would find out.
				error_log('Pluck: restored, but the schema could not be brought up to date: ' . $e->getMessage());
			}
		}

		return ['restored' => $restored, 'skipped' => $skipped, 'safety' => $safety, 'settled' => $settled];
	}

	/**
	 * Where an archived path is allowed to land, or null if nowhere.
	 *
	 * The check that matters. A tar entry is a string an attacker may have
	 * chosen: `../../etc/passwd`, an absolute path, a name with a null byte in
	 * it. Path::within refuses all of those, and only the two known prefixes are
	 * accepted in the first place.
	 */
	private function targetFor(string $path): ?string
	{
		$path = str_replace('\\', '/', $path);

		if (str_contains($path, "\0") || str_starts_with($path, '/')) {
			return null;
		}

		foreach (['data/' => $this->dataDir, 'media/' => $this->mediaDir] as $prefix => $base) {
			if (!str_starts_with($path, $prefix)) {
				continue;
			}

			try {
				return Path::within($base, substr($path, strlen($prefix)));
			} catch (Throwable) {
				return null;
			}
		}

		return null;
	}

	/**
	 * @param resource $handle
	 * @param array{size:int,offset:int} $entry
	 */
	private function extract($handle, array $entry, string $target): bool
	{
		if (fseek($handle, $entry['offset']) !== 0) {
			return false;
		}

		$out = fopen($target . '.part', 'wb');
		if ($out === false) {
			return false;
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

		if ($left > 0 || !rename($target . '.part', $target)) {
			@unlink($target . '.part');

			return false;
		}

		return true;
	}

	// ---- plumbing -------------------------------------------------------

	public function backupDir(): string
	{
		return $this->dataDir . '/' . self::DIRECTORY;
	}

	private function gzip(): bool
	{
		return function_exists('gzencode');
	}

	/**
	 * Compress in place.
	 *
	 * Read whole and written whole: gzencode has no streaming form, and a backup
	 * large enough for that to matter is one that should not be made in a web
	 * request at all. If it fails the plain tar is kept, which is still a valid
	 * archive.
	 */
	private function compress(string $path): void
	{
		$plain = @file_get_contents($path);
		if ($plain === false) {
			return;
		}

		$gz = @gzencode($plain, 6);
		if ($gz === false || $gz === '') {
			return;
		}

		@file_put_contents($path, $gz);
	}

	/**
	 * Open an archive for reading, transparently decompressing.
	 *
	 * @return resource
	 */
	private function open(string $path)
	{
		$handle = str_ends_with($path, '.gz')
			? @gzopen($path, 'rb')
			: @fopen($path, 'rb');

		if ($handle === false) {
			throw new RuntimeException('Could not read that backup.');
		}

		return $handle;
	}

	/** @return array<string,mixed> */
	private function manifestOf(string $path): array
	{
		if (!is_file($path)) {
			return [];
		}

		try {
			$handle = $this->open($path);
		} catch (Throwable) {
			return [];
		}

		try {
			foreach (Tar::entries($handle) as $entry) {
				if ($entry['path'] !== self::MANIFEST) {
					continue;
				}
				fseek($handle, $entry['offset']);
				$json = (string) fread($handle, max(1, $entry['size']));
				$decoded = json_decode($json, true);

				return is_array($decoded) ? $decoded : [];
			}
		} catch (Throwable) {
			// A damaged archive still appears in the list, without a manifest, so
			// somebody can see it and delete it.
			return [];
		} finally {
			fclose($handle);
		}

		return [];
	}

	private function lock(): void
	{
		$lock = $this->dataDir . '/' . self::LOCK;

		if (is_file($lock)) {
			$age = time() - (int) (filemtime($lock) ?: 0);
			if ($age < self::LOCK_STALE_SECONDS) {
				throw new RuntimeException('A backup is already running.');
			}
			// Older than that and the request that made it is gone.
			@unlink($lock);
		}

		Path::ensureDir($this->dataDir);
		@file_put_contents($lock, (string) time());
	}

	private function unlock(): void
	{
		@unlink($this->dataDir . '/' . self::LOCK);
	}
}
