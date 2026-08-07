<?php
declare(strict_types=1);

namespace Pluck\Site;

/**
 * Where things live, as addresses.
 *
 * Pluck runs on hosting where mod_rewrite may be off, in a subdirectory, behind
 * someone else's proxy, or all three. So pretty URLs are a setting rather than
 * an assumption, and every link in a theme goes through here instead of being
 * built by hand — that is the only way turning the setting off can be a working
 * site rather than a site full of dead links.
 *
 * Both forms address the same thing:
 *   pretty  /about/team          /blog/some-post
 *   plain   ?page=about/team     ?page=blog/some-post
 */
final class Urls
{
	/**
	 * @param string $base the directory the install is served from, with a
	 *        trailing slash: "/" at a domain root, "/cms/" in a subdirectory
	 */
	public function __construct(
		private readonly string $base = '/',
		private readonly bool $pretty = false,
	) {
	}

	/**
	 * Work out the base from the server environment.
	 *
	 * SCRIPT_NAME is the one variable that survives being behind a proxy, in a
	 * subdirectory, and under php-fpm alike, because the web server rewrote it
	 * itself rather than taking it from the request.
	 *
	 * @param array<string,mixed> $server
	 */
	public static function detectBase(array $server): string
	{
		$script = (string) ($server['SCRIPT_NAME'] ?? '/index.php');
		$dir = str_replace('\\', '/', dirname($script));

		return $dir === '/' || $dir === '.' ? '/' : rtrim($dir, '/') . '/';
	}

	/** The path part of the request, relative to the install. */
	public static function detectPath(array $server): string
	{
		// PATH_INFO is what a rewrite to index.php/<path> produces, and what
		// apache's own handler fills in. It is already decoded and already
		// relative, so nothing has to be guessed at.
		$info = (string) ($server['PATH_INFO'] ?? '');
		if ($info !== '') {
			return trim($info, '/');
		}

		$uri = (string) ($server['REQUEST_URI'] ?? '');
		$query = strpos($uri, '?');
		if ($query !== false) {
			$uri = substr($uri, 0, $query);
		}

		$base = self::detectBase($server);
		if ($base !== '/' && str_starts_with($uri, $base)) {
			$uri = substr($uri, strlen($base));
		}

		$path = trim(rawurldecode($uri), '/');

		/*
		 * The script's own name is not a page.
		 *
		 * A request for /index.php?page=about — which is what the admin's own
		 * "View site" link produces, and what anybody typing the address gets —
		 * arrives here as the path "index.php". Pluck then looks for a page called
		 * that and answers 404, on a site where every link works.
		 */
		$script = basename((string) ($server['SCRIPT_NAME'] ?? 'index.php'));
		if ($path === $script) {
			return '';
		}

		/*
		 * /sub with no trailing slash, when the install is at /sub/.
		 *
		 * Apache normally redirects to add the slash, but not every server does
		 * and not on every path — and without this the install's own directory
		 * name is read as the name of a page.
		 */
		if ($base !== '/' && '/' . $path . '/' === $base) {
			return '';
		}

		return $path;
	}

	public function base(): string
	{
		return $this->base;
	}

	/** The admin, wherever this install happens to be. */
	public function admin(): string
	{
		return $this->base . 'admin.php';
	}

	public function isPretty(): bool
	{
		return $this->pretty;
	}

	/** A link to a page or module path. */
	public function to(string $path): string
	{
		$path = trim($path, '/');

		if ($path === '') {
			return $this->base;
		}

		if ($this->pretty) {
			return $this->base . implode('/', array_map('rawurlencode', explode('/', $path)));
		}

		return $this->base . '?page=' . rawurlencode($path);
	}

	/** A link that keeps extra query parameters, such as a page number. */
	public function toWith(string $path, array $params): string
	{
		$params = array_filter($params, static fn (mixed $v): bool => $v !== null && $v !== '');
		if ($params === []) {
			return $this->to($path);
		}

		$url = $this->to($path);
		$separator = str_contains($url, '?') ? '&' : '?';

		return $url . $separator . http_build_query($params);
	}

	/** A link to a file that the web server serves directly. */
	public function asset(string $path): string
	{
		return $this->base . ltrim($path, '/');
	}

	public function media(string $filename): string
	{
		return $this->asset('media/' . rawurlencode($filename));
	}
}
