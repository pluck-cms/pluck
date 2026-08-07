<?php
declare(strict_types=1);

namespace Pluck\Model;

/**
 * Four roles, deliberately few. Pluck 4 had a single shared password; the point
 * of v5 is that a site owner can hand out an account without handing over the
 * whole install.
 */
enum Role: string
{
	case Owner  = 'owner';   // everything, including users and updates. Cannot be demoted by others.
	case Admin  = 'admin';   // everything except managing owners.
	case Editor = 'editor';  // all content and module settings, no site settings or users.
	case Author = 'author';  // own content only.

	public function label(): string
	{
		return match ($this) {
			self::Owner  => 'Owner',
			self::Admin  => 'Administrator',
			self::Editor => 'Editor',
			self::Author => 'Author',
		};
	}

	/** @return list<string> */
	public function permissions(): array
	{
		return match ($this) {
			self::Owner => ['*'],
			self::Admin => [
				'page.*', 'module.*', 'theme.*', 'file.*', 'settings.*',
				'user.view', 'user.create', 'user.edit', 'update.run', 'trash.*',
			],
			self::Editor => ['page.*', 'module.manage', 'file.*', 'theme.view', 'trash.view', 'trash.restore'],
			self::Author => ['page.view', 'page.create', 'page.edit.own', 'page.delete.own', 'file.upload', 'file.view'],
		};
	}

	public function can(string $permission): bool
	{
		foreach ($this->permissions() as $granted) {
			if ($granted === '*' || $granted === $permission) {
				return true;
			}
			if (str_ends_with($granted, '.*') && str_starts_with($permission, substr($granted, 0, -1))) {
				return true;
			}
		}

		return false;
	}
}
