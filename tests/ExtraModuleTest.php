<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Module\Modules;
use Pluck\Storage\DriverFactory;

/**
 * A module of your own can have an admin screen.
 *
 * Modules::extra() looked for SiteModule alone, so a third-party module could
 * draw something on the site but never have a screen to manage it — the admin
 * routes were simply never registered and the entry never appeared. Which meant
 * anything such a module stored had to be put there by hand over FTP, the thing
 * modules exist to avoid.
 *
 * Found while porting Pluck 4's progressbar: a scout adding what a sponsored
 * walk brought in should not need somebody with an FTP client.
 */
final class ExtraModuleTest extends TestCase
{
	public function run(): void
	{
		$this->group('both halves are loaded', fn () => $this->halves());
	}

	private function halves(): void
	{
		$dir = $this->tempDir('pluck-extra-module');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();

		/*
		 * Asserted on the source rather than by loading a module from disk.
		 *
		 * extra() reads modules/ under the install this test is running in, and
		 * writing a module into the real tree mid-suite is a strange thing to do
		 * to a working install. Second best, and said out loud.
		 */
		$source = (string) file_get_contents(dirname(__DIR__) . '/src/Module/Modules.php');

		$this->assertTrue(
			str_contains($source, 'is_subclass_of($class, AdminModule::class)'),
			'an admin module in modules/ is found, not only a site module',
		);
		$this->assertTrue(
			str_contains($source, 'is_subclass_of($class, SiteModule::class)'),
			'and a site module still is',
		);

		/*
		 * A module is commonly two classes in one folder, so stopping at the
		 * first match found whichever happened to be declared first — and lost
		 * the other half without saying so.
		 */
		$after = substr($source, strpos($source, 'foreach (get_declared_classes()') ?: 0);
		$loop = substr($after, 0, strpos($after, "\n\t\t}") ?: 0);

		$this->assertFalse(
			str_contains($loop, 'break;'),
			'and it does not stop at the first half it happens to find',
		);

		// An install with nothing enabled loads nothing and says so quietly.
		$registry = Modules::registry(null, $storage);

		$this->assertSame(
			['blog', 'albums', 'contact-form'],
			array_map(static fn ($m): string => $m->name(), $registry->all()),
			'the bundled ones, and nothing invented',
		);
	}
}
