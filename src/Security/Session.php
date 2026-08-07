<?php
declare(strict_types=1);

namespace Pluck\Security;

/**
 * Cookie/session hardening. Sessions are only ever started for the admin side;
 * a plain visitor reading a page gets no cookie at all.
 */
final class Session
{
	private bool $started = false;

	public function __construct(private readonly string $savePath = '')
	{
	}

	/**
	 * Delete the session file for this request if somebody else owns it.
	 *
	 * Only this request's own file, found from the cookie, and only when it is
	 * unreadable — never a sweep of the directory, which would sign out everybody
	 * else on a site that is working perfectly well.
	 */
	private static function removeForeignSession(string $path): void
	{
		if ($path === '' || str_contains($path, ';') || !is_dir($path) || !is_writable($path)) {
			return;
		}

		$id = $_COOKIE[session_name()] ?? '';
		if (!is_string($id) || $id === '' || preg_match('/^[A-Za-z0-9,-]{16,128}$/', $id) !== 1) {
			return;
		}

		$file = $path . '/sess_' . $id;

		// Readable means it is ours, or ours to read. Unreadable and present means
		// the one thing this is for.
		if (is_file($file) && !is_readable($file)) {
			@unlink($file);
		}
	}

	/**
	 * Throw if sessions cannot be written where PHP intends to write them.
	 *
	 * Its own method so it can be checked without starting a session — a suite
	 * that has already started one would otherwise reach the early return above
	 * and never get here.
	 *
	 * A save path with a semicolon in it carries a depth prefix (`2;/var/lib/...`)
	 * or a non-file handler, and guessing at either is worse than not checking.
	 */
	public static function refuseUnwritable(string $path): void
	{
		if ($path === '' || str_contains($path, ';') || str_contains($path, '://')) {
			return;
		}

		if (is_dir($path) && !is_writable($path)) {
			throw new SessionUnwritable($path);
		}
	}

	public function start(): void
	{
		if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
			$this->started = true;
			return;
		}

		if ($this->savePath !== '' && is_dir($this->savePath)) {
			session_save_path($this->savePath);
		}

		session_name('pluck_session');
		session_set_cookie_params([
			'lifetime' => 0,
			'path'     => self::basePath(),
			'secure'   => self::isHttps(),
			'httponly' => true,
			'samesite' => 'Lax',
		]);

		/*
		 * A session directory the web server cannot write to.
		 *
		 * Caught here rather than left to session_start(), which warns and carries
		 * on with an empty session — so signing in appears to work, the redirect
		 * lands on the dashboard, the dashboard finds nobody signed in, and it
		 * bounces back to the form. A loop with no message, which is what somebody
		 * migrating from the command line as root hits every time: the files end up
		 * owned by root and the web server runs as somebody else.
		 */
		self::refuseUnwritable(session_save_path());

		/*
		 * A session file left behind by a different user.
		 *
		 * The directory is writable — that is the check above — but a file in it
		 * belongs to somebody else, so PHP refuses to read it and carries on with
		 * an empty session. Signing in then appears to work and the dashboard
		 * bounces straight back to the form.
		 *
		 * It happens whenever the same install has been touched by two users: a
		 * migration run as root and a web server running as somebody else, or
		 * shared hosting where the CLI and the pool differ. The file is removed
		 * rather than reported: the directory is ours, the file is a dead session
		 * belonging to nobody, and deleting it costs nothing.
		 */
		self::removeForeignSession(session_save_path());

		$options = [
			'use_strict_mode'  => true,
			'use_only_cookies' => true,
			'cookie_httponly'  => true,
		];

		// sid_length and sid_bits_per_character are deprecated from PHP 8.4 and
		// warn on every request. PHP's own defaults are what they were tuned to
		// anyway, so on 8.4 and later they are simply left alone.
		if (PHP_VERSION_ID < 80400) {
			$options['sid_length'] = 48;
			$options['sid_bits_per_character'] = 5;
		}

		session_start($options);

		$this->started = true;
	}

	/** Call right after a successful login to prevent session fixation. */
	public function regenerate(): void
	{
		$this->start();
		session_regenerate_id(true);
	}

	public function get(string $key, mixed $default = null): mixed
	{
		$this->start();

		return $_SESSION[$key] ?? $default;
	}

	public function set(string $key, mixed $value): void
	{
		$this->start();
		$_SESSION[$key] = $value;
	}

	public function forget(string $key): void
	{
		$this->start();
		unset($_SESSION[$key]);
	}

	public function destroy(): void
	{
		$this->start();
		$_SESSION = [];

		if (ini_get('session.use_cookies')) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', [
				'expires'  => time() - 42000,
				'path'     => $params['path'],
				'secure'   => $params['secure'],
				'httponly' => true,
				'samesite' => 'Lax',
			]);
		}

		session_destroy();
		$this->started = false;
	}

	public static function isHttps(): bool
	{
		if (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
			return true;
		}

		return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
	}

	private static function basePath(): string
	{
		$script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/');
		$dir = rtrim(str_replace('\\', '/', dirname($script)), '/');

		return $dir === '' ? '/' : $dir . '/';
	}
}
