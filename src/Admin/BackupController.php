<?php
declare(strict_types=1);

namespace Pluck\Admin;

use Pluck\Bootstrap;

use Pluck\Backup\BackupManager;
use Pluck\Storage\DriverFactory;
use Pluck\Support\Config;
use Pluck\Model\Role;
use Throwable;

/**
 * Making, downloading, restoring and removing backups.
 *
 * Owner-only. A backup is a copy of every account and every setting on the site,
 * so being able to download one is being able to read all of it — and restoring
 * one can put an old set of accounts back, which is a way to hand access to
 * somebody whose account was removed.
 */
final class BackupController extends Controller
{
	public function index(): never
	{
		$this->requireOwner();

		$manager = $this->manager();

		$this->render('admin/backups', [
			'title' => $this->t('backup.title.backups'),
			'backups' => $manager->all(),
			'keep' => $this->keep(),
			'intervalDays' => $this->intervalDays(),
			'compressed' => function_exists('gzencode'),
		]);
	}

	public function create(): never
	{
		$this->requireOwner();

		try {
			$backup = $this->manager()->create('manual');
		} catch (Throwable $e) {
			$this->c->flash->stop($this->t('backup.flash.could_not_make', ['why' => $e->getMessage()]));
			$this->back('backups');
		}

		$this->manager()->prune($this->keep());

		$this->c->flash->ok($this->t('backup.flash.made', ['name' => $backup->name]));
		$this->back('backups');
	}

	/**
	 * Send an archive.
	 *
	 * Streamed through this route rather than linked to, which is the whole
	 * reason the archives can live under `data/`: the folder is denied by the web
	 * server, and the only way out is past the sign-in and the owner check above.
	 */
	public function download(): never
	{
		$this->requireOwner();

		$manager = $this->manager();
		$name = $this->c->request->query('name', '');

		if (!$manager->exists($name)) {
			$this->c->flash->stop($this->t('backup.flash.gone'));
			$this->back('backups');
		}

		$path = $manager->pathOf($name);

		// No output buffering in the way, and no framework page around it: this
		// response is a file and nothing else.
		while (ob_get_level() > 0) {
			ob_end_clean();
		}

		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . basename($name) . '"');
		header('Content-Length: ' . (string) filesize($path));
		header('X-Content-Type-Options: nosniff');
		header('Cache-Control: no-store');

		readfile($path);
		exit;
	}

	public function delete(): never
	{
		$this->requireOwner();

		$name = $this->c->request->post('name', '');

		if (!$this->manager()->delete($name)) {
			$this->c->flash->stop($this->t('backup.flash.gone'));
			$this->back('backups');
		}

		$this->c->flash->ok($this->t('backup.flash.deleted'));
		$this->back('backups');
	}

	/**
	 * Put a backup back.
	 *
	 * Guarded by a typed confirmation rather than a second button. Restoring is
	 * the one thing here that destroys current work, and a person who has typed
	 * the word has read the sentence above it.
	 */
	public function restore(): never
	{
		$this->requireOwner();

		$name = $this->c->request->post('name', '');
		$confirm = strtolower(trim($this->c->request->post('confirm', '')));

		if ($confirm !== strtolower($this->t('backup.confirm_word'))) {
			$this->c->flash->stop($this->t('backup.flash.type_the_word'));
			$this->back('backups');
		}

		try {
			$result = $this->manager()->restore($name, settle: $this->settler());
		} catch (Throwable $e) {
			$this->c->flash->stop($this->t('backup.flash.could_not_restore', ['why' => $e->getMessage()]));
			$this->back('backups');
		}

		if (!$result['settled']) {
			$this->c->flash->warn($this->t('backup.flash.schema_not_settled'));
		}

		if ($result['skipped'] !== []) {
			$this->c->flash->warn($this->t('backup.flash.some_skipped', ['count' => count($result['skipped'])]));
		}

		// The session survives a restore, but the account behind it may not have:
		// the archive can hold a different set of users. Saying so is kinder than
		// letting somebody find out at the next click.
		$this->c->flash->ok($this->t('backup.flash.restored', [
			'count' => $result['restored'],
			'safety' => (string) $result['safety'],
		]));
		$this->back('backups');
	}

	public function saveSettings(): never
	{
		$this->requireOwner();

		$storage = $this->c->storage;
		$storage->setSetting('backup_keep', max(1, min(50, (int) $this->c->request->post('backup_keep', '5'))));
		// Zero switches the automatic backup off, which has to stay possible: on a
		// site with a large media folder somebody may prefer to run it themselves.
		$storage->setSetting('backup_interval_days', max(0, min(365, (int) $this->c->request->post('backup_interval_days', '7'))));

		$this->c->flash->ok($this->t('backup.flash.settings_saved'));
		$this->back('backups');
	}

	/**
	 * Bring the restored store up to the current schema.
	 *
	 * A fresh Config and a fresh driver, not the ones this request is holding:
	 * the archive may have replaced config.php — possibly with one naming the
	 * other storage driver — and an SQLite connection opened before the restore
	 * points at a file that has since been overwritten underneath it.
	 *
	 * install() is idempotent and runs the schema upgrades, which is exactly the
	 * work needed and nothing more.
	 */
	private function settler(): callable
	{
		$dataDir = $this->c->app->rootDir . '/data';

		return static function () use ($dataDir): void {
			$config = Config::load($dataDir);
			$driver = DriverFactory::make((string) $config->get('storage', DriverFactory::FLAT_FILE), $dataDir);
			$driver->install();
		};
	}

	private function manager(): BackupManager
	{
		return new BackupManager(
			$this->c->app->rootDir . '/data',
			$this->c->app->rootDir . '/media',
			\Pluck\Update\Updates::runningVersion($this->c->storage),
		);
	}

	private function keep(): int
	{
		return max(1, (int) $this->c->storage->getSetting('backup_keep', 5));
	}

	private function intervalDays(): int
	{
		return max(0, (int) $this->c->storage->getSetting('backup_interval_days', 7));
	}

	private function requireOwner(): void
	{
		if ($this->c->auth->user()?->role !== Role::Owner) {
			$this->c->flash->stop($this->t('backup.flash.owners_only'));
			$this->back('dashboard');
		}
	}
}
