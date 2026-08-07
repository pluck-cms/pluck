<?php
declare(strict_types=1);

namespace Pluck\Storage\Sqlite;

use PDO;
use PDOException;
use Pluck\Model\Page;
use Pluck\Model\User;
use Pluck\Storage\StorageDriver;
use Pluck\Support\Path;
use Pluck\Support\Slug;
use RuntimeException;

/**
 * SQLite driver: one file at data/pluck.sqlite, WAL mode.
 *
 * Same contract as the flat-file driver, so the installer's choice is the only
 * place the difference is visible. Page paths stay the identity here too, which
 * keeps import/export between the two drivers a straight copy.
 */
final class SqliteDriver implements StorageDriver
{
	private const FILE = 'pluck.sqlite';
	private const SCHEMA_VERSION = 2;

	private ?PDO $pdo = null;
	private int $txDepth = 0;

	public function __construct(private readonly string $dataDir)
	{
	}

	public function name(): string
	{
		return 'sqlite';
	}

	public function install(): void
	{
		foreach (['uploads', 'cache', 'trash'] as $dir) {
			Path::ensureDir(Path::within($this->dataDir, $dir));
		}

		$pdo = $this->pdo();
		$pdo->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
		$pdo->exec('CREATE TABLE IF NOT EXISTS pages (
			path TEXT PRIMARY KEY,
			parent TEXT,
			title TEXT NOT NULL,
			content TEXT NOT NULL DEFAULT "",
			hidden INTEGER NOT NULL DEFAULT 0,
			"order" INTEGER NOT NULL DEFAULT 0,
			description TEXT NOT NULL DEFAULT "",
			keywords TEXT NOT NULL DEFAULT "",
			module TEXT,
			module_data TEXT NOT NULL DEFAULT "{}",
			theme TEXT,
			author_id TEXT,
			created_at TEXT NOT NULL,
			updated_at TEXT NOT NULL
		)');
		$pdo->exec('CREATE INDEX IF NOT EXISTS pages_parent_order ON pages (parent, "order", title)');
		$pdo->exec('CREATE TABLE IF NOT EXISTS users (
			id TEXT PRIMARY KEY,
			username TEXT NOT NULL,
			password_hash TEXT NOT NULL,
			role TEXT NOT NULL,
			display_name TEXT NOT NULL DEFAULT "",
			email TEXT NOT NULL DEFAULT "",
			active INTEGER NOT NULL DEFAULT 1,
			totp_secret TEXT,
			created_at TEXT NOT NULL,
			last_login_at TEXT,
			must_change_password INTEGER NOT NULL DEFAULT 0,
			language TEXT
		)');
		$pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS users_username ON users (username COLLATE NOCASE)');
		$pdo->exec('CREATE TABLE IF NOT EXISTS module_data (
			module TEXT NOT NULL,
			key TEXT NOT NULL,
			value TEXT NOT NULL,
			updated_at TEXT NOT NULL,
			PRIMARY KEY (module, key)
		)');
		$pdo->exec('CREATE TABLE IF NOT EXISTS trash (
			id TEXT PRIMARY KEY,
			type TEXT NOT NULL,
			path TEXT NOT NULL,
			title TEXT NOT NULL,
			payload TEXT NOT NULL,
			deleted_at TEXT NOT NULL
		)');

		$this->upgrade();
		$this->setSetting('schema_version', self::SCHEMA_VERSION);
	}

	/**
	 * Bring an older database up to the current schema.
	 *
	 * install() is idempotent, but CREATE TABLE IF NOT EXISTS does nothing for a
	 * table that already exists with fewer columns, so a site installed before
	 * per-user languages needs the column added. Kept as explicit, guarded steps
	 * rather than a migration framework: there will not be many of these, and each
	 * one wants to be readable.
	 */
	private function upgrade(): void
	{
		$from = (int) ($this->getSetting('schema_version', 1) ?? 1);

		if ($from < 2 && !$this->hasColumn('users', 'language')) {
			$this->pdo()->exec('ALTER TABLE users ADD COLUMN language TEXT');
		}
	}

	private function hasColumn(string $table, string $column): bool
	{
		$statement = $this->pdo()->prepare('SELECT 1 FROM pragma_table_info(:table) WHERE name = :column');
		$statement->execute(['table' => $table, 'column' => $column]);

		return $statement->fetchColumn() !== false;
	}

	public function isInstalled(): bool
	{
		$file = Path::within($this->dataDir, self::FILE);
		if (!is_file($file)) {
			return false;
		}

		$row = $this->pdo()
			->query('SELECT name FROM sqlite_master WHERE type = "table" AND name = "pages"')
			->fetch();

		return $row !== false;
	}

	// ---- settings -------------------------------------------------------

	public function getSetting(string $key, mixed $default = null): mixed
	{
		$statement = $this->pdo()->prepare('SELECT value FROM settings WHERE key = :key');
		$statement->execute(['key' => $key]);
		$value = $statement->fetchColumn();

		return $value === false ? $default : $this->decode((string) $value);
	}

	public function setSetting(string $key, mixed $value): void
	{
		$statement = $this->pdo()->prepare(
			'INSERT INTO settings (key, value) VALUES (:key, :value)
			 ON CONFLICT(key) DO UPDATE SET value = excluded.value',
		);
		$statement->execute(['key' => $key, 'value' => $this->encode($value)]);
	}

	public function allSettings(): array
	{
		$out = [];
		foreach ($this->pdo()->query('SELECT key, value FROM settings ORDER BY key') as $row) {
			$out[(string) $row['key']] = $this->decode((string) $row['value']);
		}

		return $out;
	}

	public function deleteSetting(string $key): void
	{
		$this->pdo()->prepare('DELETE FROM settings WHERE key = :key')->execute(['key' => $key]);
	}

	// ---- pages ----------------------------------------------------------

	public function findPage(string $path): ?Page
	{
		$path = Slug::path($path);
		if ($path === '') {
			return null;
		}

		$statement = $this->pdo()->prepare('SELECT * FROM pages WHERE path = :path');
		$statement->execute(['path' => $path]);
		$row = $statement->fetch();

		return $row === false ? null : $this->hydrate($row);
	}

	public function pageExists(string $path): bool
	{
		$statement = $this->pdo()->prepare('SELECT 1 FROM pages WHERE path = :path');
		$statement->execute(['path' => Slug::path($path)]);

		return $statement->fetchColumn() !== false;
	}

	public function listPages(?string $parent = null, bool $includeHidden = true): array
	{
		$parent = $parent !== null && $parent !== '' ? Slug::path($parent) : null;

		$sql = 'SELECT * FROM pages WHERE parent ' . ($parent === null ? 'IS NULL' : '= :parent');
		if (!$includeHidden) {
			$sql .= ' AND hidden = 0';
		}
		$sql .= ' ORDER BY "order", title';

		$statement = $this->pdo()->prepare($sql);
		$statement->execute($parent === null ? [] : ['parent' => $parent]);

		return array_map([$this, 'hydrate'], $statement->fetchAll());
	}

	public function allPages(bool $includeHidden = true): array
	{
		$collected = [];
		$walk = function (?string $parent) use (&$walk, &$collected, $includeHidden): void {
			foreach ($this->listPages($parent, $includeHidden) as $page) {
				$collected[] = $page;
				$walk($page->path);
			}
		};
		$walk(null);

		return $collected;
	}

	public function savePage(Page $page): void
	{
		$page->path = Slug::path($page->path);
		if ($page->path === '') {
			throw new RuntimeException('A page needs a path.');
		}

		$this->transaction(function () use ($page): void {
			$parent = $page->parent();
			if ($parent !== null && !$this->pageExists($parent)) {
				throw new RuntimeException("Parent page does not exist: {$parent}");
			}

			if ($page->order === 0) {
				$statement = $this->pdo()->prepare(
					'SELECT COALESCE(MAX("order"), 0) + 1 FROM pages WHERE parent ' . ($parent === null ? 'IS NULL' : '= :parent'),
				);
				$statement->execute($parent === null ? [] : ['parent' => $parent]);
				$page->order = (int) $statement->fetchColumn();
			}

			$page->touch();

			$statement = $this->pdo()->prepare(
				'INSERT INTO pages (path, parent, title, content, hidden, "order", description, keywords, module, module_data, theme, author_id, created_at, updated_at)
				 VALUES (:path, :parent, :title, :content, :hidden, :ordering, :description, :keywords, :module, :module_data, :theme, :author_id, :created_at, :updated_at)
				 ON CONFLICT(path) DO UPDATE SET
					parent = excluded.parent, title = excluded.title, content = excluded.content,
					hidden = excluded.hidden, "order" = excluded."order", description = excluded.description,
					keywords = excluded.keywords, module = excluded.module, module_data = excluded.module_data,
					theme = excluded.theme, author_id = excluded.author_id, updated_at = excluded.updated_at',
			);
			$statement->execute([
				'path' => $page->path,
				'parent' => $parent,
				'title' => $page->title,
				'content' => $page->content,
				'hidden' => $page->hidden ? 1 : 0,
				'ordering' => $page->order,
				'description' => $page->description,
				'keywords' => $page->keywords,
				'module' => $page->module,
				'module_data' => $this->encode($page->moduleData),
				'theme' => $page->theme,
				'author_id' => $page->authorId,
				'created_at' => $page->createdAt,
				'updated_at' => $page->updatedAt,
			]);
		});
	}

	public function deletePage(string $path): void
	{
		$path = Slug::path($path);
		$page = $this->findPage($path);
		if ($page === null) {
			return;
		}

		$this->transaction(function () use ($path, $page): void {
			$descendants = [];
			$collect = function (string $parent) use (&$collect, &$descendants): void {
				foreach ($this->listPages($parent) as $child) {
					$descendants[] = $child->toArray();
					$collect($child->path);
				}
			};
			$collect($path);

			$this->pdo()->prepare(
				'INSERT INTO trash (id, type, path, title, payload, deleted_at) VALUES (:id, :type, :path, :title, :payload, :deleted_at)',
			)->execute([
				'id' => bin2hex(random_bytes(8)),
				'type' => 'page',
				'path' => $path,
				'title' => $page->title,
				'payload' => $this->encode(['page' => $page->toArray(), 'children' => $descendants]),
				'deleted_at' => gmdate('c'),
			]);

			$statement = $this->pdo()->prepare('DELETE FROM pages WHERE path = :path OR path LIKE :prefix ESCAPE \'\\\'' );
			$statement->execute(['path' => $path, 'prefix' => $this->likePrefix($path)]);
		});
	}

	public function movePage(string $from, string $to): void
	{
		$from = Slug::path($from);
		$to = Slug::path($to);
		if ($from === '' || $to === '' || $from === $to) {
			return;
		}
		if (str_starts_with($to, $from . '/')) {
			throw new RuntimeException('A page cannot be moved inside itself.');
		}

		$this->transaction(function () use ($from, $to): void {
			$page = $this->findPage($from);
			if ($page === null) {
				throw new RuntimeException("Page not found: {$from}");
			}
			if ($this->pageExists($to)) {
				throw new RuntimeException("A page already exists at: {$to}");
			}

			$newParent = str_contains($to, '/') ? substr($to, 0, (int) strrpos($to, '/')) : null;
			if ($newParent !== null && !$this->pageExists($newParent)) {
				throw new RuntimeException("Parent page does not exist: {$newParent}");
			}

			$statement = $this->pdo()->prepare(
				'UPDATE pages SET path = :to, parent = :parent, updated_at = :now WHERE path = :from',
			);
			$statement->execute(['to' => $to, 'parent' => $newParent, 'now' => gmdate('c'), 'from' => $from]);

			// Rewrite descendants in one statement; SQLite has no recursive UPDATE need here
			// because the path prefix is all that changes.
			$children = $this->pdo()->prepare('SELECT path FROM pages WHERE path LIKE :prefix ESCAPE \'\\\' ORDER BY LENGTH(path)');
			$children->execute(['prefix' => $this->likePrefix($from)]);
			$update = $this->pdo()->prepare('UPDATE pages SET path = :path, parent = :parent WHERE path = :old');

			foreach ($children->fetchAll(PDO::FETCH_COLUMN) as $oldPath) {
				$newPath = $to . substr((string) $oldPath, strlen($from));
				$parent = substr($newPath, 0, (int) strrpos($newPath, '/'));
				$update->execute(['path' => $newPath, 'parent' => $parent, 'old' => $oldPath]);
			}
		});
	}

	public function reorderPage(string $path, int $delta): void
	{
		$path = Slug::path($path);
		$page = $this->findPage($path);
		if ($page === null || $delta === 0) {
			return;
		}

		$this->transaction(function () use ($page, $delta): void {
			$siblings = $this->listPages($page->parent());
			$index = null;
			foreach ($siblings as $i => $sibling) {
				if ($sibling->path === $page->path) {
					$index = $i;
					break;
				}
			}
			if ($index === null) {
				return;
			}

			$target = $index + ($delta > 0 ? 1 : -1);
			if ($target < 0 || $target >= count($siblings)) {
				return;
			}

			[$siblings[$index], $siblings[$target]] = [$siblings[$target], $siblings[$index]];

			$statement = $this->pdo()->prepare('UPDATE pages SET "order" = :ordering WHERE path = :path');
			foreach ($siblings as $position => $sibling) {
				$statement->execute(['ordering' => $position + 1, 'path' => $sibling->path]);
			}
		});
	}

	// ---- users ----------------------------------------------------------

	public function findUser(string $id): ?User
	{
		$statement = $this->pdo()->prepare('SELECT * FROM users WHERE id = :id');
		$statement->execute(['id' => $id]);
		$row = $statement->fetch();

		return $row === false ? null : User::fromArray($row);
	}

	public function findUserByUsername(string $username): ?User
	{
		$statement = $this->pdo()->prepare('SELECT * FROM users WHERE username = :username COLLATE NOCASE');
		$statement->execute(['username' => $username]);
		$row = $statement->fetch();

		return $row === false ? null : User::fromArray($row);
	}

	public function listUsers(): array
	{
		$rows = $this->pdo()->query('SELECT * FROM users ORDER BY username')->fetchAll();

		return array_map(static fn (array $row): User => User::fromArray($row), $rows);
	}

	public function saveUser(User $user): void
	{
		$statement = $this->pdo()->prepare(
			'INSERT INTO users (id, username, password_hash, role, display_name, email, active, totp_secret, created_at, last_login_at, must_change_password, language)
			 VALUES (:id, :username, :password_hash, :role, :display_name, :email, :active, :totp_secret, :created_at, :last_login_at, :must_change_password, :language)
			 ON CONFLICT(id) DO UPDATE SET
				username = excluded.username, password_hash = excluded.password_hash, role = excluded.role,
				display_name = excluded.display_name, email = excluded.email, active = excluded.active,
				totp_secret = excluded.totp_secret, last_login_at = excluded.last_login_at,
				must_change_password = excluded.must_change_password, language = excluded.language',
		);

		$row = $user->toArray();
		$row['active'] = $user->active ? 1 : 0;
		$row['must_change_password'] = $user->mustChangePassword ? 1 : 0;
		$statement->execute($row);
	}

	public function deleteUser(string $id): void
	{
		$this->pdo()->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
	}

	public function countUsers(): int
	{
		return (int) $this->pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn();
	}

	// ---- module data ----------------------------------------------------

	public function getModuleData(string $module, string $key, mixed $default = null): mixed
	{
		$statement = $this->pdo()->prepare('SELECT value FROM module_data WHERE module = :module AND key = :key');
		$statement->execute(['module' => $module, 'key' => $key]);
		$value = $statement->fetchColumn();

		return $value === false ? $default : $this->decode((string) $value);
	}

	public function setModuleData(string $module, string $key, mixed $value): void
	{
		$statement = $this->pdo()->prepare(
			'INSERT INTO module_data (module, key, value, updated_at) VALUES (:module, :key, :value, :updated_at)
			 ON CONFLICT(module, key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at',
		);
		$statement->execute([
			'module' => $module,
			'key' => $key,
			'value' => $this->encode($value),
			'updated_at' => gmdate('c'),
		]);
	}

	public function listModuleData(string $module, string $keyPrefix = ''): array
	{
		$sql = 'SELECT key, value FROM module_data WHERE module = :module';
		$params = ['module' => $module];
		if ($keyPrefix !== '') {
			$sql .= ' AND key LIKE :prefix ESCAPE \'\\\'';
			$params['prefix'] = $this->escapeLike($keyPrefix) . '%';
		}
		$sql .= ' ORDER BY key';

		$statement = $this->pdo()->prepare($sql);
		$statement->execute($params);

		$out = [];
		foreach ($statement->fetchAll() as $row) {
			$out[(string) $row['key']] = $this->decode((string) $row['value']);
		}

		return $out;
	}

	public function deleteModuleData(string $module, string $key): void
	{
		$this->pdo()
			->prepare('DELETE FROM module_data WHERE module = :module AND key = :key')
			->execute(['module' => $module, 'key' => $key]);
	}

	public function deleteModule(string $module): void
	{
		$this->pdo()->prepare('DELETE FROM module_data WHERE module = :module')->execute(['module' => $module]);
	}

	// ---- transaction ----------------------------------------------------

	public function transaction(callable $work): mixed
	{
		$pdo = $this->pdo();

		if ($this->txDepth > 0) {
			$this->txDepth++;
			try {
				return $work();
			} finally {
				$this->txDepth--;
			}
		}

		$pdo->beginTransaction();
		$this->txDepth = 1;

		try {
			$result = $work();
			$pdo->commit();

			return $result;
		} catch (\Throwable $e) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			throw $e;
		} finally {
			$this->txDepth = 0;
		}
	}

	// ---- internals ------------------------------------------------------

	private function pdo(): PDO
	{
		if ($this->pdo instanceof PDO) {
			return $this->pdo;
		}

		if (!extension_loaded('pdo_sqlite')) {
			throw new RuntimeException('The SQLite storage driver needs the pdo_sqlite extension.');
		}

		$file = Path::within($this->dataDir, self::FILE);
		Path::ensureDir(dirname($file));

		try {
			$pdo = new PDO('sqlite:' . $file, null, null, [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES => false,
			]);
		} catch (PDOException $e) {
			throw new RuntimeException('Could not open the database: ' . $e->getMessage(), 0, $e);
		}

		$pdo->exec('PRAGMA journal_mode = WAL');
		$pdo->exec('PRAGMA foreign_keys = ON');
		$pdo->exec('PRAGMA busy_timeout = 5000');
		@chmod($file, 0o644);

		return $this->pdo = $pdo;
	}

	/** @param array<string,mixed> $row */
	private function hydrate(array $row): Page
	{
		$row['hidden'] = (bool) $row['hidden'];

		return Page::fromArray($row);
	}

	private function encode(mixed $value): string
	{
		return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
	}

	private function decode(string $value): mixed
	{
		return json_decode($value, true);
	}

	private function likePrefix(string $path): string
	{
		return $this->escapeLike($path . '/') . '%';
	}

	private function escapeLike(string $value): string
	{
		return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
	}
}
