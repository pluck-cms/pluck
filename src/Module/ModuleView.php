<?php
declare(strict_types=1);

namespace Pluck\Module;

/**
 * What a module hands back.
 *
 * The `html` is inserted into the theme without further escaping, so a module is
 * responsible for its own output the way the sanitiser is responsible for page
 * content: build markup with the helpers, never by pasting stored values into a
 * string. Everything a module stores came through the sanitiser on the way in,
 * which is what makes that safe rather than merely conventional.
 */
final class ModuleView
{
	/**
	 * @param array<string,string> $meta description and keywords, if the module
	 *        has something better than the page's own
	 * @param list<array{title:string,path:string}> $breadcrumbs within the module
	 */
	public function __construct(
		public readonly string $title,
		public readonly string $html,
		public readonly array $meta = [],
		public readonly array $breadcrumbs = [],
		public readonly ?string $canonical = null,
	) {
	}
}
