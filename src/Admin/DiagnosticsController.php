<?php
declare(strict_types=1);

namespace Pluck\Admin;

use Pluck\Backup\BackupManager;
use Pluck\Media\MediaLibrary;
use Pluck\Update\Updates;

/**
 * What Pluck needs, what this server gives it, and who fixes the difference.
 *
 * Pluck 4 answered this with `phpinfo()`, which tells you everything and advises
 * nothing. The useful part is the third column: a site owner reading "ext-intl:
 * missing" still has to work out whether that matters, whether they can do
 * anything about it, and what to write to their host if they cannot.
 *
 * So each check says who it belongs to. **You** is something the person reading
 * can change — a permission, a setting. **Your host** is a support ticket, and
 * the wording is meant to be pasted into one.
 *
 * The list is not invented. Nearly every entry is something that actually went
 * wrong while Pluck 5 was being built and tested, which is why it is worth having
 * a screen for rather than a paragraph in a manual.
 */
final class DiagnosticsController extends Controller
{
	private const OK = 'ok';

	private const WARN = 'warn';

	private const BAD = 'bad';

	public function index(): never
	{
		$this->requireOwner();

		$this->render('admin/diagnostics', [
			'title' => $this->t('diagnostics.title.diagnostics'),
			'groups' => [
				'diagnostics.group.php' => $this->php(),
				'diagnostics.group.writable' => $this->writable(),
				'diagnostics.group.server' => $this->server(),
				'diagnostics.group.optional' => $this->optional(),
				'diagnostics.group.limits' => $this->limits(),
			],
			'phpinfoUrl' => Controller::url('diagnostics.phpinfo'),
		]);
	}

	/**
	 * phpinfo(), for the things no list anticipates.
	 *
	 * Owner-only and on its own address rather than inline: it prints the whole
	 * environment, including paths and every loaded ini file, and that does not
	 * belong on a page anybody might leave open.
	 */
	public function phpinfo(): never
	{
		$this->requireOwner();

		while (ob_get_level() > 0) {
			ob_end_clean();
		}

		header('Content-Type: text/html; charset=utf-8');
		header('X-Robots-Tag: noindex');
		header('Cache-Control: no-store');

		phpinfo();
		exit;
	}

	// ---- the checks -----------------------------------------------------

	/** @return list<array{label:string,value:string,state:string,advice:string,whose:string}> */
	private function php(): array
	{
		$rows = [];

		$rows[] = $this->row(
			'diagnostics.php_version',
			PHP_VERSION,
			version_compare(PHP_VERSION, '8.3', '>=') ? self::OK : self::BAD,
			version_compare(PHP_VERSION, '8.3', '>=') ? '' : 'diagnostics.advice.php_version',
			'host',
		);

		// Without these Pluck does not run at all, which is why the installer
		// refuses. Shown afterwards too, because a host can change what is
		// available underneath a site that was working yesterday.
		foreach (['dom', 'json', 'mbstring', 'session'] as $extension) {
			$rows[] = $this->row(
				'diagnostics.extension.' . $extension,
				extension_loaded($extension) ? 'diagnostics.present' : 'diagnostics.missing',
				extension_loaded($extension) ? self::OK : self::BAD,
				extension_loaded($extension) ? '' : 'diagnostics.advice.required_extension',
				'host',
			);
		}

		return $rows;
	}

	/** @return list<array{label:string,value:string,state:string,advice:string,whose:string}> */
	private function writable(): array
	{
		$root = $this->c->app->rootDir;
		$rows = [];

		foreach ([
			'data' => $root . '/data',
			'media' => $root . '/media',
		] as $name => $dir) {
			$rows[] = $this->row(
				'diagnostics.writable.' . $name,
				is_writable($dir) ? 'diagnostics.yes' : 'diagnostics.no',
				is_writable($dir) ? self::OK : self::BAD,
				is_writable($dir) ? '' : 'diagnostics.advice.writable',
				'you',
			);
		}

		/*
		 * The session folder, and the reason this screen exists.
		 *
		 * An unwritable one produces a sign-in that loops with nothing on the page
		 * to explain it: PHP warns and carries on with an empty session, so the
		 * sign-in appears to work and the dashboard bounces straight back. It is
		 * what a migration run as root leaves behind every single time.
		 */
		$sessionPath = session_save_path() !== '' ? session_save_path() : $root . '/data/cache';
		$sessionOk = !is_dir($sessionPath) || is_writable($sessionPath);

		$rows[] = $this->row(
			'diagnostics.writable.sessions',
			$sessionOk ? 'diagnostics.yes' : 'diagnostics.no',
			$sessionOk ? self::OK : self::BAD,
			$sessionOk ? '' : 'diagnostics.advice.sessions',
			'you',
		);

		/*
		 * Session files somebody else owns.
		 *
		 * The folder can be perfectly writable while the files in it belong to
		 * another user — a migration run as root, or a host where the CLI and the
		 * web pool are different accounts. PHP then refuses to read the file and
		 * carries on with an empty session, so signing in appears to work and
		 * bounces straight back to the form.
		 */
		$foreign = $this->foreignSessions($sessionPath);

		$rows[] = $this->row(
			'diagnostics.writable.session_owner',
			$foreign === 0 ? 'diagnostics.yes' : (string) $foreign,
			$foreign === 0 ? self::OK : self::BAD,
			$foreign === 0 ? '' : 'diagnostics.advice.session_owner',
			'you',
		);

		return $rows;
	}

	/** @return list<array{label:string,value:string,state:string,advice:string,whose:string}> */
	/** How many session files in there this process cannot read. */
	private function foreignSessions(string $path): int
	{
		if (!is_dir($path) || !is_readable($path)) {
			return 0;
		}

		$found = 0;

		foreach (glob($path . '/sess_*') ?: [] as $file) {
			if (!is_readable($file)) {
				$found++;
			}
		}

		return $found;
	}

	private function server(): array
	{
		$rows = [];

		// Whether data/ is refused over HTTP differs between Apache and nginx —
		// Apache reads the .htaccess Pluck ships, nginx ignores it entirely — and
		// getting it wrong exposes the content store and every account in it.
		$rows[] = $this->row(
			'diagnostics.server.data_hidden',
			'diagnostics.check_yourself',
			self::WARN,
			'diagnostics.advice.data_hidden',
			'you',
		);

		$rows[] = $this->row(
			'diagnostics.server.media_inert',
			'diagnostics.check_yourself',
			self::WARN,
			'diagnostics.advice.media_inert',
			'you',
		);

		$pretty = (bool) $this->c->storage->getSetting('pretty_urls', false);
		$rows[] = $this->row(
			'diagnostics.server.pretty_urls',
			$pretty ? 'diagnostics.on' : 'diagnostics.off',
			self::OK,
			$pretty ? '' : 'diagnostics.advice.pretty_urls',
			'you',
		);

		$mail = function_exists('mail');
		$rows[] = $this->row(
			'diagnostics.server.mail',
			$mail ? 'diagnostics.present' : 'diagnostics.missing',
			$mail ? self::OK : self::WARN,
			$mail ? '' : 'diagnostics.advice.mail',
			'host',
		);

		$online = (new Updates($this->c->app->rootDir . '/data', $this->c->storage))->canReachInternet();
		$rows[] = $this->row(
			'diagnostics.server.outbound',
			$online ? 'diagnostics.yes' : 'diagnostics.no',
			$online ? self::OK : self::WARN,
			$online ? '' : 'diagnostics.advice.outbound',
			'host',
		);

		return $rows;
	}

	/**
	 * Absent optional extensions, and what each one costs.
	 *
	 * "Optional" on its own tells nobody whether to care. Both of these are
	 * missing from every image in the project's own test bed and from a great
	 * deal of shared hosting, so the cost is the useful half.
	 *
	 * @return list<array{label:string,value:string,state:string,advice:string,whose:string}>
	 */
	private function optional(): array
	{
		$rows = [];

		foreach ([
			'intl' => 'diagnostics.advice.intl',
			'zip' => 'diagnostics.advice.zip',
			'zlib' => 'diagnostics.advice.zlib',
			'pdo_sqlite' => 'diagnostics.advice.sqlite',
			'curl' => 'diagnostics.advice.curl',
		] as $extension => $cost) {
			$present = extension_loaded($extension);

			$rows[] = $this->row(
				'diagnostics.extension.' . $extension,
				$present ? 'diagnostics.present' : 'diagnostics.missing',
				$present ? self::OK : self::WARN,
				$present ? '' : $cost,
				'host',
			);
		}

		return $rows;
	}

	/**
	 * Numbers worth seeing before they bite.
	 *
	 * @return list<array{label:string,value:string,state:string,advice:string,whose:string}>
	 */
	private function limits(): array
	{
		$rows = [];

		$upload = $this->bytes((string) ini_get('upload_max_filesize'));
		$post = $this->bytes((string) ini_get('post_max_size'));

		// A post_max_size below upload_max_filesize means the larger uploads the
		// first one promises are silently truncated — the request never arrives,
		// so nothing in Pluck can report it.
		$rows[] = $this->row(
			'diagnostics.limit.upload',
			ini_get('upload_max_filesize') . ' / ' . ini_get('post_max_size'),
			$post >= $upload ? self::OK : self::WARN,
			$post >= $upload ? '' : 'diagnostics.advice.post_smaller',
			'host',
		);

		$memory = $this->bytes((string) ini_get('memory_limit'));
		$rows[] = $this->row(
			'diagnostics.limit.memory',
			(string) ini_get('memory_limit'),
			$memory <= 0 || $memory >= 67108864 ? self::OK : self::WARN,
			$memory > 0 && $memory < 67108864 ? 'diagnostics.advice.memory' : '',
			'host',
		);

		$seconds = (int) ini_get('max_execution_time');
		$rows[] = $this->row(
			'diagnostics.limit.time',
			$seconds === 0 ? 'diagnostics.unlimited' : $seconds . 's',
			$seconds === 0 || $seconds >= 30 ? self::OK : self::WARN,
			$seconds > 0 && $seconds < 30 ? 'diagnostics.advice.time' : '',
			'host',
		);

		// Backups are the thing that fills an account up, and an account that is
		// full stops the site rather than only the backups.
		$free = @disk_free_space($this->c->app->rootDir);
		$backups = new BackupManager($this->c->app->rootDir . '/data', $this->c->app->rootDir . '/media');
		$kept = array_sum(array_map(static fn ($b): int => $b->bytes, $backups->all()));

		$rows[] = $this->row(
			'diagnostics.limit.disk',
			$this->human(is_float($free) ? (int) $free : 0) . ' (' . $this->human($kept) . ' in backups)',
			is_float($free) && $free < 104857600 ? self::WARN : self::OK,
			is_float($free) && $free < 104857600 ? 'diagnostics.advice.disk' : '',
			'you',
		);

		$media = count((new MediaLibrary($this->c->app->rootDir . '/media'))->names());
		$rows[] = $this->row('diagnostics.limit.media_count', (string) $media, self::OK, '', 'you');

		$rows[] = $this->row(
			'diagnostics.limit.timezone',
			date_default_timezone_get(),
			self::OK,
			'',
			'you',
		);

		return $rows;
	}

	// ---- helpers --------------------------------------------------------

	/** @return array{label:string,value:string,state:string,advice:string,whose:string} */
	private function row(string $label, string $value, string $state, string $advice, string $whose): array
	{
		return ['label' => $label, 'value' => $value, 'state' => $state, 'advice' => $advice, 'whose' => $whose];
	}

	private function bytes(string $value): int
	{
		$value = trim($value);
		if ($value === '' || $value === '-1') {
			return -1;
		}

		$number = (int) $value;

		return match (strtolower(substr($value, -1))) {
			'g' => $number * 1073741824,
			'm' => $number * 1048576,
			'k' => $number * 1024,
			default => $number,
		};
	}

	private function human(int $bytes): string
	{
		foreach ([['GB', 1073741824], ['MB', 1048576], ['kB', 1024]] as [$unit, $step]) {
			if ($bytes >= $step) {
				return round($bytes / $step, 1) . ' ' . $unit;
			}
		}

		return $bytes . ' B';
	}

	private function requireOwner(): void
	{
		if ($this->c->auth->user()?->role !== \Pluck\Model\Role::Owner) {
			$this->c->flash->stop($this->t('diagnostics.flash.owners_only'));
			$this->back('dashboard');
		}
	}
}
