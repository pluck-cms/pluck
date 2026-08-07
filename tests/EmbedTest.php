<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Model\Page;
use Pluck\Module\ModuleRegistry;
use Pluck\Module\Modules;
use Pluck\Security\Sanitizer;
use Pluck\Site\Embed;
use Pluck\Site\Urls;
use Pluck\I18n\Locale;
use Pluck\I18n\Translator;
use Pluck\Security\Session;
use Pluck\Security\Csp;
use Pluck\Security\Csrf;
use Pluck\Theme\Theme;
use Pluck\Site\SiteRenderer;
use Pluck\Storage\DriverFactory;
use Pluck\Storage\StorageDriver;

/**
 * `[module:blog]` in a page.
 *
 * The parsing is the risky part. A pattern loose enough to catch what an author
 * meant is a pattern that starts interpreting ordinary prose, and a page that
 * quietly swallows a sentence because it contained brackets is a bug nobody
 * reports because nobody can describe it.
 */
final class EmbedTest extends TestCase
{
	public function run(): void
	{
		$this->group('a theme that stacks pages', fn () => $this->stackedPages());

		$this->group('parsing parameters', fn () => $this->parameters());
		$this->group('what is left alone', fn () => $this->leftAlone());
		$this->group('expanding', fn () => $this->expanding());
		$this->group('surviving the sanitiser', fn () => $this->sanitiser());
		$this->group('a module that fails', fn () => $this->failing());
	}

	private function parameters(): void
	{
		$this->assertSame([], Embed::parameters(''), 'no parameters is an empty list');
		$this->assertSame(['count' => '3'], Embed::parameters(' count=3'), 'a bare value');
		$this->assertSame(['album' => 'zomer 2011'], Embed::parameters(' album="zomer 2011"'), 'a quoted value keeps its spaces');
		$this->assertSame(['album' => 'zomer 2011'], Embed::parameters(" album='zomer 2011'"), 'single quotes work too');
		$this->assertSame(
			['count' => '3', 'category' => 'nieuws'],
			Embed::parameters(' count=3 category=nieuws'),
			'several parameters',
		);
		$this->assertSame(['count' => '3'], Embed::parameters(' COUNT=3'), 'names are lowercased');

		$this->assertSame(
			200,
			mb_strlen(Embed::parameters(' album="' . str_repeat('a', 500) . '"')['album']),
			'a very long value is cut rather than carried',
		);
	}

	private function leftAlone(): void
	{
		$embed = $this->embed();

		foreach ([
			'<p>See [1] for details.</p>' => 'a footnote reference',
			'<p>Use [module] to embed.</p>' => 'the word module in brackets, without a name',
			'<p>The syntax is [module:name].</p>' => 'a marker naming a module that is not installed',
			'<p>[Module Blog]</p>' => 'square brackets around ordinary words',
			'<p>array[module:blog</p>' => 'an unclosed bracket',
		] as $html => $what) {
			$this->assertSame($html, $embed->expand($html), $what . ' is left exactly as written');
		}

		// A marker for a module nobody installed stays visible on purpose: an
		// author staring at a page that is silently missing something has nothing
		// to go on, while a visible [module:blgo] is a typo they can see.
		$this->assertTrue(
			str_contains($embed->expand('<p>[module:blgo]</p>'), '[module:blgo]'),
			'a misspelled module name stays on the page, so the mistake is findable',
		);
	}

	private function expanding(): void
	{
		[$storage, $embed] = $this->withContent();

		$html = $embed->expand('<p>Voor:</p><p>[module:blog]</p><p>Na:</p>');

		$this->assertTrue(str_contains($html, 'Bericht 3'), 'the blog is embedded');
		$this->assertTrue(str_contains($html, 'Voor:') && str_contains($html, 'Na:'), 'and the page keeps its own text');
		$this->assertFalse(str_contains($html, '[module:blog]'), 'the marker itself is gone');

		// A marker alone in a paragraph replaces the paragraph. Block-level markup
		// inside a <p> makes the browser close the paragraph early, which moves
		// everything after it out of place.
		$this->assertFalse(str_contains($html, '<p><div'), 'a block-level embed does not end up inside a paragraph');

		// The embed is a glimpse, not the index: no pagination leading the reader
		// out of the page they are on.
		$this->assertFalse(str_contains($html, 'blog-pagination'), 'an embedded blog has no pagination');
		$this->assertTrue(str_contains($html, 'blog-embed__more'), 'but does link through to the blog');

		$this->assertSame(
			2,
			substr_count($embed->expand('<p>[module:blog count=2]</p>'), '<li>'),
			'a count parameter is honoured',
		);

		$this->assertFalse(
			str_contains($embed->expand('<p>[module:blog category=nieuws]</p>'), 'Bericht 2'),
			'and so is a category',
		);

		$this->assertTrue(
			str_contains($embed->expand('<p>[module:albums album=vakantie]</p>'), 'media/strand.jpg'),
			'an album can be embedded by name',
		);
		// Naming an album that does not exist shows nothing rather than the wrong
		// one, which is the important half. It is *silently* nothing, though: null
		// from a module means "nothing to show", and that is also the honest answer
		// on a site with no albums yet, so the two cannot be told apart here. See
		// ISSUES.md — a mistyped parameter is currently invisible in a way a
		// mistyped module name is not.
		$this->assertSame(
			'',
			$embed->expand('<p>[module:albums album=bestaatniet]</p>'),
			'naming an album that does not exist shows nothing rather than the wrong one',
		);

		// Links inside an embed point at the module's own address, so there is one
		// canonical place a post lives. Pluck 4 nested modules under pages, which
		// is why sites that used the SEO module have /page/.blog/post links that
		// cannot be served now.
		$this->assertTrue(
			str_contains($embed->expand('<p>[module:blog]</p>'), 'page=blog%2Fbericht-3'),
			'an embedded post links to the blog, not to the page it is embedded in',
		);
	}

	private function sanitiser(): void
	{
		// The whole reason the marker is plain text: a comment or a data attribute
		// would need the sanitiser's allow-list widened, and text needs nothing.
		$sanitiser = new Sanitizer();

		$this->assertTrue(
			str_contains($sanitiser->clean('<p>[module:blog count=3]</p>'), '[module:blog count=3]'),
			'a marker survives sanitising untouched',
		);
		$this->assertFalse(
			str_contains($sanitiser->clean('<p>a</p><!--module:blog--><p>b</p>'), 'module:blog'),
			'a comment would not have, which is why it is not the syntax',
		);
		$this->assertFalse(
			str_contains($sanitiser->clean('<div data-module="blog"></div>'), 'blog'),
			'and neither would an attribute',
		);
	}

	private function failing(): void
	{
		$storage = $this->storage();
		$embed = new Embed(new ModuleRegistry([new BrokenModule()]), $storage, new Urls('/', false));

		// A module that throws takes down its own corner of the page. A page that
		// will not render at all because a photo album had a bad day is worse.
		$html = $embed->expand('<p>Voor</p><p>[module:broken]</p><p>Na</p>');

		$this->assertTrue(str_contains($html, 'Voor') && str_contains($html, 'Na'), 'the page still renders');
		$this->assertFalse(str_contains($html, '[module:broken]'), 'with a gap where the module was');
	}

	// ---- fixture --------------------------------------------------------

	private function storage(): StorageDriver
	{
		$dir = $this->tempDir('pluck-embed');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();

		return $storage;
	}

	private function embed(?StorageDriver $storage = null): Embed
	{
		return new Embed(Modules::registry(), $storage ?? $this->storage(), new Urls('/', false));
	}

	/** @return array{0:StorageDriver,1:Embed} */
	private function withContent(): array
	{
		$storage = $this->storage();

		$storage->setModuleData('blog', 'category:nieuws', ['title' => 'Nieuws']);
		foreach ([1, 2, 3] as $n) {
			$storage->setModuleData('blog', 'post:bericht-' . $n, [
				'title' => 'Bericht ' . $n,
				'content' => '<p>Body</p>',
				'category' => $n === 3 ? 'nieuws' : '',
				'published' => true,
				'published_at' => sprintf('2021-0%d-01T12:00:00+00:00', $n),
			]);
		}

		$storage->setModuleData('albums', 'album:vakantie', ['title' => 'Vakantie', 'order' => 0]);
		$storage->setModuleData('albums', 'image:vakantie:0000', ['file' => 'strand.jpg', 'title' => 'Strand', 'info' => '']);

		$storage->savePage(new Page(path: 'home', title: 'Home', content: '<p>x</p>'));

		return [$storage, $this->embed($storage)];
	}

	/**
	 * Markers are expanded in every page a theme is given.
	 *
	 * A theme that stacks pages — the bundled one-pager puts every top-level page
	 * into a single document — prints the menu's own page content rather than the
	 * rendered $content, and that had never been through Embed. So [module:blog]
	 * appeared on the site as those words: the marker doing precisely nothing, on
	 * the one theme somebody was using.
	 */
	private function stackedPages(): void
	{
		$dir = $this->tempDir('pluck-stacked');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();

		$storage->savePage(new Page(path: 'home', title: 'Home', content: '<p>First</p>', order: 1));
		$storage->savePage(new Page(path: 'news', title: 'News', content: '<p>[module:blog]</p>', order: 2));
		$storage->setModuleData('blog', 'settings', ['allow_reactions' => false]);
		$storage->setModuleData('blog', 'post:hay', [
			'title' => 'Hay fever',
			'content' => '<p>Something about pollen.</p>',
			'published' => true,
			'published_at' => '2026-01-01T10:00:00+00:00',
		]);

		$renderer = $this->withoutSessionWarnings(fn (): SiteRenderer => new SiteRenderer(
			Theme::load(dirname(__DIR__) . '/themes', 'onepage'),
			$storage,
			new Urls('/', true),
			new Csrf(new Session()),
			new Csp(),
			new Translator(Locale::fallback(), dirname(__DIR__) . '/lang'),
			Modules::registry(),
		));

		$html = $renderer->page($storage->findPage('home'));

        $this->assertFalse(
			str_contains($html, '[module:blog]'),
			'the marker does not appear on the page as words',
		);
		$this->assertTrue(
			str_contains($html, 'Hay fever'),
			'and the module it names is rendered in the stacked section',
		);
	}
}

/** A module that cannot answer, to prove one failure does not take the page. */
final class BrokenModule implements \Pluck\Module\SiteModule
{
	public function name(): string
	{
		return 'broken';
	}

	public function mountPath(): string
	{
		return 'broken';
	}

	public function render(string $path, array $query, StorageDriver $storage, Urls $urls): ?\Pluck\Module\ModuleView
	{
		return null;
	}

	public function embed(array $parameters, StorageDriver $storage, Urls $urls): ?string
	{
		throw new \RuntimeException('this module is having a bad day');
	}

	public function search(string $query, StorageDriver $storage): array
	{
		throw new \RuntimeException('this module is having a bad day');
	}
}
