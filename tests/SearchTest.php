<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Model\Page;
use Pluck\Module\AlbumsModule;
use Pluck\Module\BlogModule;
use Pluck\Module\ModuleRegistry;
use Pluck\Module\Modules;
use Pluck\Module\SiteModule;
use Pluck\Security\Csp;
use Pluck\Security\Csrf;
use Pluck\Security\Session;
use Pluck\Site\Search;
use Pluck\Site\SearchResult;
use Pluck\Site\SiteRenderer;
use Pluck\Site\Urls;
use Pluck\Storage\DriverFactory;
use Pluck\Storage\StorageDriver;
use Pluck\Theme\Theme;

/**
 * Site search.
 *
 * A search box is the most reliably attacker-reachable input a public site has —
 * anyone can send a link with a query in it — so the assertions that matter most
 * here are about what happens to the query on its way back onto the page, not
 * about ranking.
 */
final class SearchTest extends TestCase
{
	public function run(): void
	{
		$this->group('what counts as a query', fn () => $this->queries());
		$this->group('finding things', fn () => $this->finding());
		$this->group('snippets', fn () => $this->snippets());
		$this->group('modules join in', fn () => $this->modules());
		$this->group('a module that fails', fn () => $this->failing());
		$this->group('the query on the page', fn () => $this->reflection());
		$this->group('reserved addresses', fn () => $this->reserved());
	}

	private function queries(): void
	{
		$this->assertFalse(Search::isUsable(''), 'nothing is not a query');
		$this->assertFalse(Search::isUsable('   '), 'nor is whitespace');
		$this->assertFalse(Search::isUsable('a'), 'a single letter matches most of a site, which is a listing rather than a search');
		$this->assertTrue(Search::isUsable('ab'), 'two characters is a search');

		$this->assertSame('twee woorden', Search::normalise("  twee   \n woorden "), 'whitespace is collapsed');
		$this->assertSame(100, mb_strlen(Search::normalise(str_repeat('a', 500))), 'a query is bounded, so the work per request is too');
	}

	private function finding(): void
	{
		[$storage, $search] = $this->site();

		$results = $search->find('openingstijden');
		$this->assertSame(2, count($results), 'both the page named after it and the one mentioning it are found');

		// A title match outranks a body match: someone searching for "opening
		// hours" wants the page called that, not the fourteen that mention it.
		$this->assertSame('Openingstijden', $results[0]->title, 'the page named after the query comes first');
		$this->assertTrue($results[0]->score > $results[1]->score, 'and scores higher');

		$this->assertSame([], $search->find('nietsvanditalles'), 'a query matching nothing finds nothing');
		$this->assertSame([], $search->find('a'), 'and an unusable query is not run at all');

		$this->assertSame(
			2,
			count($search->find('OPENINGSTIJDEN')),
			'matching ignores case',
		);

		// A hidden page is out of the menu but still reachable; it must not turn up
		// in search, because that is a listing of what the site has.
		$this->assertSame(
			[],
			array_filter($search->find('geheim'), static fn (SearchResult $r): bool => $r->path === 'verborgen'),
			'a hidden page is not listed in search results',
		);

		// Markup must not be searchable. Someone looking for "div" should not get
		// every page on the site.
		$this->assertSame([], $search->find('<div'), 'tags are not matched');
		$this->assertSame([], $search->find('class='), 'nor are attributes');
	}

	private function snippets(): void
	{
		$text = 'Wij zijn geopend van dinsdag tot en met zaterdag, en op zondag alleen op afspraak.';

		$snippet = Search::snippet('zondag', $text);
		$this->assertTrue(str_contains($snippet, 'zondag'), 'the snippet contains the match');
		$this->assertTrue(str_starts_with($snippet, '…'), 'and says it started partway through');

		$this->assertSame(
			'',
			Search::snippet('x', '', ''),
			'nothing to show is an empty snippet rather than an invented one',
		);

		// Entities are decoded before matching, so a page written with &euml; is
		// found by someone typing ë.
		$this->assertSame("caf\u{e9}", Search::plain('<p>caf&eacute;</p>'), 'entities are decoded for matching');
		$this->assertSame('een twee', Search::plain("<p>een</p>\n<p>twee</p>"), 'and whitespace is collapsed');
	}

	private function modules(): void
	{
		[$storage, $search] = $this->site();

		$storage->setModuleData('blog', 'post:zomerkaart', [
			'title' => 'De zomerkaart',
			'content' => '<p>Met veel openingstijden erin</p>',
			'published' => true,
			'published_at' => '2021-06-01T12:00:00+00:00',
		]);
		$storage->setModuleData('blog', 'post:concept', [
			'title' => 'Concept over openingstijden',
			'content' => '<p>Nog niet af</p>',
			'published' => false,
		]);
		$storage->setModuleData('albums', 'album:zomer', ['title' => 'Zomer', 'order' => 0, 'description' => '<p>Openingstijden gevierd</p>']);

		$results = $search->find('openingstijden');
		$paths = array_map(static fn (SearchResult $r): string => $r->path, $results);

		$this->assertTrue(in_array('blog/zomerkaart', $paths, true), 'blog posts are searchable');
		$this->assertTrue(in_array('albums/zomer', $paths, true), 'and so are albums');

		// A draft is not on the site. Turning up in search would be a way to read
		// it, which is the same leak by a different door.
		$this->assertFalse(in_array('blog/concept', $paths, true), 'a draft post is not searchable');

		$kinds = array_unique(array_map(static fn (SearchResult $r): string => $r->kindKey, $results));
		$this->assertTrue(count($kinds) > 1, 'results say what kind of thing they are');

		// Paths are addresses within the module's own mount, so a result always
		// leads somewhere that exists.
		$this->assertTrue(
			(new BlogModule())->render('zomerkaart', [], $storage, new Urls('/', false)) !== null,
			'a blog result points at an address the module answers to',
		);
	}

	private function failing(): void
	{
		[$storage] = $this->site();
		$registry = new ModuleRegistry([new BrokenSearchModule(), new BlogModule()]);
		$storage->setModuleData('blog', 'post:iets', [
			'title' => 'Iets over openingstijden', 'content' => '<p>x</p>', 'published' => true,
		]);

		$results = (new Search($storage, $registry))->find('openingstijden');

		$this->assertTrue(count($results) > 0, 'a module that throws costs its own results and nothing else');
		$this->assertTrue(
			in_array('blog/iets', array_map(static fn (SearchResult $r): string => $r->path, $results), true),
			'the other modules still contribute',
		);
	}

	private function reflection(): void
	{
		[$storage] = $this->site();
		$storage->setSetting('search_enabled', true);

		$renderer = $this->renderer($storage);

		// The query comes back on the page, so it is the one input on a public
		// site anybody can aim. Reflecting it unescaped is the oldest way to put
		// someone else's script on somebody's site.
		$nasty = '"><script>alert(1)</script>';
		$html = $renderer->search($nasty, []);

		$this->assertFalse(str_contains($html, '<script>alert(1)</script>'), 'a script in the query does not reach the page');
		$this->assertTrue(str_contains($html, '&lt;script&gt;'), 'it comes back escaped');
		$this->assertFalse(str_contains($html, 'value=""><'), 'and cannot break out of the input it is echoed into');

		// The highlight is added after escaping. Doing it the other way round
		// escapes the mark, and doing it by hand around a mark inserted first is
		// where off-by-one holes come from.
		$results = [new SearchResult('Over <b>ons</b>', 'about', 'Wij zijn <b>open</b>', 10)];
		$html = $renderer->search('ons', $results);

		$this->assertFalse(str_contains($html, '<b>ons</b>'), 'markup in a result title is escaped, not rendered');
		$this->assertTrue(str_contains($html, '<mark>'), 'while the highlight itself is real markup');

		$this->assertTrue(str_contains($html, 'noindex'), 'a search page asks not to be indexed');

		// View::t() escapes its replacements, so wrapping it in e() escapes them
		// twice and the reader sees &amp;lt;. Invisible until a query or a title
		// contains an apostrophe, at which point it is on every page that mentions
		// it.
		$this->assertFalse(
			str_contains($renderer->search("Bas' site", []), '&amp;#039;'),
			'a translated message with a replacement is escaped once, not twice',
		);
		$this->assertTrue(
			str_contains($renderer->search("Bas' site", []), '&#039;'),
			'and really is escaped',
		);
	}

	private function reserved(): void
	{
		// If a module could mount at /search, whichever ran first would win and the
		// site would silently lose either its search or the module.
		$this->expectFailure(
			static fn () => new ModuleRegistry([new GreedyModule()]),
			'a module cannot mount at an address the site itself answers to',
		);

		$this->assertTrue(
			in_array('search', ModuleRegistry::RESERVED, true),
			'and search is on the reserved list',
		);
	}

	// ---- fixture --------------------------------------------------------

	/** @return array{0:StorageDriver,1:Search} */
	private function site(): array
	{
		$dir = $this->tempDir('pluck-search');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();

		$storage->savePage(new Page(
			path: 'openingstijden',
			title: 'Openingstijden',
			content: '<p>Wij zijn geopend van dinsdag tot en met zaterdag.</p>',
			order: 1,
		));
		$storage->savePage(new Page(
			path: 'contact',
			title: 'Contact',
			content: '<div class="x"><p>Bel ons voor onze openingstijden.</p></div>',
			order: 2,
		));
		$storage->savePage(new Page(
			path: 'verborgen',
			title: 'Geheim',
			content: '<p>Iets geheims</p>',
			hidden: true,
			order: 3,
		));

		return [$storage, new Search($storage, Modules::registry())];
	}

	private function renderer(StorageDriver $storage): SiteRenderer
	{
		return $this->withoutSessionWarnings(fn (): SiteRenderer => new SiteRenderer(
			Theme::load(dirname(__DIR__) . '/themes', 'default'),
			$storage,
			new Urls('/', false),
			new Csrf(new Session()),
			new Csp(),
			null,
			Modules::registry(),
		));
	}
}

/** Mounts where the site itself answers, to prove it cannot. */
final class GreedyModule implements SiteModule
{
	public function name(): string
	{
		return 'greedy';
	}

	public function mountPath(): string
	{
		return 'search';
	}

	public function render(string $path, array $query, StorageDriver $storage, Urls $urls): ?\Pluck\Module\ModuleView
	{
		return null;
	}

	public function embed(array $parameters, StorageDriver $storage, Urls $urls): ?string
	{
		return null;
	}

	public function search(string $query, StorageDriver $storage): array
	{
		return [];
	}
}

/** Throws when asked to search, to prove one failure does not take the page. */
final class BrokenSearchModule implements SiteModule
{
	public function name(): string
	{
		return 'brokensearch';
	}

	public function mountPath(): string
	{
		return 'brokensearch';
	}

	public function render(string $path, array $query, StorageDriver $storage, Urls $urls): ?\Pluck\Module\ModuleView
	{
		return null;
	}

	public function embed(array $parameters, StorageDriver $storage, Urls $urls): ?string
	{
		return null;
	}

	public function search(string $query, StorageDriver $storage): array
	{
		throw new \RuntimeException('no');
	}
}
