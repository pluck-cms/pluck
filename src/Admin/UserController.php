<?php
declare(strict_types=1);

namespace Pluck\Admin;

use Pluck\Model\Role;
use Pluck\Model\User;

final class UserController extends Controller
{
	private const USERNAME_PATTERN = '/^[a-zA-Z0-9._\-]{3,32}$/';
	private const MIN_PASSWORD = 12;

	public function index(): never
	{
		$users = $this->c->storage->listUsers();
		usort($users, static fn (User $a, User $b): int => strcasecmp($a->username, $b->username));

		$this->render('admin/users/index', [
			'title' => $this->t('user.title.people'),
			'users' => $users,
			'canCreate' => $this->c->auth->can('user.create'),
			'me' => $this->c->auth->user(),
		]);
	}

	public function create(): never
	{
		$this->render('admin/users/form', [
			'title' => $this->t('user.title.add_someone'),
			'user' => null,
			'roles' => $this->assignableRoles(),
			'isNew' => true,
		]);
	}

	public function edit(): never
	{
		$user = $this->findOr404($this->c->request->query('id'));

		if (!$this->maySeeAndTouch($user)) {
			$this->c->flash->stop($this->t('user.flash.only_owner_can_change_owner_account'));
			$this->back('users');
		}

		$this->render('admin/users/form', [
			'title' => $this->t('user.title.edit') . $user->username,
			'user' => $user,
			'roles' => $this->assignableRoles(),
			'isNew' => false,
		]);
	}

	public function save(): never
	{
		$request = $this->c->request;
		$id = $request->post('id');
		$existing = $id !== '' ? $this->c->storage->findUser($id) : null;

		if ($id !== '' && $existing === null) {
			$this->c->flash->stop($this->t('user.flash.account_no_longer_exists'));
			$this->back('users');
		}

		if ($existing !== null && !$this->maySeeAndTouch($existing)) {
			$this->c->flash->stop($this->t('user.flash.only_owner_can_change_owner_account'));
			$this->back('users');
		}

		if ($existing === null && !$this->c->auth->can('user.create')) {
			$this->c->flash->stop($this->t('user.flash.account_cannot_add_people'));
			$this->back('users');
		}

		$username = $request->post('username');
		if (!preg_match(self::USERNAME_PATTERN, $username)) {
			$this->c->flash->stop($this->t('user.flash.username_3_32_characters_using_letters'));
			$this->backToForm($existing);
		}

		$clash = $this->c->storage->findUserByUsername($username);
		if ($clash !== null && $clash->id !== ($existing->id ?? '')) {
			$this->c->flash->stop($this->t('user.flash.someone_already_has_username'));
			$this->backToForm($existing);
		}

		$role = Role::tryFrom($request->post('role')) ?? Role::Author;
		if (!$this->mayAssign($role)) {
			$this->c->flash->stop($this->t('user.flash.only_owner_can_hand_out_owner'));
			$this->backToForm($existing);
		}

		$password = $request->raw('password');

		if ($existing === null) {
			if (strlen($password) < self::MIN_PASSWORD) {
				$this->c->flash->stop($this->t('user.flash.new_account_needs_password_of_length', ['count' => self::MIN_PASSWORD]));
				$this->backToForm(null);
			}
			$user = User::create($username, $password, $role, $request->post('email'));
			$user->displayName = $request->post('display_name') !== '' ? $request->post('display_name') : $username;
			$user->mustChangePassword = $request->postBool('must_change');
		} else {
			$user = $existing;
			$user->username = $username;
			$user->email = $request->post('email');
			$user->displayName = $request->post('display_name') !== '' ? $request->post('display_name') : $username;

			if ($password !== '') {
				if (strlen($password) < self::MIN_PASSWORD) {
					$this->c->flash->stop($this->t('user.flash.password_needs_length', ['count' => self::MIN_PASSWORD]));
					$this->backToForm($existing);
				}
				$user->setPassword($password);
			}

			$this->applyRoleAndActive($user, $role, $request->postBool('active'));
		}

		$this->c->storage->saveUser($user);
		$this->c->flash->ok($existing === null ? 'Account added.' : 'Account saved.');
		$this->back('users');
	}

	public function delete(): never
	{
		$user = $this->findOr404($this->c->request->post('id'));
		$me = $this->c->auth->user();

		if ($me !== null && $me->id === $user->id) {
			$this->c->flash->stop($this->t('user.flash.you_cannot_delete_account_you_signed'));
			$this->back('users');
		}

		if (!$this->maySeeAndTouch($user)) {
			$this->c->flash->stop($this->t('user.flash.only_owner_can_remove_owner_account'));
			$this->back('users');
		}

		if ($user->role === Role::Owner && $this->countOwners() <= 1) {
			$this->c->flash->stop($this->t('user.flash.last_owner_promote_someone_else_first'));
			$this->back('users');
		}

		$this->c->storage->deleteUser($user->id);
		$this->c->flash->ok($this->t('user.flash.account_removed_any_pages_they_wrote'));
		$this->back('users');
	}

	/**
	 * Role and active-flag changes have their own guards, because these are the two
	 * fields that can lock everyone out of a site.
	 */
	private function applyRoleAndActive(User $user, Role $role, bool $active): void
	{
		$me = $this->c->auth->user();
		$isSelf = $me !== null && $me->id === $user->id;
		$lastOwner = $user->role === Role::Owner && $this->countOwners() <= 1;

		if ($lastOwner && ($role !== Role::Owner || !$active)) {
			$this->c->flash->warn($this->t('user.flash.left_last_owner_account_as_site'));
			return;
		}

		if ($isSelf && $role !== $user->role) {
			$this->c->flash->warn($this->t('user.flash.left_own_role_alone_ask_another'));
		} else {
			$user->role = $role;
		}

		if ($isSelf && !$active) {
			$this->c->flash->warn($this->t('user.flash.you_cannot_switch_off_own_account'));
		} else {
			$user->active = $active;
		}
	}

	/** @return array<string, string> */
	private function assignableRoles(): array
	{
		$roles = [];
		foreach (Role::cases() as $role) {
			if ($this->mayAssign($role)) {
				$roles[$role->value] = $role->label();
			}
		}

		return $roles;
	}

	private function mayAssign(Role $role): bool
	{
		return $role !== Role::Owner || $this->c->auth->user()?->role === Role::Owner;
	}

	private function maySeeAndTouch(User $user): bool
	{
		return $user->role !== Role::Owner || $this->c->auth->user()?->role === Role::Owner;
	}

	private function countOwners(): int
	{
		return count(array_filter(
			$this->c->storage->listUsers(),
			static fn (User $u): bool => $u->role === Role::Owner && $u->active,
		));
	}

	private function backToForm(?User $existing): never
	{
		$existing === null
			? $this->back('user.new')
			: $this->back('user.edit', ['id' => $existing->id]);
	}

	private function findOr404(string $id): User
	{
		$user = $id !== '' ? $this->c->storage->findUser($id) : null;

		if ($user === null) {
			$this->c->flash->stop($this->t('user.flash.no_account_id'));
			$this->back('users');
		}

		return $user;
	}
}
