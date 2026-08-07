<?php
declare(strict_types=1);

namespace Pluck\Storage;

use Pluck\Model\Page;
use Pluck\Model\User;

/**
 * The single seam between Pluck and its data.
 *
 * Nothing outside src/Storage may touch the filesystem or a database for
 * content, settings, users or module data. That rule is what makes the
 * installer's flat-file / SQLite choice possible, and it is the main thing to
 * enforce in review: Pluck 4 called glob() and fopen() from everywhere, which
 * is exactly why it could never grow a second backend.
 *
 * Both drivers must behave identically. tests/StorageParityTest.php runs the
 * same suite against both and is the contract.
 */
interface StorageDriver
{
	public function name(): string;

	/** Create tables/directories. Idempotent. */
	public function install(): void;

	public function isInstalled(): bool;

	// ---- settings -------------------------------------------------------

	public function getSetting(string $key, mixed $default = null): mixed;

	public function setSetting(string $key, mixed $value): void;

	/** @return array<string,mixed> */
	public function allSettings(): array;

	public function deleteSetting(string $key): void;

	// ---- pages ----------------------------------------------------------

	public function findPage(string $path): ?Page;

	public function pageExists(string $path): bool;

	/**
	 * Direct children of $parent (null = top level), ordered by `order` then title.
	 *
	 * @return list<Page>
	 */
	public function listPages(?string $parent = null, bool $includeHidden = true): array;

	/**
	 * Every page in the install, depth-first in menu order. Used by the sitemap,
	 * the search indexer and the migrator.
	 *
	 * @return list<Page>
	 */
	public function allPages(bool $includeHidden = true): array;

	public function savePage(Page $page): void;

	/** Moves the page and its children into the trash. */
	public function deletePage(string $path): void;

	/** Re-parent and/or rename, children moving along. */
	public function movePage(string $from, string $to): void;

	/** Shift a page up (-1) or down (+1) among its siblings. */
	public function reorderPage(string $path, int $delta): void;

	// ---- users ----------------------------------------------------------

	public function findUser(string $id): ?User;

	public function findUserByUsername(string $username): ?User;

	/** @return list<User> */
	public function listUsers(): array;

	public function saveUser(User $user): void;

	public function deleteUser(string $id): void;

	public function countUsers(): int;

	// ---- module data ----------------------------------------------------

	public function getModuleData(string $module, string $key, mixed $default = null): mixed;

	public function setModuleData(string $module, string $key, mixed $value): void;

	/** @return array<string,mixed> key => value, keys sorted */
	public function listModuleData(string $module, string $keyPrefix = ''): array;

	public function deleteModuleData(string $module, string $key): void;

	public function deleteModule(string $module): void;

	// ---- misc -----------------------------------------------------------

	/**
	 * Run $work as one unit. SQLite gets a real transaction; the flat-file
	 * driver takes a global write lock, which is enough for the handful of
	 * concurrent admins this CMS is built for.
	 *
	 * @template T
	 * @param callable():T $work
	 * @return T
	 */
	public function transaction(callable $work): mixed;
}
