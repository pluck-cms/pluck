<?php
declare(strict_types=1);

namespace Pluck\Auth;

use Pluck\Model\Role;
use Pluck\Module\ModuleRegistry;
use Pluck\Storage\StorageDriver;

/**
 * Which roles may do what, when an owner has said something about it.
 *
 * Roles ship with a built-in set of permissions, and for most installs that is
 * the whole story. This is the layer above: a grid an owner fills in, where a
 * ticked or cleared box decides, and anything left alone falls back to what the
 * role was born with.
 *
 * Falling back rather than replacing matters on upgrade. An install that has
 * never opened this screen has an empty list, and an empty list changes nothing —
 * so nobody's editors lose access because a feature was added.
 *
 * Three rules are not negotiable from the screen:
 *
 *   - An owner may do everything. Ticking boxes is how you lock yourself out of
 *     the screen that would let you unlock yourself, and the way back would be a
 *     text file on the server.
 *   - Only concrete permissions are stored. A wildcard is a fine thing for a role
 *     to be born with and a bad thing to grant from a form, because the person
 *     ticking the box cannot see what it will come to include.
 *   - Only permissions something actually consults. A stored string nothing asks
 *     about is a ticked box that does nothing, with no way to tell.
 */
final class AccessList
{
	public const SETTING = 'access_list';

	/** @param array<string,array<string,bool>> $entries role value => permission => allowed */
	private function __construct(
		private readonly array $entries,
		private readonly ?ModuleRegistry $modules = null,
	) {
	}

	public static function load(StorageDriver $storage, ?ModuleRegistry $modules = null): self
	{
		$stored = $storage->getSetting(self::SETTING, []);

		return new self(self::clean(is_array($stored) ? $stored : [], $modules), $modules);
	}

	/** @param array<string,array<string,bool>> $entries */
	public static function of(array $entries, ?ModuleRegistry $modules = null): self
	{
		return new self(self::clean($entries, $modules), $modules);
	}

	public static function empty(): self
	{
		return new self([]);
	}

	/**
	 * Whether $role may do $permission.
	 *
	 * The order is the whole design: owner, then an explicit entry, then the
	 * role's own list. A `false` in the grid is a real denial and outranks the
	 * role — that is what makes "an editor who may manage the blog but not the
	 * albums" expressible at all.
	 */
	public function allows(Role $role, string $permission): bool
	{
		if ($role === Role::Owner) {
			return true;
		}

		$entry = $this->entries[$role->value][$permission] ?? null;
		if ($entry !== null) {
			return $entry;
		}

		return $role->can($permission);
	}

	/** What the grid should show as ticked for $role. */
	public function isTicked(Role $role, string $permission): bool
	{
		return $this->allows($role, $permission);
	}

	/** Whether an owner has said anything at all about this box. */
	public function isExplicit(Role $role, string $permission): bool
	{
		return isset($this->entries[$role->value][$permission]);
	}

	/** @return array<string,array<string,bool>> */
	public function toArray(): array
	{
		return $this->entries;
	}

	public function save(StorageDriver $storage): void
	{
		$storage->setSetting(self::SETTING, $this->entries);
	}

	/**
	 * Build a list from a submitted grid.
	 *
	 * Every permission on the screen gets an entry, ticked or not, because a
	 * checkbox that is not sent by the browser is indistinguishable from one that
	 * was never on the form. Reading the full catalogue rather than the request
	 * is what turns "absent" into an honest "no".
	 *
	 * Only the roles the grid actually covered get entries. A caller that submits
	 * one column must not silently deny everything to the roles it said nothing
	 * about — which is exactly what happens if the roles are taken from the enum
	 * instead of from the request.
	 *
	 * @param array<string,list<string>> $ticked role value => permissions that were ticked
	 */
	public static function fromGrid(array $ticked, ?ModuleRegistry $modules = null): self
	{
		$entries = [];

		foreach (Role::cases() as $role) {
			if ($role === Role::Owner || !array_key_exists($role->value, $ticked)) {
				// Owner is never stored: an owner's permissions are not a setting.
				// A role the grid did not cover is left to its default.
				continue;
			}

			$chosen = array_flip($ticked[$role->value]);

			foreach (Permissions::all($modules) as $permission) {
				$entries[$role->value][$permission] = isset($chosen[$permission]);
			}
		}

		return new self($entries, $modules);
	}

	/**
	 * Drop anything that should never have been stored.
	 *
	 * Applied on the way in as well as on the way out, so a hand-edited settings
	 * file cannot introduce what the screen refuses.
	 *
	 * @param array<mixed> $raw
	 * @return array<string,array<string,bool>>
	 */
	private static function clean(array $raw, ?ModuleRegistry $modules): array
	{
		$clean = [];

		foreach ($raw as $roleValue => $permissions) {
			$role = is_string($roleValue) ? Role::tryFrom($roleValue) : null;
			if ($role === null || $role === Role::Owner || !is_array($permissions)) {
				continue;
			}

			foreach ($permissions as $permission => $allowed) {
				if (!is_string($permission) || str_contains($permission, '*')) {
					continue;
				}
				if (!Permissions::isKnown($permission, $modules)) {
					continue;
				}

				$clean[$role->value][$permission] = (bool) $allowed;
			}
		}

		return $clean;
	}
}
