<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\I18n\Translates;
use Pluck\I18n\Translator;
use Pluck\Security\Escaper;
use Pluck\Form\Guard;
use Pluck\Site\Search;
use Pluck\Site\SearchResult;
use Pluck\Site\Urls;
use Pluck\Storage\StorageDriver;
use Pluck\Support\Dates;
use Pluck\Support\Excerpt;

/**
 * The blog, on the site side.
 *
 * Reads what the migrator wrote and what the admin writes: posts, categories and
 * reactions as module data. Storage is the only thing it touches, so it works
 * the same on flat files and SQLite without knowing which it has.
 *
 * Reactions are shown but not accepted here. Taking a comment means a form, a
 * CSRF token, a rate limit and a spam decision, and none of those belong in a
 * read path; the posting side is a controller of its own.
 */
final class BlogModule implements SiteModule, Insertable, PublicForm
{
	use Translates;

	public function __construct(private readonly ?Translator $translator = null)
	{
	}

	public function name(): string
	{
		return 'blog';
	}

	public function mountPath(): string
	{
		return 'blog';
	}

	/**
	 * The blog's own settings, with the defaults 4.x used where a value is absent.
	 *
	 * @return array{posts_per_page:int,truncate_posts:int,reverse_posts:bool,allow_reactions:bool,moderate_reactions:bool,post_date:string,post_time:string}
	 */
	public static function settings(StorageDriver $storage): array
	{
		$stored = $storage->getModuleData('blog', 'settings', []);
		$stored = is_array($stored) ? $stored : [];

		return [
			'posts_per_page' => max(1, (int) ($stored['posts_per_page'] ?? 10)),
			// Zero is 4.x's spelling of "show the whole post", and it is the
			// default here because silently shortening someone's writing on
			// upgrade would be the wrong surprise.
			'truncate_posts' => max(0, (int) ($stored['truncate_posts'] ?? 0)),
			'reverse_posts' => (bool) ($stored['reverse_posts'] ?? false),
			'allow_reactions' => (bool) ($stored['allow_reactions'] ?? false),
			'moderate_reactions' => (bool) ($stored['moderate_reactions'] ?? false),
			'post_date' => (string) ($stored['post_date'] ?? ''),
			'post_time' => (string) ($stored['post_time'] ?? ''),
		];
	}

	public function render(string $path, array $query, StorageDriver $storage, Urls $urls): ?ModuleView
	{
		if ($path === '') {
			return $this->index($query, $storage, $urls);
		}

		if (str_starts_with($path, 'category/')) {
			return $this->index($query, $storage, $urls, substr($path, 9));
		}

		return $this->post($path, $storage, $urls);
	}

	/**
	 * A few recent posts, for a page.
	 *
	 * No pagination: an embedded index whose "older posts" link leads out of the
	 * page it is embedded in is worse than one that simply ends with a way through
	 * to the blog itself.
	 *
	 * Parameters: `count` (default 3, capped at 20) and `category`.
	 */
	/**
	 * What a page can hold from the blog.
	 *
	 * The two shapes, and then the categories this site actually has — a list of
	 * every category in the abstract would be a list of nothing.
	 *
	 * @return list<array{label:string,marker:string}>
	 */
	public function embedOptions(StorageDriver $storage): array
	{
		$options = [
			['label' => $this->t('blog.insert.titles'), 'marker' => '[module:blog count=5]'],
			['label' => $this->t('blog.insert.summaries'), 'marker' => '[module:blog count=5 show=summary]'],
		];

		foreach ($storage->listModuleData('blog', 'category:') as $key => $value) {
			$slug = substr($key, 9);
			$name = is_array($value) ? (string) ($value['name'] ?? $slug) : $slug;

			$options[] = [
				'label' => $this->t('blog.insert.category', ['name' => $name]),
				'marker' => '[module:blog count=5 show=summary category=' . $slug . ']',
			];
		}

		return $options;
	}

	public function embed(array $parameters, StorageDriver $storage, Urls $urls): ?string
	{
		$settings = self::settings($storage);
		$posts = $this->posts($storage, $settings['reverse_posts']);

		$category = $parameters['category'] ?? '';
		if ($category !== '') {
			if ($storage->getModuleData('blog', 'category:' . $category) === null) {
				return null;
			}
			$posts = array_values(array_filter($posts, static fn (array $p): bool => ($p['category'] ?? '') === $category));
		}

		$count = max(1, min(20, (int) ($parameters['count'] ?? 3)));
		$posts = array_slice($posts, 0, $count);

		if ($posts === []) {
			return '<div class="blog-embed"><p class="blog-empty">'
				. Escaper::html($this->t('blog.no_posts')) . '</p></div>';
		}

		/*
		 * Two shapes, because two things get asked for.
		 *
		 * A list of titles is the right answer for "latest posts" in a sidebar.
		 * It is the wrong one for a site whose posts *are* the page — a caterer
		 * whose weekly menu is one post per dish wants the pictures and the first
		 * lines, not seven links.
		 *
		 *     [module:blog count=7]              titles
		 *     [module:blog count=7 show=summary] the same summaries the blog's own
		 *                                        index uses
		 *
		 * The summary rendering is the one the index already had, called from
		 * here rather than written again: a second implementation of "what a post
		 * looks like in a list" would drift from the first the moment either was
		 * touched.
		 */
		$asSummaries = ($parameters['show'] ?? '') === 'summary';

		if ($asSummaries) {
			$html = '<div class="blog-embed blog-embed--summaries">';
			foreach ($posts as $post) {
				$html .= $this->summary($post, $urls, $storage, $settings);
			}
		} else {
			$html = '<div class="blog-embed"><ul class="blog-embed__list">';
			foreach ($posts as $post) {
				$html .= '<li><a href="' . Escaper::html($urls->to('blog/' . $post['slug'])) . '">'
					. Escaper::html((string) $post['title']) . '</a>';
				$html .= $this->time((string) ($post['published_at'] ?? ''), $settings);
				$html .= '</li>';
			}
			$html .= '</ul>';
		}

		$path = $category !== '' ? 'blog/category/' . $category : 'blog';
		$html .= '<p class="blog-embed__more"><a href="' . Escaper::html($urls->to($path)) . '">'
			. Escaper::html($this->t('blog.all_posts')) . '</a></p>';

		return $html . '</div>';
	}

	/** @return list<SearchResult> */
	public function search(string $query, StorageDriver $storage): array
	{
		$results = [];

		foreach ($this->posts($storage) as $post) {
			$text = Search::plain((string) ($post['content'] ?? ''));
			$score = Search::score($query, (string) $post['title'], '', $text);

			if ($score === 0) {
				continue;
			}

			$results[] = new SearchResult(
				title: (string) $post['title'],
				path: 'blog/' . $post['slug'],
				snippet: Search::snippet($query, $text),
				score: $score,
				kindKey: 'search.kind.post',
			);
		}

		return $results;
	}

	// ---- index ----------------------------------------------------------

	private function index(array $query, StorageDriver $storage, Urls $urls, ?string $category = null): ?ModuleView
	{
		$settings = self::settings($storage);
		$posts = $this->posts($storage, $settings['reverse_posts']);

		if ($category !== null) {
			if ($storage->getModuleData('blog', 'category:' . $category) === null) {
				return null;
			}
			$posts = array_values(array_filter($posts, static fn (array $p): bool => ($p['category'] ?? '') === $category));
		}

		$perPage = $settings['posts_per_page'];
		$total = count($posts);
		$pages = max(1, (int) ceil($total / $perPage));

		// A page number out of range is a bad address, not something to clamp
		// silently: a crawler that finds /blog?p=999 should be told it is wrong
		// once rather than served the last page forever under a thousand URLs.
		$current = (int) ($query['p'] ?? 1);
		if ($current < 1 || ($current > $pages && $total > 0)) {
			return null;
		}

		$slice = array_slice($posts, ($current - 1) * $perPage, $perPage);

		$html = '';
		if ($slice === []) {
			$html .= '<p class="blog-empty">' . Escaper::html($this->t('blog.no_posts')) . '</p>';
		}

		foreach ($slice as $post) {
			$html .= $this->summary($post, $urls, $storage, $settings);
		}

		$html .= $this->pagination($current, $pages, $category, $urls);

		$title = $category !== null
			? (string) ($storage->getModuleData('blog', 'category:' . $category)['title'] ?? $category)
			: $this->t('blog.title');

		$breadcrumbs = [['title' => $this->t('blog.title'), 'path' => 'blog']];
		if ($category !== null) {
			$breadcrumbs[] = ['title' => $title, 'path' => 'blog/category/' . $category];
		}

		return new ModuleView(
			title: $title,
			html: $html,
			breadcrumbs: $breadcrumbs,
		);
	}

	private function summary(array $post, Urls $urls, StorageDriver $storage, array $settings): string
	{
		$link = $urls->to('blog/' . $post['slug']);

		$out = '<article class="blog-post blog-post--summary">';

		/*
		 * The post's first picture, before the words.
		 *
		 * An excerpt is the first paragraph or the first so many characters, and
		 * on most posts the photograph comes after that — so a summary of a post
		 * with a picture in it showed no picture. On a site whose posts are dishes
		 * that is the whole of what somebody is choosing with.
		 *
		 * Lifted rather than duplicated: it is the post's own image, linked to the
		 * post, and a theme decides whether to show it by styling
		 * .blog-post__thumb — or not, in which case nothing changes for anybody
		 * who liked the old shape.
		 */
		$thumb = $this->firstImage((string) ($post['content'] ?? ''));

		if ($thumb !== '') {
			$out .= '<a class="blog-post__thumb" href="' . Escaper::html($link) . '">'
				. '<img src="' . Escaper::html($thumb) . '" alt="" loading="lazy"></a>';
		}

		$out .= '<h2><a href="' . Escaper::html($link) . '">' . Escaper::html((string) $post['title']) . '</a></h2>';
		$out .= $this->byline($post, $urls, $storage, $settings);
		$out .= '<div class="blog-post__body">' . $this->excerpt((string) ($post['content'] ?? ''), $settings) . '</div>';
		$out .= '<p class="blog-post__more"><a href="' . Escaper::html($link) . '">'
			. Escaper::html($this->t('blog.read_more')) . '</a></p>';

		return $out . '</article>';
	}

	// ---- one post -------------------------------------------------------

	private function post(string $slug, StorageDriver $storage, Urls $urls): ?ModuleView
	{
		$post = $storage->getModuleData('blog', 'post:' . $slug);
		if (!is_array($post) || !($post['published'] ?? true)) {
			// A draft is nothing at its own address either. Serving it to whoever
			// guesses the slug would make "unpublished" mean "unlisted".
			return null;
		}
		$post['slug'] = $slug;

		$settings = self::settings($storage);

		$html = '<article class="blog-post">';
		$html .= $this->byline($post, $urls, $storage, $settings);
		// Sanitised on the way in, by the editor or the migrator.
		$html .= '<div class="blog-post__body">' . (string) ($post['content'] ?? '') . '</div>';
		$html .= '</article>';

		$html .= $this->reactions($slug, $post, $storage, $settings, $urls);

		return new ModuleView(
			title: (string) ($post['title'] ?? $slug),
			html: $html,
			meta: ['description' => $this->plainExcerpt((string) ($post['content'] ?? ''), 160)],
			breadcrumbs: [['title' => $this->t('blog.title'), 'path' => 'blog']],
			canonical: $urls->to('blog/' . $slug),
		);
	}

	/**
	 * The form under a post.
	 *
	 * Every protected field comes from the Guard rather than being written here,
	 * so a second form cannot quietly ship with one of them missing.
	 */
	private function reactionForm(string $slug, StorageDriver $storage, Urls $urls): string
	{
		$guard = new Guard($storage, new \Pluck\Security\Session(), $this->translator);
		$challenge = $guard->challenge();

		$html = '<form class="reaction-form" method="post" action="' . Escaper::html($urls->to('blog/' . $slug)) . '">';
		$html .= $guard->fields();
		$html .= '<h3>' . Escaper::html($this->t('blog.reaction.leave_one')) . '</h3>';

		// A paragraph per field, so this stacks in a theme that has never heard of
		// it. A <label> is inline and would put the whole form on one line.
		$html .= '<p class="field"><label for="reaction-name">' . Escaper::html($this->t('blog.reaction.name')) . '</label><br>'
			. '<input type="text" id="reaction-name" name="name" maxlength="80" required></p>';
		$html .= '<p class="field"><label for="reaction-website">' . Escaper::html($this->t('blog.reaction.website')) . '</label><br>'
			. '<input type="url" id="reaction-website" name="website" maxlength="200" placeholder="https://"></p>';
		$html .= '<p class="field"><label for="reaction-text">' . Escaper::html($this->t('blog.reaction.message')) . '</label><br>'
			. '<textarea id="reaction-text" name="reaction" rows="5" maxlength="3000" required></textarea></p>';

		if ($challenge['html'] !== '') {
			$html .= '<p class="field"><label>' . Escaper::html($challenge['label']) . '</label><br>' . $challenge['html'] . '</p>';
		}

		$html .= '<p><button type="submit">' . Escaper::html($this->t('blog.reaction.send')) . '</button></p>';

		return $html . '</form>';
	}

	/**
	 * Take a reaction.
	 *
	 * Stored as plain text and escaped on the way out, never as markup: a comment
	 * field that accepts HTML is a comment field that accepts a link farm, and the
	 * sanitiser is a stricter thing to have to trust than simply not allowing any.
	 *
	 * @param array<string,mixed> $post
	 * @return array{ok:bool,message:string,redirect:?string}
	 */
	public function accept(string $path, array $post, StorageDriver $storage, Urls $urls, Guard $guard): array
	{
		$slug = trim($path, '/');
		$stored = $storage->getModuleData('blog', 'post:' . $slug);

		if (!is_array($stored) || !($stored['published'] ?? false)) {
			return ['ok' => false, 'message' => 'form.error.start_again', 'redirect' => null];
		}

		$settings = self::settings($storage);

		if (!$settings['allow_reactions'] || !($stored['allow_reaction'] ?? true)) {
			return ['ok' => false, 'message' => 'blog.reaction.closed', 'redirect' => null];
		}

		$name = $this->plain($post['name'] ?? '', 80);
		$text = $this->plain($post['reaction'] ?? '', 3000);
		$website = $this->plain($post['website'] ?? '', 200);

		if ($name === '' || $text === '') {
			return ['ok' => false, 'message' => 'form.error.fill_it_in', 'redirect' => null];
		}

		if ($website !== '' && !preg_match('#^https?://#i', $website)) {
			$website = '';
		}

		$storage->setModuleData('blog', 'reaction:' . $slug . ':' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)), [
			'name' => $name,
			'website' => $website,
			'reaction' => $text,
			'posted_at' => gmdate('c'),
			// Moderated sites hold it back; the rest publish it. Either way the
			// status is written, so turning moderation on later does not leave
			// old reactions in an undefined state.
			'status' => $settings['moderate_reactions']
				? ReactionStatus::Pending->value
				: ReactionStatus::Approved->value,
		]);

		return [
			'ok' => true,
			'message' => $settings['moderate_reactions'] ? 'blog.reaction.waiting' : 'blog.reaction.posted',
			'redirect' => $urls->to('blog/' . $slug),
		];
	}

	/** Text, with the markup and the control characters taken out. */
	private function plain(mixed $value, int $length): string
	{
		$text = is_scalar($value) ? (string) $value : '';
		$text = strip_tags($text);
		$text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';

		return mb_substr(trim($text), 0, $length);
	}

	private function reactions(string $slug, array $post, StorageDriver $storage, array $settings, Urls $urls): string
	{
		// A post may be closed on its own, without closing the rest of the blog.
		// One thread going wrong is the usual reason, and shutting the whole blog
		// for it is a heavier response than anyone wants.
		if (!($post['allow_reaction'] ?? true)) {
			return '';
		}

		$moderated = $settings['moderate_reactions'];

		$reactions = array_filter(
			$storage->listModuleData('blog', 'reaction:' . $slug . ':'),
			static fn (mixed $reaction): bool => is_array($reaction)
				&& ReactionStatus::from_($reaction['status'] ?? null)->isVisible($moderated),
		);

		$out = '<section class="blog-reactions">';

		if ($reactions === []) {
			// No thread yet, but there is still a form: an empty section that
			// invites the first comment is the point of allowing them at all.
			return $out . $this->reactionForm($slug, $storage, $urls) . '</section>';
		}

		$out .= '<h2>'
			. Escaper::html($this->t('blog.reactions', ['count' => count($reactions)], count($reactions)))
			. '</h2>';

		foreach ($reactions as $reaction) {
			if (!is_array($reaction)) {
				continue;
			}

			$name = (string) ($reaction['name'] ?? '');
			$website = (string) ($reaction['website'] ?? '');

			$out .= '<article class="blog-reaction"><h3>';
			$out .= $website !== '' && Escaper::url($website) !== '#'
				// rel=nofollow because a comment field with a link in it is the
				// oldest spam vector on the web and these were imported unchecked.
				? '<a href="' . Escaper::html(Escaper::url($website)) . '" rel="nofollow noopener">' . Escaper::html($name) . '</a>'
				: Escaper::html($name);
			$out .= '</h3>';
			$out .= $this->time((string) ($reaction['posted_at'] ?? ''), $settings);
			$out .= '<div class="blog-reaction__body">' . (string) ($reaction['message'] ?? '') . '</div>';
			$out .= '</article>';
		}

		return $out . $this->reactionForm($slug, $storage, $urls) . '</section>';
	}

	// ---- pieces ---------------------------------------------------------

	private function byline(array $post, Urls $urls, StorageDriver $storage, array $settings): string
	{
		$out = '<p class="blog-post__meta">';
		$out .= $this->time((string) ($post['published_at'] ?? ''), $settings);

		$category = (string) ($post['category'] ?? '');
		if ($category !== '') {
			$title = (string) ($storage->getModuleData('blog', 'category:' . $category)['title'] ?? $category);
			$out .= ' <a class="blog-category" href="' . Escaper::html($urls->to('blog/category/' . $category)) . '">'
				. Escaper::html($title) . '</a>';
		}

		return $out . '</p>';
	}

	/** A <time> element, or nothing at all when the post has no date. */
	private function time(string $iso, array $settings): string
	{
		$machine = Dates::iso($iso);
		if ($machine === '') {
			return '';
		}

		$locale = $this->translator?->locale()->code ?? 'en';

		$shown = Dates::pattern($iso, $settings['post_date'], $locale);
		if ($settings['post_time'] !== '') {
			$shown .= ' ' . Dates::pattern($iso, $settings['post_time'], $locale);
		}

		return '<time datetime="' . Escaper::html($machine) . '">' . Escaper::html($shown) . '</time>';
	}

	private function pagination(int $current, int $pages, ?string $category, Urls $urls): string
	{
		if ($pages < 2) {
			return '';
		}

		$path = $category === null ? 'blog' : 'blog/category/' . $category;
		$out = '<nav class="blog-pagination">';

		if ($current > 1) {
			$out .= '<a rel="prev" href="' . Escaper::html($urls->toWith($path, ['p' => $current > 2 ? $current - 1 : null])) . '">'
				. Escaper::html($this->t('blog.newer')) . '</a>';
		}
		$out .= '<span class="blog-pagination__where">'
			. Escaper::html($this->t('blog.page_of', ['current' => $current, 'total' => $pages])) . '</span>';
		if ($current < $pages) {
			$out .= '<a rel="next" href="' . Escaper::html($urls->toWith($path, ['p' => $current + 1])) . '">'
				. Escaper::html($this->t('blog.older')) . '</a>';
		}

		return $out . '</nav>';
	}

	/**
	 * Everything up to the first paragraph break, so a summary keeps its markup
	 * rather than being a string cut in half. Cutting sanitised HTML by length
	 * is how you produce an unclosed tag that eats the rest of the layout.
	 */
	/**
	 * The address of the first picture in a post, or nothing.
	 *
	 * Deliberately only the src, and only from an <img> the sanitiser has already
	 * been over — a post's body is stored sanitised, so this is reading what is
	 * there rather than trusting what arrived.
	 */
	private function firstImage(string $html): string
	{
		if (preg_match('/<img\b[^>]*\bsrc="([^"]+)"/i', $html, $m) !== 1) {
			return '';
		}

		return $m[1];
	}

	private function excerpt(string $html, array $settings): string
	{
		// A character limit, cut on the tree rather than the string — see
		// Support\Excerpt for why substr() is not an option here. Zero means the
		// whole post, which is what 4.x meant by it.
		if ($settings['truncate_posts'] > 0) {
			return Excerpt::of($html, $settings['truncate_posts']);
		}

		// No limit set: the first paragraph, which keeps its markup intact.
		$end = stripos($html, '</p>');

		return $end === false ? $html : substr($html, 0, $end + 4);
	}

	private function plainExcerpt(string $html, int $length): string
	{
		$text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');

		return mb_strlen($text) <= $length ? $text : mb_substr($text, 0, $length - 1) . '…';
	}

	/**
	 * Posts, newest first. A post with no date sorts last rather than first:
	 * 4.x installs have a few of those and they are not the news.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function posts(StorageDriver $storage, bool $oldestFirst = false): array
	{
		$posts = [];
		foreach ($storage->listModuleData('blog', 'post:') as $key => $value) {
			if (!is_array($value)) {
				continue;
			}
			// A draft is not on the site. Absent means published: everything the
			// migrator wrote predates the setting, and treating those as drafts
			// would empty a migrated blog on the day of the move.
			if (!($value['published'] ?? true)) {
				continue;
			}

			$value['slug'] = substr($key, 5);
			$posts[] = $value;
		}

		usort($posts, static function (array $a, array $b) use ($oldestFirst): int {
			$left = isset($a['published_at']) ? (strtotime((string) $a['published_at']) ?: 0) : 0;
			$right = isset($b['published_at']) ? (strtotime((string) $b['published_at']) ?: 0) : 0;

			return $oldestFirst
				? [$left, $a['slug']] <=> [$right, $b['slug']]
				: [$right, $a['slug']] <=> [$left, $b['slug']];
		});

		return $posts;
	}

	/** @param array<string,string|int> $replacements */
}
