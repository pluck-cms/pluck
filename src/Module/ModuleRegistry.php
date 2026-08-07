<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\Auth\AccessList;
use Pluck\Http\Router;
use Pluck\Model\Role;

/**
 * The modules this install has, and which one owns an address.
 *
 * Deliberately a plain list built in code rather than a scan of a directory:
 * "drop a folder in and it runs" is how Pluck 4 shipped and is exactly the
 * property that made a compromised install so easy to keep. Third-party modules
 * arrive through the installer, which is where the archive policy applies.
 */
final class ModuleRegistry
{
	/** @var array<string,SiteModule> mount path => module */
	private array $byMount = [];

	/** @var array<string,AdminModule> name => module */
	private array $admin = [];

	/** @param list<SiteModule|AdminModule> $modules */
	public function __construct(array $modules = [])
	{
		foreach ($modules as $module) {
			$this->add($module);
		}
	}

	/**
	 * Addresses the site itself answers to, which a module may not take.
	 *
	 * Short, and it should stay short. Every name here is one a module cannot
	 * have, and a list that grows is a list that eventually collides with
	 * something someone already installed.
	 */
	public const RESERVED = ['search'];

	public function add(SiteModule|AdminModule $module): void
	{
		if ($module instanceof SiteModule) {
			if (in_array(trim($module->mountPath(), '/'), self::RESERVED, true)) {
				throw new \RuntimeException(sprintf(
					'The module "%s" wants to mount at an address the site itself answers to.',
					$module->name(),
				));
			}

			$this->byMount[trim($module->mountPath(), '/')] = $module;
		}

		// A module may be both, in which case it is registered twice on purpose:
		// the two halves are looked up by different keys, a mount path and a name,
		// and conflating them would mean a module without a site presence could
		// not have an admin screen.
		if ($module instanceof AdminModule) {
			$this->admin[$module->name()] = $module;
		}
	}

	/** @return list<SiteModule> */
	public function all(): array
	{
		return array_values($this->byMount);
	}

	/** @return list<AdminModule> */
	public function adminModules(): array
	{
		return array_values($this->admin);
	}

	public function adminModule(string $name): ?AdminModule
	{
		return $this->admin[$name] ?? null;
	}

	/**
	 * Let every module contribute its admin routes to the table the rest of the
	 * admin uses, so the same tests cover them.
	 */
	public function registerAdminRoutes(Router $router): void
	{
		foreach ($this->admin as $module) {
			$module->adminRoutes($router);
		}
	}

	/**
	 * Navigation entries for the modules this account may manage.
	 *
	 * Filtered here rather than in the template, because a menu entry that leads
	 * to a page the person is refused is worse than no entry at all.
	 *
	 * @return list<array{route:string,label:string,permission:string}>
	 */
	public function navigation(Role $role, ?AccessList $list = null): array
	{
		$entries = [];
		foreach ($this->admin as $module) {
			$entry = $module->navigation();
			if ($entry !== null && ModulePermission::allows($role, $module->name(), $list)) {
				$entries[] = $entry;
			}
		}

		usort($entries, static fn (array $a, array $b): int => $a['label'] <=> $b['label']);

		return $entries;
	}

	public function has(string $mount): bool
	{
		return isset($this->byMount[trim($mount, '/')]);
	}

	/**
	 * The module that owns $path, and the part of the path left over for it.
	 *
	 * Only the first segment is a mount point. A module owning "blog" is asked
	 * about /blog and /blog/anything, but never about /news/blog — nesting
	 * modules under pages would make the address of a post depend on where an
	 * editor moved a page to, and old links would break every time.
	 *
	 * @return array{0:SiteModule,1:string}|null
	 */
	public function resolve(string $path): ?array
	{
		$path = trim($path, '/');
		if ($path === '') {
			return null;
		}

		$slash = strpos($path, '/');
		$mount = $slash === false ? $path : substr($path, 0, $slash);
		$rest = $slash === false ? '' : substr($path, $slash + 1);

		$module = $this->byMount[$mount] ?? null;

		return $module === null ? null : [$module, $rest];
	}
}
