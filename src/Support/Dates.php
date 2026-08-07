<?php
declare(strict_types=1);

namespace Pluck\Support;

use IntlDateFormatter;

/**
 * Dates, written the way the reader expects them.
 *
 * `date('j F Y')` always says "3 May 2021", whatever language the site is in,
 * because PHP's month names are not translated and setlocale() does not reach
 * them. On a Dutch site that is simply wrong, and it is the kind of wrong that
 * survives for years because it looks like a date.
 *
 * ext-intl does this properly and is present on most hosting and in the Docker
 * image. Where it is missing the ISO form is used instead: 2021-05-03 is at
 * least unambiguous in every language, which a half-translated date is not.
 */
final class Dates
{
	/** A date without a time, in $locale. */
	public static function long(string|int $when, string $locale = 'en'): string
	{
		$stamp = self::stamp($when);
		if ($stamp === null) {
			return '';
		}

		if (class_exists(IntlDateFormatter::class)) {
			$formatter = new IntlDateFormatter(
				$locale,
				IntlDateFormatter::LONG,
				IntlDateFormatter::NONE,
			);

			$formatted = $formatter->format($stamp);
			if (is_string($formatted) && $formatted !== '') {
				return $formatted;
			}
		}

		return date('Y-m-d', $stamp);
	}

	/**
	 * A date in an explicitly configured format, falling back to the localised
	 * one when no format is set.
	 *
	 * The fallback is the point. `date()` format strings are what Pluck 4 offered
	 * and some people want them — `d/m/Y` is shorter than "3 May 2021" and fits a
	 * narrow column. But a format string is the same in every language, so
	 * choosing one turns localisation off. Leaving the setting empty therefore
	 * has to mean "let the site language decide", not "use some default pattern",
	 * or the better behaviour would only be available to people who knew to clear
	 * a field.
	 *
	 * Note that `date()` uses the process timezone, which Bootstrap sets from
	 * config.php, so a configured format shows local time while the machine
	 * readable attribute alongside it stays UTC.
	 */
	public static function pattern(string|int $when, string $format, string $locale = 'en'): string
	{
		$stamp = self::stamp($when);
		if ($stamp === null) {
			return '';
		}

		$format = trim($format);
		if ($format === '') {
			return self::long($stamp, $locale);
		}

		// A format arrives from an admin form. date() executes nothing, so the
		// worst a strange one does is produce a strange date, but a thousand
		// characters of it in every summary is worth refusing.
		return date(mb_substr($format, 0, 40), $stamp);
	}

	/** The machine-readable form, for a datetime attribute. */
	public static function iso(string|int $when): string
	{
		$stamp = self::stamp($when);

		return $stamp === null ? '' : gmdate('c', $stamp);
	}

	private static function stamp(string|int $when): ?int
	{
		if (is_int($when)) {
			return $when > 0 ? $when : null;
		}

		if (trim($when) === '') {
			return null;
		}

		$stamp = strtotime($when);

		return $stamp === false ? null : $stamp;
	}
}
