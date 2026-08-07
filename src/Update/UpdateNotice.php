<?php
declare(strict_types=1);

namespace Pluck\Update;

use Pluck\Bootstrap;

use Pluck\Model\Role;
use Pluck\Storage\StorageDriver;

/**
 * Telling the people who can do something about it that a release is out.
 *
 * Two ways, and the first matters more. A notice in the admin is seen by whoever
 * signs in, needs nothing from the host, and cannot be lost to a spam folder.
 * An e-mail reaches somebody who has not signed in for a month, which is exactly
 * the site that most needs telling — but mail() is unreliable on shared hosting
 * and unreliable in a way nobody notices, so it is the second line and not the
 * first.
 *
 * Either way, once per version. A notice repeated every day is a notice people
 * learn to click past.
 */
final class UpdateNotice
{
	public const TOLD_ABOUT = 'update_told_about';

	public function __construct(
		private readonly Updates $updates,
		private readonly StorageDriver $storage,
		private readonly string $version = Bootstrap::VERSION,
	) {
	}

	/** Whether the admin should show a badge. */
	public function shouldShow(): bool
	{
		return $this->updates->updateAvailable();
	}

	public function version(): string
	{
		return $this->updates->cached()?->normalised() ?? '';
	}

	/**
	 * Send an e-mail, at most once for any given version.
	 *
	 * Returns the number of messages handed to the mailer, which is not the same
	 * as the number delivered — nothing here can know that, and pretending
	 * otherwise would be the sort of false comfort that stops people checking.
	 */
	public function emailOnce(string $siteTitle, string $siteUrl): int
	{
		if (!$this->shouldShow() || !function_exists('mail')) {
			return 0;
		}

		$version = $this->version();
		if ($version === '' || (string) $this->storage->getSetting(self::TOLD_ABOUT, '') === $version) {
			return 0;
		}

		// Recorded before sending, not after. A mailer that fails slowly would
		// otherwise be retried on every sign-in for as long as the release stands.
		$this->storage->setSetting(self::TOLD_ABOUT, $version);

		$sent = 0;
		foreach ($this->recipients() as $address) {
			$subject = sprintf('Pluck %s is out - %s', $version, $siteTitle);
			$body = implode("\n", [
				sprintf('A new version of Pluck is available: %s. You are running %s.', $version, $this->version),
				'',
				sprintf('Sign in to update: %s', rtrim($siteUrl, '/') . '/admin.php?p=updates'),
				'',
				'Pluck makes a backup before it replaces anything.',
			]);

			$headers = 'From: ' . $this->from($siteUrl) . "\r\nContent-Type: text/plain; charset=utf-8";

			if (@mail($address, $subject, $body, $headers)) {
				$sent++;
			}
		}

		return $sent;
	}

	/**
	 * Who hears about it: the accounts that could act on it.
	 *
	 * Telling an author about an update they have no permission to install is
	 * noise, and noise is what makes people stop reading.
	 *
	 * @return list<string>
	 */
	private function recipients(): array
	{
		$addresses = [];

		foreach ($this->storage->listUsers() as $user) {
			if (($user->role === Role::Owner || $user->role === Role::Admin) && $user->email !== '') {
				$addresses[] = $user->email;
			}
		}

		return array_values(array_unique($addresses));
	}

	private function from(string $siteUrl): string
	{
		$host = parse_url($siteUrl, PHP_URL_HOST);

		return 'pluck@' . (is_string($host) && $host !== '' ? $host : 'localhost');
	}
}
