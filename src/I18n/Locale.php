<?php
declare(strict_types=1);

namespace Pluck\I18n;

/**
 * A language code, validated.
 *
 * Locale codes reach the filesystem — lang/nl.json — so they are treated as
 * untrusted input, not as configuration. 4.x built the path straight from
 * $langpref and required() the result, which is how a settings value became a
 * file include.
 */
final class Locale
{
	public const FALLBACK = 'en';

	private function __construct(
		public readonly string $code,
		public readonly string $language,
		public readonly ?string $region,
	) {
	}

	/** Null when the code is not a plausible BCP 47 tag. */
	public static function tryFrom(?string $raw): ?self
	{
		if (!is_string($raw) || $raw === '') {
			return null;
		}

		$normalised = str_replace('_', '-', trim($raw));

		// 4.x stored the language as a filename ("nl.php"); accept that shape too,
		// because the migrator carries it straight over.
		if (str_ends_with(strtolower($normalised), '.php')) {
			$normalised = substr($normalised, 0, -4);
		}

		if (preg_match('/^([a-z]{2,3})(?:-([a-z]{2}|[0-9]{3}))?$/i', $normalised, $m) !== 1) {
			return null;
		}

		$language = strtolower($m[1]);
		$region = isset($m[2]) && $m[2] !== '' ? strtoupper($m[2]) : null;

		return new self($region === null ? $language : $language . '-' . $region, $language, $region);
	}

	public static function fallback(): self
	{
		return self::tryFrom(self::FALLBACK) ?? new self('en', 'en', null);
	}

	/**
	 * The chain to try, most specific first: nl-BE, nl, en.
	 *
	 * @return list<string>
	 */
	public function chain(): array
	{
		$chain = [$this->code];

		if ($this->region !== null) {
			$chain[] = $this->language;
		}
		if (!in_array(self::FALLBACK, $chain, true)) {
			$chain[] = self::FALLBACK;
		}

		return $chain;
	}

	/**
	 * Best match from an Accept-Language header, or null.
	 *
	 * @param list<string> $available
	 */
	public static function negotiate(?string $acceptLanguage, array $available): ?self
	{
		if (!is_string($acceptLanguage) || $acceptLanguage === '') {
			return null;
		}

		$offers = [];
		foreach (explode(',', $acceptLanguage) as $part) {
			$bits = explode(';q=', trim($part));
			$tag = trim($bits[0]);
			$quality = isset($bits[1]) ? (float) $bits[1] : 1.0;
			if ($tag !== '' && $quality > 0) {
				$offers[] = ['tag' => $tag, 'q' => $quality];
			}
		}

		usort($offers, static fn (array $a, array $b): int => $b['q'] <=> $a['q']);

		$lower = array_map('strtolower', $available);

		foreach ($offers as $offer) {
			$locale = self::tryFrom($offer['tag']);
			if ($locale === null) {
				continue;
			}
			foreach ($locale->chain() as $candidate) {
				if ($candidate === self::FALLBACK && strtolower($offer['tag']) !== self::FALLBACK) {
					continue; // Do not let the chain's own fallback win a negotiation.
				}
				$index = array_search(strtolower($candidate), $lower, true);
				if ($index !== false) {
					return self::tryFrom($available[$index]);
				}
			}
		}

		return null;
	}
}
