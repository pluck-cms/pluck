<?php
declare(strict_types=1);

namespace Pluck\Auth;

/**
 * Time-based one-time passwords (RFC 6238), written out rather than pulled from
 * a package: two-factor sign-in should not be the reason a Pluck install needs
 * Composer.
 *
 * Six digits, 30-second steps, SHA-1 — the combination every authenticator app
 * assumes when the provisioning URI leaves those parameters out.
 */
final class Totp
{
	private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	private const DIGITS = 6;
	private const PERIOD = 30;

	/** A fresh base32 secret. 20 bytes is the length RFC 4226 recommends for SHA-1. */
	public static function secret(int $bytes = 20): string
	{
		return self::base32Encode(random_bytes($bytes));
	}

	/**
	 * Verify a code against the current step, allowing for clock drift.
	 *
	 * A window of 1 accepts the previous and next step as well, which covers the
	 * usual case of a phone a few seconds out of sync and a user who starts
	 * typing just before the code rolls over.
	 */
	public static function verify(string $secret, string $code, int $window = 1, ?int $at = null): bool
	{
		$code = preg_replace('/\D/', '', $code) ?? '';
		if (strlen($code) !== self::DIGITS) {
			return false;
		}

		$counter = intdiv($at ?? time(), self::PERIOD);
		$valid = false;

		// Every candidate is checked even after a match so that verification takes
		// the same time whichever step succeeded.
		for ($offset = -$window; $offset <= $window; $offset++) {
			if (hash_equals(self::at($secret, $counter + $offset), $code)) {
				$valid = true;
			}
		}

		return $valid;
	}

	/** The code for a given counter value. */
	public static function at(string $secret, int $counter): string
	{
		$key = self::base32Decode($secret);
		if ($key === '') {
			return str_repeat('0', self::DIGITS);
		}

		$binary = pack('N*', 0, $counter);
		$hash = hash_hmac('sha1', $binary, $key, true);

		$offset = ord($hash[19]) & 0x0f;
		$value = ((ord($hash[$offset]) & 0x7f) << 24)
			| ((ord($hash[$offset + 1]) & 0xff) << 16)
			| ((ord($hash[$offset + 2]) & 0xff) << 8)
			| (ord($hash[$offset + 3]) & 0xff);

		return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
	}

	/** The otpauth:// URI an authenticator app scans. */
	public static function uri(string $secret, string $account, string $issuer): string
	{
		$label = rawurlencode($issuer) . ':' . rawurlencode($account);

		return 'otpauth://totp/' . $label . '?' . http_build_query([
			'secret' => $secret,
			'issuer' => $issuer,
			'algorithm' => 'SHA1',
			'digits' => self::DIGITS,
			'period' => self::PERIOD,
		], '', '&', PHP_QUERY_RFC3986);
	}

	/** Grouped in fours, for someone typing the secret in by hand. */
	public static function readable(string $secret): string
	{
		return trim(chunk_split($secret, 4, ' '));
	}

	private static function base32Encode(string $bytes): string
	{
		$bits = '';
		foreach (str_split($bytes) as $byte) {
			$bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
		}

		$out = '';
		foreach (str_split($bits, 5) as $chunk) {
			$out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
		}

		return $out;
	}

	private static function base32Decode(string $secret): string
	{
		$secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
		if ($secret === '') {
			return '';
		}

		$bits = '';
		foreach (str_split($secret) as $char) {
			$index = strpos(self::ALPHABET, $char);
			if ($index === false) {
				return '';
			}
			$bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
		}

		$out = '';
		foreach (str_split($bits, 8) as $chunk) {
			if (strlen($chunk) === 8) {
				$out .= chr(bindec($chunk));
			}
		}

		return $out;
	}
}
