<?php
declare(strict_types=1);

namespace Pluck\Security;

use RuntimeException;

/**
 * Per-session CSRF tokens, verified with hash_equals.
 *
 * Pluck 4 kept one long-lived token in data/settings/token.php, written with
 * chmod 0777. Here the secret lives in the session only, so it never touches
 * the webroot and disappears on logout.
 */
final class Csrf
{
	private const KEY = '_pluck_csrf';
	public const FIELD = '_token';

	public function __construct(private readonly Session $session)
	{
	}

	public function token(): string
	{
		$token = $this->session->get(self::KEY);
		if (!is_string($token) || strlen($token) !== 64) {
			$token = bin2hex(random_bytes(32));
			$this->session->set(self::KEY, $token);
		}

		return $token;
	}

	/** Hidden input for a form. */
	public function field(): string
	{
		return sprintf(
			'<input type="hidden" name="%s" value="%s">',
			self::FIELD,
			Escaper::html($this->token()),
		);
	}

	public function isValid(?string $candidate): bool
	{
		$expected = $this->session->get(self::KEY);

		return is_string($expected)
			&& is_string($candidate)
			&& $candidate !== ''
			&& hash_equals($expected, $candidate);
	}

	/**
	 * Guard a state-changing request. Accepts the token from the form field or
	 * from the X-CSRF-Token header, which is what an HTMX admin would send.
	 *
	 * @param array<string,mixed> $post
	 * @param array<string,mixed> $server
	 */
	public function assert(array $post, array $server = []): void
	{
		$candidate = $post[self::FIELD] ?? $server['HTTP_X_CSRF_TOKEN'] ?? null;

		if (!$this->isValid(is_string($candidate) ? $candidate : null)) {
			throw new RuntimeException('This form has expired. Reload the page and try again.');
		}
	}

	public function rotate(): void
	{
		$this->session->forget(self::KEY);
	}
}
