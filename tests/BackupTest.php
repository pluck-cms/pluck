<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Backup\BackupManager;
use Pluck\Backup\ScheduledBackup;
use Pluck\Backup\Tar;
use Pluck\Storage\DriverFactory;
use Pluck\Storage\StorageDriver;

/**
 * Backups, and putting one back.
 *
 * The half that matters is restore. Reading an archive is reading a list of
 * paths somebody else wrote, which is the same shape as unpacking an upload —
 * and the 4.x backup module got the equivalent wrong twice over, once by writing
 * archives where the web server would hand them out and once by unlinking
 * whatever `$_GET['delfile']` named.
 */
final class BackupTest extends TestCase
{
	public function run(): void
	{
		$this->group('the tar format', fn () => $this->tar());
		$this->group('making one', fn () => $this->making());
		$this->group('putting one back', fn () => $this->restoring());
		$this->group('a hostile archive', fn () => $this->hostile());
		$this->group('names that are not backups', fn () => $this->names());
		$this->group('keeping the folder from filling up', fn () => $this->pruning());
		$this->group('the automatic one', fn () => $this->scheduled());
	}

	private function tar(): void
	{
		$path = $this->tempDir('pluck-tar') . '/a.tar';

		$handle = fopen($path, 'wb');
		$long = 'media/' . str_repeat('deep/', 18) . 'photo.jpg';

		foreach ([['data/a.txt', 'hallo'], [$long, 'diep'], ['data/empty', '']] as [$name, $content]) {
			fwrite($handle, Tar::header($name, strlen($content), 1700000000));
			fwrite($handle, $content . Tar::pad(strlen($content)));
		}
		fwrite($handle, Tar::end());
		fclose($handle);

		$this->assertSame(0, filesize($path) % 512, 'a tar is a whole number of 512-byte blocks');

		$handle = fopen($path, 'rb');
		$found = [];
		foreach (Tar::entries($handle) as $entry) {
			// Reading the contents inside the loop is the normal thing to do, and
			// it moves the file pointer — the reader has to survive that.
			fseek($handle, $entry['offset']);
			$found[$entry['path']] = $entry['size'] > 0 ? (string) fread($handle, $entry['size']) : '';
		}
		fclose($handle);

		$this->assertSame(['data/a.txt', $long, 'data/empty'], array_keys($found), 'every entry is read back, in order');
		$this->assertSame('hallo', $found['data/a.txt'], 'with its contents');
		$this->assertSame('diep', $found[$long], 'including a path too long for the 100-character name field');
		$this->assertSame('', $found['data/empty'], 'and an empty file is an entry, not an absence');

		// A single filename longer than the name field cannot be split at a slash,
		// and failing loudly beats writing a truncated name that restores into the
		// wrong place.
		$this->expectFailure(
			static fn () => Tar::header('data/' . str_repeat('x', 120), 0, 0),
			'a name that cannot be represented is refused rather than truncated',
		);
	}

	private function making(): void
	{
		[$manager, $root] = $this->site();

		$backup = $manager->create('manual');

		$this->assertTrue($backup->bytes > 0, 'an archive is written');
		$this->assertTrue($backup->isReadable(), 'and carries a manifest');
		$this->assertSame('manual', $backup->reason(), 'which says why it was made');
		// Counted rather than written down: install() puts files in data/ of its
		// own, so a hardcoded number tests the fixture instead of the backup.
		$expected = $this->countFiles($root);
		$this->assertSame($expected, $backup->fileCount(), 'and how much went in');

		$this->assertTrue(
			str_starts_with($backup->name, 'pluck-'),
			'the name says what it is',
		);
		$this->assertSame(
			1,
			preg_match('/-[0-9a-f]{8}\.tar(\.gz)?$/', $backup->name),
			'and carries random characters, so a name cannot be guessed from the date alone',
		);

		$this->assertTrue(
			is_dir($root . '/data/' . BackupManager::DIRECTORY),
			'archives live under data/, which the web server does not serve',
		);

		$inside = $manager->inspect($backup->name);
		$this->assertSame($expected, $inside['files'], 'the archive really holds what the manifest claims');

		// A backup of the backups doubles in size every time.
		$second = $manager->create('manual');
		$this->assertSame($expected, $second->fileCount(), 'a later backup does not contain the earlier ones');

		// The lock is written before the files are gathered, so it would otherwise
		// travel inside every archive and be restored as a live lock.
		$this->assertFalse(
			in_array('data/backup.lock', array_keys($this->pathsIn($manager, $second->name)), true),
			'and does not contain the lock that was held while it ran',
		);
	}

	private function restoring(): void
	{
		[$manager, $root, $storage] = $this->site();

		$backup = $manager->create('manual');
		$expectedAtBackup = $backup->fileCount();

		file_put_contents($root . '/media/photo.jpg', 'RUINED');
		unlink($root . '/data/settings/site.json');
		file_put_contents($root . '/media/added-later.jpg', 'new');

		$result = $manager->restore($backup->name);

		$this->assertSame($expectedAtBackup, $result['restored'], 'everything in the archive is put back');
		$this->assertSame([], $result['skipped'], 'with nothing refused');
		$this->assertTrue(is_string($result['safety']), 'and a safety copy is made first');

		$this->assertTrue(is_file($root . '/data/settings/site.json'), 'a deleted file comes back');
		$this->assertSame('photo', file_get_contents($root . '/media/photo.jpg'), 'and a changed one is put right');

		// Deleting what is not in the archive would be defensible and would also
		// mean restoring a partial backup throws away everything added since.
		$this->assertTrue(
			is_file($root . '/media/added-later.jpg'),
			'a file added after the backup is left alone rather than deleted',
		);

		// The safety copy is a real backup of what was there a moment ago.
		$safety = $manager->describe((string) $result['safety']);
		$this->assertSame('before restore', $safety->reason(), 'the safety copy says what it is');

		// --- an older schema is brought up to date ---
		// Restoring an old archive otherwise leaves the store at a shape the code
		// no longer expects: an SQLite install from before per-user languages comes
		// back without that column, and the next query fails on a site that looked
		// as though it had restored cleanly.
		$settled = false;
		$storage->setSetting('schema_version', 1);
		$older = $manager->create('manual');
		$storage->setSetting('schema_version', 2);

		$manager->restore($older->name, safetyCopy: false, settle: static function () use (&$settled): void {
			$settled = true;
		});

		$this->assertTrue($settled, 'a restore asks the caller to bring the store up to date');

		// Read through a *fresh* driver. The one this test has been holding cached
		// its settings in memory and still reports the value from before the
		// restore — which is exactly why the settler opens a new driver rather
		// than reusing the request's own.
		$reopened = DriverFactory::make(DriverFactory::FLAT_FILE, $root . '/data');
		$this->assertSame(
			1,
			(int) $reopened->getSetting('schema_version', 0),
			'and the archive really did carry the older schema, so there was something to settle',
		);

		// An archive from a newer Pluck is refused: it may hold data this version
		// cannot read, and restoring it would be a silent downgrade of the content.
		$fromFuture = new BackupManager($root . '/data', $root . '/media', '4.0.0');
		$this->expectFailure(
			static fn () => $fromFuture->restore($backup->name),
			'a backup from a newer Pluck is refused rather than half-understood',
		);

		// The refusal has to say what to do, because "update first" is the way
		// round that works: the schema step handles an archive older than the
		// code, never one newer than it.
		try {
			$fromFuture->restore($backup->name);
			$message = '';
		} catch (\Throwable $e) {
			$message = $e->getMessage();
		}

		$this->assertTrue(str_contains($message, 'Update Pluck'), 'and says what to do about it');
	}

	private function hostile(): void
	{
		[$manager, $root] = $this->site();

		// An archive is a list of paths somebody else chose. These are the ones
		// they choose.
		$path = $manager->backupDir() . '/pluck-20260101-000000-deadbeef.tar';
		\Pluck\Support\Path::ensureDir($manager->backupDir());

		$handle = fopen($path, 'wb');
		$manifest = '{"pluck":"5.0.0-dev","reason":"manual","files":0}';
		fwrite($handle, Tar::header(BackupManager::MANIFEST, strlen($manifest), time()));
		fwrite($handle, $manifest . Tar::pad(strlen($manifest)));

		foreach ([
			'data/../../escaped.txt' => 'up and out',
			'../outside.txt' => 'straight out',
			'etc/passwd' => 'not ours at all',
			'data/backups/older.tar' => 'a backup inside a backup',
			'data/settings/fine.json' => '{"ok":true}',
		] as $name => $content) {
			fwrite($handle, Tar::header($name, strlen($content), time()));
			fwrite($handle, $content . Tar::pad(strlen($content)));
		}
		fwrite($handle, Tar::end());
		fclose($handle);

		$result = $manager->restore('pluck-20260101-000000-deadbeef.tar', safetyCopy: false);

		$this->assertSame(1, $result['restored'], 'only the one legitimate entry is written');
		$this->assertSame(4, count($result['skipped']), 'and the other four are reported, not silently dropped');

		$this->assertFalse(is_file(dirname($root) . '/escaped.txt'), 'a path climbing out of data/ writes nothing');
		$this->assertFalse(is_file(dirname($root) . '/outside.txt'), 'nor does one climbing out of the install');
		$this->assertFalse(is_file($root . '/etc/passwd'), 'nor one naming a folder Pluck does not own');
		$this->assertFalse(
			is_file($manager->backupDir() . '/older.tar'),
			'and an archive inside an archive is not unpacked into the backup folder',
		);

		$this->assertTrue(is_file($root . '/data/settings/fine.json'), 'while the ordinary entry is restored');
	}

	private function names(): void
	{
		[$manager] = $this->site();

		// The 4.x module unlinked whatever the query string named. A name is
		// rebuilt and matched against the pattern instead.
		foreach ([
			'../../data/settings/config.php',
			'/etc/passwd',
			'pluck-anything.tar',
			'pluck-20260101-000000-nothex!.tar',
			'config.php',
		] as $name) {
			$this->expectFailure(
				static fn () => $manager->pathOf($name),
				'refuses a name that is not a backup: ' . $name,
			);
			$this->assertFalse($manager->delete($name), 'and will not delete it either');
		}
	}

	private function pruning(): void
	{
		[$manager] = $this->site();

		for ($i = 0; $i < 4; $i++) {
			$manager->create('manual');
		}

		$this->assertSame(4, count($manager->all()), 'four archives exist');
		$this->assertSame(2, $manager->prune(2), 'pruning to two removes the two oldest');
		$this->assertSame(2, count($manager->all()), 'leaving two');

		// Disk is what shared hosting runs out of; keeping nothing at all is worse.
		$this->assertSame(1, $manager->prune(0), 'asking to keep none keeps one anyway');
	}

	private function scheduled(): void
	{
		[$manager, $root, $storage] = $this->site();

		$scheduled = new ScheduledBackup($manager, $storage);

		$storage->setSetting('backup_interval_days', 0);
		$this->assertFalse($scheduled->isDue(), 'zero days switches the automatic backup off');

		$storage->setSetting('backup_interval_days', 7);
		$this->assertTrue($scheduled->isDue(), 'with no backup at all, one is due');

		$manager->create('scheduled');
		$storage->setSetting(ScheduledBackup::LAST_ATTEMPT, 0);
		$this->assertFalse($scheduled->isDue(), 'and not again straight after one was made');

		// A site whose backups keep failing must not try on every single sign-in.
		$storage->setSetting(ScheduledBackup::LAST_ATTEMPT, time());
		foreach ($manager->all() as $existing) {
			$manager->delete($existing->name);
		}
		$this->assertFalse(
			$scheduled->isDue(),
			'a recent attempt holds it off even when there is no backup to show for it',
		);
	}

	// ---- fixture --------------------------------------------------------

	/**
	 * Files that belong in a backup, counted the way the manager counts them.
	 *
	 * The backup folder and the lock are excluded here for the same reason they
	 * are excluded there.
	 */
	private function countFiles(string $root): int
	{
		$count = 0;

		foreach ([$root . '/data', $root . '/media'] as $dir) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			);

			foreach ($iterator as $file) {
				$path = str_replace('\\', '/', $file->getPathname());

				if (str_contains($path, '/' . BackupManager::DIRECTORY . '/') || str_ends_with($path, '/backup.lock')) {
					continue;
				}

				if ($file->isFile() && !$file->isLink()) {
					$count++;
				}
			}
		}

		return $count;
	}

	/** @return array<string,int> archive path => size */
	private function pathsIn(BackupManager $manager, string $name): array
	{
		$paths = [];
		$handle = fopen($manager->pathOf($name), 'rb');

		// gz on most builds; the fixture is small enough to read either way.
		if (str_ends_with($name, '.gz')) {
			fclose($handle);
			$handle = gzopen($manager->pathOf($name), 'rb');
		}

		foreach (Tar::entries($handle) as $entry) {
			$paths[$entry['path']] = $entry['size'];
		}
		fclose($handle);

		return $paths;
	}

	/** @return array{0:BackupManager,1:string,2:StorageDriver} */
	private function site(): array
	{
		$root = $this->tempDir('pluck-backup');

		mkdir($root . '/data/settings', 0o755, true);
		mkdir($root . '/media', 0o755, true);

		file_put_contents($root . '/data/settings/site.json', '{"title":"Test"}');
		file_put_contents($root . '/data/settings/users.json', '[]');
		file_put_contents($root . '/media/photo.jpg', 'photo');

		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $root . '/data');
		$storage->install();

		return [new BackupManager($root . '/data', $root . '/media', '5.0.0-dev'), $root, $storage];
	}
}
