<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Bootstrap;
use Pluck\Storage\DriverFactory;
use Pluck\Storage\StorageDriver;
use Pluck\Update\Applier;
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
		$this->group('where releases are looked for', fn () => $this->source());
		$this->group('files the web server cannot replace', fn () => $this->unwritable());
		$this->group('compiled code is thrown away', fn () => $this->cacheCleared());
		$this->group('which version is running', fn () => $this->running());

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

	/**
	 * The update source can be moved, from config.php and nowhere else.
	 *
	 * An update source is code this install downloads and unpacks, so anything
	 * that can change it can run code here. An administrator cannot do that today
	 * and must not gain it through a text field — whoever can edit config.php can
	 * already replace src/ outright, so putting it there gives nothing away.
	 *
	 * It exists because testing the updater otherwise means publishing a real
	 * release on the shared repository, and /releases/latest skips pre-releases:
	 * a release candidate would have to go out as a normal release and become the
	 * headline release for everybody still on 4.7.
	 */
	private function source(): void
	{
		$dir = $this->tempDir('pluck-update-source');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();

		$api = new \ReflectionMethod(Updates::class, 'api');
		$api->setAccessible(true);

		$default = 'https://api.github.com/repos/pluck-cms/pluck/releases/latest';

		$this->assertSame(
			$default,
			$api->invoke(new Updates($dir, $storage, '5.0.0', '')),
			'unset means the Pluck repository',
		);

		$fork = 'https://api.github.com/repos/somebody/pluck/releases/latest';
		$this->assertSame(
			$fork,
			$api->invoke(new Updates($dir, $storage, '5.0.0', $fork)),
			'a fork on the same host is accepted',
		);

		// Anything else falls back rather than being fetched. A general "get it
		// from wherever this says" is the same hole by a longer road.
		foreach ([
			'https://evil.example/releases/latest',
			'http://api.github.com/repos/a/b/releases/latest',
			'https://api.github.com/repos/a/b/releases/latest?x=1',
			'https://api.github.com.evil.example/repos/a/b/releases/latest',
		] as $bad) {
			$this->assertSame(
				$default,
				$api->invoke(new Updates($dir, $storage, '5.0.0', $bad)),
				'refused and fell back: ' . $bad,
			);
		}
	}

	/**
	 * An update refuses rather than half-applies.
	 *
	 * The rollback works — it has been through a real failure on a real server —
	 * but a recovery that could have been a refusal is a bad trade. The case that
	 * found this was an install unpacked by hand as root, so some files belonged
	 * to root and the web server could not replace them.
	 */
	private function unwritable(): void
	{
		$dir = $this->tempDir('pluck-writable');

		@mkdir($dir . '/src', 0o755, true);
		file_put_contents($dir . '/index.php', '<?php');
		file_put_contents($dir . '/src/Bootstrap.php', '<?php');

		$this->assertSame(
			[],
			Applier::unwritable($dir, ['index.php', 'src/Bootstrap.php']),
			'an install it can write reports nothing',
		);

		// A file that does not exist yet is fine when its directory takes it.
		$this->assertSame(
			[],
			Applier::unwritable($dir, ['src/New.php', 'deeper/still/New.php']),
			'and so is a file the release would add',
		);

		// Read-only stands in for another owner: this suite does not run as root,
		// so it cannot make a file somebody else owns.
		chmod($dir . '/index.php', 0o444);

		$blocked = Applier::unwritable($dir, ['index.php', 'src/Bootstrap.php']);
		chmod($dir . '/index.php', 0o644);

		$this->assertSame(['index.php'], $blocked, 'and names the one it cannot');

		/*
		 * Two situations that look identical and want opposite answers.
		 *
		 * Folders unwritable too: somebody unpacked as the wrong user, and handing
		 * the files back is right. Folders writable and files not: ordinary shared
		 * hosting, PHP running as one account and the files owned by another — and
		 * telling somebody to chown everything to the web server there takes their
		 * own files away from them.
		 *
		 * The first version of this message gave the chown advice in both cases.
		 * It reached a real server where it was the wrong half.
		 */
		$hosting = Applier::explainUnwritable(['index.php'], $dir);

		$this->assertTrue(str_contains($hosting, 'index.php'), 'the message names the file');
		$this->assertTrue(
			str_contains($hosting, 'Nothing has been changed'),
			'and says nothing was touched, which is the part somebody needs first',
		);
		$this->assertTrue(
			str_contains($hosting, 'PHP runs as one account'),
			'a writable folder means the owner is not the problem',
		);
		$this->assertFalse(
			str_contains($hosting, 'chown -R'),
			'and it does not tell them to hand their files to the web server',
		);

		chmod($dir, 0o555);
		$accident = Applier::explainUnwritable(['index.php'], $dir);
		chmod($dir, 0o755);

		$this->assertTrue(
			str_contains($accident, 'chown -R'),
			'a folder that refuses writes too is an ownership accident, and there chown is right',
		);
	}

	/**
	 * An update throws the compiled code away.
	 *
	 * OPcache holds the compiled form of every .php and, by default, only asks
	 * whether a file changed every couple of seconds. Immediately after replacing
	 * 251 files that means old bytecode running against new files, and not evenly
	 * — some classes reloaded, some not, in whatever combination the timing
	 * produced.
	 *
	 * On the first real update it showed as "Bijgewerkt naar rc40" above "Je
	 * draait rc39", because Bootstrap::VERSION was still the old compiled
	 * constant. Harmless that time. The version it is not harmless is a new class
	 * calling a method an old one does not have yet.
	 *
	 * Asserted against the source: a reset in this process would throw away the
	 * suite's own compiled code mid-run, which is a strange thing to do to
	 * yourself for one assertion.
	 */
	private function cacheCleared(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/src/Update/Applier.php');

		$this->assertSame(
			2,
			substr_count($source, '@opcache_reset()'),
			'after a successful swap and after a rollback, which puts old files back under new bytecode',
		);
		$this->assertTrue(
			str_contains($source, "function_exists('opcache_reset')"),
			'without assuming the extension is loaded',
		);
	}

	/**
	 * One answer to "what am I running", asked in one place.
	 *
	 * There were two. The admin's badge asked storage with a fallback of
	 * '5.0.0-dev'; the updates screen asked with a fallback of Bootstrap::VERSION.
	 * Nothing ever writes that setting, so the fallback was the answer both times
	 * — and 'dev' sorts below everything in version_compare(), so the badge
	 * counted any release it had ever seen as newer, including ones older than
	 * the code running.
	 *
	 * It showed as "Updates (1)" beside a screen saying the check had failed and
	 * there was nothing to install.
	 */
	private function running(): void
	{
		$dir = $this->tempDir('pluck-running');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();

		$this->assertSame(
			Bootstrap::VERSION,
			Updates::runningVersion($storage),
			'with nothing stored, the constant compiled from the files that are there',
		);

		$storage->setSetting('version', '5.0.0-dev');
		$this->assertSame(
			Bootstrap::VERSION,
			Updates::runningVersion($storage),
			'and the old sentinel is treated as nothing rather than as a version below everything',
		);

		$storage->setSetting('version', '5.0.0-rc9');
		$this->assertSame(
			'5.0.0-rc9',
			Updates::runningVersion($storage),
			'a real stored value is still an override',
		);

		// The badge, which is what somebody actually sees.
		$storage->deleteSetting('version');
		$storage->setSetting(Updates::LAST_SEEN, [
			'version' => '1.0.0',
			'name' => 'old',
			'url' => '',
			'archive' => '',
			'published' => '',
			'notes' => '',
			'prerelease' => false,
		]);

		$this->assertFalse(
			(new Updates($dir, $storage, Updates::runningVersion($storage)))->updateAvailable(),
			'a remembered release older than the code running is not an update',
		);
	}
}
