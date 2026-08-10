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
			/*
			 * What would stop an install, before anybody presses Install.
			 *
			 * Checked against the files this install already has rather than
			 * against a release: those are the ones that have to be replaced, and
			 * an archive does not have to be downloaded to know that root owns
			 * half of them.
			 */
			'blocked' => $this->wouldFail(),
			// Which of the two situations it is. The folders taking writes while
			// the files do not is ordinary shared hosting, and the advice for it
			// is the opposite of the advice for a bad chown.
			'foldersWritable' => is_writable($this->c->app->rootDir),
		]);
	}

	/**
	 * A dry run of the permission part.
	 *
	 * The files an update replaces are the ones in the directories the updater
	 * owns, so walking those answers the question without a download. It is not
	 * the same set as a real release — a release may add files — but the cause is
	 * always ownership, and one file with the wrong owner means all of them do.
	 *
	 * @return list<string>
	 */
	private function wouldFail(): array
	{
		$root = $this->c->app->rootDir;
		$files = [];

		foreach (['src', 'views', 'lang', 'assets', 'bin', 'docs'] as $owned) {
			$dir = $root . '/' . $owned;

			if (!is_dir($dir)) {
				continue;
			}

			$walk = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			);

			foreach ($walk as $file) {
				if ($file->isFile()) {
					$files[] = str_replace($root . '/', '', $file->getPathname());
				}
			}
		}

		/*
		 * The loose files in the root as well — with scandir, not glob.
		 *
		 * glob() does not match a leading dot, so a first attempt at this walked
		 * straight past .dockerignore: the one file that had actually stopped an
		 * update on a real server. A check that misses the case it was written for
		 * is worse than none, because it says everything is fine.
		 */
		foreach (scandir($root) ?: [] as $entry) {
			if ($entry !== '.' && $entry !== '..' && is_file($root . '/' . $entry)) {
				$files[] = $entry;
			}
		}

		return Applier::unwritable($root, $files);
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
		return Updates::runningVersion($this->c->storage);
	}

	private function updates(): Updates
	{
		// The source comes from config.php when it is set there — see
		// Updates::api() for why it is a file and not a setting.
		return new Updates(
			$this->c->app->rootDir . '/data',
			$this->c->storage,
			$this->version(),
			(string) $this->c->app->config->get('update_source', ''),
			Updates::channelOf($this->c->storage),
		);
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
