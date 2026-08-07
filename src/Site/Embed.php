<?php
declare(strict_types=1);

namespace Pluck\Site;

use Pluck\Module\ModuleRegistry;
use Pluck\Storage\StorageDriver;
use Throwable;

/**
 * `[module:blog]` in a page, replaced by that module's output.
 *
 * Plain text rather than a comment or a data attribute, and that is not a
 * shortcut. The sanitiser strips comments and unknown attributes, so a
 * structured marker would need the allow-list widened — comments to carry
 * conditional-comment tricks, `data-*` to carry anything at all. Text survives
 * sanitising untouched, so the marker costs nothing in surface.
 *
 * What a page embeds is a *view into* a module, not the module itself. Pluck 4
 * let a page be a module, which is why the SEO module produced addresses like
 * /news/.blog/some-post and why a site that used it now has links v5 cannot
 * serve. Here /blog is where the blog lives, and a page can show a piece of it.
 *
 * Syntax:
 *   [module:blog]
 *   [module:blog count=3]
 *   [module:albums album=vakantie]
 *   [module:albums album="zomer 2011"]
 */
final class Embed
{
	/**
	 * Matches a marker and captures the module name and the rest.
	 *
	 * Deliberately narrow: a module name is lowercase letters, digits, dash and
	 * underscore, and nothing else may appear before the parameters. Anything
	 * looser and a page containing the word "module" in square brackets starts
	 * being interpreted.
	 */
	private const PATTERN = '/\[module:([a-z][a-z0-9_-]*)((?:\s+[a-z][a-z0-9_-]*=(?:"[^"]*"|\'[^\']*\'|[^\s\]]+))*)\s*\]/i';

	public function __construct(
		private readonly ModuleRegistry $modules,
		private readonly StorageDriver $storage,
		private readonly Urls $urls,
	) {
	}

	/**
	 * Replace every marker in $html.
	 *
	 * A marker naming a module that is not installed is left exactly as it was,
	 * on purpose. Removing it would leave an author staring at a page that is
	 * silently missing something with no clue why; leaving it visible is how they
	 * find the typo.
	 */
	public function expand(string $html): string
	{
		if (!str_contains($html, '[module:')) {
			return $html;
		}

		// A marker alone in a paragraph replaces the paragraph. Module output is
		// block-level markup, and putting a <div> inside a <p> makes the browser
		// close the paragraph early — which moves everything after it out of place.
		$html = preg_replace_callback(
			'/<p>\s*' . substr(self::PATTERN, 1, -2) . '\s*<\/p>/i',
			fn (array $m): string => $this->replace($m, '<p>' . $m[0] . '</p>'),
			$html,
		) ?? $html;

		return preg_replace_callback(
			self::PATTERN,
			fn (array $m): string => $this->replace($m, $m[0]),
			$html,
		) ?? $html;
	}

	/**
	 * @param array<int,string> $match
	 * @param string $original what to leave in place when the module cannot answer
	 */
	private function replace(array $match, string $original): string
	{
		$module = strtolower($match[1]);
		$mounted = $this->modules->resolve($module);

		if ($mounted === null) {
			return $match[0];
		}

		try {
			$html = $mounted[0]->embed(self::parameters($match[2] ?? ''), $this->storage, $this->urls);
		} catch (Throwable) {
			// A module that throws takes down its own corner of the page and
			// nothing else. A page that will not render at all because a photo
			// album has a bad day is a worse outcome than a page with a gap in it.
			return '';
		}

		return $html ?? '';
	}

	/**
	 * Parse `key=value key="value with spaces"` into an array.
	 *
	 * Values are strings and stay strings. A module reads what it recognises and
	 * ignores the rest, so a parameter added to a marker before the module
	 * supports it is inert rather than an error.
	 *
	 * @return array<string,string>
	 */
	public static function parameters(string $raw): array
	{
		if (trim($raw) === '') {
			return [];
		}

		preg_match_all(
			'/([a-z][a-z0-9_-]*)=(?:"([^"]*)"|\'([^\']*)\'|([^\s\]]+))/i',
			$raw,
			$matches,
			PREG_SET_ORDER,
		);

		$parameters = [];
		foreach ($matches as $match) {
			$value = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : ($match[4] ?? ''));
			// A quoted empty value is a legitimate way to say "nothing", so the
			// alternation above is checked in order rather than by truthiness.
			if ($match[2] === '' && isset($match[0]) && str_contains($match[0], '=""')) {
				$value = '';
			}

			$parameters[strtolower($match[1])] = mb_substr($value, 0, 200);
		}

		return $parameters;
	}
}
