<?php
declare(strict_types=1);

namespace Pluck\Storage\FlatFile;

use Pluck\Model\Page;
use Pluck\Model\User;
use Pluck\Storage\StorageDriver;
use Pluck\Support\Path;
use Pluck\Support\Slug;
use RuntimeException;

/**
 * The file-first driver, and the one that keeps Pluck feeling like Pluck:
 * unzip, install, back up over FTP.
 *
 * Layout under data/:
 *   content/pages/<slug>.json           a top-level page
 *   content/pages/<slug>/<child>.json   its children (a page with children has both)
 *   settings/settings.json
 *   users/users.json
 *   modules/<module>/<key>.json
 *   trash/<timestamp>-<slug>/           deleted pages, restorable
 *
 * Every page is a single JSON document. Reading one is a file read, not an
 * include, so nothing inside it can execute.
 */
final class FlatFileDriver implements StorageDriver
{
	private const PAGES = 'content/pages';
	private const SETTINGS = 'settings/settings.json';
	private const USERS = 'users/users.json';

	/** @var array<string,mixed>|null */
	private ?array $settingsCache = null;

	/** @var array<string,User>|null */
	private ?array $usersCache = null;

	private int $lockDepth = 0;

	/** @var resource|null */
	private $lockHandle = null;

	public function __construct(private readonly string $dataDir)
	{
	}

	public function name(): string
	{
		return 'flatfile';
	}

	public function install(): void
	{
		foreach (['content/pages', 'settings', 'users', 'modules', 'uploads', 'cache', 'trash'] as $dir) {
			Path::ensureDir(Path::within($this->dataDir, $dir));
		}
		if (!is_file($this->path(self::SETTINGS))) {
			$this->writeJson(self::SETTINGS, []);
		}
		if (!is_file($this->path(self::USERS))) {
			$this->writeJson(self::USERS, []);
		}
	}

	public function isInstalled(): bool
	{
		return is_file($this->path(self::SETTINGS)) && is_dir($this->path(self::PAGES));
	}

	// ---- settings -------------------------------------------------------

	public function getSetting(string $key, mixed $default = null): mixed
	{
		return $this->settings()[$key] ?? $default;
	}

	public function setSetting(string $key, mixed $value): void
	{
		$settings = $this->settings();
		$settings[$key] = $value;
		ksort($settings);
		$this->writeJson(self::SETTINGS, $settings);
		$this->settingsCache = $settings;
	}

	public function allSettings(): array
	{
		return $this->settings();
	}

	public function deleteSetting(string $key): void
	{
		$settings = $this->settings();
		unset($settings[$key]);
		$this->writeJson(self::SETTINGS, $settings);
		$this->settingsCache = $settings;
	}

	// ---- pages ----------------------------------------------------------

	public function findPage(string $path): ?Page
	{
		$path = Slug::path($path);
		if ($path === '') {
			return null;
		}

		$file = $this->path(self::PAGES . '/' . $path . '.json');
		if (!is_file($file)) {
			return null;
		}

		/** @var array<string,mixed>|null $row */
		$row = json_decode((string) file_get_contents($file), true);
		if (!is_array($row)) {
			return null;
		}
		$row['path'] = $path;

		return Page::fromArray($row);
	}

	public function pageExists(string $path): bool
	{
		$path = Slug::path($path);

		return $path !== '' && is_file($this->path(self::PAGES . '/' . $path . '.json'));
	}

	public function listPages(?string $parent = null, bool $includeHidden = true): array
	{
		$relative = self::PAGES . ($parent !== null && $parent !== '' ? '/' . Slug::path($parent) : '');
		$dir = $this->path($relative);
		if (!is_dir($dir)) {
			return [];
		}

		$pages = [];
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..' || !str_ends_with($entry, '.json')) {
				continue;
			}
			$slug = substr($entry, 0, -5);
			$path = ($parent !== null && $parent !== '' ? Slug::path($parent) . '/' : '') . $slug;
			$page = $this->findPage($path);
			if ($page === null || (!$includeHidden && $page->hidden)) {
				continue;
			}
			$pages[] = $page;
		}

		usort($pages, static fn (Page $a, Page $b): int => [$a->order, $a->title] <=> [$b->order, $b->title]);

		return $pages;
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
				$siblings = $this->listPages($parent);
				$page->order = $siblings === [] ? 1 : max(array_map(static fn (Page $p): int => $p->order, $siblings)) + 1;
			}

			$page->touch();
			$this->writeJson(self::PAGES . '/' . $page->path . '.json', $page->toArray());
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
			$stamp = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
			$trashRelative = 'trash/' . $stamp;
			$trashDir = Path::within($this->dataDir, $trashRelative);
			Path::ensureDir($trashDir);

			// Manifest so the trashcan can show what this was without parsing pages.
			Path::writeAtomic($trashDir . '/manifest.json', $this->encode([
				'type' => 'page',
				'path' => $path,
				'title' => $page->title,
				'deleted_at' => gmdate('c'),
			]));

			rename($this->path(self::PAGES . '/' . $path . '.json'), $trashDir . '/page.json');

			$childDir = $this->path(self::PAGES . '/' . $path);
			if (is_dir($childDir)) {
				rename($childDir, $trashDir . '/children');
			}
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

			$sourceFile = $this->path(self::PAGES . '/' . $from . '.json');
			$targetFile = $this->path(self::PAGES . '/' . $to . '.json');
			Path::ensureDir(dirname($targetFile));

			$page->path = $to;
			$page->touch();
			Path::writeAtomic($targetFile, $this->encode($page->toArray()));
			@unlink($sourceFile);

			$sourceChildren = $this->path(self::PAGES . '/' . $from);
			if (is_dir($sourceChildren)) {
				$targetChildren = $this->path(self::PAGES . '/' . $to);
				Path::ensureDir(dirname($targetChildren));
				rename($sourceChildren, $targetChildren);
				$this->rewriteDescendantPaths($to);
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

			// Renumber the whole sibling set so gaps and duplicates heal themselves.
			foreach ($siblings as $position => $sibling) {
				$sibling->order = $position + 1;
				$this->writeJson(self::PAGES . '/' . $sibling->path . '.json', $sibling->toArray());
			}
		});
	}

	// ---- users ----------------------------------------------------------

	public function findUser(string $id): ?User
	{
		return $this->users()[$id] ?? null;
	}

	public function findUserByUsername(string $username): ?User
	{
		$needle = mb_strtolower($username, 'UTF-8');
		foreach ($this->users() as $user) {
			if (mb_strtolower($user->username, 'UTF-8') === $needle) {
				return $user;
			}
		}

		return null;
	}

	public function listUsers(): array
	{
		$users = array_values($this->users());
		usort($users, static fn (User $a, User $b): int => strcmp($a->username, $b->username));

		return $users;
	}

	public function saveUser(User $user): void
	{
		$this->transaction(function () use ($user): void {
			$users = $this->users();
			$users[$user->id] = $user;
			$this->writeUsers($users);
		});
	}

	public function deleteUser(string $id): void
	{
		$this->transaction(function () use ($id): void {
			$users = $this->users();
			unset($users[$id]);
			$this->writeUsers($users);
		});
	}

	public function countUsers(): int
	{
		return count($this->users());
	}

	// ---- module data ----------------------------------------------------

	public function getModuleData(string $module, string $key, mixed $default = null): mixed
	{
		$file = $this->moduleFile($module, $key);
		if (!is_file($file)) {
			return $default;
		}

		/** @var array<string,mixed>|null $row */
		$row = json_decode((string) file_get_contents($file), true);

		return is_array($row) && array_key_exists('value', $row) ? $row['value'] : $default;
	}

	public function setModuleData(string $module, string $key, mixed $value): void
	{
		Path::writeAtomic($this->moduleFile($module, $key), $this->encode([
			'key' => $key,
			'value' => $value,
			'updated_at' => gmdate('c'),
		]));
	}

	public function listModuleData(string $module, string $keyPrefix = ''): array
	{
		$dir = $this->path('modules/' . $this->safeName($module));
		if (!is_dir($dir)) {
			return [];
		}

		$out = [];
		foreach (scandir($dir) ?: [] as $entry) {
			if (!str_ends_with($entry, '.json')) {
				continue;
			}
			/** @var array<string,mixed>|null $row */
			$row = json_decode((string) file_get_contents($dir . '/' . $entry), true);
			if (!is_array($row) || !isset($row['key'])) {
				continue;
			}
			$key = (string) $row['key'];
			if ($keyPrefix !== '' && !str_starts_with($key, $keyPrefix)) {
				continue;
			}
			$out[$key] = $row['value'] ?? null;
		}
		ksort($out);

		return $out;
	}

	public function deleteModuleData(string $module, string $key): void
	{
		$file = $this->moduleFile($module, $key);
		if (is_file($file)) {
			@unlink($file);
		}
	}

	public function deleteModule(string $module): void
	{
		Path::deleteTree($this->dataDir, 'modules/' . $this->safeName($module));
	}

	// ---- transaction ----------------------------------------------------

	public function transaction(callable $work): mixed
	{
		// Re-entrant: nested savePage() calls inside a transaction must not
		// release the lock when the inner call finishes.
		if ($this->lockDepth > 0) {
			$this->lockDepth++;
			try {
				return $work();
			} finally {
				$this->lockDepth--;
			}
		}

		$lockFile = $this->path('cache/write.lock');
		Path::ensureDir(dirname($lockFile));
		$handle = fopen($lockFile, 'c');
		if ($handle === false) {
			throw new RuntimeException('Could not open the write lock.');
		}
		if (!flock($handle, LOCK_EX)) {
			fclose($handle);
			throw new RuntimeException('Could not acquire the write lock.');
		}

		$this->lockHandle = $handle;
		$this->lockDepth = 1;
		$this->settingsCache = null;
		$this->usersCache = null;

		try {
			return $work();
		} finally {
			$this->lockDepth = 0;
			$this->lockHandle = null;
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}

	// ---- internals ------------------------------------------------------

	/** After a move, children carry a stale `path` field; rewrite them. */
	private function rewriteDescendantPaths(string $parent): void
	{
		foreach ($this->listPages($parent) as $child) {
			$child->path = $parent . '/' . $child->slug();
			$this->writeJson(self::PAGES . '/' . $child->path . '.json', $child->toArray());
			$this->rewriteDescendantPaths($child->path);
		}
	}

	/** @return array<string,mixed> */
	private function settings(): array
	{
		if ($this->settingsCache === null) {
			$file = $this->path(self::SETTINGS);
			$decoded = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
			$this->settingsCache = is_array($decoded) ? $decoded : [];
		}

		return $this->settingsCache;
	}

	/** @return array<string,User> */
	private function users(): array
	{
		if ($this->usersCache === null) {
			$file = $this->path(self::USERS);
			$decoded = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
			$users = [];
			if (is_array($decoded)) {
				foreach ($decoded as $row) {
					if (is_array($row) && isset($row['id'])) {
						$users[(string) $row['id']] = User::fromArray($row);
					}
				}
			}
			$this->usersCache = $users;
		}

		return $this->usersCache;
	}

	/** @param array<string,User> $users */
	private function writeUsers(array $users): void
	{
		$rows = [];
		foreach ($users as $user) {
			$rows[] = $user->toArray();
		}
		$this->writeJson(self::USERS, $rows);
		$this->usersCache = $users;
	}

	private function moduleFile(string $module, string $key): string
	{
		// Keep the key readable in the filename, but append a hash so two
		// different keys can never collapse onto one file.
		$readable = substr(preg_replace('/[^a-zA-Z0-9._-]+/', '-', $key) ?? 'key', 0, 60);
		$readable = trim($readable, '-.') ?: 'key';
		$name = $readable . '.' . substr(hash('sha256', $key), 0, 12) . '.json';

		return $this->path('modules/' . $this->safeName($module) . '/' . $name);
	}

	private function safeName(string $module): string
	{
		$safe = preg_replace('/[^a-z0-9._-]+/', '', mb_strtolower($module, 'UTF-8')) ?? '';
		if ($safe === '' || $safe === '.' || $safe === '..') {
			throw new RuntimeException('Invalid module name.');
		}

		return $safe;
	}

	private function path(string $relative): string
	{
		return Path::within($this->dataDir, $relative);
	}

	/** @param array<array-key,mixed> $data */
	private function writeJson(string $relative, array $data): void
	{
		Path::writeAtomic($this->path($relative), $this->encode($data));
	}

	/** @param array<array-key,mixed> $data */
	private function encode(array $data): string
	{
		return json_encode(
			$data,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
		) . "\n";
	}
}
