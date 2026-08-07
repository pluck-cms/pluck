<?php
declare(strict_types=1);

namespace Pluck\Support;

use DOMDocument;
use DOMNode;
use DOMText;

/**
 * Cut HTML down to roughly a number of characters, without breaking it.
 *
 * Pluck 4's blog truncated with substr(). On plain text that is fine; on the
 * HTML its own editor produced it is not, because the cut lands wherever it
 * lands — halfway through `<stro|ng>`, or after an opening `<div>` whose closing
 * tag is in the part that was thrown away. The browser then does what browsers
 * do with unclosed elements, which is to keep them open, and the rest of the page
 * ends up inside the blog summary.
 *
 * So this counts text and cuts the tree, not the string. Elements that are still
 * open at the cut are closed because they are real nodes being serialised, not
 * because anything here remembered to write them out. Words are kept whole, since
 * a summary ending mid-word reads as a bug.
 *
 * The limit is approximate on purpose: finishing a word matters more than landing
 * exactly on the five hundredth character.
 */
final class Excerpt
{
	private int $remaining;

	private bool $truncated = false;

	private function __construct(int $limit)
	{
		$this->remaining = $limit;
	}

	/**
	 * Cut $html to $limit characters of visible text.
	 *
	 * @param int $limit characters of text, ignoring markup. Zero means no limit,
	 *        which is how 4.x spelled "show the whole post".
	 * @param string $ellipsis appended when something was actually removed
	 */
	public static function of(string $html, int $limit, string $ellipsis = '…'): string
	{
		if ($limit <= 0 || trim($html) === '') {
			return $html;
		}

		// Cheap way out: if the text is already short enough, nothing is gained by
		// parsing and re-serialising, which changes entities and self-closing tags
		// even when it changes nothing else.
		if (mb_strlen(trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) <= $limit) {
			return $html;
		}

		$document = new DOMDocument();
		$previous = libxml_use_internal_errors(true);

		// The wrapper gives the fragment a single root and the meta tag forces
		// UTF-8; without it libxml assumes Latin-1 and every accented character
		// comes back wrong.
		$loaded = $document->loadHTML(
			'<?xml encoding="UTF-8"><div id="pluck-excerpt">' . $html . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
		);

		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		$root = $document->getElementById('pluck-excerpt');
		if (!$loaded || $root === null) {
			// Unparseable. Falling back to the untouched HTML is the safe failure:
			// a summary that is too long is a cosmetic problem, and a summary cut
			// with substr() is a broken page.
			return $html;
		}

		$cutter = new self($limit);
		$cutter->walk($root);

		$out = '';
		foreach (iterator_to_array($root->childNodes) as $child) {
			$out .= $document->saveHTML($child);
		}

		return $cutter->truncated ? rtrim($out) . $ellipsis : $out;
	}

	/**
	 * Depth-first, spending the budget as it goes. Once it is gone every
	 * remaining node is removed, which is what closes the elements that were
	 * still open: they are serialised as the trimmed tree, not as a string.
	 */
	private function walk(DOMNode $node): void
	{
		foreach (iterator_to_array($node->childNodes) as $child) {
			if ($this->remaining <= 0) {
				$node->removeChild($child);
				$this->truncated = true;
				continue;
			}

			if ($child instanceof DOMText) {
				$this->spend($child);
				continue;
			}

			$this->walk($child);
		}

		// An element that ended up with nothing in it contributes an empty <p> or
		// a stray <li>. Media elements are exempt: an <img> is content even though
		// it holds no text.
		if ($this->truncated
			&& $node->parentNode !== null
			&& $node->childNodes->length === 0
			&& !in_array(strtolower($node->nodeName), ['img', 'br', 'hr'], true)
		) {
			$node->parentNode->removeChild($node);
		}
	}

	private function spend(DOMText $text): void
	{
		$content = $text->nodeValue ?? '';
		$length = mb_strlen($content);

		if ($length <= $this->remaining) {
			$this->remaining -= $length;

			return;
		}

		$kept = mb_substr($content, 0, $this->remaining);

		// Back up to the last space, so the summary does not end mid-word. If
		// there is no space at all — a very long word, or a language that does not
		// use them — the hard cut stands.
		$space = mb_strrpos($kept, ' ');
		if ($space !== false && $space > 0) {
			$kept = mb_substr($kept, 0, $space);
		}

		$text->nodeValue = rtrim($kept);
		$this->remaining = 0;
		$this->truncated = true;
	}
}
