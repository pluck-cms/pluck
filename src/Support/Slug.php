<?php
declare(strict_types=1);

namespace Pluck\Support;

/**
 * Slug and path handling for pages.
 *
 * Pluck 4 fell back to a timestamp whenever a title contained no latin
 * characters (issue #27), which produced unreadable URLs for anyone not writing
 * in a latin script. Version 5 transliterates what it can and keeps unicode
 * word characters otherwise, so a Cyrillic or Greek title still yields a
 * readable slug.
 */
final class Slug
{
	/**
	 * Folded by hand when intl is missing, which is common on shared hosting.
	 * Without this, a Dutch or French title would keep its accents in the URL on
	 * exactly the servers Pluck is most often installed on.
	 */
	private const FOLD = [
		'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','ā'=>'a','ą'=>'a',
		'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ē'=>'e','ę'=>'e','ě'=>'e',
		'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ī'=>'i','į'=>'i',
		'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o','ō'=>'o','ő'=>'o',
		'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ū'=>'u','ů'=>'u','ű'=>'u',
		'ç'=>'c','ć'=>'c','č'=>'c','ñ'=>'n','ń'=>'n','ň'=>'n',
		'ś'=>'s','š'=>'s','ș'=>'s','ß'=>'ss','ť'=>'t','ț'=>'t',
		'ý'=>'y','ÿ'=>'y','ź'=>'z','ž'=>'z','ż'=>'z','ł'=>'l','đ'=>'d','ð'=>'d','þ'=>'th',
		'æ'=>'ae','œ'=>'oe','ĳ'=>'ij',
	];

	/**
	 * The fold table, for bin/slug to show and for anybody adding a language.
	 *
	 * @return array<string,string>
	 */
	public static function foldMap(): array
	{
		return self::FOLD;
	}

	public static function make(string $title, string $fallback = 'page'): string
	{
		$slug = trim($title);

		/*
		 * The map first, the transliterator after — and the order is the point.
		 *
		 * A slug is an address. If the same title produces a different address on
		 * a server with ICU than on one without, then moving a site changes its
		 * URLs, and every link anybody made to it breaks. That is a worse fault
		 * than a character coming out wrong, because it is silent and it happens
		 * during a migration when nobody is looking at slugs.
		 *
		 * So the characters Pluck has decided to guarantee are folded by a table
		 * that is the same on every machine, and ICU is asked only about what is
		 * left. Latin sites are then deterministic; Greek, Cyrillic and the rest
		 * still transliterate where ICU is present, which no table could do.
		 *
		 * The other way round — ICU first, table as a safety net — gives the same
		 * answer on this machine and a different one on somebody else's, which is
		 * exactly the report that led here.
		 */
		$slug = strtr(mb_strtolower($slug, 'UTF-8'), self::FOLD);

		// Anything still outside ASCII: a script no table covers, or a Latin
		// letter nobody has added to FOLD yet.
		if (preg_match('/[^\x00-\x7F]/', $slug) === 1 && function_exists('transliterator_transliterate')) {
			$converted = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $slug);
			if (is_string($converted) && $converted !== '') {
				$slug = $converted;
			}
		}

		$slug = mb_strtolower($slug, 'UTF-8');
		$slug = preg_replace('/[\'"\x{2018}\x{2019}\x{201C}\x{201D}]/u', '', $slug) ?? $slug;
		$slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug) ?? $slug;
		$slug = trim($slug, '-');
		$slug = preg_replace('/-{2,}/', '-', $slug) ?? $slug;

		if ($slug === '' || preg_match('/^-+$/', $slug) === 1) {
			return $fallback;
		}

		return mb_substr($slug, 0, 96, 'UTF-8');
	}

	/**
	 * Normalise a page path ("about/team"): slugify each segment, drop empties,
	 * cap the depth. Returns '' for the site root.
	 */
	public static function path(string $path, int $maxDepth = 4): string
	{
		$segments = [];
		foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
			$segment = self::make($segment, '');
			if ($segment !== '') {
				$segments[] = $segment;
			}
			if (count($segments) >= $maxDepth) {
				break;
			}
		}

		return implode('/', $segments);
	}

	/** Append/-bump a numeric suffix until $exists() reports the path as free. */
	public static function unique(string $path, callable $exists): string
	{
		if (!$exists($path)) {
			return $path;
		}

		for ($i = 2; $i < 1000; $i++) {
			$candidate = $path . '-' . $i;
			if (!$exists($candidate)) {
				return $candidate;
			}
		}

		return $path . '-' . bin2hex(random_bytes(4));
	}
}
