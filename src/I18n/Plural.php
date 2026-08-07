<?php
declare(strict_types=1);

namespace Pluck\I18n;

/**
 * Which plural form a number takes, per language.
 *
 * Only the rules for languages Pluck actually ships or is likely to get. Adding
 * a language with a different rule means adding it here; a language with no rule
 * falls back to the two-form pattern, which is wrong for Polish and Russian but
 * at least predictable, and CatalogueTest reports it rather than letting it pass
 * unnoticed.
 */
final class Plural
{
	/** @var array<string,list<string>> language => the categories it uses */
	private const CATEGORIES = [
		'en' => ['one', 'other'],
		'nl' => ['one', 'other'],
		'de' => ['one', 'other'],
		'sv' => ['one', 'other'],
		'da' => ['one', 'other'],
		'no' => ['one', 'other'],
		'es' => ['one', 'other'],
		'it' => ['one', 'other'],
		'pt' => ['one', 'other'],
		'fr' => ['one', 'other'],
		'tr' => ['one', 'other'],
		'pl' => ['one', 'few', 'many'],
		'ru' => ['one', 'few', 'many'],
		'uk' => ['one', 'few', 'many'],
		'cs' => ['one', 'few', 'other'],
		'ja' => ['other'],
		'zh' => ['other'],
		'id' => ['other'],
	];

	public static function category(string $language, int $count): string
	{
		$language = strtolower($language);
		$n = abs($count);

		return match ($language) {
			'ja', 'zh', 'id', 'ko', 'vi', 'th' => 'other',

			// French and Brazilian Portuguese treat 0 as singular.
			'fr' => $n <= 1 ? 'one' : 'other',

			'pl' => match (true) {
				$n === 1 => 'one',
				$n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 12 || $n % 100 > 14) => 'few',
				default => 'many',
			},

			'ru', 'uk' => match (true) {
				$n % 10 === 1 && $n % 100 !== 11 => 'one',
				$n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 12 || $n % 100 > 14) => 'few',
				default => 'many',
			},

			'cs', 'sk' => match (true) {
				$n === 1 => 'one',
				$n >= 2 && $n <= 4 => 'few',
				default => 'other',
			},

			default => $n === 1 ? 'one' : 'other',
		};
	}

	/** @return list<string>|null null when the language has no rule of its own */
	public static function categoriesFor(string $language): ?array
	{
		return self::CATEGORIES[strtolower($language)] ?? null;
	}
}
