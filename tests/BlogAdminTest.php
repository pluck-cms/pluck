<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Http\Flash;
use Pluck\Http\Request;
use Pluck\I18n\Locale;
use Pluck\I18n\Translator;
use Pluck\Model\Role;
use Pluck\Module\BlogAdminController;
use Pluck\Module\BlogModule;
use Pluck\Module\ModuleContext;
use Pluck\Module\ModuleIdentity;
use Pluck\Module\ReactionStatus;
use Pluck\Security\Csrf;
use Pluck\Security\Session;
use Pluck\Site\Urls;
use Pluck\Storage\DriverFactory;
use Pluck\Storage\StorageDriver;
use Pluck\View\View;

/**
 * Editing the blog.
 *
 * The controllers here end in a redirect or a rendered page, both of which exit,
 * so they cannot be called directly from a test. What is exercised instead is the
 * state each action leaves behind: the actions are driven through a subclass that
 * turns the exit into an exception, which is enough to assert on what was written.
 *
 * The cases worth having are the ones where an edit can quietly lose something —
 * a rename that orphans reactions, a deleted category that takes its posts with
 * it, a draft that is still reachable if you know the address.
 */
final class BlogAdminTest extends TestCase
{
	public function run(): void
	{
		$this->group('saving a post', fn () => $this->saving());
		$this->group('renaming', fn () => $this->renaming());
		$this->group('drafts', fn () => $this->drafts());
		$this->group('categories', fn () => $this->categories());
		$this->group('reactions', fn () => $this->reactions());
		$this->group('settings from the enhancements module', fn () => $this->enhancements());
		$this->group('what a tampered form cannot do', fn () => $this->tampering());
	}

	private function saving(): void
	{
		[$storage, $run] = $this->install();

		$run('save', post: ['title' => 'Eerste bericht', 'content' => '<p>Hallo</p>', 'published' => '1']);

		$post = $storage->getModuleData('blog', 'post:eerste-bericht');
		$this->assertTrue(is_array($post), 'the post is stored under a slug made from its title');
		$this->assertSame('Eerste bericht', $post['title'] ?? null, 'with its title');
		$this->assertTrue((bool) ($post['published'] ?? false), 'and marked published');
		$this->assertTrue(is_string($post['published_at'] ?? null), 'and given a publication time');

		// The editor is a text area and people paste out of Word, out of the old
		// site, out of anywhere. The sanitiser runs on save, as it does for pages.
		$run('save', post: ['title' => 'Geplakt', 'content' => '<p>ok</p><script>alert(1)</script>', 'published' => '1']);
		$pasted = (string) ($storage->getModuleData('blog', 'post:geplakt')['content'] ?? '');
		$this->assertFalse(str_contains($pasted, '<script'), 'a script tag pasted into a post does not survive the save');
		$this->assertTrue(str_contains($pasted, '<p>ok</p>'), 'and the rest of it does');

		$run('save', post: ['title' => 'Zonder categorie', 'content' => '', 'category' => 'bestaat-niet', 'published' => '1']);
		$this->assertSame(
			'',
			$storage->getModuleData('blog', 'post:zonder-categorie')['category'] ?? null,
			'a category that does not exist is dropped rather than stored as a dangling reference',
		);

		// Two posts with the same title must not become one post.
		$run('save', post: ['title' => 'Eerste bericht', 'content' => '<p>Andere</p>', 'published' => '1']);
		$this->assertTrue(
			is_array($storage->getModuleData('blog', 'post:eerste-bericht-2')),
			'a second post with the same title gets its own address',
		);
		$this->assertTrue(
			str_contains((string) $storage->getModuleData('blog', 'post:eerste-bericht')['content'], 'Hallo'),
			'and the first one is not overwritten',
		);
	}

	private function renaming(): void
	{
		[$storage, $run] = $this->install();

		$run('save', post: ['title' => 'Oude titel', 'content' => '<p>Tekst</p>', 'published' => '1']);
		$storage->setModuleData('blog', 'reaction:oude-titel:0001', ['name' => 'Jan', 'message' => '<p>Leuk</p>']);

		// Changing the title must not change the address. This is the failure
		// nobody notices until someone reports a dead link months later.
		$run('save', post: ['original' => 'oude-titel', 'title' => 'Nieuwe titel', 'content' => '<p>Tekst</p>', 'published' => '1']);
		$this->assertTrue(is_array($storage->getModuleData('blog', 'post:oude-titel')), 'a retitled post keeps its address');
		$this->assertSame('Nieuwe titel', $storage->getModuleData('blog', 'post:oude-titel')['title'], 'and takes the new title');

		// Changing the address deliberately does move it, and the reactions go too.
		$run('save', post: ['original' => 'oude-titel', 'slug' => 'nieuw-adres', 'title' => 'Nieuwe titel', 'content' => '<p>Tekst</p>', 'published' => '1']);
		$this->assertSame(null, $storage->getModuleData('blog', 'post:oude-titel'), 'an explicit rename moves the post');
		$this->assertTrue(is_array($storage->getModuleData('blog', 'post:nieuw-adres')), 'to the address that was asked for');
		$this->assertTrue(
			is_array($storage->getModuleData('blog', 'reaction:nieuw-adres:0001')),
			'and its reactions move with it',
		);
		$this->assertSame(
			null,
			$storage->getModuleData('blog', 'reaction:oude-titel:0001'),
			'rather than being left under a key nothing reads',
		);
	}

	private function drafts(): void
	{
		[$storage, $run] = $this->install();

		$run('save', post: ['title' => 'Nog niet af', 'content' => '<p>Half</p>']);
		$draft = $storage->getModuleData('blog', 'post:nog-niet-af');
		$this->assertFalse((bool) ($draft['published'] ?? true), 'a post saved without publishing is a draft');
		// `?? 'set'` would swallow the null this is checking for, which is exactly
		// how the same assertion was written wrong twice already.
		$this->assertTrue(array_key_exists('published_at', $draft), 'the field is written either way');
		$this->assertSame(null, $draft['published_at'], 'but a draft claims no publication date');

		$run('save', post: ['title' => 'Wel af', 'content' => '<p>Klaar</p>', 'published' => '1']);

		$module = new BlogModule();
		$urls = new Urls('/', false);

		$index = $module->render('', [], $storage, $urls);
		$this->assertTrue(str_contains((string) $index?->html, 'Wel af'), 'the site lists the published post');
		$this->assertFalse(str_contains((string) $index?->html, 'Nog niet af'), 'and not the draft');

		// Unpublished has to mean unpublished, not unlisted. A draft served to
		// whoever guesses the address is a draft that leaked.
		$this->assertSame(null, $module->render('nog-niet-af', [], $storage, $urls), 'a draft is not reachable at its own address either');
		$this->assertTrue($module->render('wel-af', [], $storage, $urls) !== null, 'while the published one is');
	}

	private function categories(): void
	{
		[$storage, $run] = $this->install();

		$run('saveCategory', post: ['title' => 'Nieuws']);
		$this->assertSame('Nieuws', $storage->getModuleData('blog', 'category:nieuws')['title'] ?? null, 'a category is created');

		$run('saveCategory', post: ['original' => 'nieuws', 'title' => 'Actueel']);
		$this->assertSame('Actueel', $storage->getModuleData('blog', 'category:nieuws')['title'] ?? null, 'renaming keeps the address');

		$run('save', post: ['title' => 'Met categorie', 'content' => '<p>x</p>', 'category' => 'nieuws', 'published' => '1']);

		// Deleting a category must not delete a year of writing with it.
		$run('deleteCategory', post: ['slug' => 'nieuws']);
		$this->assertSame(null, $storage->getModuleData('blog', 'category:nieuws'), 'the category is gone');

		$orphan = $storage->getModuleData('blog', 'post:met-categorie');
		$this->assertTrue(is_array($orphan), 'its posts are kept');
		$this->assertSame('', $orphan['category'] ?? null, 'and are unfiled rather than left pointing at nothing');
	}

	private function reactions(): void
	{
		[$storage, $run] = $this->install();

		$run('save', post: ['title' => 'Bericht', 'content' => '<p>x</p>', 'published' => '1', 'allow_reaction' => '1']);
		$storage->setModuleData('blog', 'reaction:bericht:0001', ['name' => 'Jan', 'message' => '<p>Leuk</p>', 'status' => 'pending']);
		$storage->setModuleData('blog', 'reaction:bericht:0002', ['name' => 'Piet', 'message' => '<p>Ook leuk</p>', 'status' => 'approved']);

		$module = new BlogModule();
		$urls = new Urls('/', false);

		// Moderation off: everything shows. This is what a migrated site gets, and
		// it must not change on the day of the move.
		$html = (string) $module->render('bericht', [], $storage, $urls)?->html;
		$this->assertTrue(str_contains($html, 'Jan'), 'with moderation off a waiting reaction is still shown');

		$run('saveSettings', post: ['posts_per_page' => '10', 'moderate_reactions' => '1']);
		$html = (string) $module->render('bericht', [], $storage, $urls)?->html;
		$this->assertFalse(str_contains($html, 'Jan'), 'with moderation on a waiting reaction is held back');
		$this->assertTrue(str_contains($html, 'Piet'), 'and an approved one is not');

		$run('setReactionStatus', post: ['post' => 'bericht', 'id' => '0001', 'status' => 'approved']);
		$html = (string) $module->render('bericht', [], $storage, $urls)?->html;
		$this->assertTrue(str_contains($html, 'Jan'), 'approving it puts it on the site');

		// Spam stays hidden whatever the setting says. Someone made that decision;
		// switching moderation off should not undo it.
		$run('setReactionStatus', post: ['post' => 'bericht', 'id' => '0002', 'status' => 'spam']);
		$run('saveSettings', post: ['posts_per_page' => '10']);
		$html = (string) $module->render('bericht', [], $storage, $urls)?->html;
		$this->assertFalse(str_contains($html, 'Piet'), 'spam stays hidden even with moderation switched off');

		$run('deleteReaction', post: ['post' => 'bericht', 'id' => '0001']);
		$this->assertSame(null, $storage->getModuleData('blog', 'reaction:bericht:0001'), 'a reaction can be deleted');

		// One thread going wrong should not mean closing the whole blog, so a post
		// can be shut on its own.
		$storage->setModuleData('blog', 'reaction:bericht:0003', ['name' => 'Klaas', 'message' => '<p>Nog een</p>', 'status' => 'approved']);
		$html = (string) $module->render('bericht', [], $storage, $urls)?->html;
		$this->assertTrue(str_contains($html, 'Klaas'), 'an open post shows its reactions');

		$run('save', post: ['original' => 'bericht', 'title' => 'Bericht', 'content' => '<p>x</p>', 'published' => '1']);
		$html = (string) $module->render('bericht', [], $storage, $urls)?->html;
		$this->assertFalse(str_contains($html, 'Klaas'), 'closing one post hides its reactions');
		$this->assertTrue(str_contains($html, '<p>x</p>'), 'while the post itself is still there');
	}

	private function tampering(): void
	{
		[$storage, $run] = $this->install();

		$run('save', post: ['title' => 'Blijft staan', 'content' => '<p>x</p>', 'published' => '1']);

		// The reaction key is rebuilt from a post slug and an id rather than taken
		// from the form. A form that names a post key must not be able to hand it
		// to delete().
		$run('deleteReaction', post: ['post' => '../post:blijft-staan', 'id' => '0001']);
		$this->assertTrue(
			is_array($storage->getModuleData('blog', 'post:blijft-staan')),
			'a reaction delete cannot be aimed at a post',
		);

		$run('setReactionStatus', post: ['post' => 'blijft-staan', 'id' => '0001', 'status' => 'published']);
		$this->assertTrue(
			is_array($storage->getModuleData('blog', 'post:blijft-staan')),
			'and a status that is not a status changes nothing',
		);
	}

	/**
	 * The four options the old blog-enhancements module offered.
	 *
	 * Two of them Pluck 5 did not have at all; two it solved differently, and
	 * these assertions pin down that the old behaviour is available without the
	 * better default being lost.
	 */
	private function enhancements(): void
	{
		[$storage, $run] = $this->install();

		foreach ([['eerst', '2021-01-01'], ['later', '2021-06-01']] as [$title, $date]) {
			$run('save', post: [
				'title' => $title, 'content' => '<p>x</p>', 'published' => '1',
				'published_at' => $date . 'T12:00:00+00:00', 'allow_reaction' => '1',
			]);
		}

		$module = new BlogModule();
		$urls = new Urls('/', false);

		$newestFirst = (string) $module->render('', [], $storage, $urls)?->html;
		$this->assertTrue(
			strpos($newestFirst, 'later') < strpos($newestFirst, 'eerst'),
			'by default the newest post is at the top',
		);

		$run('saveSettings', post: ['posts_per_page' => '10', 'reverse_posts' => '1']);
		$oldestFirst = (string) $module->render('', [], $storage, $urls)?->html;
		$this->assertTrue(
			strpos($oldestFirst, 'eerst') < strpos($oldestFirst, 'later'),
			'reverse_posts puts the oldest first, for a diary or a series',
		);

		// --- truncation ---
		$long = '<p>' . str_repeat('woord ', 100) . '</p><p>Tweede alinea</p>';
		$run('save', post: ['title' => 'Lang', 'content' => $long, 'published' => '1', 'allow_reaction' => '1']);

		$run('saveSettings', post: ['posts_per_page' => '10', 'truncate_posts' => '0']);
		$whole = (string) $module->render('', [], $storage, $urls)?->html;
		$this->assertFalse(str_contains($whole, 'Tweede alinea'), 'with no limit the summary is the first paragraph');

		$run('saveSettings', post: ['posts_per_page' => '10', 'truncate_posts' => '50']);
		$cut = (string) $module->render('', [], $storage, $urls)?->html;
		$this->assertTrue(str_contains($cut, 'woord'), 'a character limit still shows the start of the post');
		$this->assertTrue(str_contains($cut, '…'), 'and says it was cut');

		// The reason this is not substr(): a cut string leaves a tag open and the
		// rest of the page ends up inside the summary.
		$this->assertSame(
			substr_count($cut, '<p'),
			substr_count($cut, '</p>'),
			'every paragraph the summary opens is closed again',
		);

		// --- date format ---
		// Read the visible text, not the markup: the datetime attribute is an ISO
		// timestamp that contains "12:00" whatever the display format says, and
		// asserting against the whole document quietly matches that instead.
		$run('saveSettings', post: ['posts_per_page' => '10', 'post_date' => 'd/m/Y', 'post_time' => 'H:i']);
		$formatted = strip_tags((string) $module->render('', [], $storage, $urls)?->html);

		// Expected values are computed rather than written out. A configured
		// format renders in the site's timezone, which the installer writes into
		// config.php, so hardcoding "12:00" here would pass in UTC and fail for
		// anyone running the suite from Amsterdam — including this suite, once an
		// earlier one has set the process timezone.
		$stamp = strtotime('2021-06-01T12:00:00+00:00');
		$this->assertTrue(str_contains($formatted, date('d/m/Y', $stamp)), 'a configured date format is used as given');
		$this->assertTrue(str_contains($formatted, date('H:i', $stamp)), 'including a time when one is asked for');

		$run('saveSettings', post: ['posts_per_page' => '10']);
		$localised = strip_tags((string) $module->render('', [], $storage, $urls)?->html);
		$this->assertFalse(str_contains($localised, date('d/m/Y', $stamp)), 'clearing it goes back to the localised date');
		$this->assertFalse(str_contains($localised, date('H:i', $stamp)), 'and to showing no time');

		// What "localised" means depends on ext-intl, which is *not* in the
		// official PHP images and is missing from plenty of shared hosting. With
		// it the month is named in the site language; without it the date falls
		// back to the ISO form, which is unambiguous in every language. Both are
		// correct, and a test that only knows the first passes on the developer's
		// machine and fails on the server.
		if (extension_loaded('intl')) {
			$this->assertTrue(str_contains($localised, 'June'), 'with ext-intl the month is named in the site language');
		} else {
			$this->assertTrue(
				str_contains($localised, date('Y-m-d', $stamp)),
				'without ext-intl the date falls back to the ISO form rather than to English',
			);
		}
	}

	// ---- fixture --------------------------------------------------------

	/** @return array{0:StorageDriver,1:callable} */
	private function install(): array
	{
		$dir = $this->tempDir('pluck-blog-admin');
		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();

		$root = dirname(__DIR__);
		$translator = new Translator(Locale::tryFrom('en') ?? Locale::fallback(), $root . '/lang');

		$run = function (string $action, array $post = [], array $query = []) use ($storage, $root, $translator): void {
			$this->withoutSessionWarnings(function () use ($action, $post, $query, $storage, $root, $translator): void {
				$session = new Session();
				$csrf = new Csrf($session);

				$context = new ModuleContext(
					module: 'blog',
					storage: $storage,
					identity: new ModuleIdentity('u1', 'Test', Role::Owner),
					request: Request::fake(method: "POST", post: $post, query: $query),
					flash: new Flash($session),
					csrf: $csrf,
					translator: $translator,
					adminView: new View($root . '/views', $csrf, new \Pluck\Security\Csp(), $translator),
				);

				try {
					(new StoppingBlogAdminController($context))->{$action}();
				} catch (ControllerStopped) {
					// Every action ends in a redirect or a rendered page, both of
					// which exit. What is being asserted on is what it wrote.
				}
			});
		};

		return [$storage, $run];
	}
}

/** Raised in place of the exit() a redirect or a render would perform. */
final class ControllerStopped extends \RuntimeException
{
}

/**
 * The real controller, with the two ways out replaced.
 *
 * Subclassing rather than mocking: every line under test is the code that ships,
 * and the only difference is that the process survives to be asserted on.
 */
final class StoppingBlogAdminController extends BlogAdminController
{
	protected function render(string $template, array $data = []): never
	{
		throw new ControllerStopped('rendered ' . $template);
	}

	protected function back(string $route, array $params = []): never
	{
		throw new ControllerStopped('redirected to ' . $route);
	}
}
