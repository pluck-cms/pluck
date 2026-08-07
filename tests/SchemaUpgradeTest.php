<?php
declare(strict_types=1);

namespace Pluck\Tests;

use PDO;
use Pluck\Model\Role;
use Pluck\Model\User;
use Pluck\Storage\Sqlite\SqliteDriver;

/**
 * A database written by an older version has to keep working.
 *
 * `CREATE TABLE IF NOT EXISTS` does nothing for a table that already exists with
 * fewer columns, so the idempotent install() is not enough on its own. This builds
 * a schema-1 database by hand, runs install() over it, and checks that the site
 * still works and nothing was lost.
 */
final class SchemaUpgradeTest extends TestCase
{
	public function run(): void
	{
		if (!extension_loaded('pdo_sqlite')) {
			return;
		}

		$dir = $this->tempDir('pluck-upgrade');
		$this->writeSchemaOne($dir . '/pluck.sqlite');

		$driver = new SqliteDriver($dir);
		$driver->install();

		$this->assertTrue($driver->isInstalled(), 'the upgraded database is a working install');
		$this->assertSame(2, $driver->getSetting('schema_version'), 'the schema version moved on');

		// The account written under the old schema survives, with the new field
		// defaulting rather than exploding.
		$old = $driver->findUserByUsername('olduser');
		$this->assertTrue($old !== null, 'the existing account is still there');
		$this->assertSame(Role::Owner, $old->role, 'its role is intact');
		$this->assertSame(null, $old->language, 'the new language field defaults to null');
		$this->assertTrue($old->verify('the-old-password'), 'the existing password still works');

		// And the new field can be written and read.
		$old->language = 'nl';
		$driver->saveUser($old);
		$this->assertSame('nl', $driver->findUserByUsername('olduser')->language, 'a language can now be stored');

		$fresh = User::create('newuser', 'another-long-password', Role::Editor);
		$fresh->language = 'pl';
		$driver->saveUser($fresh);
		$this->assertSame('pl', $driver->findUser($fresh->id)->language, 'a new account keeps its language');

		// The page written under the old schema is untouched.
		$page = $driver->findPage('oldpage');
		$this->assertTrue($page !== null, 'the existing page survived');
		$this->assertSame('Old page', $page->title, 'with its title');

		// Running install() again changes nothing.
		$driver->install();
		$this->assertSame(2, $driver->countUsers(), 'a second install() does not duplicate anything');
		$this->assertSame('nl', $driver->findUserByUsername('olduser')->language, 'nor lose anything');
	}

	/** The schema as it shipped before per-user languages existed. */
	private function writeSchemaOne(string $file): void
	{
		$pdo = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

		$pdo->exec('CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
		$pdo->exec('CREATE TABLE pages (
			path TEXT PRIMARY KEY, parent TEXT, title TEXT NOT NULL, content TEXT NOT NULL DEFAULT "",
			hidden INTEGER NOT NULL DEFAULT 0, "order" INTEGER NOT NULL DEFAULT 0,
			description TEXT NOT NULL DEFAULT "", keywords TEXT NOT NULL DEFAULT "",
			module TEXT, module_data TEXT NOT NULL DEFAULT "{}", theme TEXT, author_id TEXT,
			created_at TEXT NOT NULL, updated_at TEXT NOT NULL
		)');
		$pdo->exec('CREATE TABLE users (
			id TEXT PRIMARY KEY, username TEXT NOT NULL, password_hash TEXT NOT NULL, role TEXT NOT NULL,
			display_name TEXT NOT NULL DEFAULT "", email TEXT NOT NULL DEFAULT "",
			active INTEGER NOT NULL DEFAULT 1, totp_secret TEXT, created_at TEXT NOT NULL,
			last_login_at TEXT, must_change_password INTEGER NOT NULL DEFAULT 0
		)');
		$pdo->exec('CREATE TABLE module_data (module TEXT NOT NULL, key TEXT NOT NULL, value TEXT NOT NULL, updated_at TEXT NOT NULL, PRIMARY KEY (module, key))');
		$pdo->exec('CREATE TABLE trash (id TEXT PRIMARY KEY, type TEXT NOT NULL, path TEXT NOT NULL, title TEXT NOT NULL, payload TEXT NOT NULL, deleted_at TEXT NOT NULL)');

		$pdo->prepare('INSERT INTO settings (key, value) VALUES (:k, :v)')
			->execute(['k' => 'schema_version', 'v' => '1']);

		$pdo->prepare(
			'INSERT INTO users (id, username, password_hash, role, display_name, email, active, created_at, must_change_password)
			 VALUES (:id, :u, :h, :r, :d, :e, 1, :c, 0)',
		)->execute([
			'id' => 'olduserid',
			'u' => 'olduser',
			'h' => password_hash('the-old-password', PASSWORD_DEFAULT),
			'r' => 'owner',
			'd' => 'Old User',
			'e' => '',
			'c' => gmdate('c'),
		]);

		$pdo->prepare(
			'INSERT INTO pages (path, parent, title, content, created_at, updated_at)
			 VALUES (:p, NULL, :t, :c, :n, :n)',
		)->execute(['p' => 'oldpage', 't' => 'Old page', 'c' => '<p>Still here</p>', 'n' => gmdate('c')]);
	}
}
