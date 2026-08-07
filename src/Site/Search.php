<?php
declare(strict_types=1);

namespace Pluck\Site;

use Pluck\Module\ModuleRegistry;
use Pluck\Storage\StorageDriver;
use Throwable;

/**
 * Site search.
 *
 * Scans rather than indexes, and that is a decision with a shelf life. An index
 * is faster and can go stale, and "I just changed that page and it still says
 * the old thing" is a complaint nobody can reproduce. On the hosting Pluck
 * targets, a site of a few hundred pages is read and matched inside a request
 * without anyone noticing. When that stops being true the answer is an index
 * behind this same class — see ISSUES.md, where the threshold is written down
 * rather than guessed at now.
 *
 * Pages come from storage; everything else comes from the modules, each asked
 * for its own matches. A module that throws contributes nothing and the rest of
 * the results still arrive.
 */
final class Search
{
	private const MIN_LENGTH = 2;

	private const MAX_LENGTH = 100;

	private const SNIPPET_CHARS = 160;

	public function __construct(
		private readonly StorageDriver $storage,
		private readonly ?ModuleRegistry $modules = null,
	) {
	}

	/**
	 * Normalise what someone typed.
	 *
	 * A single character matches most of a site, which is not a search but a
	 * listing, and an unbounded query is a way to make the server do arbitrary
	 * work per request.
	 */
	public static function normalise(string $query): string
	{
		$query = trim(preg_replace('/\s+/u', ' ', $query) ?? $query);

		return mb_substr($query, 0, self::MAX_LENGTH);
	}

	public static function isUsable(string $query): bool
	{
		return mb_strlen(self::normalise($query)) >= self::MIN_LENGTH;
	}

	/** @return list<SearchResult> best match first */
	public function find(string $query): array
	{
		$query = self::normalise($query);
		if (!self::isUsable($query)) {
			return [];
		}

		$results = $this->pages($query);

		foreach ($this->modules?->all() ?? [] as $module) {
			try {
				foreach ($module->search($query, $this->storage) as $result) {
					$results[] = $result;
				}
			} catch (Throwable) {
				// A module having a bad day costs its own results and nothing
				// else. Half a page of results beats an error page.
				continue;
			}
		}

		usort($results, static fn (SearchResult $a, SearchResult $b): int => [$b->score, $a->title] <=> [$a->score, $b->title]);

		return $results;
	}

	/** @return list<SearchResult> */
	private function pages(string $query): array
	{
		$results = [];

		foreach ($this->storage->allPages(includeHidden: false) as $page) {
			$text = self::plain($page->content);
			$score = self::score($query, $page->title, $page->description, $text);

			if ($score === 0) {
				continue;
			}

			$results[] = new SearchResult(
				title: $page->title,
				path: $page->path,
				snippet: self::snippet($query, $text, $page->description),
				score: $score,
				kindKey: 'search.kind.page',
			);
		}

		return $results;
	}

	/**
	 * How well something matches.
	 *
	 * A title match outranks a body match, because someone searching for "opening
	 * hours" wants the page called that rather than the fourteen pages mentioning
	 * it. Deliberately simple: no stemming, no ranking by frequency. Both are
	 * language-specific, and Pluck runs in languages nobody here has thought
	 * about.
	 */
	public static function score(string $query, string $title, string $description, string $text): int
	{
		$needle = mb_strtolower($query);

		$score = 0;
		if (str_contains(mb_strtolower($title), $needle)) {
			$score += 10;
		}
		if (str_contains(mb_strtolower($description), $needle)) {
			$score += 4;
		}
		if (str_contains(mb_strtolower($text), $needle)) {
			$score += 1;
		}

		return $score;
	}

	/**
	 * Text around the match, as plain text.
	 *
	 * Never HTML: a snippet cut out of markup is markup with the tags missing
	 * from one end, and the template would then be asked to print something that
	 * is neither text to escape nor markup to trust.
	 */
	public static function snippet(string $query, string $text, string $fallback = ''): string
	{
		$text = trim($text);
		if ($text === '') {
			return mb_substr(trim($fallback), 0, self::SNIPPET_CHARS);
		}

		$at = mb_stripos($text, $query);
		if ($at === false) {
			return mb_substr($text, 0, self::SNIPPET_CHARS) . (mb_strlen($text) > self::SNIPPET_CHARS ? '…' : '');
		}

		$start = max(0, $at - (int) (self::SNIPPET_CHARS / 3));
		$snippet = mb_substr($text, $start, self::SNIPPET_CHARS);

		// Do not start or end mid-word; a snippet that begins with "ndagmorgen"
		// reads as a bug rather than as an excerpt.
		if ($start > 0) {
			$space = mb_strpos($snippet, ' ');
			$snippet = $space === false ? $snippet : mb_substr($snippet, $space + 1);
		}

		return ($start > 0 ? '…' : '') . rtrim($snippet) . (mb_strlen($text) > $start + self::SNIPPET_CHARS ? '…' : '');
	}

	/** Markup reduced to the words a reader would see. */
	public static function plain(string $html): string
	{
		$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

		return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
	}
}
