<?php
declare(strict_types=1);

namespace Pluck\Migrate;

/**
 * One image in a 4.x album.
 *
 * The metadata and the image live in separate files: `<n>.<name>.<ext>.php`
 * holds the caption, `<name>.<ext>` next to it is the picture, and `thumb/` has
 * a scaled copy. v5 stores one image and scales on request, so the thumbnail is
 * read but not carried over.
 */
final class LegacyAlbumImage
{
	public function __construct(
		public readonly int $order,
		public readonly string $filename,
		public readonly string $title,
		public readonly string $info,
		public readonly string $relativePath,
		public readonly bool $fileExists,
	) {
	}
}
