<?php
declare(strict_types=1);

namespace Pluck\Security;

/**
 * Security response headers, including a nonce-based CSP.
 *
 * The admin has no inline event handlers, so its policy needs no
 * 'unsafe-inline' for scripts. Theme authors get a looser style policy because
 * existing Pluck themes rely on inline <style> blocks.
 */
final class Csp
{
	private string $nonce;

	public function __construct()
	{
		$this->nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
	}

	public function nonce(): string
	{
		return $this->nonce;
	}

	/** @return array<string,string> */
	public function adminHeaders(): array
	{
		return $this->common([
			"default-src 'self'",
			"script-src 'self' 'nonce-{$this->nonce}'",
			"style-src 'self' 'nonce-{$this->nonce}'",
			"img-src 'self' data: blob:",
			"font-src 'self'",
			"connect-src 'self'",
			"frame-ancestors 'none'",
			"form-action 'self'",
			"base-uri 'none'",
			"object-src 'none'",
		]);
	}

	/** @return array<string,string> */
	public function siteHeaders(): array
	{
		return $this->common([
			"default-src 'self'",
			"script-src 'self'",
			"style-src 'self' 'unsafe-inline'",
			"img-src 'self' data: https:",
			"font-src 'self' data:",
			"frame-ancestors 'self'",
			"form-action 'self'",
			"base-uri 'self'",
			"object-src 'none'",
		]);
	}

	/**
	 * @param list<string> $policy
	 * @return array<string,string>
	 */
	private function common(array $policy): array
	{
		$headers = [
			'Content-Security-Policy'   => implode('; ', $policy),
			'X-Content-Type-Options'    => 'nosniff',
			'Referrer-Policy'           => 'strict-origin-when-cross-origin',
			'X-Frame-Options'           => 'SAMEORIGIN',
			'Cross-Origin-Opener-Policy'=> 'same-origin',
			'Permissions-Policy'        => 'geolocation=(), microphone=(), camera=()',
		];

		if (Session::isHttps()) {
			$headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
		}

		return $headers;
	}

	/** @param array<string,string> $headers */
	public static function send(array $headers): void
	{
		if (headers_sent()) {
			return;
		}
		foreach ($headers as $name => $value) {
			header($name . ': ' . $value);
		}
	}
}
