<?php
declare(strict_types=1);

namespace Pluck\Theme;

use Pluck\Support\Path;
use RuntimeException;

/**
 * A theme on disk.
 *
 * The difference from 4.x, and the reason themes had to be rebuilt rather than
 * carried over: a 4.x theme was PHP that ran inside the page, so a theme archive
 * was arbitrary code and installing one was equivalent to handing over the
 * server. Here a theme is templates plus a manifest, rendered through View,
 * which gives it escaped values and nothing else.
 *
 * Templates a theme may provide:
 *   layout.php   required — the frame around everything
 *   page.php     required — one content page
 *   module.php   optional — output from a module; falls back to page.php
 *   404.php      optional — falls back to page.php
 */
final class Theme
{
	private const REQUIRED = ['layout', 'page'];

	/** @param array<string,mixed> $manifest */
	private function __construct(
		public readonly string $name,
		public readonly string $directory,
		public readonly array $manifest,
	) {
	}

	/**
	 * @throws RuntimeException when the directory is not a usable theme. Callers
	 *         on the site path catch this and fall back, because a broken theme
	 *         must not be the reason a site is unreachable.
	 */
	public static function load(string $themesDir, string $name): self
	{
		$directory = Path::within($themesDir, $name);
		if (!is_dir($directory)) {
			throw new RuntimeException(sprintf('No theme called "%s".', $name));
		}

		$manifest = [];
		$manifestFile = $directory . '/theme.json';
		if (is_file($manifestFile)) {
			$decoded = json_decode((string) file_get_contents($manifestFile), true);
			$manifest = is_array($decoded) ? $decoded : [];
		}

		foreach (self::REQUIRED as $template) {
			if (!is_file($directory . '/templates/' . $template . '.php')) {
				throw new RuntimeException(sprintf('Theme "%s" has no %s.php template.', $name, $template));
			}
		}

		return new self($name, $directory, $manifest);
	}

	public function title(): string
	{
		$title = $this->manifest['name'] ?? $this->name;

		return is_string($title) ? $title : $this->name;
	}

	/**
	 * The settings this theme asks the site to fill in.
	 *
	 * Declared in theme.json so a theme can rely on them existing, and so an
	 * owner cannot invent one that nothing reads:
	 *
	 *     "parameters": {
	 *         "carnavalsdata": {
	 *             "label": "De drie dagen",
	 *             "default": "7, 8 en 9 februari 2027",
	 *             "help": "Wordt boven elke pagina getoond."
	 *         }
	 *     }
	 *
	 * This is what Pluck 4's template editor was for, minus the part that made it
	 * a shell. Editing PHP through a browser is a shell whatever it is called;
	 * filling in a value is not, and most of what people used that editor for was
	 * filling in values.
	 *
	 * Names are restricted to what can safely be a storage key and an HTML
	 * attribute, because they become both.
	 *
	 * @return array<string,array{label:string,default:string,help:string}>
	 */
	public function parameters(): array
	{
		$declared = $this->manifest['parameters'] ?? null;

		if (!is_array($declared)) {
			return [];
		}

		$out = [];

		foreach ($declared as $name => $spec) {
			if (!is_string($name) || preg_match('/^[a-z][a-z0-9_-]{0,39}$/', $name) !== 1) {
				continue;
			}

			// A bare string is the default, for a theme that wants one line.
			if (is_string($spec)) {
				$spec = ['default' => $spec];
			}

			if (!is_array($spec)) {
				continue;
			}

			$out[$name] = [
				'label' => $this->text($spec['label'] ?? '', 80) ?: $name,
				'default' => $this->text($spec['default'] ?? '', 500),
				'help' => $this->text($spec['help'] ?? '', 300),
			];
		}

		return $out;
	}

	/**
	 * A value out of theme.json, as text and nothing else.
	 *
	 * A manifest is a file in the themes directory, and a theme can arrive as an
	 * upload. Tags are stripped here rather than trusted to whoever prints it: a
	 * label ends up in an admin screen and a default ends up on the site.
	 */
	private function text(mixed $value, int $limit): string
	{
		if (!is_string($value)) {
			return '';
		}

		$clean = strip_tags($value);
		$clean = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $clean) ?? '';

		return mb_substr(trim($clean), 0, $limit);
	}

	public function version(): string
	{
		$version = $this->manifest['version'] ?? '';

		return is_string($version) ? $version : '';
	}

	public function has(string $template): bool
	{
		return is_file($this->directory . '/templates/' . $template . '.php');
	}

	/** The template to use, falling back through the list. */
	public function pick(string ...$candidates): string
	{
		foreach ($candidates as $candidate) {
			if ($this->has($candidate)) {
				return $candidate;
			}
		}

		return 'page';
	}

	public function templateDir(): string
	{
		return $this->directory . '/templates';
	}

	/** Whether the theme ships a stylesheet of its own. */
	public function hasAssets(): bool
	{
		return is_dir($this->directory . '/assets');
	}
}
