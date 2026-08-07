<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Model\Page;
use Pluck\Model\Role;
use Pluck\Model\User;
use Pluck\Storage\DriverFactory;
use Pluck\Storage\StorageDriver;

/**
 * The storage contract, run against every driver.
 *
 * If the two drivers ever drift, the installer's storage choice stops being an
 * implementation detail and becomes a trap. This file is the guard rail: add a
 * method to StorageDriver, add a case here.
 */
final class StorageParityTest extends TestCase
{
	public function cases(): array
	{
		$cases = ['flatfile'];
		if (extension_loaded('pdo_sqlite')) {
			$cases[] = 'sqlite';
		}

		return $cases;
	}

	public function run(): void
	{
		foreach ($this->cases() as $driverName) {
			$dir = $this->tempDir('pluck-' . $driverName);
			$driver = DriverFactory::make($driverName, $dir);
			$driver->install();

			$this->group($driverName, function () use ($driver): void {
				$this->settings($driver);
				$this->pages($driver);
				$this->hierarchy($driver);
				$this->ordering($driver);
				$this->moving($driver);
				$this->users($driver);
				$this->moduleData($driver);
				$this->rollback($driver);
			});
		}
	}

	private function settings(StorageDriver $s): void
	{
		$this->assertSame(null, $s->getSetting('nope'), 'missing setting returns null');
		$this->assertSame('fallback', $s->getSetting('nope', 'fallback'), 'missing setting returns default');

		$s->setSetting('site_title', 'My Site');
		$this->assertSame('My Site', $s->getSetting('site_title'), 'string setting round-trips');

		$s->setSetting('search_enabled', false);
		$this->assertSame(false, $s->getSetting('search_enabled'), 'false survives the round-trip');

		$s->setSetting('menu', ['a' => 1, 'b' => [2, 3]]);
		$this->assertSame(['a' => 1, 'b' => [2, 3]], $s->getSetting('menu'), 'nested array setting round-trips');

		$this->assertTrue(array_key_exists('menu', $s->allSettings()), 'allSettings includes the key');

		$s->deleteSetting('menu');
		$this->assertSame(null, $s->getSetting('menu'), 'deleted setting is gone');
	}

	private function pages(StorageDriver $s): void
	{
		$this->assertSame(null, $s->findPage('ghost'), 'missing page returns null');
		$this->assertFalse($s->pageExists('ghost'), 'missing page does not exist');

		$page = new Page(
			path: 'about',
			title: 'About us',
			content: '<p>Hello &amp; welcome</p>',
			description: 'Who we are',
			keywords: 'about, us',
			moduleData: ['blog' => ['posts_per_page' => 5]],
		);
		$s->savePage($page);

		$loaded = $s->findPage('about');
		$this->assertTrue($loaded instanceof Page, 'saved page can be read back');
		$this->assertSame('About us', $loaded->title, 'title round-trips');
		$this->assertSame('<p>Hello &amp; welcome</p>', $loaded->content, 'html content round-trips untouched');
		$this->assertSame(['blog' => ['posts_per_page' => 5]], $loaded->moduleData, 'module data round-trips as structure');
		$this->assertSame(1, $loaded->order, 'first page gets order 1');
		$this->assertFalse($loaded->hidden, 'page is visible by default');

		$loaded->title = 'About';
		$loaded->hidden = true;
		$s->savePage($loaded);
		$again = $s->findPage('about');
		$this->assertSame('About', $again->title, 'update overwrites');
		$this->assertTrue($again->hidden, 'hidden flag persists as bool');
		$this->assertSame(1, $again->order, 'order is kept on update');

		$again->hidden = false;
		$s->savePage($again);
	}

	private function hierarchy(StorageDriver $s): void
	{
		$s->savePage(new Page(path: 'about/team', title: 'Team'));
		$s->savePage(new Page(path: 'about/history', title: 'History'));

		$children = $s->listPages('about');
		$this->assertSame(2, count($children), 'two children under about');
		$this->assertSame('about', $children[0]->parent(), 'child reports its parent');
		$this->assertSame(2, $children[0]->depth(), 'child sits at depth 2');

		$this->assertTrue(
			in_array('about/team', array_map(static fn (Page $p): string => $p->path, $s->allPages()), true),
			'allPages walks into children',
		);

		$this->expectFailure(
			static fn () => $s->savePage(new Page(path: 'nowhere/child', title: 'Orphan')),
			'saving under a missing parent is refused',
		);

		$s->savePage(new Page(path: 'about/secret', title: 'Secret', hidden: true));
		$this->assertSame(2, count($s->listPages('about', false)), 'hidden pages are skipped when asked');
		$this->assertSame(3, count($s->listPages('about', true)), 'hidden pages are included by default');
	}

	private function ordering(StorageDriver $s): void
	{
		$paths = static fn (array $pages): array => array_map(static fn (Page $p): string => $p->path, $pages);

		$before = $paths($s->listPages('about'));
		$this->assertSame(['about/team', 'about/history', 'about/secret'], $before, 'children keep insertion order');

		$s->reorderPage('about/history', -1);
		$this->assertSame(
			['about/history', 'about/team', 'about/secret'],
			$paths($s->listPages('about')),
			'moving up swaps with the previous sibling',
		);

		$s->reorderPage('about/history', -1);
		$this->assertSame(
			['about/history', 'about/team', 'about/secret'],
			$paths($s->listPages('about')),
			'moving up at the top is a no-op',
		);

		$s->reorderPage('about/secret', 1);
		$this->assertSame(
			['about/history', 'about/team', 'about/secret'],
			$paths($s->listPages('about')),
			'moving down at the bottom is a no-op',
		);
	}

	private function moving(StorageDriver $s): void
	{
		$s->savePage(new Page(path: 'company', title: 'Company'));
		$s->movePage('about', 'company/about');

		$this->assertFalse($s->pageExists('about'), 'the old path is gone');
		$this->assertTrue($s->pageExists('company/about'), 'the page lives at the new path');
		$this->assertTrue($s->pageExists('company/about/team'), 'children moved along');

		$child = $s->findPage('company/about/team');
		$this->assertSame('company/about', $child->parent(), 'child path was rewritten, not just relocated');

		$this->expectFailure(
			static fn () => $s->movePage('company', 'company/about/company'),
			'a page cannot be moved into its own subtree',
		);

		$s->savePage(new Page(path: 'contact', title: 'Contact'));
		$this->expectFailure(
			static fn () => $s->movePage('contact', 'company/about'),
			'moving onto an occupied path is refused',
		);

		$s->deletePage('company/about');
		$this->assertFalse($s->pageExists('company/about'), 'deleted page is gone');
		$this->assertFalse($s->pageExists('company/about/team'), 'children went with it');
		$this->assertTrue($s->pageExists('company'), 'the parent survived');
	}

	private function users(StorageDriver $s): void
	{
		$this->assertSame(0, $s->countUsers(), 'a fresh install has no users');

		$owner = User::create('bas', 'a-long-enough-password', Role::Owner, 'bas@example.org');
		$s->saveUser($owner);

		$this->assertSame(1, $s->countUsers(), 'user was stored');
		$found = $s->findUserByUsername('BAS');
		$this->assertTrue($found instanceof User, 'username lookup is case-insensitive');
		$this->assertSame(Role::Owner, $found->role, 'role round-trips as an enum');
		$this->assertTrue($found->verify('a-long-enough-password'), 'password verifies');
		$this->assertFalse($found->verify('wrong'), 'wrong password is rejected');
		$this->assertTrue($found->active, 'user is active by default');
		$this->assertTrue($found->can('page.create'), 'owner can create pages');

		$editor = User::create('editor', 'another-long-password', Role::Editor);
		$s->saveUser($editor);
		$this->assertFalse($editor->can('user.create'), 'an editor cannot create users');
		$this->assertTrue($editor->can('page.edit'), 'an editor can edit pages');

		$editor->active = false;
		$s->saveUser($editor);
		$this->assertFalse($s->findUser($editor->id)->active, 'deactivation persists');
		$this->assertFalse($s->findUser($editor->id)->can('page.edit'), 'a deactivated user can do nothing');

		$s->deleteUser($editor->id);
		$this->assertSame(1, $s->countUsers(), 'user was removed');
		$this->assertSame(null, $s->findUser($editor->id), 'removed user cannot be found');
	}

	private function moduleData(StorageDriver $s): void
	{
		$this->assertSame('none', $s->getModuleData('blog', 'missing', 'none'), 'missing module key returns default');

		$s->setModuleData('blog', 'post/2026-07-30-hello', ['title' => 'Hello', 'body' => 'First post']);
		$s->setModuleData('blog', 'post/2026-07-29-older', ['title' => 'Older', 'body' => 'Second']);
		$s->setModuleData('blog', 'settings', ['posts_per_page' => 10]);

		$post = $s->getModuleData('blog', 'post/2026-07-30-hello');
		$this->assertSame('Hello', $post['title'], 'module data round-trips as structure');

		$posts = $s->listModuleData('blog', 'post/');
		$this->assertSame(2, count($posts), 'prefix filter selects only posts');
		$this->assertSame(
			['post/2026-07-29-older', 'post/2026-07-30-hello'],
			array_keys($posts),
			'keys come back sorted, so a blog index needs no extra sort',
		);
		$this->assertSame(3, count($s->listModuleData('blog')), 'no prefix returns everything');

		// Keys that differ only in characters a filename cannot hold must stay distinct.
		$s->setModuleData('blog', 'a/b', 'slash');
		$s->setModuleData('blog', 'a:b', 'colon');
		$this->assertSame('slash', $s->getModuleData('blog', 'a/b'), 'slash key kept its own value');
		$this->assertSame('colon', $s->getModuleData('blog', 'a:b'), 'colon key did not collide with it');

		$s->deleteModuleData('blog', 'settings');
		$this->assertSame(null, $s->getModuleData('blog', 'settings'), 'deleted module key is gone');

		$s->setModuleData('albums', 'settings', ['thumb' => 200]);
		$s->deleteModule('blog');
		$this->assertSame(0, count($s->listModuleData('blog')), 'uninstalling a module clears its data');
		$this->assertSame(1, count($s->listModuleData('albums')), 'and leaves other modules alone');
	}

	private function rollback(StorageDriver $s): void
	{
		$before = count($s->allPages());

		$this->expectFailure(function () use ($s): void {
			$s->transaction(function () use ($s): void {
				$s->savePage(new Page(path: 'doomed', title: 'Doomed'));
				throw new \RuntimeException('stop');
			});
		}, 'a failing transaction reports the error');

		// Only SQLite can actually undo the write; the flat-file driver documents
		// this as a known limitation, so assert the behaviour each one promises.
		if ($s->name() === 'sqlite') {
			$this->assertSame($before, count($s->allPages()), 'sqlite rolls the write back');
		} else {
			$this->assertSame($before + 1, count($s->allPages()), 'flatfile keeps the write (documented limitation)');
			$s->deletePage('doomed');
		}
	}
}
