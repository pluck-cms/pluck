<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Archive\SafeZip;
use ZipArchive;

/**
 * SafeZip against archives that exist on disk.
 *
 * EntryPolicy is tested exhaustively on its own because it is a pure function.
 * That leaves the part only a real archive can show: that the wiring between
 * ZipArchive and the policy is right, that a refused archive writes nothing at
 * all rather than stopping halfway, and that the symlink detection reads the
 * external attributes the way it thinks it does.
 */
final class SafeZipTest extends TestCase
{
	public function run(): void
	{
		if (!$this->requiresExtension('zip', 'reading theme archives')) {
			return;
		}

		$this->group('a theme that behaves', fn () => $this->goodArchive());
		$this->group('archives that are refused', fn () => $this->refusals());
		$this->group('a refusal writes nothing', fn () => $this->allOrNothing());
		$this->group('not an archive at all', fn () => $this->notAnArchive());
	}

	private function goodArchive(): void
	{
		$dir = $this->tempDir('safezip');
		$zipPath = $this->makeZip($dir . '/theme.zip', [
			'theme/index.html' => '<h1>Hello</h1>',
			'theme/style.css' => 'body { color: red }',
			'theme/img/logo.png' => 'notreallyapng',
		]);

		$safe = new SafeZip();
		$report = $safe->inspect($zipPath);

		$this->assertTrue($report['ok'], 'a plain theme archive is accepted');
		$this->assertSame([], $report['problems'], 'and has nothing to complain about');
		$this->assertSame(3, count($report['files']), 'all three files are listed');

		$target = $dir . '/out';
		mkdir($target, 0o755, true);
		$written = $safe->extractTo($zipPath, $target);

		$this->assertSame(3, count($written), 'all three files are written');
		$this->assertSame('<h1>Hello</h1>', (string) file_get_contents($target . '/theme/index.html'), 'contents survive intact');
		$this->assertTrue(is_file($target . '/theme/img/logo.png'), 'nested directories are created');
	}

	private function refusals(): void
	{
		$dir = $this->tempDir('safezip');
		$safe = new SafeZip();

		$cases = [
			// #100: zip slip. The classic way to write outside the target.
			'traversal' => ['theme/../../evil.html' => 'x'],
			// #85 and CVE-2022-26965: a theme whose theme.php is a shell.
			'php inside a theme' => ['theme/theme.php' => $this->webshell()],
			// A dot-file that reconfigures the web server is as good as code.
			'htaccess' => ['theme/.htaccess' => 'php_flag engine on'],
			'absolute path' => ['/etc/passwd' => 'x'],
			'empty archive' => [],
		];

		foreach ($cases as $label => $entries) {
			$path = $this->makeZip($dir . '/' . md5($label) . '.zip', $entries);
			$report = $safe->inspect($path);

			$this->assertFalse($report['ok'], $label . ' is refused');
			$this->assertTrue($report['problems'] !== [], $label . ' comes with a reason');
		}

		$path = $dir . '/link.zip';
		$zip = new ZipArchive();
		$zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
		$zip->addFromString('theme/index.html', '<h1>ok</h1>');
		$zip->addFromString('theme/link.css', '../../../../etc/passwd');
		// 0xA1FF0000: symlink, mode 777, in the high half of the unix attributes.
		$zip->setExternalAttributesName('theme/link.css', ZipArchive::OPSYS_UNIX, 0xA1FF << 16);
		$zip->close();

		$report = $safe->inspect($path);
		$this->assertFalse($report['ok'], 'a symlink entry is refused');
	}

	private function allOrNothing(): void
	{
		$dir = $this->tempDir('safezip');
		$target = $dir . '/out';
		mkdir($target, 0o755, true);

		// One good file first, so a naive extractor would have written it before
		// reaching the bad one.
		$zipPath = $this->makeZip($dir . '/mixed.zip', [
			'theme/index.html' => '<h1>fine</h1>',
			'theme/shell.php' => $this->webshell(),
		]);

		$this->expectFailure(
			static fn () => (new SafeZip())->extractTo($zipPath, $target),
			'an archive with one bad entry is refused as a whole',
		);
		$this->assertSame([], $this->treeOf($target), 'and not one byte of it was written');
	}

	private function notAnArchive(): void
	{
		$dir = $this->tempDir('safezip');
		$fake = $dir . '/not.zip';
		file_put_contents($fake, 'this is a text file wearing a hat');

		$safe = new SafeZip();
		$this->expectFailure(static fn () => $safe->inspect($fake), 'a non-zip file is rejected');
		$this->expectFailure(static fn () => $safe->inspect($dir . '/missing.zip'), 'a missing file is rejected');
	}

	/** @param array<string,string> $entries */
	private function makeZip(string $path, array $entries): string
	{
		$zip = new ZipArchive();
		$zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
		foreach ($entries as $name => $contents) {
			$zip->addFromString($name, $contents);
		}
		if ($entries === []) {
			// ZipArchive will not save an archive with nothing in it, so give it a
			// directory entry: an archive that holds no files is its own test case.
			$zip->addEmptyDir('theme');
		}
		$zip->close();

		return $path;
	}

	/** @return list<string> */
	private function treeOf(string $dir): array
	{
		$found = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
		);
		foreach ($iterator as $file) {
			if ($file->isFile()) {
				$found[] = substr($file->getPathname(), strlen($dir) + 1);
			}
		}
		sort($found);

		return $found;
	}
}
