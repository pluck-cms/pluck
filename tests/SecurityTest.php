<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Security\Escaper;
use Pluck\Security\Sanitizer;

/**
 * The XSS regression suite. Every payload here is either one that worked against
 * Pluck 4 or a variant of one, kept as a permanent test rather than a changelog
 * entry.
 */
final class SecurityTest extends TestCase
{
	public function run(): void
	{
		$this->group('escaper', function (): void {
			$this->assertSame(
				'&lt;script&gt;alert(1)&lt;/script&gt;',
				Escaper::html('<script>alert(1)</script>'),
				'script tags are escaped in html context',
			);
			$this->assertSame('&quot;&gt;&lt;img&gt;', Escaper::html('"><img>'), 'attribute breakout is escaped');
			$this->assertSame('&#039;', Escaper::html("'"), 'single quotes are escaped too');

			$this->assertSame('#', Escaper::url('javascript:alert(1)'), 'javascript: url is neutralised');
			$this->assertSame('#', Escaper::url('JaVaScRiPt:alert(1)'), 'scheme check is case-insensitive');
			$this->assertSame('#', Escaper::url("java\tscript:alert(1)"), 'whitespace inside the scheme does not help');
			$this->assertSame('#', Escaper::url('data:text/html;base64,PHNjcmlwdD4='), 'data: url is neutralised');
			$this->assertSame('#', Escaper::url('vbscript:msgbox'), 'vbscript: url is neutralised');
			$this->assertSame('https://example.org/a?b=1', Escaper::url('https://example.org/a?b=1'), 'https url survives');
			$this->assertSame('/about', Escaper::url('/about'), 'relative url survives');
			$this->assertSame('mailto:a@example.org', Escaper::url('mailto:a@example.org'), 'mailto survives');
		});

		$this->group('sanitizer', function (): void {
			if (!$this->requiresExtension('dom', 'the HTML sanitiser')) {
				return;
			}
			$sanitizer = new Sanitizer();

			$this->assertSame('', $sanitizer->clean('<script>alert(1)</script>'), 'script element is removed entirely');
			$this->assertSame('', $sanitizer->clean('<style>body{display:none}</style>'), 'style element is removed');
			$this->assertSame('', $sanitizer->clean('<iframe src="//evil"></iframe>'), 'iframe is removed');

			$this->assertSame(
				'<p>hi</p>',
				$sanitizer->clean('<p onclick="alert(1)">hi</p>'),
				'event handler attribute is stripped',
			);
			$this->assertSame(
				'<p>hi</p>',
				$sanitizer->clean('<p ONMOUSEOVER="alert(1)">hi</p>'),
				'uppercase event handler is stripped as well',
			);
			$this->assertSame(
				'<img alt="x">',
				$sanitizer->clean('<img src="javascript:alert(1)" alt="x">'),
				'javascript: src is dropped but the image tag stays',
			);
			$this->assertSame(
				'<a>click</a>',
				$sanitizer->clean('<a href="javascript:alert(1)">click</a>'),
				'javascript: href is dropped',
			);
			$this->assertSame(
				'<a href="https://example.org" target="_blank" rel="noopener noreferrer">x</a>',
				$sanitizer->clean('<a href="https://example.org" target="_blank">x</a>'),
				'target=_blank always gets noopener',
			);

			$this->assertSame(
				'<p>keep me</p>',
				$sanitizer->clean('<unknown-tag><p>keep me</p></unknown-tag>'),
				'unknown wrapper is unwrapped, content kept',
			);
			$this->assertSame(
				'<p>text</p>',
				$sanitizer->clean('<p>text</p><!-- <script>alert(1)</script> -->'),
				'comments are removed so conditional-comment tricks cannot hide script',
			);
			$this->assertSame(
				'<p>a &amp; b</p>',
				$sanitizer->clean('<p>a &amp; b</p>'),
				'legitimate entities are preserved',
			);
			$this->assertSame(
				'<table><tr><td>1</td></tr></table>',
				$sanitizer->clean('<table><tr><td>1</td></tr></table>'),
				'tables survive, because themes and content use them',
			);
			$this->assertSame(
				'<p>unicode: ✓ é 日本語</p>',
				$sanitizer->clean('<p>unicode: ✓ é 日本語</p>'),
				'utf-8 content is not mangled',
			);
			$this->assertSame('', $sanitizer->clean('   '), 'whitespace-only input yields empty output');
		});
	}
}
