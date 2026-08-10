<?php
declare(strict_types=1);

namespace Pluck\Tests;

/**
 * Every setting can be reached, and every setting is read.
 *
 * `contact_email` was written by the migrator, read everywhere, and offered by
 * no screen — so a site installed rather than migrated had a contact form that
 * mailed nobody, and nothing said so. That is the worst shape a fault can take:
 * everything works and nobody knows it does not.
 *
 * This walks the source instead of waiting for the next one.
 */
final class SettingsReachableTest extends TestCase
{
	/**
	 * Settings that are deliberately not on a screen, with the reason.
	 *
	 * An exception has to be argued for, which is the point of listing them here
	 * rather than making the check softer.
	 */
	private const NOT_ON_A_SCREEN = [
		// Generated once and never shown: it is a secret, and a secret on a
		// screen is a secret in a screenshot.
		'form_secret' => 'generated, never shown',
		// Written by the storage driver when it upgrades itself.
		'schema_version' => 'the driver keeps this',
		// An override for a test install; see Updates::runningVersion().
		'version' => 'an override, normally absent',
		// Remembered from the last check rather than chosen.
		'update_last_seen' => 'remembered, not chosen',
		'update_last_check' => 'remembered, not chosen',
		// Rebuilt from the media folder rather than typed.
		'media_register' => 'derived from the folder',
		/*
		 * Set from a screen, but through config.php rather than storage — it has
		 * to be readable before storage exists, because storage needs a locale to
		 * report a problem in.
		 */
		'language' => 'lives in config.php, set under Settings',
	];

	public function run(): void
	{
		$this->group('nothing is unreachable', fn () => $this->reachable());
		$this->group('nothing is written and never read', fn () => $this->dead());
	}

	/**
	 * @return array{0:array<string,list<string>>,1:array<string,list<string>>}
	 */
	private function scan(): array
	{
		$read = [];
		$written = [];
		$root = dirname(__DIR__);

		$walk = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
		);

		foreach ($walk as $file) {
			$path = str_replace($root . '/', '', $file->getPathname());

			if (!str_ends_with($path, '.php')) {
				continue;
			}

			// The suite's own fixtures set whatever they like, and a theme or a
			// module is somebody else's code.
			foreach (['tests/', 'data/', 'media/', 'themes/', 'modules/'] as $skip) {
				if (str_starts_with($path, $skip)) {
					continue 2;
				}
			}

			$src = (string) file_get_contents($file->getPathname());

			foreach (['getSetting' => &$read, 'setSetting' => &$written] as $call => &$into) {
				if (preg_match_all('/' . $call . "\\(\\s*'([a-z_0-9]+)'/", $src, $m) === false) {
					continue;
				}

				foreach ($m[1] as $key) {
					$into[$key][] = $path;
				}
			}
		}

		return [$read, $written];
	}

	private function reachable(): void
	{
		[$read, $written] = $this->scan();
		$unreachable = [];

		foreach (array_keys($read) as $key) {
			if (isset(self::NOT_ON_A_SCREEN[$key])) {
				continue;
			}

			$onAScreen = false;

			foreach ($written[$key] ?? [] as $path) {
				if (str_starts_with($path, 'src/Admin/') || str_starts_with($path, 'views/')) {
					$onAScreen = true;
				}
			}

			if (!$onAScreen) {
				$unreachable[] = $key;
			}
		}

		sort($unreachable);

		$this->assertSame(
			[],
			$unreachable,
			'every setting the code reads can be set from a screen, or is listed as deliberately not',
		);
	}

	/**
	 * A setting nothing reads is worse than no setting.
	 *
	 * Somebody finds it in the store, believes the feature exists, and goes
	 * looking for the screen that switches it. `updates_channel` was exactly
	 * that: written at install, read nowhere, and there is no beta channel.
	 */
	private function dead(): void
	{
		[$read, $written] = $this->scan();

		$dead = array_values(array_diff(array_keys($written), array_keys($read)));
		sort($dead);

		$this->assertSame([], $dead, 'nothing is stored that nothing ever reads');
	}
}
