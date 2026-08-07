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
