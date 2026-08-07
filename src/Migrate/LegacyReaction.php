<?php
declare(strict_types=1);

namespace Pluck\Migrate;

/**
 * One reaction to a blog post.
 *
 * 4.x kept the commenter's e-mail address alongside the comment and never
 * displayed it. That makes it personal data the site owner is holding without a
 * visible reason, so the migrator asks before carrying it over rather than
 * moving it silently; see Migrator::migrateBlog().
 */
final class LegacyReaction
{
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly string $email,
		public readonly string $website,
		public readonly string $message,
		public readonly int $time,
	) {
	}

	public function postedAt(): ?string
	{
		return $this->time > 0 ? gmdate('c', $this->time) : null;
	}
}
