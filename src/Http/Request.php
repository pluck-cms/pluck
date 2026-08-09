<?php
declare(strict_types=1);

namespace Pluck\Http;

/**
 * The incoming request, read once and never touched again.
 *
 * Version 4 read $_GET straight out of the superglobal all over the codebase and
 * defended itself with one blacklist in security.php. Here the superglobals are
 * captured in one place and every accessor returns a string, so a caller cannot
 * accidentally hand an array to something expecting a scalar.
 */
final class Request
{
	/** Route names are our own vocabulary, so they can be validated strictly. */
	private const ROUTE_PATTERN = '/^[a-z][a-z0-9.\-]{0,39}$/';

	private function __construct(
		public readonly string $method,
		public readonly string $route,
		private readonly array $query,
		private readonly array $post,
		private readonly array $files,
		private readonly array $server,
	) {
	}

	public static function capture(): self
	{
		$route = $_GET['p'] ?? '';
		if (!is_string($route) || !preg_match(self::ROUTE_PATTERN, $route)) {
			$route = '';
		}

		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

		return new self(
			method: is_string($method) ? strtoupper($method) : 'GET',
			route: $route,
			query: is_array($_GET) ? $_GET : [],
			post: is_array($_POST) ? $_POST : [],
			files: is_array($_FILES) ? $_FILES : [],
			server: is_array($_SERVER) ? $_SERVER : [],
		);
	}

	/** For tests and for the router's own use. */
	public static function fake(string $method = 'GET', string $route = '', array $post = [], array $query = []): self
	{
		return new self(strtoupper($method), $route, $query, $post, [], []);
	}

	public function isPost(): bool
	{
		return $this->method === 'POST';
	}

	/** A trimmed scalar from the body. Arrays and objects collapse to the default. */
	public function post(string $key, string $default = ''): string
	{
		$value = $this->post[$key] ?? null;

		return is_scalar($value) ? trim((string) $value) : $default;
	}

	/**
	 * A body value that is a list, as a group of checkboxes sends.
	 *
	 * Only strings, and only scalars: a form can post arrays nested however deep
	 * the sender likes, and a caller expecting a list of names should get a list
	 * of names rather than whatever arrived.
	 *
	 * @return list<string>
	 */
	public function postArray(string $key): array
	{
		$value = $this->post[$key] ?? null;

		if (!is_array($value)) {
			return [];
		}

		$out = [];

		foreach ($value as $item) {
			if (is_scalar($item)) {
				$out[] = trim((string) $item);
			}
		}

		return $out;
	}

	/** The body value untrimmed, for content fields where whitespace is meaningful. */
	public function raw(string $key, string $default = ''): string
	{
		$value = $this->post[$key] ?? null;

		return is_scalar($value) ? (string) $value : $default;
	}

	public function postBool(string $key): bool
	{
		$value = $this->post[$key] ?? null;

		return $value === '1' || $value === 'on' || $value === 'true' || $value === true;
	}

	/** @return list<string> */
	public function postList(string $key): array
	{
		$value = $this->post[$key] ?? null;
		if (!is_array($value)) {
			return [];
		}

		$out = [];
		foreach ($value as $item) {
			if (is_scalar($item)) {
				$out[] = (string) $item;
			}
		}

		return $out;
	}

	public function all(): array
	{
		return $this->post;
	}

	public function query(string $key, string $default = ''): string
	{
		$value = $this->query[$key] ?? null;

		return is_scalar($value) ? trim((string) $value) : $default;
	}

	public function file(string $key): ?array
	{
		$file = $this->files[$key] ?? null;

		return is_array($file) ? $file : null;
	}

	/**
	 * The client address, taken from the connection only.
	 *
	 * Forwarded headers are spoofable and Pluck cannot know whether it sits
	 * behind a proxy, so trusting them would let anyone reset their own sign-in
	 * throttle by changing a header. A reverse-proxy install sets the trusted
	 * header at the web-server level instead.
	 */
	public function ip(): string
	{
		$ip = $this->server['REMOTE_ADDR'] ?? '';

		return is_string($ip) ? $ip : '';
	}

	public function userAgent(): string
	{
		$agent = $this->server['HTTP_USER_AGENT'] ?? '';

		return is_string($agent) ? $agent : '';
	}

	/** True when HTMX made the request, so a handler can answer with a fragment. */
	public function isHtmx(): bool
	{
		return ($this->server['HTTP_HX_REQUEST'] ?? '') === 'true';
	}

	/**
	 * The raw server environment.
	 *
	 * Kept private except through here, and here it is read-only. The one caller
	 * that needs it is the pretty-URL probe, which has to reconstruct this site's
	 * own address from the same variables the web server set.
	 *
	 * @return array<string,mixed>
	 */
	public function server(): array
	{
		return $this->server;
	}

	public function header(string $name): string
	{
		$key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
		$value = $this->server[$key] ?? '';

		return is_string($value) ? $value : '';
	}
}
