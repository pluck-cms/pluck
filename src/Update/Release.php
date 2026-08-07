<?php
declare(strict_types=1);

namespace Pluck\Update;

/** One published release. */
final class Release
{
	public function __construct(
		public readonly string $version,
		public readonly string $name,
		public readonly string $url,
		public readonly string $archiveUrl,
		public readonly string $publishedAt,
		public readonly string $notes = '',
		public readonly bool $prerelease = false,
	) {
	}

	/**
	 * Whether this release is newer than $current.
	 *
	 * `version_compare` understands the shapes Pluck uses — 5.0.0, 5.0.1,
	 * 5.1.0-rc1 — and treats a suffixed version as older than the plain one,
	 * which is what a release candidate should be.
	 */
	public function isNewerThan(string $current): bool
	{
		return version_compare($this->normalised(), self::normalise($current), '>');
	}

	public function normalised(): string
	{
		return self::normalise($this->version);
	}

	/** Tags are written `v5.0.1` as often as `5.0.1`. */
	public static function normalise(string $version): string
	{
		return ltrim(trim($version), 'vV');
	}
}
