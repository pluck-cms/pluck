<?php
declare(strict_types=1);

namespace Pluck\Security;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list HTML sanitiser for content produced in the editor.
 *
 * This is the second half of the XSS story. Editors are trusted to write HTML,
 * but "trusted" in a multi-admin install means an author must not be able to
 * plant script that runs in the owner's session. Everything not on the list is
 * unwrapped (children kept) or dropped.
 */
final class Sanitizer
{
	/** @var array<string,list<string>> tag => allowed attributes */
	/**
	 * The tags this accepts, for anything that has to agree with it.
	 *
	 * The editor cleans pasted markup to the same shape so a page does not change
	 * appearance on save, and it kept its own list — which had drifted: it dropped
	 * hr, sub, sup, mark, q and the definition list, all of which survive a save
	 * perfectly well. One list, read from here.
	 *
	 * @return list<string>
	 */
	public static function allowedTags(): array
	{
		return array_keys(self::ALLOWED);
	}

	private const ALLOWED = [
		'p' => ['class'], 'br' => [], 'hr' => [],
		'h1' => ['id', 'class'], 'h2' => ['id', 'class'], 'h3' => ['id', 'class'],
		'h4' => ['id', 'class'], 'h5' => ['id', 'class'], 'h6' => ['id', 'class'],
		'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
		'sub' => [], 'sup' => [], 'small' => [], 'mark' => [], 'code' => [],
		'pre' => ['class'], 'blockquote' => ['cite'], 'q' => ['cite'],
		'ul' => ['class'], 'ol' => ['class', 'start'], 'li' => ['class'],
		'dl' => [], 'dt' => [], 'dd' => [],
		'a' => ['href', 'title', 'target', 'rel', 'class'],
		'img' => ['src', 'alt', 'title', 'width', 'height', 'loading', 'class'],
		'figure' => ['class'], 'figcaption' => [],
		'table' => ['class'], 'thead' => [], 'tbody' => [], 'tfoot' => [],
		'tr' => [], 'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'],
		/*
		 * width on <col> so a table can have column widths at all.
		 *
		 * On <col> rather than through style, which is not on this list and should
		 * not be: a width attribute holds a number or a percentage and can carry
		 * nothing else, while a style attribute can carry a background image, a
		 * font from elsewhere, or a position that covers the page.
		 */
		'caption' => [], 'col' => ['span', 'width'], 'colgroup' => ['span'],
		'div' => ['class'], 'span' => ['class'],
		'section' => ['class'], 'article' => ['class'], 'aside' => ['class'],
		'time' => ['datetime'], 'abbr' => ['title'],
	];

	private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'noscript', 'template', 'svg', 'math', 'base', 'link', 'meta'];

	/** @var array<string,int> what the current run took out */
	private array $removals = [];

	/**
	 * Sanitise, and say what was removed.
	 *
	 * Callers that only want clean HTML use clean(). This exists for the two
	 * places where a person has to decide something about the result: the
	 * migrator, which reports on someone else's decade-old content, and the
	 * editor, which should be able to say why what you pasted came out different.
	 */
	public function inspect(string $html): SanitizerReport
	{
		$this->removals = [];
		$clean = $this->clean($html);
		$removals = $this->removals;
		$this->removals = [];

		return new SanitizerReport($clean, $removals);
	}

	public function clean(string $html): string
	{
		if (trim($html) === '') {
			return '';
		}

		$doc = new DOMDocument('1.0', 'UTF-8');
		$previous = libxml_use_internal_errors(true);
		$doc->loadHTML(
			'<?xml encoding="UTF-8"><body>' . $html . '</body>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
		);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		$body = $doc->getElementsByTagName('body')->item(0);
		if ($body === null) {
			return '';
		}

		$this->walk($body);

		$out = '';
		foreach ($body->childNodes as $child) {
			$out .= $doc->saveHTML($child);
		}

		return trim($out);
	}

	private function walk(DOMNode $node): void
	{
		// Iterate over a snapshot: we mutate the tree while walking it.
		$children = [];
		foreach ($node->childNodes as $child) {
			$children[] = $child;
		}

		foreach ($children as $child) {
			if ($child instanceof DOMElement) {
				$this->handleElement($child);
				continue;
			}
			if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
				$child->parentNode?->removeChild($child);
			}
		}
	}

	private function handleElement(DOMElement $el): void
	{
		$tag = strtolower($el->nodeName);

		if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
			$this->record('<' . $tag . '> and its contents');
			$el->parentNode?->removeChild($el);
			return;
		}

		if (!array_key_exists($tag, self::ALLOWED)) {
			// Unknown tag: keep the text, lose the wrapper.
			$this->record('<' . $tag . '> tags (the text inside was kept)');
			$this->walk($el);
			$parent = $el->parentNode;
			while ($el->firstChild !== null) {
				$parent?->insertBefore($el->firstChild, $el);
			}
			$parent?->removeChild($el);
			return;
		}

		$allowed = self::ALLOWED[$tag];
		$attributes = [];
		foreach ($el->attributes ?? [] as $attr) {
			$attributes[] = $attr->nodeName;
		}

		foreach ($attributes as $name) {
			$lower = strtolower($name);
			if (!in_array($lower, $allowed, true) || str_starts_with($lower, 'on')) {
				// An on* handler is script, whatever tag it sits on, so it is worth
				// naming separately from a merely unlisted attribute.
				$this->record(str_starts_with($lower, 'on')
					? sprintf('%s="..." event handlers', $lower)
					: sprintf('%s on <%s>', $lower, $tag));
				$el->removeAttribute($name);
				continue;
			}
			if (in_array($lower, ['href', 'src', 'cite'], true)) {
				$value = $el->getAttribute($name);
				if (Escaper::url($value) === '#' && $value !== '#') {
					$this->record(sprintf('%s on <%s> pointing somewhere unsafe', $lower, $tag));
					$el->removeAttribute($name);
				}
			}
		}

		// Anything opening a new window gets noopener, always.
		if ($tag === 'a' && $el->getAttribute('target') !== '') {
			$el->setAttribute('rel', 'noopener noreferrer');
		}

		$this->walk($el);
	}

	private function record(string $what): void
	{
		$this->removals[$what] = ($this->removals[$what] ?? 0) + 1;
	}
}
