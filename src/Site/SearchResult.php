<?php
declare(strict_types=1);

namespace Pluck\Site;

/**
 * One thing found.
 *
 * The snippet is plain text, not markup, and stays that way until the template
 * escapes it. Building a highlighted excerpt out of already-escaped HTML means
 * counting characters that are not the characters the reader sees — `&amp;` is
 * one glyph and five bytes — and every off-by-one there is either a broken
 * entity on the page or a hole. So the text travels raw and is escaped once, at
 * the end, by the code that knows it is writing HTML.
 */
final class SearchResult
{
	public function __construct(
		public readonly string $title,
		public readonly string $path,
		public readonly string $snippet,
		public readonly int $score,
		/** A translation key naming what kind of thing this is. */
		public readonly string $kindKey = 'search.kind.page',
	) {
	}
}
