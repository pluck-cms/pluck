<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Auth\AccessList;
use Pluck\Auth\Permissions;
use Pluck\Model\Role;
use Pluck\Module\ModulePermission;
use Pluck\Module\Modules;
use Pluck\Storage\DriverFactory;

/**
 * Per-role permissions, and the three things the screen must never be able to do.
 *
 * The resolution order is the design: owner, then an explicit entry, then the
 * role's own list. Most of these assertions are about the edges of that — an
 * empty list changing nothing, a deny outranking a role, and an owner staying an
 * owner however the grid is filled in.
 */
final class AccessListTest extends TestCase
{
	public function run(): void
	{
		$this->group('an empty list changes nothing', fn () => $this->empty());
		$this->group('grants and denials', fn () => $this->decisions());
		$this->group('the owner cannot be locked out', fn () => $this->owner());
		$this->group('what will not be stored', fn () => $this->refusals());
		$this->group('the catalogue matches the code', fn () => $this->catalogue());
		$this->group('module permissions', fn () => $this->modules());
		$this->group('round trip', fn () => $this->roundTrip());
	}

	private function empty(): void
	{
		$list = AccessList::empty();

		// The whole upgrade story: an install that never opens the screen behaves
		// exactly as it did before the screen existed.
		foreach (Role::cases() as $role) {
			foreach (Permissions::all() as $permission) {
				$this->assertSame(
					$role === Role::Owner ? true : $role->can($permission),
					$list->allows($role, $permission),
					sprintf('%s / %s falls back to the role', $role->value, $permission),
				);
			}
		}

		$this->assertFalse($list->isExplicit(Role::Editor, 'page.create'), 'and nothing counts as decided');
	}

	private function decisions(): void
	{
		$this->assertTrue(Role::Author->can('page.create'), 'an author creates pages by default');
		$this->assertFalse(Role::Author->can('user.view'), 'and does not see the people list');

		$list = AccessList::of([
			'author' => ['user.view' => true, 'page.create' => false],
		]);

		$this->assertTrue($list->allows(Role::Author, 'user.view'), 'a grant adds what the role lacks');
		$this->assertFalse($list->allows(Role::Author, 'page.create'), 'and a denial removes what it had');
		$this->assertTrue($list->isExplicit(Role::Author, 'page.create'), 'both count as decided');

		$this->assertTrue(
			$list->allows(Role::Editor, 'page.create'),
			'a decision about one role says nothing about another',
		);
		$this->assertTrue(
			$list->allows(Role::Author, 'page.view'),
			'and a decision about one permission says nothing about the next',
		);
	}

	private function owner(): void
	{
		// The one thing a permission screen must never be able to do is take away
		// the permission needed to open it again.
		$list = AccessList::of([
			'owner' => ['settings.edit' => false, 'user.view' => false, 'page.view' => false],
		]);

		foreach (Permissions::all() as $permission) {
			$this->assertTrue($list->allows(Role::Owner, $permission), 'an owner keeps ' . $permission);
		}

		$this->assertSame(
			[],
			$list->toArray(),
			'an entry for the owner is not merely ignored, it is not stored',
		);

		$fromGrid = AccessList::fromGrid(['owner' => [], 'editor' => ['page.view']]);
		$this->assertFalse(
			array_key_exists('owner', $fromGrid->toArray()),
			'and the grid does not write one either, however it is submitted',
		);

		// A grid covering one role must say nothing about the others. Taking the
		// roles from the enum rather than from the request denied everything to
		// every role the caller had not mentioned.
		$this->assertFalse(
			array_key_exists('author', $fromGrid->toArray()),
			'a partial grid leaves the roles it did not cover alone',
		);
		$this->assertTrue(
			$fromGrid->allows(Role::Author, 'page.create'),
			'so those roles keep their defaults',
		);
	}

	private function refusals(): void
	{
		$list = AccessList::of([
			'editor' => [
				'page.*' => true,
				'*' => true,
				'made.up.permission' => true,
				'page.view' => false,
			],
			'nonexistent-role' => ['page.view' => true],
		]);

		$stored = $list->toArray()['editor'] ?? [];

		$this->assertFalse(array_key_exists('page.*', $stored), 'a wildcard is not stored');
		$this->assertFalse(array_key_exists('*', $stored), 'nor is the broadest one of all');
		$this->assertFalse(
			array_key_exists('made.up.permission', $stored),
			'nor a permission nothing ever asks about, which would be a ticked box that does nothing',
		);
		$this->assertTrue(array_key_exists('page.view', $stored), 'while a real one is kept');
		$this->assertFalse(array_key_exists('nonexistent-role', $list->toArray()), 'an unknown role is dropped');

		// Cleaning happens on load as well as on save, so a hand-edited settings
		// file cannot introduce what the form refuses.
		$this->assertTrue($list->allows(Role::Editor, 'page.edit'), 'a stripped wildcard does not silently grant');
	}

	private function catalogue(): void
	{
		$root = dirname(__DIR__);
		$source = '';
		foreach (['src', 'views'] as $directory) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS),
			);
			foreach ($iterator as $file) {
				if ($file->isFile() && $file->getExtension() === 'php') {
					$source .= (string) file_get_contents($file->getPathname());
				}
			}
		}
		$source .= (string) file_get_contents($root . '/src/Admin/Routes.php');

		// Every box on the screen must correspond to something the code actually
		// consults. A permission nobody asks about is a ticked box with no effect
		// and nothing to look at that explains why.
		foreach (Permissions::all() as $permission) {
			$this->assertTrue(
				str_contains($source, "'" . $permission . "'"),
				$permission . ' is a permission the code really asks about',
			);
		}

		// Module permissions are built rather than written out, so grepping for the
		// literal proves nothing. What matters is stronger anyway: that the route
		// table really guards that module's screens with it.
		$modules = Modules::registry();
		$guards = [];
		foreach (\Pluck\Admin\Routes::table($modules)->all() as $route) {
			if ($route->permission !== null) {
				$guards[$route->permission] = true;
			}
		}

		foreach ($modules->adminModules() as $module) {
			$permission = ModulePermission::manage($module->name());
			$this->assertTrue(
				isset($guards[$permission]),
				$permission . ' guards a real route, so the box on the screen does something',
			);
		}

		foreach (Permissions::all($modules) as $permission) {
			$this->assertFalse(str_contains($permission, '*'), $permission . ' is concrete, not a wildcard');
		}
	}

	private function modules(): void
	{
		$modules = Modules::registry();

		$this->assertTrue(
			in_array('module.blog.manage', Permissions::all($modules), true),
			'a module with an admin screen appears in the catalogue',
		);

		// The point of the whole exercise: an editor who may manage one module and
		// not the other, which the role system alone cannot express.
		$list = AccessList::of([
			'editor' => ['module.blog.manage' => true, 'module.albums.manage' => false],
		], $modules);

		$this->assertTrue(ModulePermission::allows(Role::Editor, 'blog', $list), 'the editor keeps the blog');
		$this->assertFalse(ModulePermission::allows(Role::Editor, 'albums', $list), 'and loses the albums');

		$this->assertTrue(ModulePermission::allows(Role::Owner, 'albums', $list), 'an owner is unaffected');

		// Without a list the old behaviour stands, so nothing changed for installs
		// that never open the screen.
		$this->assertTrue(ModulePermission::allows(Role::Editor, 'albums'), 'and with no list at all, the role decides');
	}

	private function roundTrip(): void
	{
		$dir = $this->tempDir('pluck-acl');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();

		$modules = Modules::registry();

		// A submitted grid: the editor keeps two boxes and loses everything else.
		AccessList::fromGrid(['editor' => ['page.view', 'module.blog.manage']], $modules)->save($storage);

		$loaded = AccessList::load($storage, $modules);

		$this->assertTrue($loaded->allows(Role::Editor, 'page.view'), 'a ticked box survives the round trip');
		$this->assertTrue($loaded->allows(Role::Editor, 'module.blog.manage'), 'including a module one');

		// The reason fromGrid() writes every permission rather than only the ticked
		// ones: a checkbox the browser did not send is absent, not false, and
		// without this an untick would silently do nothing.
		$this->assertFalse($loaded->allows(Role::Editor, 'page.create'), 'and an unticked one is a real denial');
		$this->assertTrue($loaded->isExplicit(Role::Editor, 'page.create'), 'recorded as decided, not left to the role');

		$storage->setSetting(AccessList::SETTING, []);
		$this->assertTrue(
			AccessList::load($storage, $modules)->allows(Role::Editor, 'page.create'),
			'clearing the setting puts every role back to its default',
		);
	}
}
