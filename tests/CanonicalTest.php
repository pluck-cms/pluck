<?php
declare(strict_types=1);

namespace Pluck\Tests;

/**
 * An ordinary page says where it lives.
 *
 * Every theme in the box has `if ($canonical !== null)` in its head, and nothing
 * ever passed a value for a page — only a module got one. So the tag that keeps
 * one page from looking like several never appeared on the pages that need it.
 *
 * One page really does answer to more than one address: `/` and `/welkom` are
 * the same page, and `?page=x` and `/x` both work while old links point at a
 * site.
 */
final class CanonicalTest extends TestCase
{
	public function run(): void
	{
		$this->group('a page carries one', fn () => $this->passed());
	}

	private function passed(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/src/Site/SiteRenderer.php');

		$this->assertTrue(
			str_contains($source, "'canonical' => \$this->isFrontPage(\$page)"),
			'page() passes a canonical rather than leaving the default null',
		);

		/*
		 * The front page names the root, not its own path.
		 *
		 * `/` is what people link to and what a search result should show, and the
		 * page is reachable both ways — so both have to name the same address or
		 * the tag does nothing at all.
		 */
		$this->assertTrue(
			str_contains($source, "? \$this->urls->to('')"),
			'and the front page names the root',
		);

		/*
		 * One place decides which page that is.
		 *
		 * index.php picks the page to serve and the renderer names the canonical;
		 * if both worked it out, the day somebody changes what "front page" means
		 * only one of them would follow.
		 */
		$index = (string) file_get_contents(dirname(__DIR__) . '/index.php');

		$this->assertFalse(
			str_contains($index, 'The front page is the first page in menu order'),
			'and the rule is not written out in two places',
		);
	}
}
