<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\Security\Sanitizer;
use Pluck\Support\Dates;
use Pluck\Support\Slug;

/**
 * Editing the blog.
 *
 * Runs on ModuleContext, so everything it touches is the blog's own module data.
 * There is no call in this file that could reach a page, a user or a site
 * setting, and that is a property of what it was handed rather than of the care
 * taken writing it.
 */
class BlogAdminController extends ModuleController
{
	private const PER_PAGE = 25;

	// ---- posts ----------------------------------------------------------

	public function index(): never
	{
		$posts = $this->posts();
		$page = max(1, (int) $this->c->request->query('p', '1'));
		$pages = max(1, (int) ceil(count($posts) / self::PER_PAGE));
		$page = min($page, $pages);

		$this->render('admin/blog/index', [
			'title' => $this->t('blog.title.posts'),
			'posts' => array_slice($posts, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
			'categories' => $this->categoryTitles(),
			'page' => $page,
			'pages' => $pages,
			'total' => count($posts),
			'pendingCount' => $this->pendingCount(),
			'settings' => $this->settings(),
		]);
	}

	public function create(): never
	{
		$this->form(null);
	}

	public function edit(): never
	{
		$slug = $this->slugFromQuery();
		$post = $this->c->get('post:' . $slug);

		if (!is_array($post)) {
			$this->c->flash->stop($this->t('blog.flash.post_gone'));
			$this->back('module.blog.index');
		}

		$this->form($slug, $post);
	}

	public function save(): never
	{
		$request = $this->c->request;

		$title = trim($request->post('title'));
		if ($title === '') {
			$this->c->flash->stop($this->t('blog.flash.post_needs_title'));
			$this->back('module.blog.index');
		}

		$original = Slug::path($request->post('original', ''));
		$existing = $original !== '' ? $this->c->get('post:' . $original) : null;

		// A slug the author typed wins; otherwise it follows the title, and an
		// existing post keeps the address it already had. Renaming on every title
		// edit would break links silently, which is the failure people notice
		// months later and cannot explain.
		$wanted = trim($request->post('slug', ''));
		$slug = $wanted !== ''
			? Slug::make($wanted, 'post')
			: ($original !== '' ? $original : Slug::make($title, 'post'));

		if ($slug !== $original) {
			$slug = Slug::unique($slug, fn (string $candidate): bool => $this->c->get('post:' . $candidate) !== null);
		}

		$inspected = (new Sanitizer())->inspect($request->post('content', ''));

		$published = $request->postBool('published');
		$publishedAt = $this->publicationTime($request->post('published_at', ''), $existing, $published);

		$allowReaction = $request->postBool('allow_reaction');

		$category = Slug::path($request->post('category', ''));
		if ($category !== '' && $this->c->get('category:' . $category) === null) {
			$category = '';
		}

		$this->c->transaction(function () use ($slug, $original, $title, $inspected, $category, $published, $publishedAt, $existing, $allowReaction): void {
			$this->c->set('post:' . $slug, [
				'title' => $title,
				'content' => $inspected->html,
				'category' => $category,
				'allow_reaction' => $allowReaction,
				'published' => $published,
				'published_at' => $publishedAt,
				'author_id' => $existing['author_id'] ?? $this->c->user()->id,
				'legacy_seoname' => $existing['legacy_seoname'] ?? null,
			]);

			// A rename moves the reactions with the post. Leaving them behind
			// would orphan them under a key nothing reads, which reads as "the
			// comments vanished when I fixed a typo".
			if ($original !== '' && $original !== $slug) {
				foreach ($this->c->list('reaction:' . $original . ':') as $key => $reaction) {
					$this->c->set('reaction:' . $slug . ':' . substr($key, strlen('reaction:' . $original . ':')), $reaction);
					$this->c->delete($key);
				}
				$this->c->delete('post:' . $original);
			}
		});

		if ($inspected->removedAnything()) {
			$this->c->flash->warn($this->t('blog.flash.content_cleaned', ['what' => $inspected->summary()]));
		}

		$this->c->flash->ok($published ? $this->t('blog.flash.post_published') : $this->t('blog.flash.draft_saved'));
		$this->back('module.blog.edit', ['slug' => $slug]);
	}

	public function delete(): never
	{
		$slug = $this->slugFromPost();

		if ($this->c->get('post:' . $slug) === null) {
			$this->c->flash->stop($this->t('blog.flash.post_gone'));
			$this->back('module.blog.index');
		}

		$this->c->transaction(function () use ($slug): void {
			foreach ($this->c->list('reaction:' . $slug . ':') as $key => $ignored) {
				$this->c->delete($key);
			}
			$this->c->delete('post:' . $slug);
		});

		$this->c->flash->ok($this->t('blog.flash.post_deleted'));
		$this->back('module.blog.index');
	}

	// ---- categories -----------------------------------------------------

	public function categories(): never
	{
		$categories = [];
		foreach ($this->c->list('category:') as $key => $value) {
			if (is_array($value)) {
				$slug = substr($key, 9);
				$categories[$slug] = [
					'title' => (string) ($value['title'] ?? $slug),
					'posts' => $this->postsInCategory($slug),
				];
			}
		}
		ksort($categories);

		$this->render('admin/blog/categories', [
			'title' => $this->t('blog.title.categories'),
			'categories' => $categories,
		]);
	}

	public function saveCategory(): never
	{
		$title = trim($this->c->request->post('title'));
		if ($title === '') {
			$this->c->flash->stop($this->t('blog.flash.category_needs_title'));
			$this->back('module.blog.categories');
		}

		$original = Slug::path($this->c->request->post('original', ''));
		$slug = $original !== '' ? $original : Slug::unique(
			Slug::make($title, 'category'),
			fn (string $candidate): bool => $this->c->get('category:' . $candidate) !== null,
		);

		$this->c->set('category:' . $slug, ['title' => $title]);
		$this->c->flash->ok($this->t('blog.flash.category_saved'));
		$this->back('module.blog.categories');
	}

	public function deleteCategory(): never
	{
		$slug = Slug::path($this->c->request->post('slug', ''));
		if ($slug === '' || $this->c->get('category:' . $slug) === null) {
			$this->c->flash->stop($this->t('blog.flash.category_gone'));
			$this->back('module.blog.categories');
		}

		// Posts are not deleted with the category, only unfiled. Losing a year of
		// writing because a category was tidied up is not a trade anyone would
		// choose, and the admin screen says how many will be affected.
		$this->c->transaction(function () use ($slug): void {
			foreach ($this->c->list('post:') as $key => $post) {
				if (is_array($post) && ($post['category'] ?? '') === $slug) {
					$post['category'] = '';
					$this->c->set($key, $post);
				}
			}
			$this->c->delete('category:' . $slug);
		});

		$this->c->flash->ok($this->t('blog.flash.category_deleted'));
		$this->back('module.blog.categories');
	}

	// ---- reactions ------------------------------------------------------

	public function reactions(): never
	{
		$filter = $this->c->request->query('status', '');
		$wanted = ReactionStatus::tryFrom($filter);

		$reactions = [];
		foreach ($this->c->list('reaction:') as $key => $value) {
			if (!is_array($value)) {
				continue;
			}

			$status = ReactionStatus::from_($value['status'] ?? null);
			if ($wanted !== null && $status !== $wanted) {
				continue;
			}

			// reaction:<post-slug>:<id>
			$rest = substr($key, strlen('reaction:'));
			$split = strrpos($rest, ':');

			$reactions[] = [
				'key' => $key,
				'post' => $split === false ? '' : substr($rest, 0, $split),
				'status' => $status,
				'name' => (string) ($value['name'] ?? ''),
				'website' => (string) ($value['website'] ?? ''),
				'message' => (string) ($value['message'] ?? ''),
				'posted_at' => (string) ($value['posted_at'] ?? ''),
			];
		}

		usort($reactions, static fn (array $a, array $b): int => [$b['posted_at'], $b['key']] <=> [$a['posted_at'], $a['key']]);

		$this->render('admin/blog/reactions', [
			'title' => $this->t('blog.title.reactions'),
			'reactions' => $reactions,
			'filter' => $wanted,
			'statuses' => ReactionStatus::cases(),
			'settings' => $this->settings(),
			'postTitles' => $this->postTitles(),
		]);
	}

	public function setReactionStatus(): never
	{
		$key = $this->reactionKeyFromPost();
		$status = ReactionStatus::tryFrom($this->c->request->post('status', ''));

		if ($status === null) {
			$this->c->flash->stop($this->t('blog.flash.unknown_status'));
			$this->back('module.blog.reactions');
		}

		$reaction = $this->c->get($key);
		if (!is_array($reaction)) {
			$this->c->flash->stop($this->t('blog.flash.reaction_gone'));
			$this->back('module.blog.reactions');
		}

		$reaction['status'] = $status->value;
		$this->c->set($key, $reaction);

		$this->c->flash->ok($this->t('blog.flash.reaction_updated'));
		$this->back('module.blog.reactions');
	}

	public function deleteReaction(): never
	{
		$key = $this->reactionKeyFromPost();

		if ($this->c->get($key) === null) {
			$this->c->flash->stop($this->t('blog.flash.reaction_gone'));
			$this->back('module.blog.reactions');
		}

		$this->c->delete($key);
		$this->c->flash->ok($this->t('blog.flash.reaction_deleted'));
		$this->back('module.blog.reactions');
	}

	// ---- settings -------------------------------------------------------

	public function saveSettings(): never
	{
		$request = $this->c->request;

		$this->c->set('settings', [
			'posts_per_page' => max(1, min(100, (int) $request->post('posts_per_page', '10'))),
			// Zero means the whole post, as it did in 4.x. Capped so a typo cannot
			// turn every summary into a full page.
			'truncate_posts' => max(0, min(20000, (int) $request->post('truncate_posts', '0'))),
			'reverse_posts' => $request->postBool('reverse_posts'),
			'allow_reactions' => $request->postBool('allow_reactions'),
			'moderate_reactions' => $request->postBool('moderate_reactions'),
			// Empty means "let the site language decide", which is the better
			// default; a format string is the same in every language.
			'post_date' => mb_substr(trim($request->post('post_date', '')), 0, 40),
			'post_time' => mb_substr(trim($request->post('post_time', '')), 0, 40),
		]);

		$this->c->flash->ok($this->t('blog.flash.settings_saved'));
		$this->back('module.blog.index');
	}

	// ---- internals ------------------------------------------------------

	private function form(?string $slug, array $post = []): never
	{
		$this->render('admin/blog/form', [
			'title' => $slug === null ? $this->t('blog.title.new_post') : $this->t('blog.title.edit_post'),
			'slug' => $slug,
			'post' => $post,
			'categories' => $this->categoryTitles(),
			'reactions' => $slug === null ? [] : $this->c->list('reaction:' . $slug . ':'),
		]);
	}

	/**
	 * When a post says it was published.
	 *
	 * A date the author typed wins. Otherwise a post keeps the time it already
	 * had, and a post being published for the first time gets now. A draft that
	 * has never been published has no date at all rather than a placeholder,
	 * because a date is a claim and an unpublished post has not made it.
	 */
	private function publicationTime(string $typed, ?array $existing, bool $published): ?string
	{
		$typed = trim($typed);
		if ($typed !== '') {
			$iso = Dates::iso($typed);
			if ($iso !== '') {
				return $iso;
			}
		}

		$had = $existing['published_at'] ?? null;
		if (is_string($had) && $had !== '') {
			return $had;
		}

		return $published ? gmdate('c') : null;
	}

	/** @return list<array<string,mixed>> newest first, drafts at the top */
	private function posts(): array
	{
		$posts = [];
		foreach ($this->c->list('post:') as $key => $value) {
			if (!is_array($value)) {
				continue;
			}
			$value['slug'] = substr($key, 5);
			$value['published'] = (bool) ($value['published'] ?? true);
			$value['reactions'] = count($this->c->list('reaction:' . $value['slug'] . ':'));
			$posts[] = $value;
		}

		// Drafts first: they are the ones with something outstanding.
		usort($posts, static function (array $a, array $b): int {
			if ($a['published'] !== $b['published']) {
				return $a['published'] ? 1 : -1;
			}

			$left = strtotime((string) ($a['published_at'] ?? '')) ?: 0;
			$right = strtotime((string) ($b['published_at'] ?? '')) ?: 0;

			return [$right, $a['slug']] <=> [$left, $b['slug']];
		});

		return $posts;
	}

	/** @return array<string,string> */
	private function categoryTitles(): array
	{
		$titles = [];
		foreach ($this->c->list('category:') as $key => $value) {
			if (is_array($value)) {
				$titles[substr($key, 9)] = (string) ($value['title'] ?? substr($key, 9));
			}
		}
		asort($titles);

		return $titles;
	}

	/** @return array<string,string> slug => title, for naming a reaction's post */
	private function postTitles(): array
	{
		$titles = [];
		foreach ($this->c->list('post:') as $key => $value) {
			if (is_array($value)) {
				$titles[substr($key, 5)] = (string) ($value['title'] ?? substr($key, 5));
			}
		}

		return $titles;
	}

	private function postsInCategory(string $slug): int
	{
		$count = 0;
		foreach ($this->c->list('post:') as $post) {
			if (is_array($post) && ($post['category'] ?? '') === $slug) {
				$count++;
			}
		}

		return $count;
	}

	private function pendingCount(): int
	{
		$count = 0;
		foreach ($this->c->list('reaction:') as $reaction) {
			if (is_array($reaction) && ReactionStatus::from_($reaction['status'] ?? null) === ReactionStatus::Pending) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * The blog's settings, resolved the same way the site resolves them.
	 *
	 * @return array<string,mixed>
	 */
	private function settings(): array
	{
		$stored = $this->c->get('settings', []);
		$stored = is_array($stored) ? $stored : [];

		return [
			'posts_per_page' => max(1, (int) ($stored['posts_per_page'] ?? 10)),
			'truncate_posts' => max(0, (int) ($stored['truncate_posts'] ?? 0)),
			'reverse_posts' => (bool) ($stored['reverse_posts'] ?? false),
			'allow_reactions' => (bool) ($stored['allow_reactions'] ?? false),
			'moderate_reactions' => (bool) ($stored['moderate_reactions'] ?? false),
			'post_date' => (string) ($stored['post_date'] ?? ''),
			'post_time' => (string) ($stored['post_time'] ?? ''),
		];
	}

	private function slugFromQuery(): string
	{
		$slug = Slug::path($this->c->request->query('slug', ''));
		if ($slug === '') {
			$this->c->flash->stop($this->t('blog.flash.post_gone'));
			$this->back('module.blog.index');
		}

		return $slug;
	}

	private function slugFromPost(): string
	{
		$slug = Slug::path($this->c->request->post('slug', ''));
		if ($slug === '') {
			$this->c->flash->stop($this->t('blog.flash.post_gone'));
			$this->back('module.blog.index');
		}

		return $slug;
	}

	/**
	 * A reaction key, rebuilt from its parts rather than taken from the form.
	 *
	 * The form knows a post slug and an id; accepting the whole key would let a
	 * request name any key in this module's data, including a post, and hand it
	 * to delete(). Rebuilding it means the worst a tampered form can do is name a
	 * reaction that does not exist.
	 */
	private function reactionKeyFromPost(): string
	{
		$post = Slug::path($this->c->request->post('post', ''));
		$id = preg_replace('/[^A-Za-z0-9_-]/', '', $this->c->request->post('id', '')) ?? '';

		if ($post === '' || $id === '') {
			$this->c->flash->stop($this->t('blog.flash.reaction_gone'));
			$this->back('module.blog.reactions');
		}

		return 'reaction:' . $post . ':' . $id;
	}
}
