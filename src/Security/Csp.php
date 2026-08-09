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

	/**
	 * @param list<string> $frameHosts hosts a page may embed a frame from
	 * @return array<string,string>
	 */
	public function siteHeaders(array $frameHosts = []): array
	{
		$directives = [
			"default-src 'self'",
			"script-src 'self'",
			"style-src 'self' 'unsafe-inline'",
			"img-src 'self' data: https:",
			"font-src 'self' data:",
			"frame-ancestors 'self'",
			"form-action 'self'",
			"base-uri 'self'",
			"object-src 'none'",
		];

		/*
		 * Frames are refused unless somebody named the host.
		 *
		 * Without frame-src, a frame falls back to default-src 'self' and a
		 * YouTube embed simply does not appear — which is correct until somebody
		 * actually wants one, and then it is a blank space with nothing in the
		 * page to explain it.
		 *
		 * So it is a setting an owner fills in with the hosts they mean, checked
		 * against a short list of names rather than taken as written. A module
		 * cannot widen this on its own: a module that could would be a module that
		 * can point a frame anywhere.
		 */
		$allowed = self::cleanHosts($frameHosts);

		if ($allowed !== []) {
			$directives[] = "frame-src 'self' " . implode(' ', $allowed);
		}

		return $this->common($directives);
	}

	/**
	 * Hosts that may be named, and nothing else.
	 *
	 * An allow-list rather than a syntax check. "Anything that parses as a host"
	 * lets one careless setting point a frame at whatever somebody talked an owner
	 * into typing, and the people running these sites are not the people who
	 * should have to judge that.
	 *
	 * @param list<string> $hosts
	 * @return list<string>
	 */
	private static function cleanHosts(array $hosts): array
	{
		$known = [
			'youtube' => 'https://www.youtube-nocookie.com',
			'vimeo' => 'https://player.vimeo.com',
			'openstreetmap' => 'https://www.openstreetmap.org',
		];

		$out = [];

		foreach ($hosts as $host) {
			$key = is_string($host) ? strtolower(trim($host)) : '';

			if (isset($known[$key]) && !in_array($known[$key], $out, true)) {
				$out[] = $known[$key];
			}
		}

		return $out;
	}

	/** The names siteHeaders() accepts, for a settings screen to offer. */
	public static function frameHostNames(): array
	{
		return ['youtube', 'vimeo', 'openstreetmap'];
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
