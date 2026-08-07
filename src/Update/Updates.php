<?php
declare(strict_types=1);

namespace Pluck\Update;

use Pluck\Bootstrap;

use Pluck\Storage\StorageDriver;
use Pluck\Support\Path;
use RuntimeException;
use Throwable;

/**
 * Finding out that a new Pluck exists, and fetching it.
 *
 * What this deliberately does not do is install it. The archive is downloaded,
 * its hash is shown, and there it stays until a person unpacks it themselves.
 *
 * That is a decision about who is trusted rather than about effort. An updater
 * that unpacks over the running install is remote code execution by design: the
 * only thing between a compromised release and every site running it is whatever
 * the updater checks. The honest way to make that safe is signed releases, and a
 * signature is a promise a project has to keep on every single release — one
 * forgotten signing and the updater refuses a legitimate version, somebody turns
 * the check off to get past it, and the check is gone for good. Pluck is built by
 * several people who have found such agreements hard to hold to before.
 *
 * So the code never runs what it downloaded. A person unpacks it over FTP,
 * looking at the file, having compared the hash if they want to. That is a much
 * smaller thing to get right, and it is what Pluck 4 should have done.
 */
final class Updates
{
	public const DIRECTORY = 'updates';

	public const LAST_CHECK = 'update_last_check';

	public const LAST_SEEN = 'update_last_seen';

	/** GitHub allows 60 unauthenticated calls an hour per address. Once every six is plenty. */
	private const CHECK_INTERVAL = 21600;

	private const API = 'https://api.github.com/repos/pluck-cms/pluck/releases/latest';

	public function __construct(
		private readonly string $dataDir,
		private readonly StorageDriver $storage,
		private readonly string $version = Bootstrap::VERSION,
	) {
	}

	// ---- checking -------------------------------------------------------

	/**
	 * The newest release, from cache unless it is stale.
	 *
	 * Cached because this runs when somebody opens a screen, and a site with
	 * several admins would otherwise spend its rate limit on telling four people
	 * the same thing.
	 */
	public function latest(bool $force = false): ?Release
	{
		if (!$force && !$this->isStale()) {
			return $this->cached();
		}

		try {
			$release = $this->fetch();
		} catch (Throwable $e) {
			// Recorded as an attempt either way, so a server that cannot reach
			// GitHub does not try again on every page load.
			$this->storage->setSetting(self::LAST_CHECK, time());
			throw $e;
		}

		$this->storage->setSetting(self::LAST_CHECK, time());

		if ($release !== null) {
			$this->storage->setSetting(self::LAST_SEEN, [
				'version' => $release->version,
				'name' => $release->name,
				'url' => $release->url,
				'archive' => $release->archiveUrl,
				'published' => $release->publishedAt,
				'notes' => mb_substr($release->notes, 0, 4000),
				'prerelease' => $release->prerelease,
			]);
		}

		return $release;
	}

	public function isStale(): bool
	{
		return time() - (int) $this->storage->getSetting(self::LAST_CHECK, 0) >= self::CHECK_INTERVAL;
	}

	public function lastCheckedAt(): int
	{
		return (int) $this->storage->getSetting(self::LAST_CHECK, 0);
	}

	public function cached(): ?Release
	{
		$stored = $this->storage->getSetting(self::LAST_SEEN, []);
		if (!is_array($stored) || ($stored['version'] ?? '') === '') {
			return null;
		}

		return new Release(
			version: (string) $stored['version'],
			name: (string) ($stored['name'] ?? $stored['version']),
			url: (string) ($stored['url'] ?? ''),
			archiveUrl: (string) ($stored['archive'] ?? ''),
			publishedAt: (string) ($stored['published'] ?? ''),
			notes: (string) ($stored['notes'] ?? ''),
			prerelease: (bool) ($stored['prerelease'] ?? false),
		);
	}

	/** Whether a newer release is known about, without asking GitHub. */
	public function updateAvailable(): bool
	{
		return $this->cached()?->isNewerThan($this->version) ?? false;
	}

	private function fetch(): ?Release
	{
		$json = $this->get(self::API);
		$data = json_decode($json, true);

		if (!is_array($data) || !isset($data['tag_name'])) {
			throw new RuntimeException('GitHub answered with something this does not understand.');
		}

		// The source tarball, not a build artefact: Pluck has no build step, so
		// the tag is the release. tar.gz because ext-zip is missing from a great
		// deal of shared hosting, and Pluck can read tar itself.
		$archive = (string) ($data['tarball_url'] ?? '');

		return new Release(
			version: (string) $data['tag_name'],
			name: (string) ($data['name'] ?? $data['tag_name']),
			url: (string) ($data['html_url'] ?? ''),
			archiveUrl: $archive,
			publishedAt: (string) ($data['published_at'] ?? ''),
			notes: (string) ($data['body'] ?? ''),
			prerelease: (bool) ($data['prerelease'] ?? false),
		);
	}

	// ---- downloading ----------------------------------------------------

	/**
	 * Fetch a release archive and keep it.
	 *
	 * Stored under `data/updates/`, which the web server denies for the same
	 * reason it denies `data/backups/`, and handed out through an authenticated
	 * route. A downloaded archive is a file, nothing more: nothing here unpacks
	 * it, and nothing here will.
	 */
	public function download(Release $release): Download
	{
		if ($release->archiveUrl === '') {
			throw new RuntimeException('That release has nothing to download.');
		}

		Path::ensureDir($this->downloadDir());

		$name = sprintf('pluck-%s-%s.tar.gz', preg_replace('/[^A-Za-z0-9._-]/', '', $release->normalised()) ?: 'release', bin2hex(random_bytes(4)));
		$target = Path::within($this->downloadDir(), $name);

		$bytes = $this->get($release->archiveUrl, binary: true);

		// Written beside the target and renamed, so a half-finished download never
		// appears in the list looking complete.
		if (@file_put_contents($target . '.part', $bytes) === false || !rename($target . '.part', $target)) {
			@unlink($target . '.part');

			throw new RuntimeException('The download could not be saved. Is data/ writable?');
		}

		@chmod($target, 0o600);

		return $this->describe($name);
	}

	/** @return list<Download> newest first */
	public function downloads(): array
	{
		if (!is_dir($this->downloadDir())) {
			return [];
		}

		$found = [];
		foreach (scandir($this->downloadDir()) ?: [] as $entry) {
			if (preg_match('/^pluck-[A-Za-z0-9._-]+-[0-9a-f]{8}\.tar\.gz$/', $entry) === 1) {
				$found[] = $this->describe($entry);
			}
		}

		usort($found, static fn (Download $a, Download $b): int => $b->downloadedAt <=> $a->downloadedAt);

		return $found;
	}

	public function describe(string $name): Download
	{
		$path = $this->pathOf($name);

		return new Download(
			name: $name,
			bytes: (int) (filesize($path) ?: 0),
			downloadedAt: (int) (filemtime($path) ?: 0),
			// Shown so it can be compared against what GitHub reports. Not a
			// guarantee — the same connection delivered both — but it does answer
			// "did this arrive intact", which is the question people actually have.
			sha256: (string) (hash_file('sha256', $path) ?: ''),
		);
	}

	/**
	 * The path of a download, refusing anything that is not one.
	 *
	 * Rebuilt from a basename and matched against the pattern, so a name arriving
	 * from a form cannot address a file elsewhere.
	 */
	public function pathOf(string $name): string
	{
		$name = basename($name);

		if (preg_match('/^pluck-[A-Za-z0-9._-]+-[0-9a-f]{8}\.tar\.gz$/', $name) !== 1) {
			throw new RuntimeException('That is not the name of a downloaded release.');
		}

		return Path::within($this->downloadDir(), $name);
	}

	public function exists(string $name): bool
	{
		try {
			return is_file($this->pathOf($name));
		} catch (Throwable) {
			return false;
		}
	}

	public function delete(string $name): bool
	{
		return $this->exists($name) && @unlink($this->pathOf($name));
	}

	public function downloadDir(): string
	{
		return $this->dataDir . '/' . self::DIRECTORY;
	}

	// ---- reaching the internet ------------------------------------------

	/**
	 * Whether this server can fetch anything at all.
	 *
	 * Worth answering separately: plenty of shared hosting blocks outbound HTTPS
	 * or switches allow_url_fopen off, and "no update found" would be a lie in
	 * that case.
	 */
	public function canReachInternet(): bool
	{
		return function_exists('curl_init') || (bool) ini_get('allow_url_fopen');
	}

	private function get(string $url, bool $binary = false): string
	{
		if (!str_starts_with($url, 'https://')) {
			// Plain HTTP would mean anything on the path could replace the answer,
			// and the release archive with it.
			throw new RuntimeException('Refusing to fetch anything over an unencrypted connection.');
		}

		$agent = 'Pluck/' . $this->version . ' (+https://github.com/pluck-cms/pluck)';

		if (function_exists('curl_init')) {
			return $this->viaCurl($url, $agent, $binary);
		}

		if (!ini_get('allow_url_fopen')) {
			throw new RuntimeException('This server cannot reach the internet: it has neither curl nor allow_url_fopen.');
		}

		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'timeout' => $binary ? 120 : 15,
				'header' => "Accept: application/vnd.github+json\r\nUser-Agent: {$agent}\r\n",
				'follow_location' => 1,
				'max_redirects' => 5,
			],
			// Left at the defaults on purpose: PHP verifies the certificate and the
			// hostname, and turning that off is how an updater becomes a way in.
			'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
		]);

		$body = @file_get_contents($url, false, $context);

		if ($body === false) {
			throw new RuntimeException('Could not reach GitHub. Try again later, or download the release by hand.');
		}

		return $body;
	}

	private function viaCurl(string $url, string $agent, bool $binary): string
	{
		$curl = curl_init($url);
		if ($curl === false) {
			throw new RuntimeException('Could not start a connection.');
		}

		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_TIMEOUT => $binary ? 120 : 15,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_USERAGENT => $agent,
			CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
			// Both on, always. An updater that skips certificate checking hands
			// anyone on the network the ability to choose what the site downloads.
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			// A release archive is a few megabytes; anything wildly larger is not
			// what was asked for.
			CURLOPT_MAXFILESIZE => 104857600,
		]);

		$body = curl_exec($curl);
		$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$error = curl_error($curl);
		curl_close($curl);

		if (!is_string($body) || $body === '') {
			throw new RuntimeException('Could not reach GitHub' . ($error !== '' ? ': ' . $error : '.'));
		}

		if ($status !== 200) {
			throw new RuntimeException(sprintf('GitHub answered %d rather than 200.', $status));
		}

		return $body;
	}
}
