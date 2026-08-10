<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Model\Page;
use Pluck\Module\AlbumsModule;
use Pluck\Module\BlogModule;
use Pluck\Module\ModuleRegistry;
use Pluck\Site\Menu;
use Pluck\Site\Urls;
use Pluck\I18n\Locale;
use Pluck\I18n\Translator;
use Pluck\Security\Session;
use Pluck\Security\Csp;
use Pluck\Security\Csrf;
use Pluck\Theme\Theme;
use Pluck\Site\SiteRenderer;
use Pluck\Storage\DriverFactory;

/**
 * Addresses: how they are read, how they are written, and who owns them.
 *
 * Worth testing on its own because both halves have to agree. A link the theme
 * writes has to be an address the front controller can read back, in both URL
 * styles and from a sub-directory — and it is exactly the sub-directory case
 * that nobody has installed locally when they change this code.
 */
final class SiteRouteTest extends TestCase
{
	public function run(): void
	{
		$this->group('an install that moved', fn () => $this->movedInstall());
		$this->group('a site with nothing in it', fn () => $this->emptySite());
		$this->group('relative addresses in content', fn () => $this->relativeLinks());
		$this->group('relative addresses in a module', fn () => $this->relativeInModule());

		$this->group('the rewrite probe', fn () => $this->probe());
		$this->group('plain urls', fn () => $this->plainUrls());
		$this->group('pretty urls', fn () => $this->prettyUrls());
		$this->group('sub-directory', fn () => $this->subDirectory());
		$this->group('reading the request', fn () => $this->detection());
		$this->group('module mounting', fn () => $this->modules());
		$this->group('menu', fn () => $this->menu());
		$this->group('breadcrumbs', fn () => $this->breadcrumbs());
	}

	/**
	 * The probe has to answer on a *broken* install, since that is exactly when
	 * someone is trying to find out whether rewriting works. It used to be
	 * answered after Bootstrap::boot(), which throws when data/ is not writable —
	 * so the one check that diagnoses a misconfigured server was the first thing
	 * that server broke.
	 */
	private function probe(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/index.php');

		// The assignment, not the name: the comment above the probe mentions
		// Bootstrap::boot() and matching that made this assertion answer about
		// prose rather than about code.
		$probeAt = strpos($source, 'echo Probe::MARKER');
		$bootAt = strpos($source, '$app = Bootstrap::boot(');

		$this->assertTrue($probeAt !== false && $bootAt !== false, 'index.php has both a probe and a boot');
		$this->assertTrue(
			$probeAt < $bootAt,
			'the probe is answered before anything that can fail on a misconfigured install',
		);

		// And the marker is not a word anyone would produce by accident, so a
		// server that answers every unknown path with a friendly page cannot pass
		// the check by coincidence.
		$this->assertTrue(
			str_contains(\Pluck\Admin\Probe::MARKER, 'pluck-rewrite-probe'),
			'the marker is distinctive enough that only Pluck can have written it',
		);
	}

	private function plainUrls(): void
	{
		$plain = new Urls('/', false);
		$this->assertSame('/', $plain->to(''), 'the front page is the base itself');
		$this->assertSame('/?page=about', $plain->to('about'), 'a page is a query parameter');
		$this->assertSame('/?page=about%2Fteam', $plain->to('about/team'), 'and a nested one keeps its slash encoded');
		$this->assertSame('/?page=blog&p=2', $plain->toWith('blog', ['p' => 2]), 'extra parameters are appended');
		$this->assertSame('/?page=blog', $plain->toWith('blog', ['p' => null]), 'an empty parameter is left off');

	}

	private function prettyUrls(): void
	{
		$pretty = new Urls('/', true);
		$this->assertSame('/', $pretty->to(''), 'the front page is still the base');
		$this->assertSame('/about/team', $pretty->to('about/team'), 'a page is a path');
		$this->assertSame('/blog?p=2', $pretty->toWith('blog', ['p' => 2]), 'and parameters start a query string');
		$this->assertSame('/media/photo.jpg', $pretty->media('photo.jpg'), 'media is served by path either way');

	}

	private function subDirectory(): void
	{
		$sub = new Urls('/cms/', true);
		$this->assertSame('/cms/', $sub->to(''), 'the base is kept for the front page');
		$this->assertSame('/cms/about', $sub->to('about'), 'and prefixed onto every page');
		$this->assertSame('/cms/media/photo.jpg', $sub->media('photo.jpg'), 'and onto assets');

		$subPlain = new Urls('/cms/', false);
		$this->assertSame('/cms/?page=about', $subPlain->to('about'), 'the plain form is prefixed too');

		// A title with a space or a plus in it has to survive being turned into a
		// link and read back. Slugs make that rare, but imported content is full
		// of names nobody chose.
		$this->assertSame('/?page=caf%C3%A9-nl', (new Urls('/', false))->to('café-nl'), 'non-ascii is encoded, not dropped');
		$this->assertSame('/caf%C3%A9-nl', (new Urls('/', true))->to('café-nl'), 'in both styles');
	}

	private function detection(): void
	{
		$this->assertSame('/', Urls::detectBase(['SCRIPT_NAME' => '/index.php']), 'a domain root is /');
		$this->assertSame('/cms/', Urls::detectBase(['SCRIPT_NAME' => '/cms/index.php']), 'a sub-directory keeps its path');
		$this->assertSame('/a/b/', Urls::detectBase(['SCRIPT_NAME' => '/a/b/index.php']), 'however deep it is');

		$this->assertSame(
			'about/team',
			Urls::detectPath(['SCRIPT_NAME' => '/index.php', 'PATH_INFO' => '/about/team']),
			'PATH_INFO is used when the server filled it in',
		);
		$this->assertSame(
			'about/team',
			Urls::detectPath(['SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/about/team?x=1']),
			'otherwise the path comes out of REQUEST_URI, without the query',
		);
		$this->assertSame(
			'about/team',
			Urls::detectPath(['SCRIPT_NAME' => '/cms/index.php', 'REQUEST_URI' => '/cms/about/team']),
			'and the install directory is taken off the front',
		);
		$this->assertSame(
			'',
			Urls::detectPath(['SCRIPT_NAME' => '/cms/index.php', 'REQUEST_URI' => '/cms/']),
			'the front page of a sub-directory install is the empty path',
		);
		$this->assertSame(
			"caf\u{e9}-nl",
			Urls::detectPath(['SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/caf%C3%A9-nl']),
			'percent-encoding is decoded on the way in',
		);
	}

	private function modules(): void
	{
		$registry = new ModuleRegistry([new BlogModule(), new AlbumsModule()]);

		$this->assertTrue($registry->has('blog'), 'the blog is mounted');
		$this->assertSame(2, count($registry->all()), 'both modules are registered');
		$this->assertSame(null, $registry->resolve(''), 'the front page is not a module');
		$this->assertSame(null, $registry->resolve('about/team'), 'nor is an ordinary page');

		[$module, $rest] = $registry->resolve('blog') ?? [null, null];
		$this->assertSame('blog', $module?->name(), 'the mount point alone reaches the module');
		$this->assertSame('', $rest, 'with nothing left over');

		[$module, $rest] = $registry->resolve('blog/some-post') ?? [null, null];
		$this->assertSame('blog', $module?->name(), 'and so does anything under it');
		$this->assertSame('some-post', $rest, 'which is handed on as the remainder');

		[, $rest] = $registry->resolve('blog/category/news') ?? [null, null];
		$this->assertSame('category/news', $rest, 'however many segments deep');

		// A module owns a first segment and only a first segment. If /news/blog
		// reached the blog module, the address of a post would depend on where an
		// editor last moved a page to.
		$this->assertSame(null, $registry->resolve('news/blog'), 'a module does not claim a nested path');
	}

	/** @return list<Page> */
	private function pages(): array
	{
		return [
			new Page(path: 'about', title: 'About', order: 2),
			new Page(path: 'home', title: 'Home', order: 1),
			new Page(path: 'about/team', title: 'Team', order: 1),
			new Page(path: 'about/history', title: 'History', order: 2),
		];
	}

	private function menu(): void
	{
		$pages = $this->pages();

		$menu = new Menu($pages, 'about/team');
		$titles = array_map(static fn ($i): string => $i->title(), $menu->items());
		$this->assertSame(['Home', 'About'], $titles, 'the top level is in menu order, not storage order');

		$about = $menu->items()[1];
		$this->assertTrue($about->hasChildren(), 'a page with sub-pages has children');
		$this->assertSame(
			['Team', 'History'],
			array_map(static fn ($i): string => $i->title(), $about->children),
			'which are ordered the same way',
		);
		$this->assertFalse($about->active, 'an ancestor of the current page is not itself current');
		$this->assertTrue($about->open, 'but it is on the open branch');
		$this->assertTrue($about->children[0]->active, 'and the page being viewed is marked current');
		$this->assertFalse($menu->items()[0]->open, 'a branch you are not in stays closed');

	}

	private function breadcrumbs(): void
	{
		$pages = $this->pages();

		$trail = Menu::trail($pages, 'about/team');
		$this->assertSame(
			['About', 'Team'],
			array_map(static fn ($i): string => $i->title(), $trail),
			'the trail runs from the top down to the page',
		);
		$this->assertTrue($trail[1]->active, 'with the last entry marked as where you are');
		$this->assertSame([], Menu::trail($pages, ''), 'the front page has no trail');

		// A hidden parent is not in the menu, so it is not in the trail either.
		// Leaving a gap is right: linking to it would advertise a page the owner
		// took out of the menu on purpose.
		$this->assertSame(
			['Team'],
			array_map(static fn ($i): string => $i->title(), Menu::trail([$pages[2]], 'about/team')),
			'an ancestor that is not in the menu leaves a gap rather than a bad link',
		);
	}

	/**
	 * The same site, in a sub-directory.
	 *
	 * Nothing about the base is stored anywhere: it is worked out from
	 * SCRIPT_NAME on every request, so moving an install is meant to be a matter
	 * of moving the files. These are the two places that was not true.
	 */
	private function movedInstall(): void
	{
		$sub = ['SCRIPT_NAME' => '/sub/index.php', 'REQUEST_URI' => '/sub/about'];

		$this->assertSame('/sub/', Urls::detectBase($sub), 'the base follows the files');
		$this->assertSame('about', Urls::detectPath($sub), 'and a page is found under it');

		// The admin's own "View site" link is relative, so it lands here. Read as
		// a path, "index.php" is the name of a page nobody has — 404 on a site
		// where every other link works.
		$this->assertSame(
			'',
			Urls::detectPath(['SCRIPT_NAME' => '/sub/index.php', 'REQUEST_URI' => '/sub/index.php?page=about']),
			'the script is not mistaken for a page',
		);
		$this->assertSame(
			'',
			Urls::detectPath(['SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/index.php']),
			'at the root either',
		);

		// Apache usually adds the slash. Usually is not always.
		$this->assertSame(
			'',
			Urls::detectPath(['SCRIPT_NAME' => '/sub/index.php', 'REQUEST_URI' => '/sub']),
			'the install directory is not mistaken for a page',
		);

		// And a page that really is called "index" still is.
		$this->assertSame(
			'index',
			Urls::detectPath(['SCRIPT_NAME' => '/sub/index.php', 'REQUEST_URI' => '/sub/index']),
			'a page called index is left alone',
		);

		$this->assertSame(
			'a/b/c',
			Urls::detectPath(['SCRIPT_NAME' => '/sub/index.php', 'REQUEST_URI' => '/sub/a/b/c']),
			'and a nested page keeps all of its path',
		);
	}

	/**
	 * A fresh install answers rather than 404s.
	 *
	 * "Page not found" on a site nobody has written yet is true and useless:
	 * nothing is missing, and it reads like a broken install to the one person
	 * who most needs it not to.
	 */
	private function emptySite(): void
	{
		$dir = $this->tempDir('pluck-empty');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();

		$renderer = $this->withoutSessionWarnings(fn (): SiteRenderer => new SiteRenderer(
			Theme::load(dirname(__DIR__) . '/themes', 'default'),
			$storage,
			new Urls('/new2026/', false),
			new Csrf(new Session()),
			new Csp(),
			new Translator(Locale::fallback(), dirname(__DIR__) . '/lang'),
		));

		$html = $renderer->emptySite();

		$this->assertTrue(str_contains($html, 'Nothing here yet'), 'it says the site is empty');
		$this->assertTrue(
			str_contains($html, '/new2026/admin.php'),
			'and points at the admin where this install actually is, not at /admin.php',
		);
		$this->assertFalse(
			str_contains($html, 'not found'),
			'and does not claim something is missing',
		);
	}

	/**
	 * `media/photo.jpg` in a page means the install, not the page's folder.
	 *
	 * The editor writes it that way, and it is right until a page is nested and
	 * readable addresses are on — then the browser looks in
	 * /de-club/media/photo.jpg and every picture on the page is a 404. It showed
	 * up on a live site while the preview looked fine, because the preview runs
	 * at a different path.
	 */
	private function relativeLinks(): void
	{
		$dir = $this->tempDir('pluck-relative');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();

		$storage->savePage(new Page(path: 'club', title: 'Club', content: '<p>x</p>'));
		$storage->savePage(new Page(
			path: 'club/tocht',
			title: 'Tocht',
			content: '<p><img src="media/medaille.jpg" alt="">'
				. '<a href="media/regels.pdf">regels</a>'
				. '<a href="#top">omhoog</a>'
				. '<a href="/al/goed">al goed</a>'
				. '<a href="https://example.com/x">elders</a>'
				. '<a href="mailto:a@b.nl">mail</a></p>',
		));

		$renderer = $this->withoutSessionWarnings(fn (): SiteRenderer => new SiteRenderer(
			Theme::load(dirname(__DIR__) . '/themes', 'default'),
			$storage,
			new Urls('/new/', true),
			new Csrf(new Session()),
			new Csp(),
			new Translator(Locale::fallback(), dirname(__DIR__) . '/lang'),
		));

		$html = $renderer->page($storage->findPage('club/tocht'));

		$this->assertTrue(
			str_contains($html, 'src="/new/media/medaille.jpg"'),
			'a relative picture points at the install, not at the page folder',
		);
		$this->assertTrue(
			str_contains($html, 'href="/new/media/regels.pdf"'),
			'and so does a relative link',
		);

		// The four kinds that must be left exactly as written.
		$this->assertTrue(str_contains($html, 'href="#top"'), 'a fragment is untouched');
		$this->assertTrue(str_contains($html, 'href="/al/goed"'), 'an already-rooted path is untouched');
		$this->assertTrue(str_contains($html, 'href="https://example.com/x"'), 'another site is untouched');
		$this->assertTrue(str_contains($html, 'href="mailto:a@b.nl"'), 'and mailto is untouched');
	}

	/**
	 * A module's output gets the same treatment as a page's.
	 *
	 * It did not, and on a blog that is every picture on the site: the editor
	 * writes `media/photo.jpg`, a post lives at /blog/<slug>, and the browser
	 * looks in /blog/media/. The page next to it worked, which is what made it
	 * look like a content problem rather than a rendering one.
	 */
	private function relativeInModule(): void
	{
		$dir = $this->tempDir('pluck-module-links');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();

		$renderer = $this->withoutSessionWarnings(fn (): SiteRenderer => new SiteRenderer(
			Theme::load(dirname(__DIR__) . '/themes', 'default'),
			$storage,
			new Urls('/new/', true),
			new Csrf(new Session()),
			new Csp(),
			new Translator(Locale::fallback(), dirname(__DIR__) . '/lang'),
		));

		$html = $renderer->module(
			new \Pluck\Module\ModuleView(
				title: 'A dish',
				html: '<p><img src="media/dish.jpg" alt=""><a href="#top">up</a>'
					. '<a href="https://example.com">elsewhere</a></p>',
			),
			'blog/a-dish',
		);

		$this->assertTrue(
			str_contains($html, 'src="/new/media/dish.jpg"'),
			'a relative picture in module output points at the install',
		);
		$this->assertTrue(str_contains($html, 'href="#top"'), 'a fragment is untouched');
		$this->assertTrue(str_contains($html, 'href="https://example.com"'), 'another site is untouched');
	}
}
