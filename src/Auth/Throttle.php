<?php
declare(strict_types=1);

namespace Pluck\Auth;

use Pluck\Storage\StorageDriver;

/**
 * Sign-in rate limiting.
 *
 * Counters live in the storage driver rather than the session, because the whole
 * point is to slow down an attacker who throws away cookies between attempts.
 * Keys are hashed so the settings table never holds a list of tried usernames.
 */
final class Throttle
{
	private const SETTING = 'auth.attempts';

	public function __construct(
		private readonly StorageDriver $storage,
		private readonly int $maxAttempts = 5,
		private readonly int $lockSeconds = 900,
	) {
	}

	public function key(string $ip, string $username): string
	{
		return substr(hash('sha256', $ip . '|' . strtolower($username)), 0, 32);
	}

	/** Seconds still to wait, or 0 when the caller may try. */
	public function retryAfter(string $key, ?int $now = null): int
	{
		$now ??= time();
		$entry = $this->load()[$key] ?? null;
		if (!is_array($entry)) {
			return 0;
		}

		$until = (int) ($entry['until'] ?? 0);

		return $until > $now ? $until - $now : 0;
	}

	public function isLocked(string $key, ?int $now = null): bool
	{
		return $this->retryAfter($key, $now) > 0;
	}

	public function hit(string $key, ?int $now = null): void
	{
		$now ??= time();
		$entries = $this->prune($this->load(), $now);

		$count = (int) ($entries[$key]['count'] ?? 0) + 1;
		$entry = ['count' => $count, 'seen' => $now, 'until' => 0];

		if ($count >= $this->maxAttempts) {
			// Back off further on each lock instead of resetting: a patient script
			// hitting the same account gets slower and slower.
			$multiplier = 2 ** min(4, intdiv($count, $this->maxAttempts) - 1);
			$entry['until'] = $now + ($this->lockSeconds * $multiplier);
		}

		$entries[$key] = $entry;
		$this->storage->setSetting(self::SETTING, $entries);
	}

	public function clear(string $key, ?int $now = null): void
	{
		$entries = $this->prune($this->load(), $now ?? time());
		unset($entries[$key]);
		$this->storage->setSetting(self::SETTING, $entries);
	}

	private function load(): array
	{
		$entries = $this->storage->getSetting(self::SETTING, []);

		return is_array($entries) ? $entries : [];
	}

	/** Forget anything untouched for a day, so the setting cannot grow without bound. */
	private function prune(array $entries, int $now): array
	{
		foreach ($entries as $key => $entry) {
			if (!is_array($entry)) {
				unset($entries[$key]);
				continue;
			}
			$seen = (int) ($entry['seen'] ?? 0);
			$until = (int) ($entry['until'] ?? 0);
			if ($until <= $now && $seen < $now - 86400) {
				unset($entries[$key]);
			}
		}

		return $entries;
	}
}
