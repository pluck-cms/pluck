<?php
declare(strict_types=1);

namespace Pluck\Theme;

use Pluck\Storage\StorageDriver;

/**
 * What a site has filled in for its theme's parameters.
 *
 * The theme declares which parameters exist; this holds the answers. Values are
 * stored per theme name, so switching to another theme and back finds what was
 * there rather than nothing.
 *
 * ## Text, and only text
 *
 * A value is stripped of tags and control characters on the way in. Not because
 * a template will print it unescaped — a template must escape, and the bundled
 * ones do — but because this is the one screen where somebody types something
 * that lands on every page of the site, and the honest guarantee is that it
 * cannot be anything but words.
 *
 * That is the line Pluck 4's template editor did not have. Editing PHP through a
 * browser is a shell whatever it is called, and most of what people used that
 * editor for was filling in values.
 */
final class ThemeParameters
{
	private const PREFIX = 'theme_params:';

	/** Long enough for a tagline or a date, short enough not to be a page. */
	private const MAX_LENGTH = 500;

	public function __construct(private readonly StorageDriver $storage)
	{
	}

	/**
	 * Every parameter the theme declares, with the site's answer or the default.
	 *
	 * Driven by the declaration rather than by what is stored: a value left over
	 * from an older version of the theme is not returned, so a template never
	 * receives a parameter the current theme does not know about.
	 *
	 * @return array<string,string>
	 */
	public function values(Theme $theme): array
	{
		$stored = $this->stored($theme->name);
		$out = [];

		foreach ($theme->parameters() as $name => $spec) {
			$out[$name] = array_key_exists($name, $stored) && $stored[$name] !== ''
				? $stored[$name]
				: $spec['default'];
		}

		return $out;
	}

	/**
	 * Save what an owner typed.
	 *
	 * Anything the theme does not declare is dropped rather than kept: a value
	 * nothing reads is a line in the store that confuses whoever finds it next,
	 * and this is exactly where one would accumulate.
	 *
	 * An empty value is stored as empty, which `values()` reads as "use the
	 * default" — that is what the reset button does, and it means resetting never
	 * has to guess what the default was.
	 *
	 * @param array<string,string> $given
	 */
	public function save(Theme $theme, array $given): void
	{
		$declared = $theme->parameters();
		$clean = [];

		foreach ($declared as $name => $spec) {
			$clean[$name] = self::clean($given[$name] ?? '');
		}

		$this->storage->setSetting(self::PREFIX . $theme->name, $clean);
	}

	/**
	 * Text, with nothing in it that could be anything else.
	 *
	 * Tags stripped, entities decoded first so `&lt;script&gt;` cannot survive as
	 * a tag that reappears when something decodes it later, and control
	 * characters replaced with a space — a newline in a value that becomes an
	 * attribute is how one attribute turns into two.
	 */
	public static function clean(mixed $value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		$text = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = strip_tags($text);
		$text = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $text) ?? '';
		$text = preg_replace('/\s{2,}/u', ' ', $text) ?? '';

		return mb_substr(trim($text), 0, self::MAX_LENGTH);
	}

	/** @return array<string,string> */
	private function stored(string $themeName): array
	{
		$raw = $this->storage->getSetting(self::PREFIX . $themeName, []);

		if (!is_array($raw)) {
			return [];
		}

		$out = [];

		foreach ($raw as $name => $value) {
			if (is_string($name) && is_scalar($value)) {
				$out[$name] = (string) $value;
			}
		}

		return $out;
	}
}
