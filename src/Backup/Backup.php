<?php
declare(strict_types=1);

namespace Pluck\Backup;

/** One archive on disk. */
final class Backup
{
	/** @param array<string,mixed> $manifest empty when the archive is damaged */
	public function __construct(
		public readonly string $name,
		public readonly int $bytes,
		public readonly int $createdAt,
		public readonly array $manifest = [],
	) {
	}

	public function reason(): string
	{
		$reason = $this->manifest['reason'] ?? '';

		return is_string($reason) ? $reason : '';
	}

	public function madeBy(): string
	{
		$version = $this->manifest['pluck'] ?? '';

		return is_string($version) ? $version : '';
	}

	public function fileCount(): int
	{
		return (int) ($this->manifest['files'] ?? 0);
	}

	/** An archive whose manifest could not be read is damaged, or is not ours. */
	public function isReadable(): bool
	{
		return $this->manifest !== [];
	}
}
