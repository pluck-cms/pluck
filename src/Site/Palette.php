<?php
declare(strict_types=1);

namespace Pluck\Site;

/**
 * The colours a writer can pick.
 *
 * Read out of `assets/site/colours.css` rather than listed here as well. That
 * file is the palette: adding a colour is one rule in it, and the picker follows.
 * A second list in PHP would be a second list to forget.
 *
 * What is read is only the class name and the colour it sets — enough to draw a
 * swatch. Everything else in that file is CSS and none of this parser's business.
 *
 * A theme that overrules `.c-red` in its own stylesheet changes what the colour
 * looks like on the site. The picker keeps showing the shipped swatch, which is
 * a small lie and the honest alternative — reading every theme's stylesheet to
 * find out — is a CSS parser in the admin, which is a worse thing to own.
 */
final class Palette
{
	/**
	 * @return array<string,string> class name without the prefix => colour
	 */
	public static function read(string $cssFile): array
	{
		if (!is_file($cssFile)) {
			return [];
		}

		$css = (string) file_get_contents($cssFile);
		$found = [];

		// `.c-name { color: #rrggbb; }` and nothing else. A rule with anything
		// more in it is not a plain colour and does not belong in a picker.
		if (preg_match_all('/\.c-([a-z0-9-]+)\s*\{\s*color:\s*(#[0-9a-f]{3,8})\s*;\s*\}/i', $css, $matches, PREG_SET_ORDER) === false) {
			return [];
		}

		foreach ($matches as $match) {
			$found[$match[1]] = strtolower($match[2]);
		}

		return $found;
	}

	/**
	 * A name a person can read, from a class name.
	 *
	 * `red-dark` becomes "Red dark". Crude on purpose: a translator can give a
	 * better one under `colour.<name>`, and until somebody does, "Red dark" is
	 * more use than "c-red-dark".
	 */
	public static function label(string $name): string
	{
		return ucfirst(str_replace('-', ' ', $name));
	}
}
