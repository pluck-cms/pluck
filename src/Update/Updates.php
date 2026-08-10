<?php
declare(strict_types=1);

namespace Pluck\Update;

use Pluck\Bootstrap;

use Pluck\Storage\StorageDriver;
use Pluck\Archive\ArchiveStore;
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

	/*
	 * Two channels, and only two.
	 *
	 * Stable is what a site running somebody's business should be on. The other
	 * exists because testing an update otherwise means publishing a release
	 * candidate as the headline release for everybody — which is a lot of risk to
	 * take on other people's installs so that one of yours can be tried out.
	 *
	 * Not "the newest tag": a tag is not a release, it has no notes and nobody
	 * decided it was ready. Something has to have been published on purpose.
	 */
	public const STABLE = 'stable';

	public const PRERELEASE = 'prerelease';

	public function __construct(
		private readonly string $dataDir,
		private readonly StorageDriver $storage,
		private readonly string $version = Bootstrap::VERSION,
		private readonly ?string $source = null,
		private readonly string $channel = self::STABLE,
	) {
	}

	/**
	 * Which channel this install is on.
	 *
	 * A setting rather than config.php, unlike the update source — and the
	 * difference is worth being clear about. The source decides *where* code
	 * comes from, which is code execution, and an administrator does not have
	 * that and must not gain it through a text field.
	 *
	 * A channel only chooses among releases from the same trusted repository.
	 * Somebody who switches it on could already have installed that release by
	 * hand; they gain an offer, not a permission. What they do gain is
	 * less-tested code on a running site, and the screen says so rather than the
	 * setting hiding it.
	 */
	public static function channelOf(StorageDriver $storage): string
	{
		return $storage->getSetting('updates_channel', self::STABLE) === self::PRERELEASE
			? self::PRERELEASE
			: self::STABLE;
	}

	/**
	 * Where releases are looked for.
	 *
	 * Overridable from `config.php` — `'update_source' => '...'` — and from there
	 * only. Deliberately not a setting: an update source is code this install will
	 * download and unpack, so anything that can change it can run code here. An
	 * administrator cannot do that today and should not gain it through a text
	 * field. Whoever can edit config.php can already replace src/ outright, so
	 * nothing is given away.
	 *
	 * It exists because testing the updater otherwise means publishing a real
	 * release on the shared repository, which makes a release candidate the
	 * headline release for everybody still on 4.7. Point a test install at a fork
	 * instead.
	 *
	 * Only api.github.com over TLS: not a general "fetch from anywhere", which is
	 * the same hole by a longer road.
	 */
	private function api(): string
	{
		if ($this->source === null || $this->source === '') {
			return self::API;
		}

		return preg_match('~^https://api\\.github\\.com/repos/[A-Za-z0-9._-]+/[A-Za-z0-9._-]+/releases/latest$~', $this->source) === 1
			? $this->source
			: self::API;
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

			/*
			 * A 404 means the release is gone, so what was remembered is wrong.
			 *
			 * Anything else — no network, a timeout, GitHub having a bad day — is
			 * a reason to keep it: the release is still out there and forgetting
			 * it would hide an update behind a dropped connection.
			 *
			 * A withdrawn release is the case that matters. Somebody pulls one
			 * because it was broken, and every install that had seen it goes on
			 * offering it until somebody notices.
			 */
			if (str_contains($e->getMessage(), '404')) {
				$this->storage->deleteSetting(self::LAST_SEEN);
			}

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
	/**
	 * The version this install is running.
	 *
	 * One answer, in one place. There were two: the admin's badge asked storage
	 * with a fallback of '5.0.0-dev' and this screen asked with a fallback of
	 * Bootstrap::VERSION — and nothing ever writes that setting, so the fallback
	 * was the answer both times. 'dev' sorts below everything in
	 * version_compare(), so the badge thought any release it had ever seen was
	 * newer, including ones older than the code running.
	 *
	 * The constant is the honest answer: it is compiled from the files that are
	 * actually there. The stored setting is kept as an override for anyone who
	 * needs one, but it can no longer make the running version look older than it
	 * is by being absent.
	 */
	public static function runningVersion(StorageDriver $storage): string
	{
		$stored = $storage->getSetting('version', '');

		return is_string($stored) && $stored !== '' && $stored !== '5.0.0-dev'
			? $stored
			: Bootstrap::VERSION;
	}

	public function updateAvailable(): bool
	{
		return $this->cached()?->isNewerThan($this->version) ?? false;
	}

	private function fetch(): ?Release
	{
		/*
		 * A different address per channel.
		 *
		 * `/releases/latest` is GitHub's own idea of the newest published release
		 * and skips pre-releases entirely, which is exactly right for stable and
		 * useless for the other one. `/releases` is the list, newest first, and
		 * the first one that suits is the answer.
		 */
		$json = $this->get($this->channel === self::PRERELEASE
			? preg_replace('~/releases/latest$~', '/releases', $this->api()) ?? $this->api()
			: $this->api());

		$data = json_decode($json, true);

		if (!is_array($data)) {
			throw new RuntimeException('GitHub answered with something this does not understand.');
		}

		// The list form: take the newest that this channel accepts.
		if (!isset($data['tag_name'])) {
			$data = self::pick($data);

			if ($data === null) {
				return null;
			}
		}

		if (!isset($data['tag_name'])) {
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

	/**
	 * The newest release in a list that is worth offering.
	 *
	 * GitHub returns them newest first, so the first that qualifies is the
	 * answer. A draft never qualifies whatever the channel says: a draft is
	 * somebody still writing, and its tag may not even exist yet.
	 *
	 * @param array<int|string,mixed> $releases
	 * @return array<string,mixed>|null
	 */
	private static function pick(array $releases): ?array
	{
		foreach ($releases as $release) {
			if (!is_array($release) || !isset($release['tag_name'])) {
				continue;
			}

			if (($release['draft'] ?? false) === true) {
				continue;
			}

			return $release;
		}

		return null;
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
		$found = array_map(
			fn (string $name): Download => $this->describe($name),
			$this->store()->names(),
		);

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
	/** The download folder, sharing its rules with the backup folder. */
	private function store(): ArchiveStore
	{
		return new ArchiveStore(
			$this->downloadDir(),
			'/^pluck-[A-Za-z0-9._-]+-[0-9a-f]{8}\.tar\.gz$/',
			'That is not the name of a downloaded release.',
		);
	}

	public function pathOf(string $name): string
	{
		return $this->store()->pathOf($name);
	}

	public function exists(string $name): bool
	{
		return $this->store()->exists($name);
	}

	public function delete(string $name): bool
	{
		return $this->store()->delete($name);
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
			/*
			 * Say which repository was asked.
			 *
			 * A bare "GitHub answered 404" sends somebody to look at their release,
			 * their tag and their pre-release flag — when the actual cause can be
			 * that update_source still holds the example from the documentation.
			 * That happened on the first install to use it.
			 *
			 * Only owner/repo, never the whole URL: it is the part that differs and
			 * the part somebody can check, and a 404 page is not the place to print
			 * configuration back at whoever is reading it.
			 */
			$repo = preg_match('~/repos/([^/]+/[^/]+)/~', $url, $m) === 1 ? $m[1] : 'the repository';

			throw new RuntimeException(sprintf(
				'GitHub answered %d rather than 200 for %s. %s',
				$status,
				$repo,
				$status === 404
					? 'That means no published release there — check the name, and that the release is not marked as a pre-release, which /releases/latest skips.'
					: '',
			));
		}

		return $body;
	}
}
