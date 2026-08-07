<?php
declare(strict_types=1);

namespace Pluck\Theme;

use Pluck\Storage\StorageDriver;
use Throwable;

/**
 * Which themes exist, and which one is in use.
 *
 * The fallback matters more than it looks. A theme is the one part of an install
 * an owner edits by hand over FTP at eleven at night, and a typo in it must cost
 * them their design for a moment, not their website. So a theme that will not
 * load is reported and stepped over, and the bundled one takes its place.
 */
final class ThemeRepository
{
	public const FALLBACK = 'default';

	/** @var list<string> */
	private array $problems = [];

	public function __construct(private readonly string $themesDir)
	{
	}

	/** @return list<string> names of every directory that loads as a theme */
	public function available(): array
	{
		if (!is_dir($this->themesDir)) {
			return [];
		}

		$names = [];
		foreach (scandir($this->themesDir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..' || !is_dir($this->themesDir . '/' . $entry)) {
				continue;
			}

			try {
				Theme::load($this->themesDir, $entry);
				$names[] = $entry;
			} catch (Throwable) {
				// Not a theme, or a broken one. available() is used to build a
				// chooser, and offering something that cannot render is worse
				// than not offering it.
			}
		}

		sort($names);

		return $names;
	}

	/**
	 * The theme to render with: the page's own, else the site setting, else the
	 * bundled one. Each step falls through if it will not load.
	 */
	public function active(StorageDriver $storage, ?string $pageTheme = null): Theme
	{
		$candidates = [];
		if ($pageTheme !== null && $pageTheme !== '') {
			$candidates[] = $pageTheme;
		}

		$configured = $storage->getSetting('theme');
		if (is_string($configured) && $configured !== '') {
			$candidates[] = $configured;
		}

		$candidates[] = self::FALLBACK;

		foreach ($candidates as $name) {
			try {
				return Theme::load($this->themesDir, $name);
			} catch (Throwable $e) {
				$this->problems[] = sprintf('%s: %s', $name, $e->getMessage());
			}
		}

		// Nothing loaded, not even the bundled theme. That is an install
		// problem rather than a content problem, so it is worth being loud.
		throw new \RuntimeException(
			'No usable theme was found, not even "' . self::FALLBACK . '". Tried: ' . implode('; ', $this->problems),
		);
	}

	/** @return list<string> what went wrong on the way to the active theme */
	public function problems(): array
	{
		return $this->problems;
	}
}
