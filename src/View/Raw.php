<?php
declare(strict_types=1);

namespace Pluck\View;

use Stringable;

/**
 * Markup that is already safe to print.
 *
 * Wrapping trusted HTML in an object rather than passing a bare string means a
 * template can print it with a short echo tag and a reviewer can still grep for
 * every place escaping was skipped, by looking for this class.
 */
final class Raw implements Stringable
{
	public function __construct(private readonly string $html)
	{
	}

	public function __toString(): string
	{
		return $this->html;
	}
}
