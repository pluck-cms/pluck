<?php
declare(strict_types=1);

namespace Pluck\Auth;

use Pluck\Model\User;
use Pluck\Module\ModuleRegistry;
use Pluck\Security\Session;
use Pluck\Storage\StorageDriver;

/**
 * Sign-in and permission checks.
 *
 * Version 4 had one password in a file and a session flag; anyone who could set
 * that flag was the administrator. Here the session holds a user id and a
 * fingerprint, and the user is re-read from storage on every request, so
 * deactivating an account or changing a role takes effect immediately.
 */
final class Auth
{
	private const KEY_USER = '_pluck_uid';
	private const KEY_PRINT = '_pluck_print';
	private const KEY_SINCE = '_pluck_since';
	private const KEY_PENDING = '_pluck_2fa_pending';

	/** Re-authenticate after this long, whatever the cookie lifetime says. */
	private const MAX_SESSION_SECONDS = 43200;

	/**
	 * Salt and digest of a bcrypt hash, without its `$2y$cc$` prefix. The digest
	 * belongs to a string nobody knows and nothing needs it to be the digest of
	 * anything: this is only ever verified against, and must never match.
	 */
	private const DUMMY_BODY = 'e.yeiJzc0fyVkrXqPec4Xe7zoMmeDneRSdOE5c7XNly2RGVTv89Hy';

	private ?User $user = null;
	private bool $resolved = false;

	public function __construct(
		private readonly StorageDriver $storage,
		private readonly Session $session,
		private readonly Throttle $throttle,
		private readonly ?ModuleRegistry $modules = null,
	) {
	}

	/**
	 * The access list, loaded once per request.
	 *
	 * Every permission question in the admin arrives here eventually — the router
	 * guards, the controllers, the navigation — so this is the one place the grid
	 * has to be consulted, and the one place a mistake in it would be felt.
	 */
	public function accessList(): AccessList
	{
		return $this->accessList ??= AccessList::load($this->storage, $this->modules);
	}

	private ?AccessList $accessList = null;

	public function user(): ?User
	{
		if ($this->resolved) {
			return $this->user;
		}
		$this->resolved = true;

		$id = $this->session->get(self::KEY_USER);
		if (!is_string($id) || $id === '') {
			return null;
		}

		$since = (int) $this->session->get(self::KEY_SINCE, 0);
		if ($since > 0 && $since < time() - self::MAX_SESSION_SECONDS) {
			$this->logout();
			return null;
		}

		if (!hash_equals((string) $this->session->get(self::KEY_PRINT, ''), $this->fingerprint())) {
			// The cookie is being replayed from a different client.
			$this->logout();
			return null;
		}

		$user = $this->storage->findUser($id);
		if ($user === null || !$user->active) {
			$this->logout();
			return null;
		}

		return $this->user = $user;
	}

	public function check(): bool
	{
		return $this->user() !== null;
	}

	/**
	 * Try a username and password, and a TOTP code when the account has 2FA on.
	 *
	 * The result is an enum rather than a bool so the controller can tell the
	 * difference between "wrong password" (say nothing useful) and "code needed"
	 * (ask for it), without the caller inventing its own convention.
	 */
	/**
	 * A hash to verify against when the username does not exist, so that an
	 * unknown account costs the same as a real one.
	 *
	 * The cost is read at runtime instead of being baked into a constant. PHP
	 * 8.4 raised the bcrypt default from 10 to 12, and one hardcoded string
	 * cannot be right on both sides of that: on 8.4 the old cost-10 hash made an
	 * unknown username roughly four times faster than a known one, which is
	 * precisely the enumeration tell this exists to close.
	 *
	 * Only the cost in the prefix has to be right. password_verify() runs the
	 * full key derivation at whatever cost the string declares and then compares
	 * it against a digest that will never match — both of which is what we want.
	 * Should a future PHP make something other than bcrypt the default, this
	 * falls back to hashing once and keeping the result for the process.
	 */
	public static function dummyHash(): string
	{
		static $hash = null;

		if ($hash !== null) {
			return $hash;
		}

		if (PASSWORD_DEFAULT === PASSWORD_BCRYPT && defined('PASSWORD_BCRYPT_DEFAULT_COST')) {
			return $hash = sprintf('$2y$%02d$%s', PASSWORD_BCRYPT_DEFAULT_COST, self::DUMMY_BODY);
		}

		return $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
	}

	public function attempt(string $username, string $password, string $totpCode = ''): AttemptResult
	{
		$key = $this->throttle->key($this->clientIp(), $username);

		if ($this->throttle->isLocked($key)) {
			return AttemptResult::Locked;
		}

		$user = $this->storage->findUserByUsername($username);

		// Spend the same work on an unknown username as on a real one, so response
		// time does not reveal which accounts exist. The dummy has to be a valid
		// hash at the same cost as a real one: an invalid string fails in
		// microseconds, and a higher cost is just as much of a tell in the other
		// direction. AuthTest checks that the cost still matches PASSWORD_DEFAULT.
		$hash = $user?->passwordHash ?? self::dummyHash();
		$passwordOk = password_verify($password, $hash);

		if ($user === null || !$passwordOk) {
			$this->throttle->hit($key);
			return AttemptResult::BadCredentials;
		}

		if (!$user->active) {
			$this->throttle->hit($key);
			return AttemptResult::Inactive;
		}

		if ($user->totpSecret !== null && $user->totpSecret !== '') {
			if ($totpCode === '') {
				// Remember who cleared the password step so the second screen does
				// not need the password again, but do not sign anyone in yet.
				$this->session->set(self::KEY_PENDING, $user->id);
				return AttemptResult::TotpRequired;
			}
			if (!Totp::verify($user->totpSecret, $totpCode)) {
				$this->throttle->hit($key);
				return AttemptResult::TotpInvalid;
			}
		}

		if ($user->needsRehash()) {
			$user->setPassword($password);
		}

		$this->throttle->clear($key);
		$this->completeLogin($user);

		return AttemptResult::Ok;
	}

	/** The account waiting on a TOTP code, if the password step just succeeded. */
	public function pendingTwoFactorUser(): ?User
	{
		$id = $this->session->get(self::KEY_PENDING);

		return is_string($id) && $id !== '' ? $this->storage->findUser($id) : null;
	}

	/** Second step of a 2FA sign-in. */
	public function completeTwoFactor(string $code): AttemptResult
	{
		$user = $this->pendingTwoFactorUser();
		if ($user === null) {
			return AttemptResult::BadCredentials;
		}

		$key = $this->throttle->key($this->clientIp(), $user->username);
		if ($this->throttle->isLocked($key)) {
			return AttemptResult::Locked;
		}

		if ($user->totpSecret === null || !Totp::verify($user->totpSecret, $code)) {
			$this->throttle->hit($key);
			return AttemptResult::TotpInvalid;
		}

		$this->throttle->clear($key);
		$this->session->forget(self::KEY_PENDING);
		$this->completeLogin($user);

		return AttemptResult::Ok;
	}

	public function logout(): void
	{
		$this->user = null;
		$this->resolved = true;
		$this->session->destroy();
	}

	/**
	 * Whether the signed-in account may do something.
	 *
	 * Asks the access list rather than the role directly. The role is still the
	 * answer for anything an owner has not decided about, which is most things on
	 * most installs — see AccessList.
	 */
	public function can(string $permission): bool
	{
		$user = $this->user();

		return $user === null ? false : $this->accessList()->allows($user->role, $permission);
	}

	/** True when the signed-in user may act on a page owned by $authorId. */
	public function canEditPage(?string $authorId): bool
	{
		$user = $this->user();
		if ($user === null) {
			return false;
		}
		if ($this->can('page.edit')) {
			return true;
		}

		return $this->can('page.edit.own') && $authorId !== null && $authorId === $user->id;
	}

	public function canDeletePage(?string $authorId): bool
	{
		$user = $this->user();
		if ($user === null) {
			return false;
		}
		if ($this->can('page.delete')) {
			return true;
		}

		return $this->can('page.delete.own') && $authorId !== null && $authorId === $user->id;
	}

	private function completeLogin(User $user): void
	{
		// New id before anything user-specific goes into the session: this is what
		// stops a fixated session cookie from becoming an authenticated one.
		$this->session->regenerate();
		$this->session->set(self::KEY_USER, $user->id);
		$this->session->set(self::KEY_PRINT, $this->fingerprint());
		$this->session->set(self::KEY_SINCE, time());

		$user->lastLoginAt = gmdate('c');
		$this->storage->saveUser($user);

		$this->user = $user;
		$this->resolved = true;
	}

	/**
	 * A weak binding between the session and the client. It is not a security
	 * boundary on its own — a stolen cookie usually comes with the user agent —
	 * but it costs nothing and blocks the lazier replay.
	 */
	private function fingerprint(): string
	{
		$agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

		return hash('sha256', is_string($agent) ? $agent : '');
	}

	private function clientIp(): string
	{
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';

		return is_string($ip) ? $ip : '';
	}
}
