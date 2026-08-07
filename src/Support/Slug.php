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

	public static function make(string $title, string $fallback = 'page'): string
	{
		$slug = trim($title);

		if (function_exists('transliterator_transliterate')) {
			$converted = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $slug);
			if (is_string($converted) && $converted !== '') {
				$slug = $converted;
			}
		}

		/*
		 * The map runs either way, not only as a fallback.
		 *
		 * Latin-ASCII depends on the ICU the server was built against, and older
		 * ones leave characters alone that newer ones fold — Polish ł is the one
		 * that gets reported, because a site whose pages are named in Polish hits
		 * it on the first page. Whatever the transliterator did or did not do, the
		 * map has the last word.
		 *
		 * Costs one strtr over a string that is already short.
		 */
		$slug = strtr(mb_strtolower($slug, 'UTF-8'), self::FOLD);

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
