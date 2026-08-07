<?php
declare(strict_types=1);

namespace Pluck\Admin;

use Pluck\Auth\AccessList;
use Pluck\Auth\Permissions;
use Pluck\Model\Role;

/**
 * Who may do what.
 *
 * Owner-only, and that is a decision rather than caution. An administrator who
 * can edit this screen can grant themselves anything on it, which makes every
 * other permission on the install advisory. Delegating that is something an owner
 * should have to do deliberately, by making someone an owner.
 */
final class AccessController extends Controller
{
	public function show(): never
	{
		$this->requireOwner();

		$modules = $this->c->modules;
		$list = $this->c->auth->accessList();

		$rows = [];
		foreach (Permissions::groups($modules) as $group => $permissions) {
			foreach ($permissions as $permission) {
				$cells = [];
				foreach ($this->editableRoles() as $role) {
					$cells[$role->value] = [
						'ticked' => $list->isTicked($role, $permission),
						// A box an owner has actually decided about looks different
						// from one that is simply what the role was born with. Without
						// that, an empty grid and a grid someone filled in identically
						// are indistinguishable, and nobody can tell whether a setting
						// is deliberate.
						'explicit' => $list->isExplicit($role, $permission),
					];
				}

				$rows[] = [
					'group' => $group,
					'permission' => $permission,
					'label' => Permissions::labelKey($permission),
					'cells' => $cells,
				];
			}
		}

		$this->render('admin/access', [
			'title' => $this->t('acl.title.permissions'),
			'roles' => $this->editableRoles(),
			'rows' => $rows,
			'groups' => array_keys(Permissions::groups($modules)),
		]);
	}

	public function save(): never
	{
		$this->requireOwner();

		// One field per role rather than a nested array, so the request stays a
		// flat list of strings and nothing has to walk an arbitrary structure that
		// arrived from a form.
		// Every editable role gets a key, even an empty one: this form shows all of
		// them, so a role with nothing ticked means "nothing", not "unmentioned".
		$ticked = [];
		foreach ($this->editableRoles() as $role) {
			$ticked[$role->value] = $this->c->request->postList('permissions_' . $role->value);
		}

		// fromGrid() writes an entry for every permission on the screen, ticked or
		// not, because an unticked checkbox is simply absent from the request and
		// would otherwise be indistinguishable from a permission that was never
		// offered. Reading the catalogue instead of the request is what turns
		// "absent" into an honest "no".
		AccessList::fromGrid($ticked, $this->c->modules)->save($this->c->storage);

		$this->c->flash->ok($this->t('acl.flash.permissions_saved'));
		$this->back('access');
	}

	public function reset(): never
	{
		$this->requireOwner();

		$this->c->storage->setSetting(AccessList::SETTING, []);

		$this->c->flash->ok($this->t('acl.flash.back_to_defaults'));
		$this->back('access');
	}

	/**
	 * Every role but Owner.
	 *
	 * An owner is not on the grid because an owner may do everything, and a
	 * ticked-and-disabled row of boxes teaches people that some boxes lie.
	 *
	 * @return list<Role>
	 */
	private function editableRoles(): array
	{
		return array_values(array_filter(Role::cases(), static fn (Role $role): bool => $role !== Role::Owner));
	}

	private function requireOwner(): void
	{
		if ($this->c->auth->user()?->role !== Role::Owner) {
			$this->c->flash->stop($this->t('acl.flash.owners_only'));
			$this->back('dashboard');
		}
	}
}
