<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Storage\DriverFactory;
use Pluck\Storage\StorageDriver;
use Pluck\Update\Download;
use Pluck\Update\Release;
use Pluck\Update\Updates;

/**
 * Looking for updates, and fetching one.
 *
 * No network here. What is worth testing is the reasoning around the request —
 * which version counts as newer, how often GitHub is asked, what happens to a
 * name arriving from a form — and every one of those is a decision made before
 * or after the connection rather than during it.
 *
 * The one thing asserted about the connection itself is that it refuses to be
 * made over plain HTTP, because an updater that fetches over http:// lets
 * anything on the path choose what a site downloads.
 */
final class UpdateTest extends TestCase
{
	public function run(): void
	{
		$this->group('which version is newer', fn () => $this->versions());
		$this->group('how often GitHub is asked', fn () => $this->caching());
		$this->group('reaching the internet', fn () => $this->reaching());
		$this->group('names that are not downloads', fn () => $this->names());
		$this->group('what is deliberately absent', fn () => $this->absent());
	}

	private function versions(): void
	{
		$release = static fn (string $version): Release => new Release(
			version: $version,
			name: $version,
			url: '',
			archiveUrl: '',
			publishedAt: '',
		);

		$this->assertTrue($release('5.0.1')->isNewerThan('5.0.0'), 'a later patch is newer');
		$this->assertTrue($release('5.1.0')->isNewerThan('5.0.9'), 'and a later minor');
		$this->assertFalse($release('5.0.0')->isNewerThan('5.0.0'), 'the same version is not an update');
		$this->assertFalse($release('4.7.15')->isNewerThan('5.0.0'), 'and an older one certainly is not');

		// Tags are written both ways in the wild, and a site told it is running
		// "5.0.0" while GitHub says "v5.0.0" would offer an update to itself for
		// ever.
		$this->assertFalse($release('v5.0.0')->isNewerThan('5.0.0'), 'a v prefix is not a version difference');
		$this->assertTrue($release('v5.0.1')->isNewerThan('5.0.0'), 'and does not stop a real one being seen');

		// A release candidate is older than the release it leads to, which is what
		// version_compare already believes and what this relies on.
		$this->assertTrue($release('5.1.0')->isNewerThan('5.1.0-rc1'), 'a final release beats its candidate');
		$this->assertFalse($release('5.0.0-rc1')->isNewerThan('5.0.0'), 'and a candidate does not beat the release');
	}

	private function caching(): void
	{
		[$updates, $storage] = $this->site();

		$this->assertTrue($updates->isStale(), 'with no check on record, one is due');

		$storage->setSetting(Updates::LAST_CHECK, time());
		$this->assertFalse($updates->isStale(), 'and not again straight afterwards');

		// GitHub allows sixty unauthenticated calls an hour per address. A site
		// with four admins opening the screen would otherwise spend that telling
		// them all the same thing.
		$storage->setSetting(Updates::LAST_CHECK, time() - 3600);
		$this->assertFalse($updates->isStale(), 'an hour later it is still cached');

		$storage->setSetting(Updates::LAST_CHECK, time() - 86400);
		$this->assertTrue($updates->isStale(), 'a day later it is not');

		$this->assertSame(null, $updates->cached(), 'nothing has been seen yet');

		$storage->setSetting(Updates::LAST_SEEN, [
			'version' => 'v5.1.0',
			'name' => 'Pluck 5.1.0',
			'url' => 'https://github.com/pluck-cms/pluck/releases/tag/v5.1.0',
			'archive' => 'https://api.github.com/repos/pluck-cms/pluck/tarball/v5.1.0',
			'published' => '2026-09-01T10:00:00Z',
			'notes' => 'Things changed.',
		]);

		$this->assertSame('v5.1.0', $updates->cached()?->version, 'a remembered release is read back');
		$this->assertTrue($updates->updateAvailable(), 'and recognised as newer than what is running');

		$this->assertSame(
			'5.1.0',
			$updates->cached()?->normalised(),
			'with the tag normalised for display',
		);
	}

	private function reaching(): void
	{
		[$updates] = $this->site();

		$this->assertSame(
			function_exists('curl_init') || (bool) ini_get('allow_url_fopen'),
			$updates->canReachInternet(),
			'whether the server can fetch anything is answered honestly',
		);

		// "No update found" would be a lie on a host that blocks outbound HTTPS,
		// and plenty of shared hosting does. The screen says so instead.
		$this->assertTrue(
			method_exists($updates, 'canReachInternet'),
			'and is a separate question from whether an update exists',
		);
	}

	private function names(): void
	{
		[$updates] = $this->site();

		foreach ([
			'../../data/settings/config.php',
			'/etc/passwd',
			'pluck.tar.gz',
			'pluck-5.1.0-nothex.tar.gz',
			'pluck-5.1.0-deadbeef.tar.gz.php',
		] as $name) {
			$this->expectFailure(
				static fn () => $updates->pathOf($name),
				'refuses a name that is not a download: ' . $name,
			);
			$this->assertFalse($updates->delete($name), 'and will not delete it either');
		}

		$this->assertSame(
			'5.1.0',
			(new Download('pluck-5.1.0-deadbeef.tar.gz', 100, 0))->version(),
			'a well-formed name gives up its version',
		);
	}

	private function absent(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/src/Update/Updates.php');

		// Fetching and applying are separate files on purpose: everything that
		// touches the network is here, and everything that writes over the install
		// is in Applier, which is only ever reached from a button.
		foreach (['PharData', 'extractTo', 'Tar::entries'] as $forbidden) {
			$this->assertFalse(
				str_contains($source, $forbidden),
				'the part that talks to the network does not unpack anything: ' . $forbidden,
			);
		}

		// And applying is never scheduled. The checksum proves the bytes arrived
		// as GitHub sent them, not that the release is sound, so one bad release
		// must not reach every site before anybody has looked at it.
		$applier = (string) file_get_contents(dirname(__DIR__) . '/src/Update/Applier.php');
		foreach (['register_shutdown_function', 'ScheduledBackup', 'isDue'] as $forbidden) {
			$this->assertFalse(
				str_contains($applier, $forbidden),
				'applying an update is never scheduled: ' . $forbidden,
			);
		}

		$admin = (string) file_get_contents(dirname(__DIR__) . '/admin.php');
		$this->assertFalse(str_contains($admin, 'Applier'), 'and nothing applies one outside the controller');

		// And it will not fetch over a connection anyone can rewrite.
		$this->assertTrue(
			str_contains($source, "str_starts_with(\$url, 'https://')"),
			'plain http is refused outright',
		);
		$this->assertFalse(
			str_contains($source, 'CURLOPT_SSL_VERIFYPEER => false'),
			'certificate checking is never switched off',
		);
	}

	// ---- fixture --------------------------------------------------------

	/** @return array{0:Updates,1:StorageDriver} */
	private function site(): array
	{
		$dir = $this->tempDir('pluck-update');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();

		return [new Updates($dir, $storage, '5.0.0'), $storage];
	}
}
