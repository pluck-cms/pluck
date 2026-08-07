<?php
declare(strict_types=1);

namespace Pluck\Migrate;

/** One page as it exists in a 4.x install. */
final class LegacyPage
{
	/** @param array<string,string> $extra */
	public function __construct(
		public readonly string $seoname,
		public readonly ?string $parentSeoname,
		public readonly int $order,
		public readonly string $title,
		public readonly string $content,
		public readonly bool $hidden,
		public readonly string $description = '',
		public readonly string $keywords = '',
		public readonly array $extra = [],
		public readonly string $sourceFile = '',
		public readonly ?string $problem = null,
	) {
	}

	public static function unreadable(string $file, string $problem): self
	{
		return new self(
			seoname: basename($file),
			parentSeoname: null,
			order: 0,
			title: basename($file),
			content: '',
			hidden: true,
			sourceFile: $file,
			problem: $problem,
		);
	}

	/**
	 * Whether a 4.x page was kept out of the menu.
	 *
	 * Worth spelling out, because getting it backwards hides an entire site. 4.7
	 * writes `$hidden = 'no'` or `$hidden = 'yes'` and its own menu builder asks
	 * `$hidden == 'no'` before listing a page — so under 4.7 anything that is not
	 * literally "no" is hidden, empty included.
	 *
	 * That last part is not carried over. An empty or missing field almost always
	 * means a version that predates the setting rather than a deliberate choice,
	 * and treating those pages as hidden would migrate a working site into one
	 * with no menu at all. So a value that is there is obeyed exactly, and a value
	 * that is not there means visible.
	 */
	public static function readHidden(mixed $raw): bool
	{
		$value = strtolower(trim((string) ($raw ?? '')));

		return match ($value) {
			'', 'no', 'off', '0', 'false' => false,
			default => true,
		};
	}

	/** The old URL path, which is what a redirect has to match. */
	public function path(): string
	{
		return $this->parentSeoname === null ? $this->seoname : $this->parentSeoname . '/' . $this->seoname;
	}

	/**
	 * 4.x fell back to a date-time stamp for any title with no latin characters
	 * (issue #27), producing addresses like "20231104093012". Worth flagging: the
	 * new slug will be readable, so the old URL needs a redirect.
	 */
	public function hasGeneratedName(): bool
	{
		return preg_match('/^\d{14}$/', $this->seoname) === 1;
	}
}
