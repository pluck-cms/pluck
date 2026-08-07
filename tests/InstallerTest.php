<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Bootstrap;
use Pluck\Install\Installer;
use Pluck\Model\Role;
use Pluck\Storage\DriverFactory;

final class InstallerTest extends TestCase
{
	public function run(): void
	{
		$this->validation();

		foreach (['flatfile', 'sqlite'] as $driver) {
			if ($driver === 'sqlite' && !extension_loaded('pdo_sqlite')) {
				continue;
			}
			$this->group($driver, fn () => $this->fullInstall($driver));
		}
	}

	private function validation(): void
	{
		$root = $this->tempDir('pluck-install-validate');
		$installer = new Installer(Bootstrap::boot($root));

		$this->assertSame(
			[],
			$installer->validate($this->input()),
			'a sensible set of answers validates cleanly',
		);

		$this->assertTrue(
			$installer->validate($this->input(['password' => 'short'])) !== [],
			'a short password is rejected',
		);
		$this->assertTrue(
			$installer->validate($this->input(['username' => 'a'])) !== [],
			'a one-character username is rejected',
		);
		$this->assertTrue(
			$installer->validate($this->input(['username' => 'bad name'])) !== [],
			'a username with a space is rejected',
		);
		$this->assertTrue(
			$installer->validate($this->input(['site_title' => '  '])) !== [],
			'an empty site name is rejected',
		);
		$this->assertTrue(
			$installer->validate($this->input(['email' => 'not-an-email'])) !== [],
			'a malformed email is rejected',
		);
		$this->assertSame(
			[],
			$installer->validate($this->input(['email' => ''])),
			'no email at all is fine',
		);
		$this->assertTrue(
			$installer->validate($this->input(['timezone' => 'Mars/Olympus'])) !== [],
			'an unknown time zone is rejected',
		);
		$this->assertTrue(
			$installer->validate($this->input(['storage' => 'mysql'])) !== [],
			'an unknown storage driver is rejected',
		);
		$this->assertSame(
			[],
			$installer->validate($this->input(['username' => 'jörgen'])),
			'a username with a non-ascii letter is accepted',
		);
	}

	private function fullInstall(string $driver): void
	{
		$root = $this->tempDir('pluck-install-' . $driver);
		$app = Bootstrap::boot($root);

		$this->assertFalse($app->isInstalled(), 'a bare directory is not an install');

		$errors = (new Installer($app))->run($this->input(['storage' => $driver]));
		$this->assertSame([], $errors, 'the install runs without errors');

		// Re-boot, the way the next request would.
		$app = Bootstrap::boot($root);
		$this->assertTrue($app->isInstalled(), 'the install is detected on the next request');
		$this->assertSame($driver, $app->storage()->name(), 'the chosen driver is the one that gets used');

		$storage = $app->storage();
		$this->assertSame('Test Site', $storage->getSetting('site_title'), 'the site name was saved');
		$this->assertSame(false, $storage->getSetting('search_enabled'), 'search starts switched off');
		$this->assertSame(true, $storage->getSetting('updates_check_enabled'), 'update checks start switched on');

		$owner = $storage->findUserByUsername('bas');
		$this->assertTrue($owner !== null, 'the first account exists');
		$this->assertSame(Role::Owner, $owner->role, 'the first account is the owner');
		$this->assertTrue($owner->verify('a-decent-long-password'), 'the owner can sign in with that password');
		$this->assertSame(1, $storage->countUsers(), 'exactly one account was created');

		$this->assertSame(1, count($storage->allPages()), 'a single welcome page was created');
		$this->assertSame($owner->id, $storage->allPages()[0]->authorId, 'the welcome page belongs to the owner');

		$this->assertTrue(is_file($root . '/data/.htaccess'), 'the data folder got a deny-all .htaccess');
		$this->assertTrue(
			str_contains((string) file_get_contents($root . '/data/.htaccess'), 'Require all denied'),
			'the data folder denies all requests',
		);
		$uploads = (string) file_get_contents($root . '/data/uploads/.htaccess');
		$this->assertFalse(
			str_contains($uploads, "\nRequire all denied"),
			'uploads are not denied wholesale, because images must be servable',
		);
		$this->assertTrue(
			str_contains($uploads, 'php_flag engine off') && str_contains($uploads, 'RemoveHandler'),
			'uploads refuse to execute anything',
		);
		$this->assertTrue(is_file($root . '/data/settings/config.php'), 'the config file was written');
		$this->assertSame(
			'0644',
			substr(sprintf('%o', fileperms($root . '/data/settings/config.php')), -4),
			'the config file is not world-writable, unlike Pluck 4',
		);

		if ($driver === 'flatfile') {
			$pageFile = $root . '/data/content/pages/welcome.json';
			$this->assertTrue(is_file($pageFile), 'the page is a json file');
			$raw = (string) file_get_contents($pageFile);
			$this->assertFalse(str_contains($raw, '<?php'), 'a stored page contains no php');
			$this->assertTrue(json_decode($raw, true) !== null, 'a stored page is valid json');
		}
	}

	/**
	 * @param array<string,string> $overrides
	 * @return array<string,string>
	 */
	private function input(array $overrides = []): array
	{
		return array_merge([
			'site_title' => 'Test Site',
			'storage' => DriverFactory::FLAT_FILE,
			'username' => 'bas',
			'password' => 'a-decent-long-password',
			'email' => 'bas@example.org',
			'timezone' => 'Europe/Amsterdam',
			'language' => 'en',
		], $overrides);
	}
}
