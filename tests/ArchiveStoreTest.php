<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Archive\ArchiveStore;
use Throwable;

/**
 * A folder of archives, shared by backups and downloads.
 *
 * They had four identical methods each — pathOf, exists, delete, and a listing
 * that differed only in what it built from the names. Identical is how it
 * started; a rule kept in two places is one that gets fixed in one of them.
 *
 * The refactor found a fault that had been there the whole time: the backup
 * listing accepted any name starting with "pluck-" and ending in .tar or
 * .tar.gz, while pathOf accepted a much narrower shape — and the listing called
 * describe(), which calls pathOf(). One stray file in data/backups therefore
 * threw on the backups page. Now one pattern answers both.
 */
final class ArchiveStoreTest extends TestCase
{
	private const PATTERN = '/^pluck-\d{8}-\d{6}-[0-9a-f]{8}\.tar(\.gz)?$/';

	public function run(): void
	{
		$this->group('what a name may be', fn () => $this->names());
		$this->group('what a name may not be', fn () => $this->refusals());
		$this->group('listing and deleting', fn () => $this->listing());
	}

	private function names(): void
	{
		$store = $this->store($dir);

		$good = 'pluck-20260809-143804-320fe32d.tar.gz';
		touch($dir . '/' . $good);

		/*
		 * Both sides resolved before comparing.
		 *
		 * pathOf() resolves the path, and on macOS the system temp directory is
		 * /var/folders/... where /var is a symlink to /private/var — so the
		 * expected string and the real one differ by a prefix nobody typed. The
		 * test failed on every Mac and passed everywhere else, which is the
		 * worst kind of red: it says nothing about the code.
		 */
		$this->assertSame(
			realpath($dir . '/' . $good),
			realpath((string) $store->pathOf($good)),
			'a real name resolves',
		);
		$this->assertTrue($store->exists($good), 'and exists');

		// The uncompressed form, for a server without zlib.
		$plain = 'pluck-20260809-143804-320fe32d.tar';
		touch($dir . '/' . $plain);
		$this->assertTrue($store->exists($plain), 'the uncompressed form is a name too');
	}

	private function refusals(): void
	{
		$store = $this->store($dir);

		foreach ([
			'../../index.php' => 'a traversal',
			'/etc/passwd' => 'an absolute path',
			'pluck-rommel.tar.gz' => 'the right prefix and the wrong shape',
			'anything.tar.gz' => 'somebody else\'s archive',
			'pluck-20260809-143804-320fe32d.tar.gz.bak' => 'a name with something after it',
			'' => 'nothing at all',
		] as $name => $what) {
			$threw = false;

			try {
				$store->pathOf($name);
			} catch (Throwable) {
				$threw = true;
			}

			$this->assertTrue($threw, 'refused: ' . $what);

			// exists() answers rather than throwing: asking whether a file is
			// there should not be something a caller has to catch.
			$this->assertFalse($store->exists($name), 'and reports it absent: ' . $what);
		}
	}

	private function listing(): void
	{
		$store = $this->store($dir);

		$good = 'pluck-20260809-143804-320fe32d.tar.gz';
		$older = 'pluck-20260808-101010-aabbccdd.tar.gz';

		foreach ([$good, $older, 'pluck-rommel.tar.gz', 'notes.txt'] as $name) {
			touch($dir . '/' . $name);
		}

		$names = $store->names();
		sort($names);

		/*
		 * The listing and pathOf agree, which is the whole point of the shared
		 * class. They did not before: the backup listing accepted pluck-rommel
		 * and then described it, and describing calls pathOf, which threw.
		 */
		$this->assertSame([$older, $good], $names, 'only names this store would resolve');

		$this->assertTrue($store->delete($good), 'a real one deletes');
		$this->assertFalse($store->exists($good), 'and is gone');
		$this->assertFalse($store->delete('pluck-rommel.tar.gz'), 'and one it does not own is left alone');
		$this->assertTrue(is_file($dir . '/pluck-rommel.tar.gz'), 'really left alone, not quietly removed');
	}

	private function store(?string &$dir = null): ArchiveStore
	{
		$dir = $this->tempDir('pluck-archive');

		return new ArchiveStore($dir, self::PATTERN, 'That is not the name of a backup.');
	}
}
