<?php
declare(strict_types=1);

namespace Pluck\Update;

/** A release archive sitting on disk, waiting for somebody to unpack it. */
final class Download
{
	public function __construct(
		public readonly string $name,
		public readonly int $bytes,
		public readonly int $downloadedAt,
		public readonly string $sha256 = '',
	) {
	}

	/** The version this was downloaded for, read back out of the filename. */
	public function version(): string
	{
		return preg_match('/^pluck-(.+)-[0-9a-f]{8}\.tar\.gz$/', $this->name, $m) === 1 ? $m[1] : '';
	}

	public function shortHash(): string
	{
		return substr($this->sha256, 0, 16);
	}
}
