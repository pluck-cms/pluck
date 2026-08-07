<?php
declare(strict_types=1);

namespace Pluck\Migrate;

/** One photo album as it exists in a 4.x install. */
final class LegacyAlbum
{
	/** @param list<LegacyAlbumImage> $images */
	public function __construct(
		public readonly string $seoname,
		public readonly string $title,
		/** From the albums-enhancements module; empty on a stock 4.x install. */
		public readonly string $description = '',
		public readonly array $images = [],
		public readonly string $sourceFile = '',
		public readonly ?string $problem = null,
	) {
	}

	public static function unreadable(string $file, string $problem): self
	{
		return new self(
			seoname: basename($file),
			title: basename($file),
			sourceFile: $file,
			problem: $problem,
		);
	}

	public function path(): string
	{
		return 'albums/' . $this->seoname;
	}
}
