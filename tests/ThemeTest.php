<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\I18n\Locale;
use Pluck\I18n\Translator;
use Pluck\Model\Page;
use Pluck\Module\AlbumsModule;
use Pluck\Module\BlogModule;
use Pluck\Security\Csp;
use Pluck\Security\Csrf;
use Pluck\Security\Session;
use Pluck\Site\SiteRenderer;
use Pluck\Site\Urls;
use Pluck\Storage\DriverFactory;
use Pluck\Storage\StorageDriver;
use Pluck\Theme\Theme;
use Pluck\Theme\ThemeRepository;

/**
 * The theme layer, rendering real HTML.
 *
 * Everything else in this suite can pass while the site serves a blank page, so
 * this suite renders and reads the output. The assertions are about the two
 * things a template can get wrong that nothing else catches: printing a value
 * without escaping it, and printing the wrong value entirely because a merge
 * put a default on top of it.
 */
final class ThemeTest extends TestCase
{
	public function run(): void
	{
		$this->group('loading a theme', fn () => $this->loading());
		$this->group('falling back', fn () => $this->fallback());
		$this->group('rendering a page', fn () => $this->page());
		$this->group('one page from several', fn () => $this->onePage());
		$this->group('escaping', fn () => $this->escaping());
		$this->group('the blog', fn () => $this->blog());
		$this->group('albums', fn () => $this->albums());
		$this->group('not found', fn () => $this->notFound());
	}

	private function themesDir(): string
	{
		return dirname(__DIR__) . '/themes';
	}

	private function loading(): void
	{
		$theme = Theme::load($this->themesDir(), 'default');
		$this->assertSame('default', $theme->name, 'the bundled theme loads');
		$this->assertSame('Default', $theme->title(), 'and reads its name out of theme.json');
		$this->assertTrue($theme->has('layout'), 'it has a layout');
		$this->assertTrue($theme->has('page'), 'and a page template');
		$this->assertTrue($theme->hasAssets(), 'and ships a stylesheet');

		$this->assertSame('module', $theme->pick('module', 'page'), 'pick takes the first template that exists');
		$this->assertSame('page', $theme->pick('nonexistent', 'page'), 'and falls through when it does not');
		$this->assertSame('page', $theme->pick('nonexistent'), 'landing on page as the last resort');

		$repository = new ThemeRepository($this->themesDir());
		$available = $repository->available();

		// Every bundled theme has to load, render and carry the module markup. A
		// theme that only the person who wrote it ever tried is a theme that
		// breaks on the first site that is not theirs.
		foreach (['default', 'columns', 'blank', 'blank-dark', 'onepage'] as $bundled) {
			$this->assertTrue(in_array($bundled, $available, true), $bundled . ' is offered as available');
			$this->assertTrue(Theme::load($this->themesDir(), $bundled)->has('layout'), $bundled . ' has a layout');
		}

		// The two rebuilt from a Pluck 4 theme open their menu with a checkbox and
		// no script. That is not a style preference: the framework they replace
		// blocked touchmove while its menu was open and cleared the flag in an
		// animation callback, so an interrupted animation left the page
		// unscrollable until it was reloaded.
		foreach (['columns', 'blank', 'blank-dark', 'onepage'] as $rebuilt) {
			$layout = (string) file_get_contents($this->themesDir() . '/' . $rebuilt . '/templates/layout.php');
			$css = (string) file_get_contents($this->themesDir() . '/' . $rebuilt . '/assets/style.css');

			$this->assertTrue(str_contains($layout, 'type="checkbox"'), $rebuilt . ' opens its menu with a checkbox');
			$this->assertTrue(str_contains($css, 'menu-toggle:checked'), 'and styles it from :checked');
			$this->assertFalse(str_contains($layout, '<script'), $rebuilt . ' ships no script at all');
			$this->assertFalse(str_contains($layout, '.js"'), 'and links to none');
		}
	}

	/**
	 * The one-page theme stacks every top-level page into one document.
	 *
	 * It needs no core support to do it: $menu already carries the full Page
	 * objects because allPages() loads their content. Worth an assertion, since
	 * making allPages() lazy later would break this theme in a way nothing else
	 * would notice.
	 */
	private function onePage(): void
	{
		[$storage] = $this->install();
		$storage->setSetting('site_title', 'One');

		$storage->savePage(new Page(path: 'welcome', title: 'Welcome', content: '<p>First</p>', order: 1));
		$storage->savePage(new Page(path: 'services', title: 'Services', content: '<p>Second</p>', order: 2));
		$storage->savePage(new Page(path: 'services/rates', title: 'Rates', content: '<p>Nested</p>', order: 1));
		$storage->savePage(new Page(path: 'hidden', title: 'Hidden', content: '<p>Not shown</p>', hidden: true, order: 3));

		$theme = Theme::load($this->themesDir(), 'onepage');
		$renderer = $this->withoutSessionWarnings(fn (): SiteRenderer => new SiteRenderer(
			$theme,
			$storage,
			new Urls('/', true),
			new Csrf(new Session()),
			new Csp(),
			$this->translator(),
		));

		$html = $renderer->page($storage->findPage('services'));

		$this->assertTrue(str_contains($html, '<p>First</p>'), 'every top-level page is on the document');
		$this->assertTrue(str_contains($html, '<p>Second</p>'), 'not only the one that was asked for');
		$this->assertTrue(str_contains($html, 'id="welcome"'), 'each one gets an anchor');
		$this->assertTrue(str_contains($html, 'href="/#welcome"'), 'and the menu points at it rather than at its address');

		// A section has one address, and it is the anchor. Without this every
		// section would exist at its own URL with the whole page behind it, and a
		// search engine would see the same content several times over.
		$this->assertTrue(str_contains($html, 'rel="canonical"'), 'a section says where it really lives');
		$this->assertTrue(str_contains($html, '/#services'), 'which is the anchor on the front page');

		// Hidden means out of the menu, and the stack is built from the menu.
		$this->assertFalse(str_contains($html, 'Not shown'), 'a hidden page is not stacked');

		// Sub-pages stay ordinary pages: stacking those as well would put the
		// whole site on one screen, which is what a one-pager is trying not to be.
		$this->assertFalse(str_contains($html, '<p>Nested</p>'), 'a sub-page is not stacked');
		$this->assertTrue(str_contains($html, '/services/rates'), 'but is linked at its own address');
	}

	private function fallback(): void
	{
		$dir = $this->tempDir('pluck-themes');
		mkdir($dir . '/broken/templates', 0o755, true);
		file_put_contents($dir . '/broken/theme.json', '{"name":"Broken"}');

		$repository = new ThemeRepository($dir);
		$this->assertSame([], $repository->available(), 'a theme with no templates is not offered');

		$this->expectFailure(
			static fn () => Theme::load($dir, 'broken'),
			'and loading it by name is an error rather than a blank page',
		);

		$this->expectFailure(
			static fn () => Theme::load($dir, 'never-existed'),
			'as is a theme that is not there at all',
		);

		// The important half: a site whose configured theme is broken still
		// serves, because active() steps over it onto the bundled one.
		[$storage] = $this->install();
		$storage->setSetting('theme', 'does-not-exist');

		$active = (new ThemeRepository($this->themesDir()))->active($storage);
		$this->assertSame('default', $active->name, 'a configured theme that will not load falls back to the bundled one');
	}

	private function page(): void
	{
		[$storage] = $this->install();
		$storage->setSetting('site_title', 'Voorbeeldsite');

		$storage->savePage(new Page(path: 'home', title: 'Home', content: '<p>Welkom</p>', order: 1));
		$storage->savePage(new Page(path: 'about', title: 'About us', content: '<p>Over ons</p>', order: 2));
		$storage->savePage(new Page(path: 'about/team', title: 'Team', content: '<p>Wij</p>', order: 1));
		$storage->savePage(new Page(path: 'secret', title: 'Secret', content: '<p>Hidden</p>', hidden: true, order: 3));

		$html = $this->renderer($storage)->page($storage->findPage('about/team'));

		$this->assertTrue(str_starts_with($html, '<!DOCTYPE html>'), 'the layout wraps the page');
		$this->assertTrue(str_contains($html, '<title>Team - Voorbeeldsite</title>'), 'the window title is page then site');
		$this->assertTrue(str_contains($html, '<p>Wij</p>'), 'the page content is rendered, not the default');
		$this->assertTrue(str_contains($html, '<h1>Team</h1>'), 'under its own heading');

		// The bug this exists to catch: `$data + $defaults` in the wrong order
		// silently renders the outer template's content instead of the page's.
		$this->assertFalse(str_contains($html, '<p>Welkom</p>'), 'and not some other page');

		$this->assertTrue(str_contains($html, 'About us'), 'the menu is there');
		$this->assertFalse(str_contains($html, 'Secret'), 'and a hidden page stays out of it');
		$this->assertTrue(str_contains($html, 'aria-current="page"'), 'the current page is marked for screen readers');
		$this->assertTrue(str_contains($html, 'style.css'), 'the theme stylesheet is linked');

		// Breadcrumbs run from the top down to where you are.
		$this->assertTrue(str_contains($html, '?page=about'), 'the trail links the parent');
		$this->assertNoRawKeys($html, 'a rendered page');
	}

	private function escaping(): void
	{
		[$storage] = $this->install();

		// A title is plain text and must never be markup, wherever it appears —
		// the window title, the heading, the menu, the breadcrumbs.
		$storage->savePage(new Page(
			path: 'xss',
			title: 'Quotes " and <script>alert(1)</script>',
			content: '<p>ok</p>',
		));
		$storage->setSetting('site_title', '<img src=x onerror=alert(1)>');

		$html = $this->renderer($storage)->page($storage->findPage('xss'));

		$this->assertFalse(str_contains($html, '<script>alert(1)</script>'), 'a title cannot smuggle a script tag in');
		$this->assertFalse(str_contains($html, '<img src=x onerror'), 'nor can the site title');
		$this->assertTrue(str_contains($html, '&lt;script&gt;'), 'it comes out escaped instead');
		$this->assertTrue(str_contains($html, 'Quotes &quot;'), 'and a quote does not end an attribute early');
	}

	private function blog(): void
	{
		[$storage] = $this->install();
		$storage->setModuleData('blog', 'settings', ['posts_per_page' => 2]);
		$storage->setModuleData('blog', 'category:nieuws', ['title' => 'Nieuws']);

		foreach ([1, 2, 3] as $n) {
			$storage->setModuleData('blog', 'post:bericht-' . $n, [
				'title' => 'Bericht ' . $n,
				'content' => '<p>Body ' . $n . '</p><p>Meer tekst</p>',
				'category' => $n === 1 ? 'nieuws' : '',
				'published_at' => sprintf('2021-0%d-01T12:00:00+00:00', $n),
			]);
		}
		$storage->setModuleData('blog', 'reaction:bericht-1:0001', [
			'name' => 'Jan',
			'message' => '<p>Leuk</p>',
			'posted_at' => '2021-01-02T12:00:00+00:00',
		]);

		$module = new BlogModule($this->translator());
		$urls = new Urls('/', false);

		$index = $module->render('', [], $storage, $urls);
		$this->assertTrue($index !== null, 'the index renders');
		$this->assertTrue(str_contains($index->html, 'Bericht 3'), 'newest post first');
		$this->assertFalse(str_contains($index->html, 'Bericht 1'), 'and the page size is respected');

		// A summary is cut at a paragraph boundary, never mid-tag: slicing
		// sanitised HTML by length is how you leak an unclosed element into the
		// rest of the layout.
		$this->assertTrue(str_contains($index->html, '<p>Body 3</p>'), 'the first paragraph is the summary');
		$this->assertFalse(str_contains($index->html, 'Meer tekst'), 'the rest is left for the post itself');

		$this->assertTrue(str_contains($index->html, 'blog-pagination'), 'there is pagination');
		$page2 = $module->render('', ['p' => '2'], $storage, $urls);
		$this->assertTrue(str_contains((string) $page2?->html, 'Bericht 1'), 'the second page holds the older post');

		$this->assertSame(null, $module->render('', ['p' => '99'], $storage, $urls), 'a page number past the end is a 404, not the last page');
		$this->assertSame(null, $module->render('', ['p' => '0'], $storage, $urls), 'and so is a nonsense one');

		// A date on a Dutch site must be a Dutch date. date('j F Y') always says
		// "May" and looks plausible enough to survive review for years.
		$dutch = new BlogModule(new Translator(Locale::tryFrom('nl') ?? Locale::fallback(), dirname(__DIR__) . '/lang'));
		$dutchIndex = $dutch->render('', [], $storage, $urls);
		$this->assertTrue(
			str_contains((string) $dutchIndex?->html, 'maart') || str_contains((string) $dutchIndex?->html, '2021-03-01'),
			'a date follows the site language, or falls back to an unambiguous one',
		);
		$this->assertFalse(str_contains((string) $dutchIndex?->html, 'March'), 'and is never half-translated');

		$post = $module->render('bericht-1', [], $storage, $urls);
		$this->assertTrue(str_contains((string) $post?->html, 'Meer tekst'), 'a single post shows in full');
		$this->assertTrue(str_contains((string) $post?->html, 'Jan'), 'with its reactions');
		$this->assertSame(null, $module->render('nope', [], $storage, $urls), 'an unknown post is nothing');

		$category = $module->render('category/nieuws', [], $storage, $urls);
		$this->assertTrue(str_contains((string) $category?->html, 'Bericht 1'), 'a category lists its own posts');
		$this->assertFalse(str_contains((string) $category?->html, 'Bericht 2'), 'and only those');
		$this->assertSame(null, $module->render('category/nope', [], $storage, $urls), 'an unknown category is nothing');

		// Through the theme, end to end.
		$html = $this->renderer($storage)->module($post, 'blog/bericht-1');
		$this->assertTrue(str_starts_with($html, '<!DOCTYPE html>'), 'module output goes through the layout');
		$this->assertTrue(str_contains($html, '<title>Bericht 1 - '), 'and titles the window after the post');
		$this->assertTrue(str_contains($html, 'rel="canonical"'), 'a post declares its canonical address');
		$this->assertNoRawKeys($html, 'a rendered blog post');
	}

	private function albums(): void
	{
		[$storage] = $this->install();
		$storage->setModuleData('albums', 'album:vakantie', ['title' => 'Vakantie', 'order' => 0]);
		$storage->setModuleData('albums', 'image:vakantie:0000', [
			'file' => 'strand.jpg',
			'title' => 'Strand',
			'info' => '<p>Dag een</p>',
		]);

		$module = new AlbumsModule($this->translator());
		$urls = new Urls('/', false);

		$index = $module->render('', [], $storage, $urls);
		$this->assertTrue(str_contains((string) $index?->html, 'Vakantie'), 'the album is listed');
		$this->assertTrue(str_contains((string) $index?->html, '/media/strand.jpg'), 'with its first picture as the cover');

		$album = $module->render('vakantie', [], $storage, $urls);
		$this->assertTrue(str_contains((string) $album?->html, 'alt="Strand"'), 'a picture carries its caption as alt text');
		$this->assertTrue(str_contains((string) $album?->html, 'Dag een'), 'and its description');
		$this->assertSame(null, $module->render('nope', [], $storage, $urls), 'an unknown album is nothing');

		$empty = new AlbumsModule($this->translator());
		[$bare] = $this->install();
		$this->assertTrue(
			str_contains((string) $empty->render('', [], $bare, $urls)?->html, 'no albums yet'),
			'an install with no albums says so rather than rendering an empty list',
		);
	}

	private function notFound(): void
	{
		[$storage] = $this->install();
		$html = $this->renderer($storage)->notFound('does/not/exist');

		$this->assertTrue(str_starts_with($html, '<!DOCTYPE html>'), 'a 404 is a full page, not a bare string');

		// Reflecting the requested path puts attacker-chosen text on the site for
		// anyone who can get a link clicked. The visitor already knows what they
		// typed, so there is nothing to gain by echoing it.
		$this->assertFalse(str_contains($html, 'does/not/exist'), 'the requested address is not reflected back');
		$this->assertNoRawKeys($html, 'a 404 page');
	}

	// ---- fixture --------------------------------------------------------

	/** @return array{0:StorageDriver} */
	private function install(): array
	{
		$dir = $this->tempDir('pluck-theme');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();

		return [$storage];
	}

	private function translator(): Translator
	{
		return new Translator(Locale::tryFrom('en') ?? Locale::fallback(), dirname(__DIR__) . '/lang');
	}

	private function renderer(StorageDriver $storage): SiteRenderer
	{
		$theme = Theme::load($this->themesDir(), 'default');

		return $this->withoutSessionWarnings(fn (): SiteRenderer => new SiteRenderer(
			$theme,
			$storage,
			new Urls('/', false),
			new Csrf(new Session()),
			new Csp(),
			$this->translator(),
		));
	}

	/**
	 * A translation key that reached the page is a string nobody translated, and
	 * it looks like a bug to a visitor because it is one. Reading the rendered
	 * text for anything shaped like `word.word` catches the whole class at once,
	 * which the assertions above cannot: they were written against output that
	 * had no translator at all and passed happily on raw keys.
	 */
	private function assertNoRawKeys(string $html, string $where): void
	{
		$text = strip_tags($html);
		preg_match_all('/\b[a-z][a-z0-9]*\.[a-z][a-z0-9_]*\b/', $text, $matches);

		$found = array_values(array_unique(array_filter(
			$matches[0],
			// Filenames are not translation keys.
			static fn (string $m): bool => !preg_match('/\.(css|js|png|jpe?g|gif|webp|svg|html?|php)$/', $m),
		)));

		$this->assertSame([], $found, 'no untranslated key survives into ' . $where);
	}
}
