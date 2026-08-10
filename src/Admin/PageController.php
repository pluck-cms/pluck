<?php
declare(strict_types=1);

namespace Pluck\Admin;

use Pluck\Security\Sanitizer;
use Throwable;
use Pluck\Security\Escaper;
use Pluck\Theme\Theme;
use Pluck\Theme\ThemeRepository;
use Pluck\Site\Palette;
use Pluck\Site\Urls;
use Pluck\Site\SiteRenderer;

use Pluck\Media\MediaLibrary;

use Pluck\Model\Page;
use Pluck\Support\Slug;

final class PageController extends Controller
{
	public function index(): never
	{
		$pages = $this->c->storage->allPages();

		// Authors see the whole tree but can only open their own pages: hiding
		// other people's pages entirely would make the site structure impossible
		// to reason about when deciding where a new page belongs.
		$this->render('admin/pages/index', [
			'title' => $this->t('page.title.pages'),
			'tree' => $this->tree($pages),
			'canCreate' => $this->c->auth->can('page.create'),
		]);
	}

	public function create(): never
	{
		$parent = Slug::path($this->c->request->query('parent'));

		$this->render('admin/pages/form', [
			// So a picture already uploaded can be inserted without typing its path.
			'media' => $this->mediaGroups(),
			// The palette the editor offers, read from the stylesheet that defines
			// it — see Site\Palette for why it is not listed here as well.
			'palette' => Palette::read($this->c->app->rootDir . '/assets/site/colours.css'),
			// Modules that have something to show inside a page.
			'embeddable' => array_map(
				static fn ($module): string => $module->mountPath(),
				$this->c->modules?->all() ?? [],
			),
			'title' => $this->t('page.title.new_page'),
			'page' => new Page(path: '', title: ''),
			'parent' => $parent,
			'isNew' => true,
			'parents' => $this->parentOptions(null),
		]);
	}

	public function edit(): never
	{
		$page = $this->findOr404($this->c->request->query('path'));

		if (!$this->c->auth->canEditPage($page->authorId)) {
			$this->c->flash->stop($this->t('page.flash.page_belongs_someone_else'));
			$this->back('pages');
		}

		$this->render('admin/pages/form', [
			// So a picture already uploaded can be inserted without typing its path.
			'media' => $this->mediaGroups(),
			// The palette the editor offers, read from the stylesheet that defines
			// it — see Site\Palette for why it is not listed here as well.
			'palette' => Palette::read($this->c->app->rootDir . '/assets/site/colours.css'),
			// Modules that have something to show inside a page.
			'embeddable' => array_map(
				static fn ($module): string => $module->mountPath(),
				$this->c->modules?->all() ?? [],
			),
			'title' => $this->t('page.title.edit_page'),
			'page' => $page,
			'parent' => $page->parent() ?? '',
			'isNew' => false,
			'parents' => $this->parentOptions($page->path),
		]);
	}

	public function save(): never
	{
		$request = $this->c->request;
		$storage = $this->c->storage;
		$user = $this->c->auth->user();

		$original = $request->post('original');
		$existing = $original !== '' ? $storage->findPage($original) : null;

		if ($original !== '' && $existing === null) {
			$this->c->flash->stop($this->t('page.flash.page_no_longer_exists_may_have'));
			$this->back('pages');
		}

		if ($existing !== null && !$this->c->auth->canEditPage($existing->authorId)) {
			$this->c->flash->stop($this->t('page.flash.page_belongs_someone_else'));
			$this->back('pages');
		}

		if ($existing === null && !$this->c->auth->can('page.create')) {
			$this->c->flash->stop($this->t('page.flash.account_cannot_create_pages'));
			$this->back('pages');
		}

		$title = $request->post('title');
		if ($title === '') {
			$this->c->flash->stop($this->t('page.flash.page_needs_title'));
			$this->back($existing !== null ? 'page.edit' : 'page.new', $existing !== null ? ['path' => $original] : []);
		}

		$parent = Slug::path($request->post('parent'));

		// The parent comes from a select box, but a hand-made POST can name
		// anything. savePage() would throw, which the user would see as a blank
		// 500 rather than an answer.
		if ($parent !== '' && !$storage->pageExists($parent)) {
			$this->c->flash->stop($this->t('page.flash.parent_page_does_not_exist'));
			$this->back($existing !== null ? 'page.edit' : 'page.new', $existing !== null ? ['path' => $original] : []);
		}

		$requestedSlug = $request->post('slug');
		$slug = Slug::make($requestedSlug !== '' ? $requestedSlug : $title);
		$path = $parent !== '' ? $parent . '/' . $slug : $slug;

		// Refuse to make a page its own descendant: the tree walk would never end.
		if ($existing !== null && ($path === $existing->path || str_starts_with($path . '/', $existing->path . '/'))
			&& $parent !== ($existing->parent() ?? '')) {
			$this->c->flash->stop($this->t('page.flash.page_cannot_moved_inside_itself'));
			$this->back('page.edit', ['path' => $original]);
		}

		if ($path !== $original) {
			$path = Slug::unique($path, fn (string $candidate): bool => $this->c->storage->pageExists($candidate));
		}

		// The editor's HTML is the one place untrusted markup enters the site, so
		// it is filtered through the allow-list on the way in, not on the way out.
		$content = $this->c->app->sanitizer()->clean($request->raw('content'));

		$page = $existing ?? new Page(path: $path, title: $title);
		$page->path = $path;
		$page->title = $title;
		$page->content = $content;
		$page->description = mb_substr($request->post('description'), 0, 320);
		$page->keywords = mb_substr($request->post('keywords'), 0, 320);
		$page->hidden = $request->postBool('hidden');
		$page->authorId ??= $user?->id;
		$page->touch();

		if ($existing !== null && $original !== $path) {
			$storage->movePage($original, $path);
		}

		$storage->savePage($page);

		$this->c->flash->ok($existing === null ? $this->t('page.flash.page_created') : $this->t('page.flash.page_saved'));

		[$route, $params] = self::whereAfterSaving($request->post('then', ''), $page->path);

		$this->back($route, $params);
	}

	/**
	 * Where a save goes next.
	 *
	 * Its own method so it can be tested: the answer is a redirect, and header()
	 * does nothing under the CLI SAPI, so a test watching for a Location would
	 * pass whether or not one was ever sent.
	 *
	 * "Save and close" goes back to the list — somebody who is done is done.
	 * Plain save stays on the page, which is what somebody still working on it
	 * wants and what every editor does.
	 *
	 * @return array{0:string,1:array<string,string>}
	 */
	public static function whereAfterSaving(string $then, string $path): array
	{
		return $then === 'close'
			? ['pages', []]
			: ['page.edit', ['path' => $path]];
	}

	/**
	 * What the page will look like once it is saved.
	 *
	 * Answered by the server rather than rendered in the browser, because the
	 * question people actually have is not "what does this HTML look like" but
	 * "what will be left of it". The sanitiser runs on save, and finding out
	 * afterwards that half of what was pasted from Word has gone is the most
	 * surprising thing Pluck does.
	 *
	 * JSON rather than HTML: the caller puts it into a sandboxed iframe, where it
	 * cannot reach the admin page at all.
	 */
	/**
	 * Render the whole page, unsaved, through the site's own renderer.
	 *
	 * The Page here is transient: built from what is in the form, never stored.
	 * That is what lets a page be previewed before it has ever been saved, and
	 * what keeps a preview from being a way to write to the site.
	 */
	private function renderPreview(string $html): string
	{
		$path = $this->c->request->post('path', '');
		$page = $this->c->storage->findPage($path) ?? new Page(path: $path !== '' ? $path : 'preview', title: '');

		$page->title = $this->c->request->post('title', $page->title);
		$page->content = $html;

		try {
			$renderer = new SiteRenderer(
				// The same resolution the site uses, not Theme::load directly.
				// ThemeRepository::active() falls back when the stored theme is
				// missing; loading by name throws, so the preview died on any
				// install whose theme setting named something that is not there —
				// while the site itself rendered perfectly well.
				(new ThemeRepository($this->c->app->rootDir . '/themes'))->active($this->c->storage),
				$this->c->storage,
				// Derived the same way the front controller derives it — the admin
				// runs from the same directory — so the theme's stylesheet and the
				// pictures in the content resolve from inside the iframe.
				new Urls(
					Urls::detectBase($_SERVER),
					(bool) $this->c->storage->getSetting('pretty_urls', false),
				),
				$this->c->csrf,
				$this->c->app->csp(),
				$this->c->app->translator(),
				$this->c->modules,
			);

			// So a theme that builds itself from the menu shows what is being
			// edited rather than what was last saved.
			$renderer->previewing($page);

			return $renderer->page($page);
		} catch (Throwable $e) {
			// A theme that throws is a theme somebody is in the middle of editing.
			// Saying so beats an empty frame.
			return '<!DOCTYPE html><meta charset="utf-8"><p style="font:1rem system-ui;padding:1rem">'
				. Escaper::html($this->t('page.help.preview_failed', ['why' => $e->getMessage()]))
				. '</p>';
		}
	}

	/**
	 * The media library, grouped by where each file came from.
	 *
	 * Albums first, by name, then everything uploaded loose. On a site with two
	 * hundred pictures — which is what a migrated album site looks like — one flat
	 * list is not a picker, it is a haystack.
	 *
	 * @return array<string,list<string>>
	 */
	private function mediaGroups(): array
	{
		$library = new MediaLibrary($this->c->app->rootDir . '/media');
		$owners = [];

		foreach ($this->c->modules?->all() ?? [] as $module) {
			foreach ($this->c->storage->listModuleData($module->name(), 'media:') as $key => $value) {
				$name = substr($key, 6);
				// An album's own name where there is one, so the group reads as
				// "Open dag 4 februari 2012" rather than "albums".
				$owners[$name] = is_array($value) && isset($value['album'])
					? (string) $value['album']
					: $module->name();
			}
		}

		$groups = $library->grouped($owners);

		/*
		 * Pictures and files are two different jobs.
		 *
		 * Inserting a photograph and linking to a PDF have nothing in common
		 * except that both live in media/, and 4.x kept them apart for good
		 * reason: one goes in the page, the other is something to click.
		 *
		 * @var array{images:array<string,list<string>>,files:array<string,list<string>>}
		 */
		$split = ['images' => [], 'files' => []];

		foreach ($groups as $group => $names) {
			foreach ($names as $name) {
				$kind = in_array(
					strtolower(pathinfo($name, PATHINFO_EXTENSION)),
					MediaLibrary::IMAGE_EXTENSIONS,
					true,
				) ? 'images' : 'files';

				$split[$kind][$group][] = $name;
			}
		}

		return $split;
	}

	/**
	 * The page as it will be, theme and all.
	 *
	 * Not the content in a box with a stylesheet borrowed from the theme, which
	 * is what this was: that showed the paragraphs but nothing around them, so a
	 * layout question — does this picture fit beside that text — could not be
	 * answered without saving and looking.
	 *
	 * So it renders the real thing: the site's own renderer, the site's own
	 * theme, a Page that exists only for the length of this request. Nothing is
	 * written, and the page being previewed need not exist yet.
	 */
	/**
	 * Hold the unsaved page, then send the browser to the site to look at it.
	 *
	 * The panel below the editor answers "what will this paragraph look like".
	 * What it cannot answer is "what will the site look like" — the real width,
	 * the real menu, the real everything — and that is what people mean when they
	 * press Preview.
	 *
	 * Kept in the session rather than in a temporary file. A file needs a name
	 * nobody can guess, permissions, and something to delete it; a session is
	 * already per-person, already expires, and already cannot be read by anybody
	 * else. Nothing is written to the site, so a preview can never become a
	 * publish by accident.
	 */
	public function previewToSite(): never
	{
		$path = Slug::path($this->c->request->post('path', ''));
		$inspected = (new Sanitizer())->inspect($this->c->request->post('content', ''));

		$this->c->app->session()->set(self::PREVIEW_KEY, [
			'path' => $path,
			'title' => mb_substr(trim($this->c->request->post('title', '')), 0, 200),
			'content' => $inspected->html,
			'at' => time(),
		]);

		// index.php reads it back when it sees ?preview=1, and only for the
		// session that put it there.
		header('Location: ' . ($path === '' ? 'index.php?preview=1' : 'index.php?page=' . rawurlencode($path) . '&preview=1'));
		exit;
	}

	public const PREVIEW_KEY = 'page_preview';

	/**
	 * What address a title would get.
	 *
	 * Exists so the editor does not have to fold a title itself. It used to, in
	 * JavaScript, with a rule that agreed with Slug::make() for most of Europe
	 * and dropped Polish ł entirely — and because it filled the field in, the
	 * server used its answer and never saw the title.
	 *
	 * One implementation of a rule, on the side that has the table.
	 */
	public function slug(): never
	{
		// The router checks the token on every POST before a controller is
		// reached, so there is nothing to repeat here.
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');

		echo json_encode(
			['slug' => Slug::make($this->c->request->post('title', ''), '')],
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
		) ?: '{}';

		exit;
	}

	public function preview(): never
	{
		$inspected = (new Sanitizer())->inspect($this->c->request->post('content', ''));

		while (ob_get_level() > 0) {
			ob_end_clean();
		}

		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		header('X-Content-Type-Options: nosniff');

		echo json_encode([
			'document' => $this->renderPreview($inspected->html),
			'removed' => $inspected->removedAnything() ? $inspected->summary() : '',
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

		exit;
	}

	public function delete(): never
	{
		$path = $this->c->request->post('path');
		$page = $this->findOr404($path);

		if (!$this->c->auth->canDeletePage($page->authorId)) {
			$this->c->flash->stop($this->t('page.flash.page_belongs_someone_else'));
			$this->back('pages');
		}

		$children = $this->c->storage->listPages($page->path);
		if ($children !== []) {
			$this->c->flash->stop($this->t('page.flash.page_has_sub_pages_move_or'));
			$this->back('pages');
		}

		$this->c->storage->deletePage($page->path);
		$this->c->flash->ok($this->t('page.flash.page_deleted'));
		$this->back('pages');
	}

	public function move(): never
	{
		$path = $this->c->request->post('path');
		$page = $this->findOr404($path);

		if (!$this->c->auth->canEditPage($page->authorId)) {
			$this->c->flash->stop($this->t('page.flash.page_belongs_someone_else'));
			$this->back('pages');
		}

		$delta = $this->c->request->post('direction') === 'up' ? -1 : 1;
		$this->c->storage->reorderPage($page->path, $delta);

		$this->back('pages');
	}

	/** Flat list ordered for display, each row carrying its depth. */
	private function tree(array $pages): array
	{
		$byParent = [];
		foreach ($pages as $page) {
			$byParent[$page->parent() ?? ''][] = $page;
		}

		foreach ($byParent as &$group) {
			usort($group, static fn (Page $a, Page $b): int => [$a->order, $a->title] <=> [$b->order, $b->title]);
		}
		unset($group);

		$rows = [];
		$walk = function (string $parent, int $depth) use (&$walk, &$rows, $byParent): void {
			foreach ($byParent[$parent] ?? [] as $index => $page) {
				$siblings = $byParent[$parent];
				$rows[] = [
					'page' => $page,
					'depth' => $depth,
					'isFirst' => $index === 0,
					'isLast' => $index === count($siblings) - 1,
					'canEdit' => $this->c->auth->canEditPage($page->authorId),
					'canDelete' => $this->c->auth->canDeletePage($page->authorId),
				];
				$walk($page->path, $depth + 1);
			}
		};
		$walk('', 0);

		return $rows;
	}

	/** Parent choices, minus the page itself and its own descendants. */
	private function parentOptions(?string $exclude): array
	{
		$options = ['' => 'Top level'];

		foreach ($this->c->storage->allPages() as $page) {
			if ($exclude !== null && ($page->path === $exclude || str_starts_with($page->path . '/', $exclude . '/'))) {
				continue;
			}
			if ($page->depth() >= 3) {
				continue; // Slug::path caps depth at four segments.
			}
			$options[$page->path] = str_repeat('— ', $page->depth()) . $page->title;
		}

		return $options;
	}

	private function findOr404(string $path): Page
	{
		$page = $this->c->storage->findPage(Slug::path($path));

		if ($page === null) {
			$this->c->flash->stop($this->t('page.flash.no_page_at_address'));
			$this->back('pages');
		}

		return $page;
	}
}
