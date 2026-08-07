<?php
declare(strict_types=1);

namespace Pluck\Archive;

use Pluck\Support\Path;
use RuntimeException;
use ZipArchive;

/**
 * Reads an archive and hands each entry to EntryPolicy.
 *
 * All the judgement lives in EntryPolicy, which is why this class is short: the
 * part worth testing exhaustively should not need the zip extension to test.
 * Nothing is written unless the whole archive passes — a half-extracted theme is
 * worse than a rejected one.
 */
final class SafeZip
{
	private readonly EntryPolicy $policy;

	/** @param list<string> $allowedExtensions */
	public function __construct(array $allowedExtensions = EntryPolicy::THEME_EXTENSIONS)
	{
		$this->policy = new EntryPolicy($allowedExtensions, 'theme');
	}

	public static function isSupported(): bool
	{
		return class_exists(ZipArchive::class);
	}

	/**
	 * Inspect without writing anything. The installer shows this before asking for
	 * confirmation: "this archive wants to write shell.php" is worth seeing before
	 * the fact rather than after.
	 *
	 * @return array{ok:bool,files:list<string>,problems:list<string>,bytes:int}
	 */
	public function inspect(string $zipPath): array
	{
		$zip = $this->open($zipPath);

		$files = [];
		$problems = [];
		$total = 0;

		$count = $zip->numFiles;
		if ($count > EntryPolicy::MAX_ENTRIES) {
			$problems[] = sprintf('The archive holds %d entries; the limit is %d.', $count, EntryPolicy::MAX_ENTRIES);
			$count = EntryPolicy::MAX_ENTRIES;
		}

		for ($i = 0; $i < $count; $i++) {
			$stat = $zip->statIndex($i);
			if ($stat === false) {
				$problems[] = 'An entry could not be read.';
				continue;
			}

			$name = (string) $stat['name'];
			$problem = $this->policy->judge(
				$name,
				(int) $stat['size'],
				(int) $stat['comp_size'],
				$this->isSymlink($zip, $i),
			);

			if ($problem !== null) {
				$problems[] = $problem;
				continue;
			}

			if (!str_ends_with(str_replace('\\', '/', $name), '/')) {
				$files[] = $name;
				$total += (int) $stat['size'];
			}
		}

		if ($total > EntryPolicy::MAX_TOTAL_BYTES) {
			$problems[] = sprintf(
				'Unpacked the archive would be %s; the limit is %s.',
				EntryPolicy::humanBytes($total),
				EntryPolicy::humanBytes(EntryPolicy::MAX_TOTAL_BYTES),
			);
		}

		if ($files === [] && $problems === []) {
			$problems[] = 'The archive contains no files.';
		}

		$zip->close();

		return ['ok' => $problems === [], 'files' => $files, 'problems' => $problems, 'bytes' => $total];
	}

	/**
	 * @return list<string> the files written, relative to $targetDir
	 */
	public function extractTo(string $zipPath, string $targetDir): array
	{
		$report = $this->inspect($zipPath);
		if (!$report['ok']) {
			throw new RuntimeException('This archive was refused: ' . implode(' ', $report['problems']));
		}
		if (!is_dir($targetDir)) {
			throw new RuntimeException("Target directory does not exist: {$targetDir}");
		}

		$zip = $this->open($zipPath);
		$written = [];

		try {
			foreach ($report['files'] as $name) {
				// Resolved through Path::within as well, so even if the policy were
				// somehow fooled the write still cannot leave the target directory.
				$destination = Path::within($targetDir, ltrim(str_replace('\\', '/', $name), '/'));

				$stream = $zip->getStream($name);
				if ($stream === false) {
					throw new RuntimeException("Could not read {$name} from the archive.");
				}

				$contents = stream_get_contents($stream, EntryPolicy::MAX_ENTRY_BYTES + 1);
				fclose($stream);

				if ($contents === false || strlen($contents) > EntryPolicy::MAX_ENTRY_BYTES) {
					throw new RuntimeException("{$name} is larger than the archive claimed.");
				}

				Path::writeAtomic($destination, $contents);
				$written[] = $name;
			}
		} finally {
			$zip->close();
		}

		return $written;
	}

	private function isSymlink(ZipArchive $zip, int $index): bool
	{
		$opsys = 0;
		$attributes = 0;

		if (!$zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
			return false;
		}

		return $opsys === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0xF000) === 0xA000;
	}

	private function open(string $zipPath): ZipArchive
	{
		if (!self::isSupported()) {
			throw new RuntimeException(
				'This server has no zip support, so themes cannot be installed from an archive. '
				. 'Upload the theme folder over FTP instead.',
			);
		}
		if (!is_file($zipPath)) {
			throw new RuntimeException("No such archive: {$zipPath}");
		}

		$zip = new ZipArchive();
		if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
			throw new RuntimeException('That file is not a readable zip archive.');
		}

		return $zip;
	}
}
