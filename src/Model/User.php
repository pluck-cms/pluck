<?php
declare(strict_types=1);

namespace Pluck\Model;

final class User
{
	public function __construct(
		public string $id,
		public string $username,
		public string $passwordHash,
		public Role $role = Role::Author,
		public string $displayName = '',
		public string $email = '',
		public bool $active = true,
		public ?string $totpSecret = null,
		public ?string $createdAt = null,
		public ?string $lastLoginAt = null,
		public bool $mustChangePassword = false,
		/**
		 * Preferred admin language, or null to follow the site setting. 4.x had one
		 * global langpref because it had one admin; with several accounts a Dutch
		 * owner and a Polish editor are a normal situation.
		 */
		public ?string $language = null,
	) {
		$this->displayName = $displayName !== '' ? $displayName : $username;
		$this->createdAt ??= gmdate('c');
	}

	public static function create(string $username, string $plainPassword, Role $role, string $email = ''): self
	{
		return new self(
			id: bin2hex(random_bytes(8)),
			username: $username,
			passwordHash: password_hash($plainPassword, PASSWORD_DEFAULT),
			role: $role,
			email: $email,
		);
	}

	public function verify(string $plainPassword): bool
	{
		return password_verify($plainPassword, $this->passwordHash);
	}

	public function needsRehash(): bool
	{
		return password_needs_rehash($this->passwordHash, PASSWORD_DEFAULT);
	}

	public function setPassword(string $plainPassword): void
	{
		$this->passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
		$this->mustChangePassword = false;
	}

	public function can(string $permission): bool
	{
		return $this->active && $this->role->can($permission);
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'username' => $this->username,
			'password_hash' => $this->passwordHash,
			'role' => $this->role->value,
			'display_name' => $this->displayName,
			'email' => $this->email,
			'active' => $this->active,
			'totp_secret' => $this->totpSecret,
			'created_at' => $this->createdAt,
			'last_login_at' => $this->lastLoginAt,
			'must_change_password' => $this->mustChangePassword,
			'language' => $this->language,
		];
	}

	/** @param array<string,mixed> $row */
	public static function fromArray(array $row): self
	{
		return new self(
			id: (string) ($row['id'] ?? bin2hex(random_bytes(8))),
			username: (string) ($row['username'] ?? ''),
			passwordHash: (string) ($row['password_hash'] ?? ''),
			role: Role::tryFrom((string) ($row['role'] ?? 'author')) ?? Role::Author,
			displayName: (string) ($row['display_name'] ?? ''),
			email: (string) ($row['email'] ?? ''),
			active: (bool) ($row['active'] ?? true),
			totpSecret: isset($row['totp_secret']) && $row['totp_secret'] !== '' ? (string) $row['totp_secret'] : null,
			createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
			lastLoginAt: isset($row['last_login_at']) && $row['last_login_at'] !== '' ? (string) $row['last_login_at'] : null,
			mustChangePassword: (bool) ($row['must_change_password'] ?? false),
			language: isset($row['language']) && $row['language'] !== '' ? (string) $row['language'] : null,
		);
	}
}
