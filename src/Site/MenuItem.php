<?php
declare(strict_types=1);

namespace Pluck\Site;

use Pluck\Model\Page;

/** One entry in the menu, with whatever sits under it. */
final class MenuItem
{
	/** @param list<MenuItem> $children */
	public function __construct(
		public readonly Page $page,
		public readonly array $children = [],
		public readonly bool $active = false,
		public readonly bool $open = false,
	) {
	}

	public function title(): string
	{
		return $this->page->title;
	}

	public function path(): string
	{
		return $this->page->path;
	}

	public function hasChildren(): bool
	{
		return $this->children !== [];
	}
}
