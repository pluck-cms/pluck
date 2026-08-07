<?php
declare(strict_types=1);

namespace Pluck\Backup;

use Pluck\Storage\StorageDriver;
use Throwable;

/**
 * The backup nobody has to remember to make.
 *
 * Shared hosting frequently has no cron, so the only thing that reliably happens
 * on a small site is that somebody signs in to the admin. That is the trigger: on
 * sign-in, if the newest backup is older than the interval, make one.
 *
 * "In the background" is a promise PHP cannot quite keep, so this does the next
 * best thing. The response is flushed and the connection closed first —
 * `fastcgi_finish_request()` under php-fpm, and a flush plus `ignore_user_abort`
 * everywhere else — and only then is the archive written. The person signing in
 * sees their dashboard at the usual speed and the work happens after they have it.
 *
 * A lock in BackupManager keeps two people signing in at once from doing it
 * twice, and a failure is swallowed: not getting a backup is bad, and not being
 * able to sign in because a backup failed is worse.
 */
final class ScheduledBackup
{
	public const LAST_ATTEMPT = 'backup_last_attempt';

	public function __construct(
		private readonly BackupManager $manager,
		private readonly StorageDriver $storage,
	) {
	}

	/** Whether one is due, without making it. */
	public function isDue(): bool
	{
		$days = (int) $this->storage->getSetting('backup_interval_days', 7);
		if ($days <= 0) {
			return false;
		}

		$interval = $days * 86400;

		// The last *attempt* counts, not the last success. Without that, a site
		// whose backups keep failing would try again on every single sign-in.
		$attempted = (int) $this->storage->getSetting(self::LAST_ATTEMPT, 0);
		if ($attempted > 0 && time() - $attempted < min($interval, 3600)) {
			return false;
		}

		$newest = $this->manager->newest();

		return $newest === null || time() - $newest->createdAt >= $interval;
	}

	/**
	 * Make one if it is due, after the visitor has their page.
	 *
	 * Call this at the very end of a request that has finished rendering.
	 */
	public function runIfDue(): void
	{
		if (!$this->isDue()) {
			return;
		}

		$this->storage->setSetting(self::LAST_ATTEMPT, time());

		self::detach();

		try {
			$this->manager->create('scheduled');
			$this->manager->prune(max(1, (int) $this->storage->getSetting('backup_keep', 5)));
		} catch (Throwable $e) {
			// Including "a backup is already running", which is the ordinary
			// outcome when two owners sign in within a minute of each other.
			error_log('Pluck: scheduled backup did not run: ' . $e->getMessage());
		}
	}

	/**
	 * Send what has been rendered and let the visitor go.
	 *
	 * Under php-fpm this genuinely closes the connection. Elsewhere it is the
	 * best available approximation: flush what there is, and ask PHP to keep
	 * running if the browser gives up.
	 */
	private static function detach(): void
	{
		ignore_user_abort(true);

		if (function_exists('fastcgi_finish_request')) {
			fastcgi_finish_request();

			return;
		}

		while (ob_get_level() > 0) {
			ob_end_flush();
		}
		flush();
	}
}
