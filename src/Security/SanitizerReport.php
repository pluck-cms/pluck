<?php
declare(strict_types=1);

namespace Pluck\Security;

/**
 * The result of sanitising, plus what had to be taken out to get there.
 *
 * The distinction this exists to draw: parsing and re-serialising HTML changes
 * it even when nothing was wrong with it. `&euml;` comes back as `ë`, `<img />`
 * as `<img>`. Comparing input to output therefore says "changed" for almost
 * every page written in a 4.x editor, which is useless as a signal — a migration
 * report that flags 91 of 99 pages tells the owner to check everything, which
 * means they check nothing.
 *
 * Removals are the part worth a person's attention: a tag that was dropped, an
 * attribute that was not allowed. Those are countable and nameable, so they are.
 */
final class SanitizerReport
{
	/** @param array<string,int> $removals description => how many times */
	public function __construct(
		public readonly string $html,
		public readonly array $removals = [],
	) {
	}

	public function removedAnything(): bool
	{
		return $this->removals !== [];
	}

	/** Compact, in report order: the most frequent removal first. */
	public function summary(): string
	{
		$removals = $this->removals;
		arsort($removals);

		$parts = [];
		foreach ($removals as $what => $count) {
			$parts[] = $count > 1 ? sprintf('%s (%d×)', $what, $count) : $what;
		}

		return implode(', ', $parts);
	}
}
