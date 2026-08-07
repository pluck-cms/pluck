<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\Auth\AccessList;
use Pluck\Model\Role;

/**
 * What a role is allowed to do with a module.
 *
 * There are two names for every module permission, and asking about the specific
 * one first is what makes a per-module access list possible later without
 * rewriting anything that calls this.
 *
 *   module.blog.manage   this module in particular
 *   module.manage        any module at all
 *
 * Today only the second is granted, by Role::Editor, so an editor who can manage
 * one module can manage all of them — which is the behaviour 4.x had and which
 * nobody has asked to keep. When per-role, per-module access arrives it grants
 * the specific name to a role and stops granting the broad one, and every call
 * site here keeps working unchanged.
 *
 * That is the whole reason this class exists rather than the strings being
 * written out at each route. A permission spelled by hand in nine places is a
 * permission that will be spelled wrong in one of them.
 */
final class ModulePermission
{
	/** The permission a screen for $module should be guarded by. */
	public static function manage(string $module): string
	{
		return 'module.' . self::safe($module) . '.manage';
	}

	/**
	 * Whether $role may manage $module.
	 *
	 * The specific grant wins; the broad one is the fallback. Once an access list
	 * exists it is consulted here, in front of the role, and nothing else changes.
	 */
	public static function allows(Role $role, string $module, ?AccessList $list = null): bool
	{
		if ($list !== null) {
			// The access list has the last word, and it answers about the specific
			// permission — which is what makes "may manage the blog but not the
			// albums" expressible. Its own fallback is the role, so nothing is lost
			// on an install that never opened the screen.
			return $list->allows($role, self::manage($module));
		}

		return $role->can(self::manage($module)) || $role->can('module.manage');
	}

	/**
	 * A module name reduced to what may appear in a permission. A module that
	 * arrived as an archive supplies its own name, and a name containing a dot
	 * or a star would otherwise widen a permission by being written into one.
	 */
	private static function safe(string $module): string
	{
		$safe = strtolower(preg_replace('/[^a-z0-9_-]/i', '', $module) ?? '');

		return $safe === '' ? 'unknown' : $safe;
	}
}
