<?php
declare(strict_types=1);

namespace Pluck\Storage;

use InvalidArgumentException;
use Pluck\Storage\FlatFile\FlatFileDriver;
use Pluck\Storage\Sqlite\SqliteDriver;
use Pluck\Support\Config;

final class DriverFactory
{
	public const FLAT_FILE = 'flatfile';
	public const SQLITE = 'sqlite';

	public static function make(string $driver, string $dataDir): StorageDriver
	{
		return match ($driver) {
			self::FLAT_FILE => new FlatFileDriver($dataDir),
			self::SQLITE => new SqliteDriver($dataDir),
			default => throw new InvalidArgumentException("Unknown storage driver: {$driver}"),
		};
	}

	public static function fromConfig(Config $config, string $dataDir): StorageDriver
	{
		return self::make((string) $config->get('storage', self::FLAT_FILE), $dataDir);
	}

	/** @return array<string,array{label:string,description:string,available:bool,reason:string}> */
	public static function options(): array
	{
		$hasSqlite = extension_loaded('pdo_sqlite');

		return [
			self::FLAT_FILE => [
				'label' => 'Files',
				'description' => 'One JSON file per page. Nothing to set up, easy to back up with FTP, and a page is still something you can read in an editor.',
				'available' => true,
				'reason' => '',
			],
			self::SQLITE => [
				'label' => 'SQLite',
				'description' => 'A single database file. Faster on sites with hundreds of pages and better when several people edit at once.',
				'available' => $hasSqlite,
				'reason' => $hasSqlite ? '' : 'Needs the pdo_sqlite extension, which this server does not have.',
			],
		];
	}
}
