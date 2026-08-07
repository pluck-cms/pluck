<?php
declare(strict_types=1);

namespace Pluck\I18n;

/**
 * Translations, from flat-keyed JSON files.
 *
 * Keys are dotted and flat — "page.saved", not a nested $lang['page']['saved'] —
 * because a flat namespace can be checked mechanically. CatalogueTest reads every
 * key the code uses and every key each file defines, and fails on either side of
 * the difference, so a translation cannot rot quietly.
 *
 * English is the source of truth and must be complete; every other locale may be
 * partial and falls back per key rather than per file. A half-translated Dutch
 * install shows Dutch where it has it and English where it does not, which is what
 * someone actually wants.
 *
 * ## Why translations are never trusted markup
 *
 * A theme may ship its own lang/*.json, and a theme arrives as an uploaded
 * archive. So a translation string is attacker-influenceable in the same way page
 * content is, and it is treated the same way: View::t() escapes on output, and
 * there is deliberately no raw variant. A hostile translation can produce visible
 * text and nothing else.
 */
final class Translator
{
	/** @var array<string,array<string,mixed>> locale code => key => value */
	private array $catalogues = [];

	/** @var list<string> */
	private array $missing = [];

	/** @var list<string> directories holding <locale>.json files, later ones win */
	private array $sources = [];

	public function __construct(
		private Locale $locale,
		string ...$sources,
	) {
		foreach ($sources as $source) {
			$this->addSource($source);
		}
	}

	public function locale(): Locale
	{
		return $this->locale;
	}

	public function setLocale(Locale $locale): void
	{
		$this->locale = $locale;
	}

	/**
	 * Add a directory of <locale>.json files. Modules and themes call this with
	 * their own lang folder; their keys are expected to be namespaced already
	 * ("module.blog.title"), so nothing can overwrite a core string by accident.
	 */
	public function addSource(string $directory): void
	{
		if ($directory !== '' && is_dir($directory) && !in_array($directory, $this->sources, true)) {
			$this->sources[] = $directory;
			$this->catalogues = [];
		}
	}

	/**
	 * The translation for $key, with {placeholders} filled in.
	 *
	 * Returns plain text. Anything putting this into HTML must escape it —
	 * templates use View::t(), which does that itself.
	 *
	 * @param array<string,string|int|float> $replacements
	 */
	public function get(string $key, array $replacements = [], ?int $count = null): string
	{
		$value = $this->lookup($key);

		if ($value === null) {
			$this->missing[] = $key;

			// Better a visible key than a blank space: an untranslated screen should
			// look unfinished, not broken.
			return $key;
		}

		if (is_array($value)) {
			$value = $this->pluralForm($value, $count ?? (int) ($replacements['count'] ?? 0));
		}

		$text = (string) $value;

		if ($count !== null && !array_key_exists('count', $replacements)) {
			$replacements['count'] = $count;
		}

		foreach ($replacements as $name => $replacement) {
			$text = str_replace('{' . $name . '}', (string) $replacement, $text);
		}

		return $text;
	}

	public function has(string $key): bool
	{
		return $this->lookup($key) !== null;
	}

	/** @return list<string> keys that were asked for and not found */
	public function missing(): array
	{
		return array_values(array_unique($this->missing));
	}

	/**
	 * Locales with at least one file present.
	 *
	 * @return list<string>
	 */
	public function available(): array
	{
		$codes = [];

		foreach ($this->sources as $directory) {
			foreach (glob($directory . '/*.json') ?: [] as $file) {
				$code = basename($file, '.json');
				if (Locale::tryFrom($code) !== null) {
					$codes[$code] = true;
				}
			}
		}

		$list = array_keys($codes);
		sort($list);

		return $list;
	}

	/**
	 * Everything defined for one locale, without the fallback chain. Used by the
	 * catalogue test and by any translation tooling.
	 *
	 * @return array<string,mixed>
	 */
	public function catalogue(string $code): array
	{
		if (isset($this->catalogues[$code])) {
			return $this->catalogues[$code];
		}

		$merged = [];

		foreach ($this->sources as $directory) {
			// The locale code has been validated, but resolve it here too: this is
			// the one place a language setting becomes a filename.
			$locale = Locale::tryFrom($code);
			if ($locale === null) {
				continue;
			}

			$file = $directory . '/' . $locale->code . '.json';
			if (!is_file($file)) {
				continue;
			}

			$decoded = json_decode((string) file_get_contents($file), true);
			if (!is_array($decoded)) {
				continue;
			}

			$merged = array_merge($merged, $this->flatten($decoded));
		}

		return $this->catalogues[$code] = $merged;
	}

	private function lookup(string $key): string|array|null
	{
		foreach ($this->locale->chain() as $code) {
			$catalogue = $this->catalogue($code);
			if (array_key_exists($key, $catalogue)) {
				$value = $catalogue[$key];
				if (is_string($value) || is_array($value)) {
					return $value;
				}
				if (is_scalar($value)) {
					return (string) $value;
				}
			}
		}

		return null;
	}

	/** @param array<string,mixed> $forms */
	private function pluralForm(array $forms, int $count): string
	{
		$category = Plural::category($this->locale->language, $count);

		foreach ([$category, 'other', 'one'] as $candidate) {
			if (isset($forms[$candidate]) && is_string($forms[$candidate])) {
				return $forms[$candidate];
			}
		}

		$first = reset($forms);

		return is_string($first) ? $first : '';
	}

	/**
	 * Accept both a flat file and a nested one, flattening the latter to dotted
	 * keys. Plural objects — the ones whose keys are all plural categories — are
	 * left as values rather than flattened into "key.one".
	 *
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>
	 */
	private function flatten(array $data, string $prefix = ''): array
	{
		$out = [];

		foreach ($data as $key => $value) {
			$full = $prefix === '' ? (string) $key : $prefix . '.' . $key;

			if (is_array($value) && !$this->isPluralSet($value)) {
				$out += $this->flatten($value, $full);
				continue;
			}

			$out[$full] = $value;
		}

		return $out;
	}

	/** @param array<mixed> $value */
	private function isPluralSet(array $value): bool
	{
		if ($value === []) {
			return false;
		}

		foreach (array_keys($value) as $key) {
			if (!in_array($key, ['zero', 'one', 'two', 'few', 'many', 'other'], true)) {
				return false;
			}
		}

		return true;
	}
}
