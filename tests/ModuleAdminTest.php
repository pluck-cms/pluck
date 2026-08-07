<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Http\Router;
use Pluck\Model\Role;
use Pluck\Module\AdminModule;
use Pluck\Module\BlogModule;
use Pluck\Module\ModuleContext;
use Pluck\Module\ModulePermission;
use Pluck\Module\ModuleRegistry;
use Pluck\Module\Modules;
use ReflectionClass;

/**
 * The admin half of the module contract.
 *
 * Two things are being pinned down here. That a module's routes are held to the
 * same rules as a core route — which is what registering them in the shared table
 * buys, and which is worth an explicit test because the day someone adds a second
 * router is the day that stops being true. And that ModuleContext is actually
 * narrow: an interface that documents what a module cannot reach is worth
 * nothing if the class quietly exposes it anyway.
 */
final class ModuleAdminTest extends TestCase
{
	public function run(): void
	{
		$this->group('permissions', fn () => $this->permissions());
		$this->group('routes join the shared table', fn () => $this->routes());
		$this->group('navigation', fn () => $this->navigation());
		$this->group('the narrowed context', fn () => $this->narrowing());
	}

	private function permissions(): void
	{
		$this->assertSame('module.blog.manage', ModulePermission::manage('blog'), 'a module permission is namespaced by module');

		// A name arriving from an uploaded archive must not be able to widen the
		// permission it ends up inside.
		$this->assertSame('module.blog.manage', ModulePermission::manage('BLOG'), 'case is normalised');
		$this->assertSame('module.evil.manage', ModulePermission::manage('e.v*i/l'), 'dots and stars are stripped, not honoured');
		$this->assertSame('module.unknown.manage', ModulePermission::manage('***'), 'a name with nothing usable left does not become a wildcard');

		$this->assertTrue(ModulePermission::allows(Role::Owner, 'blog'), 'an owner may manage a module');
		$this->assertTrue(ModulePermission::allows(Role::Admin, 'blog'), 'so may an administrator');
		$this->assertTrue(ModulePermission::allows(Role::Editor, 'blog'), 'an editor keeps the access they had in 4.x');
		$this->assertFalse(ModulePermission::allows(Role::Author, 'blog'), 'an author does not');

		// The hook for per-role, per-module access: the specific grant is asked
		// about first, so granting it later needs no change at any call site.
		$this->assertTrue(Role::Admin->can('module.blog.manage'), 'the specific permission is already answerable');
	}

	private function routes(): void
	{
		$module = new FakeAdminModule();
		$registry = new ModuleRegistry([$module]);

		$router = \Pluck\Admin\Routes::table($registry);

		$index = $router->find('GET', 'module.fake.index');
		$save = $router->find('POST', 'module.fake.save');

		$this->assertTrue($index !== null, 'a module GET route reaches the shared table');
		$this->assertTrue($save !== null, 'and so does a POST');
		$this->assertSame('fake', $save?->module, 'a module route is marked with the module that owns it');
		$this->assertSame(null, $router->find('GET', 'pages')?->module, 'a core route is not');
		$this->assertSame('module.fake.manage', $save?->permission, 'and is guarded by that module\'s permission');
		$this->assertFalse($save?->guest ?? true, 'a module route is never open to a signed-out visitor');

		// The point of one table: the existing surface tests walk it, so a module
		// cannot ship a state change that skips the CSRF check or the permission
		// guard. This asserts the property those tests rely on.
		foreach ($router->all() as $route) {
			if ($route->module === null || $route->method !== 'POST') {
				continue;
			}
			$this->assertTrue(
				$route->permission !== null,
				'every module POST route carries a permission: ' . $route->name,
			);
		}

		$this->assertTrue(
			\Pluck\Admin\Routes::table()->find('GET', 'module.fake.index') === null,
			'a table built without the registry has no module routes',
		);
	}

	private function navigation(): void
	{
		$registry = new ModuleRegistry([new FakeAdminModule()]);

		$this->assertSame(1, count($registry->navigation(Role::Owner)), 'an owner sees the module in the menu');
		$this->assertSame(
			[],
			$registry->navigation(Role::Author),
			'an account that may not manage it does not get a menu entry leading to a refusal',
		);

		$entry = $registry->navigation(Role::Owner)[0];
		$this->assertSame('module.fake.index', $entry['route'], 'the entry points at the module index');
		$this->assertTrue(
			str_contains($entry['label'], '.'),
			'the label is a translation key, not a finished string',
		);

		// A module may render on the site, in the admin, or both. The registry
		// keys those separately, by mount path and by name.
		$both = Modules::registry();
		// Blog, albums and the contact form. The contact form deliberately has no
		// admin entry of its own, so this count and the navigation count differ.
		$this->assertSame(3, count($both->all()), 'the bundled modules render on the site');
		$this->assertTrue($both->has('blog'), 'the blog is mounted for the site');
		$this->assertTrue($both->adminModule('blog') !== null, 'and both have an admin screen');
		$this->assertTrue($both->adminModule('albums') !== null, 'including albums');

		// The two halves are keyed separately, by mount path and by name, so a
		// module could have one without the other.
		$this->assertSame(
			['module.albums.index', 'module.blog.index'],
			array_column($both->navigation(Role::Owner), 'route'),
			'every module with an admin screen appears in the menu',
		);
		$this->assertSame([], $both->navigation(Role::Author), 'and none of them for an account that may not manage modules');
	}

	private function narrowing(): void
	{
		$methods = [];
		foreach ((new ReflectionClass(ModuleContext::class))->getMethods() as $method) {
			if ($method->isPublic()) {
				$methods[] = $method->getName();
			}
		}
		sort($methods);

		// Whatever a module can reach, it reaches through one of these. Adding to
		// this list is a deliberate widening of what every module on every install
		// may do, so it should be a decision rather than a diff nobody read.
		$this->assertSame(
			[
				'__construct', 'addMedia', 'back', 'can', 'delete', 'get', 'isAtLeast',
				'list', 'media', 'module', 'ownsMedia', 'removeMedia', 'render', 'set',
				't', 'transaction', 'user',
			],
			$methods,
			'ModuleContext exposes exactly the agreed surface',
		);

		// The narrowing that matters: there is no way to name another module's
		// data, correct or otherwise, because the module is fixed at construction
		// and is never an argument.
		foreach (['get', 'set', 'list', 'delete'] as $name) {
			$parameters = (new ReflectionClass(ModuleContext::class))->getMethod($name)->getParameters();
			$names = array_map(static fn ($p): string => $p->getName(), $parameters);
			$this->assertFalse(
				in_array('module', $names, true),
				$name . '() takes no module argument, so it cannot address another module',
			);
		}

		// And the things deliberately left out stay out.
		$properties = [];
		foreach ((new ReflectionClass(ModuleContext::class))->getProperties() as $property) {
			$type = (string) $property->getType();
			$properties[] = ltrim($type, '?');
		}

		// Media is shared with pages and with every other module, so write access
		// is scoped by ownership rather than by location: a module may add, and may
		// remove only what it added. Nothing here takes or returns a path.
		foreach (['addMedia', 'removeMedia', 'ownsMedia', 'media'] as $name) {
			$method = (new ReflectionClass(ModuleContext::class))->getMethod($name);
			$returns = (string) $method->getReturnType();
			$this->assertFalse(
				str_contains(strtolower($name . $returns), 'path'),
				$name . '() deals in names, not paths',
			);
		}

		foreach (['Pluck\Auth\Auth', 'Pluck\Bootstrap'] as $forbidden) {
			$this->assertFalse(
				in_array($forbidden, $properties, true),
				$forbidden . ' is not reachable from a module',
			);
		}
	}
}

/** A module that exists only to be registered, so the wiring can be tested. */
final class FakeAdminModule implements AdminModule
{
	public function name(): string
	{
		return 'fake';
	}

	public function adminRoutes(Router $router): void
	{
		$router->get('module.fake.index', BlogModule::class, 'index', ModulePermission::manage('fake'), module: 'fake');
		$router->post('module.fake.save', BlogModule::class, 'save', ModulePermission::manage('fake'), module: 'fake');
	}

	public function navigation(): ?array
	{
		return ['route' => 'module.fake.index', 'label' => 'nav.fake', 'permission' => ModulePermission::manage('fake')];
	}

	public function viewDir(): ?string
	{
		return null;
	}
}
