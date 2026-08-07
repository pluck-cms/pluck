<?php
declare(strict_types=1);

namespace Pluck\Site;

use Pluck\Model\Page;
use Pluck\Storage\StorageDriver;

/**
 * The site menu.
 *
 * Built from the page tree in one pass over allPages() rather than a query per
 * level: on flat files that is the difference between one directory read and one
 * per branch, and Pluck's whole point is that it stays quick on hosting nobody
 * would choose.
 *
 * "Hidden" means out of the menu, not withdrawn. The admin form says as much, so
 * a hidden page still resolves and still renders — it just is not linked from
 * here. Anything else would be a promise the editor did not make.
 */
final class Menu
{
	/** @var list<MenuItem> */
	private array $items;

	/** @param list<Page> $pages */
	public function __construct(array $pages, private readonly string $activePath = '')
	{
		$this->items = $this->build($pages, null);
	}

	public static function fromStorage(StorageDriver $storage, string $activePath = ''): self
	{
		return new self($storage->allPages(includeHidden: false), $activePath);
	}

	/** @return list<MenuItem> */
	public function items(): array
	{
		return $this->items;
	}

	public function isEmpty(): bool
	{
		return $this->items === [];
	}

	/**
	 * The trail from the top level down to the active page, for breadcrumbs.
	 * Every ancestor is looked up rather than derived from the path alone, so a
	 * hidden page in the middle of a trail leaves a gap instead of a bad link.
	 *
	 * @param list<Page> $pages
	 * @return list<MenuItem>
	 */
	public static function trail(array $pages, string $activePath): array
	{
		if ($activePath === '') {
			return [];
		}

		$byPath = [];
		foreach ($pages as $page) {
			$byPath[$page->path] = $page;
		}

		$trail = [];
		$parts = explode('/', $activePath);
		$walked = '';
		foreach ($parts as $part) {
			$walked = $walked === '' ? $part : $walked . '/' . $part;
			if (isset($byPath[$walked])) {
				$trail[] = new MenuItem($byPath[$walked], [], $walked === $activePath, true);
			}
		}

		return $trail;
	}

	/**
	 * @param list<Page> $pages
	 * @return list<MenuItem>
	 */
	private function build(array $pages, ?string $parent): array
	{
		$items = [];

		foreach ($pages as $page) {
			if ($page->parent() !== $parent) {
				continue;
			}

			$children = $this->build($pages, $page->path);
			$active = $page->path === $this->activePath;
			$open = $active || $this->activePath === $page->path || str_starts_with($this->activePath, $page->path . '/');

			$items[] = new MenuItem($page, $children, $active, $open);
		}

		usort($items, static fn (MenuItem $a, MenuItem $b): int => [$a->page->order, $a->page->title] <=> [$b->page->order, $b->page->title]);

		return $items;
	}
}
