<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Model\Page;
use Pluck\Site\Urls;
use Pluck\Storage\DriverFactory;

/**
 * A link to another page has to work with readable addresses off.
 *
 * The insert menu writes what the editor stores: `behandelmethoden/acupunctuur`,
 * relative, because that is what it is relative to. Making it absolute used to
 * mean gluing the install's base in front, which is right for a file and wrong
 * for a page: with readable addresses off a page lives at
 * `?page=behandelmethoden/acupunctuur`, and `/behandelmethoden/acupunctuur` is an
 * address the install does not answer to.
 *
 * The person who hits this has done nothing wrong — they picked the page from a
 * menu — and there is nothing in their markup to look at. Which is why it is
 * worth a test rather than a fix.
 */
final class PageLinkTest extends TestCase
{
	public function run(): void
	{
		$this->group('a page link, addresses off', fn () => $this->plain());
		$this->group('a page link, addresses on', fn () => $this->pretty());
		$this->group('a file is still a file', fn () => $this->files());
	}

	/**
	 * absolute() is private, and this is the behaviour it exists for.
	 *
	 * Built without a constructor: SiteRenderer wants a theme, a CSRF token and a
	 * policy to render a page, and this method needs none of them. Handing it a
	 * whole working renderer to test one rewrite would tie this test to every
	 * future argument that constructor grows.
	 */
	private function rewrite(string $html, bool $pretty): string
	{
		$dir = $this->tempDir('pluck-page-link');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();

		$storage->savePage(new Page(path: 'behandelmethoden', title: 'Behandelmethoden', content: ''));
		$storage->savePage(new Page(
			path: 'behandelmethoden/acupunctuur',
			title: 'Acupunctuur',
			content: '',
		));

		$class = new \ReflectionClass(\Pluck\Site\SiteRenderer::class);
		$renderer = $class->newInstanceWithoutConstructor();

		foreach (['storage' => $storage, 'urls' => new Urls('/', $pretty)] as $name => $value) {
			$property = $class->getProperty($name);
			$property->setAccessible(true);
			$property->setValue($renderer, $value);
		}

		$method = $class->getMethod('absolute');
		$method->setAccessible(true);

		return (string) $method->invoke($renderer, $html);
	}

	private function plain(): void
	{
		$out = $this->rewrite('<a href="behandelmethoden/acupunctuur">Acupunctuur</a>', false);

		$this->assertTrue(
			str_contains($out, 'href="/?page=behandelmethoden%2Facupunctuur"')
				|| str_contains($out, 'href="/?page=behandelmethoden/acupunctuur"'),
			'a page link becomes a query address when readable addresses are off',
		);
	}

	private function pretty(): void
	{
		$out = $this->rewrite('<a href="behandelmethoden/acupunctuur">Acupunctuur</a>', true);

		$this->assertTrue(
			str_contains($out, 'href="/behandelmethoden/acupunctuur"'),
			'and a readable one when they are on',
		);
	}

	private function files(): void
	{
		// A file is not a page and must keep the old treatment: `?page=media/x`
		// would be nonsense, and an install with no page of that name should go
		// on serving the file.
		$out = $this->rewrite('<img src="media/foto.jpg"><a href="brochure.pdf">Brochure</a>', false);

		$this->assertTrue(str_contains($out, 'src="/media/foto.jpg"'), 'an image is left as a file');
		$this->assertTrue(str_contains($out, 'href="/brochure.pdf"'), 'and so is a link to one');
	}
}
