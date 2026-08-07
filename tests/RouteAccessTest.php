<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Admin\Routes;
use Pluck\Model\Role;

/**
 * Which roles can reach which screens.
 *
 * This reads the same route table admin.php dispatches, so a permission changed
 * on a route shows up here as a changed expectation rather than as a surprise in
 * production. It exists because of a real bug: the overview screen offered every
 * account a "Manage people" shortcut, and an author who clicked it got a 403 —
 * the guard was right, the interface was lying about it.
 */
final class RouteAccessTest extends TestCase
{
	public function run(): void
	{
		$table = Routes::table();

		foreach ([Role::Owner, Role::Admin, Role::Editor, Role::Author] as $role) {
			$this->group($role->value, function () use ($table, $role): void {
				$reachable = $this->reachable($table, $role);

				// Everyone signed in reaches the overview and their own account.
				$this->assertTrue(in_array('dashboard', $reachable, true), 'reaches the overview');
				$this->assertTrue(in_array('account', $reachable, true), 'reaches their own account');
				$this->assertTrue(in_array('pages', $reachable, true), 'reaches the page list');

				match ($role) {
					Role::Owner => $this->owner($reachable),
					Role::Admin => $this->admin($reachable),
					Role::Editor => $this->editor($reachable),
					Role::Author => $this->author($reachable),
				};
			});
		}

		$this->group('links to the site front controller', fn () => $this->frontLinks());
	}

	/**
	 * Every link into index.php uses the query key index.php actually reads.
	 *
	 * The page editor's Preview button linked to index.php?p=... while the front
	 * controller read ?page=..., so preview silently showed the front page of the
	 * site instead of the page being edited. Nothing threw, so only a person
	 * clicking it would ever find out.
	 */
	private function frontLinks(): void
	{
		$root = dirname(__DIR__);

		$front = (string) file_get_contents($root . '/index.php');
		$this->assertTrue(
			preg_match("/\\\$_GET\\['([a-z_]+)'\\]/", $front, $m) === 1,
			'index.php reads a page from the query string',
		);
		$key = $m[1] ?? '';
		$this->assertSame('page', $key, 'and reads it from ?page=');

		$wrong = [];
		foreach ($this->templates($root) as $file) {
			$source = (string) file_get_contents($file);
			if (preg_match_all('/index\\.php\\?([a-z_]+)=/', $source, $found) > 0) {
				foreach ($found[1] as $used) {
					if ($used !== $key) {
						$wrong[] = basename($file) . ': ?' . $used . '=';
					}
				}
			}
		}
		sort($wrong);

		$this->assertSame([], $wrong, 'every template links to index.php with the key it reads');
	}

	/** @return list<string> */
	private function templates(string $root): array
	{
		$files = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($root . '/views', \FilesystemIterator::SKIP_DOTS),
		);
		foreach ($iterator as $file) {
			if ($file->isFile() && $file->getExtension() === 'php') {
				$files[] = $file->getPathname();
			}
		}

		return $files;
	}

	/** @param list<string> $reachable */
	private function owner(array $reachable): void
	{
		foreach (['settings', 'settings.save', 'users', 'user.new', 'user.save', 'user.delete', 'media.upload'] as $route) {
			$this->assertTrue(in_array($route, $reachable, true), 'reaches ' . $route);
		}
	}

	/** @param list<string> $reachable */
	private function admin(array $reachable): void
	{
		foreach (['settings', 'settings.save', 'users', 'user.new', 'user.save'] as $route) {
			$this->assertTrue(in_array($route, $reachable, true), 'reaches ' . $route);
		}
		$this->assertFalse(in_array('user.delete', $reachable, true), 'cannot remove accounts');
	}

	/** @param list<string> $reachable */
	private function editor(array $reachable): void
	{
		$this->assertTrue(in_array('page.new', $reachable, true), 'reaches the new page screen');
		$this->assertTrue(in_array('media.upload', $reachable, true), 'may upload files');
		foreach (['settings', 'settings.save', 'users', 'user.new', 'user.save', 'user.delete'] as $route) {
			$this->assertFalse(in_array($route, $reachable, true), 'cannot reach ' . $route);
		}
	}

	/** @param list<string> $reachable */
	private function author(array $reachable): void
	{
		$this->assertTrue(in_array('page.new', $reachable, true), 'may start a page');
		$this->assertTrue(in_array('media.upload', $reachable, true), 'may upload files');
		foreach (['settings', 'settings.save', 'users', 'user.new', 'user.save', 'user.delete'] as $route) {
			$this->assertFalse(in_array($route, $reachable, true), 'cannot reach ' . $route);
		}
		// Ownership, not the route table, is what stops an author touching someone
		// else's page: page.save is reachable, and the controller checks the author.
		$this->assertTrue(in_array('page.save', $reachable, true), 'may save, subject to the ownership check');
	}

	/** @return list<string> */
	private function reachable(\Pluck\Http\Router $table, Role $role): array
	{
		$names = [];
		foreach ($table->all() as $route) {
			if ($route->guest) {
				continue;
			}
			if ($route->permission === null || $role->can($route->permission)) {
				$names[] = $route->name;
			}
		}

		return array_values(array_unique($names));
	}
}
