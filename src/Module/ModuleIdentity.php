<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\Model\Role;
use Pluck\Model\User;

/**
 * Who is signed in, as much of it as a module has any business knowing.
 *
 * A module often needs an author id and a display name — a blog post has both.
 * It does not need the password hash, the TOTP secret, the e-mail address or the
 * ability to write any of them back, and handing over the User object would give
 * it all four.
 *
 * So this is a copy of the three fields that have a legitimate use, and there is
 * deliberately no way back from here to the account it was made from.
 */
final class ModuleIdentity
{
	public function __construct(
		public readonly string $id,
		public readonly string $displayName,
		public readonly Role $role,
	) {
	}

	public static function of(User $user): self
	{
		return new self($user->id, $user->displayName, $user->role);
	}
}
