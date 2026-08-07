<?php
declare(strict_types=1);

namespace Pluck\Security;

/**
 * Context-aware escaping.
 *
 * Pluck 4 tried to stop XSS at the door with a $_GET blacklist in
 * data/inc/security.php (which carried its own "quick and dirty fix" note) while
 * templates echoed values raw. Version 5 inverts that: input is accepted as-is
 * and every value is escaped for the context it lands in.
 */
final class Escaper
{
	/** Text inside an element or a double-quoted attribute. */
	public static function html(?string $value): string
	{
		return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/** A value being interpolated into a JS context, e.g. a data attribute payload. */
	public static function js(mixed $value): string
	{
		return json_encode(
			$value,
			JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR,
		);
	}

	/** A single URL query parameter value. */
	public static function query(string $value): string
	{
		return rawurlencode($value);
	}

	/**
	 * A complete URL used in href/src. Rejects javascript:, data: and other
	 * script-bearing schemes; anything unrecognised degrades to '#'.
	 */
	public static function url(string $url): string
	{
		$trimmed = trim($url);
		$stripped = preg_replace('/[\x00-\x20]/', '', $trimmed) ?? $trimmed;

		if (preg_match('#^([a-z][a-z0-9+.\-]*):#i', $stripped, $m) === 1) {
			$scheme = strtolower($m[1]);
			if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
				return '#';
			}
		}

		return self::html($trimmed);
	}

	/** A CSS identifier or token coming from settings (theme names, colours). */
	public static function cssToken(string $value): string
	{
		return preg_replace('/[^a-zA-Z0-9#(),.%\s_-]/', '', $value) ?? '';
	}
}
