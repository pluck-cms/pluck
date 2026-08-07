<?php
declare(strict_types=1);

namespace Pluck\Admin;

use Pluck\Bootstrap;

use Pluck\Backup\BackupManager;
use Pluck\Update\Applier;
use Pluck\Update\Updates;
use Throwable;

/**
 * Telling somebody a new Pluck exists, and fetching it for them.
 *
 * Nothing here unpacks anything. The archive is downloaded, its hash is shown,
 * and a person puts it in place themselves — see Updates for why that is the
 * design rather than a stage on the way to something more automatic.
 */
final class UpdateController extends Controller
{
	public function index(): never
	{
		$updates = $this->updates();

		$error = '';
		if ($this->wantsCheck() && $updates->canReachInternet()) {
			try {
				$updates->latest(force: $this->c->request->query('check', '') === '1');
			} catch (Throwable $e) {
				$error = $e->getMessage();
			}
		}

		$this->render('admin/updates', [
			'title' => $this->t('update.title.updates'),
			'current' => $this->version(),
			'release' => $updates->cached(),
			'available' => $updates->updateAvailable(),
			'downloads' => $updates->downloads(),
			'lastChecked' => $updates->lastCheckedAt(),
			'online' => $updates->canReachInternet(),
			'enabled' => (bool) $this->c->storage->getSetting('updates_check_enabled', true),
			'error' => $error,
		]);
	}

	public function download(): never
	{
		$updates = $this->updates();
		$release = $updates->cached();

		if ($release === null) {
			$this->c->flash->stop($this->t('update.flash.check_first'));
			$this->back('updates');
		}

		try {
			$download = $updates->download($release);
		} catch (Throwable $e) {
			$this->c->flash->stop($this->t('update.flash.download_failed', ['why' => $e->getMessage()]));
			$this->back('updates');
		}

		// A backup before an update is the one thing the 4.x updater got right,
		// and it is taken here rather than at unpacking time because unpacking
		// happens outside Pluck — by which point nothing of ours is running.
		$backupName = '';
		try {
			$backupName = $this->backups()->create('before update')->name;
		} catch (Throwable) {
			$this->c->flash->warn($this->t('update.flash.no_backup'));
		}

		$this->c->flash->ok($this->t('update.flash.downloaded', [
			'name' => $download->name,
			'backup' => $backupName,
		]));
		$this->back('updates');
	}

	/** Hand over an archive. Streamed, because data/ is not served. */
	public function fetch(): never
	{
		$updates = $this->updates();
		$name = $this->c->request->query('name', '');

		if (!$updates->exists($name)) {
			$this->c->flash->stop($this->t('update.flash.gone'));
			$this->back('updates');
		}

		$path = $updates->pathOf($name);

		while (ob_get_level() > 0) {
			ob_end_clean();
		}

		header('Content-Type: application/gzip');
		header('Content-Disposition: attachment; filename="' . basename($name) . '"');
		header('Content-Length: ' . (string) filesize($path));
		header('X-Content-Type-Options: nosniff');
		header('Cache-Control: no-store');

		readfile($path);
		exit;
	}

	/**
	 * Put a downloaded release in place.
	 *
	 * A button, never a schedule. One compromised release should not reach every
	 * site before anybody has looked at it, and a person pressing this has decided
	 * to trust that release — see Update\Applier for what the checksum does and
	 * does not prove.
	 */
	public function apply(): never
	{
		$name = $this->c->request->post('name', '');

		if (!$this->updates()->exists($name)) {
			$this->c->flash->stop($this->t('update.flash.gone'));
			$this->back('updates');
		}

		// No backup, no update. This is the one action where a way back is not a
		// nicety, and refusing is better than proceeding without one.
		try {
			$backup = $this->backups()->create('before update')->name;
		} catch (Throwable $e) {
			$this->c->flash->stop($this->t('update.flash.no_backup_no_update', ['why' => $e->getMessage()]));
			$this->back('updates');
		}

		try {
			$result = (new Applier($this->c->app->rootDir, $this->updates()))->apply($name);
		} catch (Throwable $e) {
			$this->c->flash->stop($this->t('update.flash.apply_failed', [
				'why' => $e->getMessage(),
				'backup' => $backup,
			]));
			$this->back('updates');
		}

		$this->updates()->delete($name);

		$this->c->flash->ok($this->t('update.flash.applied', [
			'to' => $result['to'] !== '' ? $result['to'] : '?',
			'replaced' => $result['replaced'],
			'backup' => $backup,
		]));
		$this->back('updates');
	}

	public function delete(): never
	{
		if (!$this->updates()->delete($this->c->request->post('name', ''))) {
			$this->c->flash->stop($this->t('update.flash.gone'));
			$this->back('updates');
		}

		$this->c->flash->ok($this->t('update.flash.deleted'));
		$this->back('updates');
	}

	private function wantsCheck(): bool
	{
		return (bool) $this->c->storage->getSetting('updates_check_enabled', true);
	}

	private function version(): string
	{
		return (string) $this->c->storage->getSetting('version', Bootstrap::VERSION);
	}

	private function updates(): Updates
	{
		return new Updates($this->c->app->rootDir . '/data', $this->c->storage, $this->version());
	}

	private function backups(): BackupManager
	{
		return new BackupManager(
			$this->c->app->rootDir . '/data',
			$this->c->app->rootDir . '/media',
			$this->version(),
		);
	}
}
