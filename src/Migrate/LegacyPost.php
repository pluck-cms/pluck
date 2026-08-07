<?php
declare(strict_types=1);

namespace Pluck\Migrate;

/** One blog post as it exists in a 4.x install, with its reactions. */
final class LegacyPost
{
	/** @param list<LegacyReaction> $reactions */
	public function __construct(
		public readonly string $seoname,
		public readonly int $order,
		public readonly string $title,
		public readonly string $content,
		public readonly string $category,
		public readonly int $time,
		/**
		 * Whether this post accepts reactions. Written by the blog-enhancements
		 * module; the stock module has no such field, and its absence means open.
		 */
		public readonly bool $allowReaction = true,
		public readonly array $reactions = [],
		public readonly string $sourceFile = '',
		public readonly ?string $problem = null,
	) {
	}

	public static function unreadable(string $file, string $problem): self
	{
		return new self(
			seoname: basename($file),
			order: 0,
			title: basename($file),
			content: '',
			category: '',
			time: 0,
			sourceFile: $file,
			problem: $problem,
		);
	}

	/**
	 * The old address, which is what a redirect has to match. 4.x served posts
	 * as ?file=blog&amp;blog=<seoname>, but the redirect map is written in terms
	 * of paths, so this is the path part of it.
	 */
	public function path(): string
	{
		return 'blog/' . $this->seoname;
	}

	/** ISO 8601 in UTC, or null when the old file had no usable timestamp. */
	public function publishedAt(): ?string
	{
		return $this->time > 0 ? gmdate('c', $this->time) : null;
	}
}
