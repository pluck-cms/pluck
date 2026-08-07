<?php
declare(strict_types=1);

namespace Pluck\Install;

/**
 * What the server has to offer before an install can start.
 *
 * Reported as three states rather than two: a missing extension that only one
 * storage driver needs is a note, not a blocker.
 */
final class Requirements
{
	public const PHP_MINIMUM = '8.3.0';

	/** @return list<array{name:string,status:string,detail:string}> */
	public static function check(string $rootDir): array
	{
		$checks = [];

		$phpOk = version_compare(PHP_VERSION, self::PHP_MINIMUM, '>=');
		$checks[] = [
			'name' => 'PHP ' . self::PHP_MINIMUM . ' or newer',
			'status' => $phpOk ? 'pass' : 'fail',
			'detail' => 'This server runs PHP ' . PHP_VERSION . '.',
		];

		$extensions = [
			'json' => 'Reading and writing content',
			'mbstring' => 'Handling non-latin text',
			// The sanitiser runs on every page save. Without it an editor could
			// not save at all, so this is a blocker and not a note.
			'dom' => 'Cleaning the HTML your editors write',
		];

		foreach ($extensions as $extension => $why) {
			$loaded = extension_loaded($extension);
			$checks[] = [
				'name' => 'The ' . $extension . ' extension',
				'status' => $loaded ? 'pass' : 'fail',
				'detail' => $loaded ? $why . '.' : $why . '. Ask your host to enable it.',
			];
		}

		$sqlite = extension_loaded('pdo_sqlite');
		$checks[] = [
			'name' => 'The pdo_sqlite extension',
			'status' => $sqlite ? 'pass' : 'note',
			'detail' => $sqlite
				? 'Storing content in a database is available.'
				: 'Not available, so content will be stored in files. That works fine.',
		];

		$intl = function_exists('transliterator_transliterate');
		$checks[] = [
			'name' => 'The intl extension',
			'status' => $intl ? 'pass' : 'note',
			'detail' => $intl
				? 'Page addresses will be readable in any language.'
				: 'Not available. Titles in non-latin scripts still work, but their web addresses will keep the original characters.',
		];

		$dataDir = $rootDir . '/data';
		$writable = is_dir($dataDir) ? is_writable($dataDir) : is_writable($rootDir);
		$checks[] = [
			'name' => 'A writable data folder',
			'status' => $writable ? 'pass' : 'fail',
			'detail' => $writable
				? 'Pluck can save your pages.'
				: 'Give the web server write access to the data folder, then reload this page.',
		];

		return $checks;
	}

	/** @param list<array{name:string,status:string,detail:string}> $checks */
	public static function passes(array $checks): bool
	{
		foreach ($checks as $check) {
			if ($check['status'] === 'fail') {
				return false;
			}
		}

		return true;
	}
}
