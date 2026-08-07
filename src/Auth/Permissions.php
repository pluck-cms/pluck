<?php
declare(strict_types=1);

namespace Pluck\Auth;

use Pluck\Module\ModulePermission;
use Pluck\Module\ModuleRegistry;

/**
 * Every permission the access list may speak about.
 *
 * A closed list, and that is the point. An access list that can hold any string
 * is one where a typo produces a permission nothing ever asks about — the box is
 * ticked, the screen still refuses, and there is nothing to look at that explains
 * why. Everything here is a permission some route guard or `can()` call actually
 * consults; `AccessListTest` checks that claim against the source rather than
 * trusting this comment.
 *
 * Wildcards are deliberately absent. `page.*` is a fine thing for a role to be
 * born with and a terrible thing to grant from a screen, because the person
 * ticking it cannot see what it will come to include.
 */
final class Permissions
{
	/**
	 * Groups of concrete permissions, in the order the screen shows them.
	 *
	 * @return array<string,list<string>> group label key => permissions
	 */
	public static function groups(?ModuleRegistry $modules = null): array
	{
		$groups = [
			'acl.group.pages' => [
				'page.view',
				'page.create',
				'page.edit',
				'page.edit.own',
				'page.delete',
				'page.delete.own',
			],
			'acl.group.media' => [
				'file.view',
				'file.upload',
				'file.delete',
			],
			'acl.group.people' => [
				'user.view',
				'user.create',
				'user.edit',
				'user.delete',
			],
			'acl.group.site' => [
				'settings.view',
				'settings.edit',
				'theme.view',
				'update.run',
			],
		];

		$modulePermissions = [];
		foreach ($modules?->adminModules() ?? [] as $module) {
			$modulePermissions[] = ModulePermission::manage($module->name());
		}

		if ($modulePermissions !== []) {
			sort($modulePermissions);
			$groups['acl.group.modules'] = $modulePermissions;
		}

		return $groups;
	}

	/** @return list<string> every permission, flat */
	public static function all(?ModuleRegistry $modules = null): array
	{
		return array_merge(...array_values(self::groups($modules)));
	}

	public static function isKnown(string $permission, ?ModuleRegistry $modules = null): bool
	{
		return in_array($permission, self::all($modules), true);
	}

	/**
	 * The label key for a permission.
	 *
	 * Derived rather than tabulated, so adding a module permission needs no entry
	 * here — module rows are labelled by the module's own name, which is the only
	 * part a person recognises.
	 */
	public static function labelKey(string $permission): string
	{
		return 'acl.permission.' . str_replace('.', '_', $permission);
	}
}
