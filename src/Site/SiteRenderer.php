<?php
declare(strict_types=1);

namespace Pluck\Site;

use Pluck\I18n\Translator;
use Pluck\Model\Page;
use Pluck\Security\Escaper;
use Pluck\Module\ModuleRegistry;
use Pluck\Module\ModuleView;
use Pluck\Security\Csp;
use Pluck\Security\Csrf;
use Pluck\Storage\StorageDriver;
use Pluck\Theme\Theme;
use Pluck\Theme\ThemeParameters;
use Pluck\View\Raw;
use Pluck\View\View;

/**
 * Turns a resolved request into a page of HTML, through the active theme.
 *
 * The theme decides layout and nothing else. It receives values that are already
 * escaped or already sanitised, and it has no way to reach storage, the session
 * or the request — a theme is markup with holes in it, not a program with access
 * to the install. That constraint is what makes it safe to let someone install a
 * theme they downloaded.
 */
final class SiteRenderer
{
	public function __construct(
		private readonly Theme $theme,
		private readonly StorageDriver $storage,
		private readonly Urls $urls,
		private readonly Csrf $csrf,
		private readonly Csp $csp,
		private readonly ?Translator $translator = null,
		private readonly ?ModuleRegistry $modules = null,
	) {
	}

	/**
	 * A page that is being previewed and has not been saved.
	 *
	 * Themes that build themselves from the menu — the bundled one-pager stacks
	 * every top-level page into one document — read their content from storage,
	 * not from whatever page was handed to page(). Previewing an unsaved change in
	 * such a theme therefore showed the *saved* version, which is the one thing a
	 * preview must not do.
	 *
	 * So the unsaved page is substituted into the menu as well. Only in memory,
	 * and only for the length of the request.
	 */
	public function previewing(Page $page): void
	{
		$this->preview = $page;
	}

	private ?Page $preview = null;

	public function page(Page $page): string
	{
		return $this->render(
			template: $this->theme->pick('page'),
			title: $page->title,
			data: [
				'page' => $page,
				// Markers are expanded here rather than on save, so a page shows
				// what the module holds now rather than what it held when the page
				// was last edited.
				'content' => new Raw($this->expand($page->content)),
				'description' => $page->description,
				'keywords' => $page->keywords,
			],
			activePath: $page->path,
		);
	}

	public function module(ModuleView $module, string $activePath): string
	{
		return $this->render(
			template: $this->theme->pick('module', 'page'),
			title: $module->title,
			data: [
				'page' => null,
				/*
				 * Through absolute() as well, and not only expand().
				 *
				 * A page's content was rewritten and a module's was not, so a blog
				 * post written with `media/photo.jpg` — which is what the editor
				 * produces — was a 404 on /blog/<post> while the same picture on a
				 * page worked. The posts are the site on a blog, so this was every
				 * picture on it.
				 *
				 * Not expand(): a module's output is already rendered, and running
				 * embeds over it would let a post's text contain a marker that the
				 * module never meant to expand.
				 */
				'content' => new Raw($this->absolute($module->html)),
				'description' => $module->meta['description'] ?? '',
				'keywords' => $module->meta['keywords'] ?? '',
				'canonical' => $module->canonical,
				'moduleBreadcrumbs' => $module->breadcrumbs,
			],
			activePath: $activePath,
		);
	}

	/**
	 * The search page.
	 *
	 * @param list<SearchResult> $results
	 */
	public function search(string $query, array $results): string
	{
		return $this->render(
			template: $this->theme->pick('search', 'page'),
			title: $this->translator?->get('search.title') ?? 'Search',
			data: [
				'page' => null,
				'content' => new Raw(''),
				'query' => $query,
				'results' => $results,
				// A search page is a different set of results for every visitor and
				// has nothing worth indexing; asking to be left out keeps a site's
				// own search results from competing with its pages.
				'noindex' => true,
			],
			activePath: '',
		);
	}

	/**
	 * A site that has been installed and not yet written.
	 *
	 * Answered 200 rather than 404, because nothing is missing: "page not found"
	 * on a fresh install reads like something is broken and sends somebody
	 * looking for what they did wrong.
	 *
	 * Rendered through the theme like everything else, so it is the site's own
	 * empty state rather than a screen bolted on beside it.
	 */
	public function emptySite(): string
	{
		$title = $this->translator?->get('site.empty.title') ?? 'Nothing here yet';

		/*
		 * The same shape notFound() uses: a null page and a Raw body.
		 *
		 * Not a synthesised Page — a theme that reads $page->content is one thing,
		 * a theme that does something else with a Page is another, and this has to
		 * work in every theme somebody has written.
		 */
		return $this->render(
			template: $this->theme->pick('404'),
			title: $title,
			data: [
				'page' => null,
				'content' => new Raw(
					'<p>' . Escaper::html($this->translator?->get('site.empty.body') ?? '') . '</p>'
					. '<p><a href="' . Escaper::html($this->urls->admin()) . '">'
					. Escaper::html($this->translator?->get('site.empty.sign_in') ?? '')
					. '</a></p>',
				),
				'description' => '',
				'keywords' => '',
				'requestedPath' => '',
			],
			activePath: '',
		);
	}

	public function notFound(string $requestedPath): string
	{
		return $this->render(
			template: $this->theme->pick('404'),
			title: $this->translator?->get('site.page_not_found') ?? 'Page not found',
			data: [
				'page' => null,
				'content' => new Raw(
					'<p>' . Escaper::html($this->translator?->get('site.page_not_found_body') ?? '') . '</p>',
				),
				'description' => '',
				'keywords' => '',
				'requestedPath' => $requestedPath,
			],
			activePath: '',
		);
	}

	private function expand(string $html): string
	{
		$html = $this->modules === null
			? $html
			: (new Embed($this->modules, $this->storage, $this->urls))->expand($html);

		return $this->absolute($html);
	}

	/**
	 * Relative addresses in page content, made relative to the install instead.
	 *
	 * The editor writes `media/photo.jpg`, which is right when the browser's idea
	 * of "here" is the install — with `?page=x`, or for a page at the top level.
	 * It is wrong the moment a page is nested and readable addresses are on:
	 * on /de-club/de-11-kroegentocht the browser looks in
	 * /de-club/media/photo.jpg, and every picture on the page is a 404.
	 *
	 * The obvious fix is a `<base>` in the layout, and it is a trap: `<base>` also
	 * changes what `#anchor` means, so every in-page link — including the skip
	 * link — starts navigating to the front page. Rewriting here fixes it for
	 * every theme without that.
	 *
	 * Left alone: anything with a scheme, anything protocol-relative, anything
	 * already rooted at /, fragments, mailto: and tel:.
	 */
	private function absolute(string $html): string
	{
		$base = $this->urls->base();

		return (string) preg_replace_callback(
			'/\b(src|href)="([^"]*)"/i',
			static function (array $m) use ($base): string {
				$value = $m[2];

				if (
					$value === ''
					|| str_starts_with($value, '/')
					|| str_starts_with($value, '#')
					|| str_starts_with($value, '?')
					|| preg_match('~^[a-z][a-z0-9+.-]*:~i', $value) === 1
				) {
					return $m[0];
				}

				return $m[1] . '="' . $base . ltrim($value, './') . '"';
			},
			$html,
		) ?: $html;
	}

	/**
	 * The page list, with the unsaved page standing in for its saved self.
	 *
	 * @param list<Page> $pages
	 * @return list<Page>
	 */
	private function withPreview(array $pages): array
	{
		if ($this->preview === null) {
			return $pages;
		}

		$replaced = false;

		foreach ($pages as $index => $page) {
			if ($page->path === $this->preview->path) {
				$pages[$index] = $this->preview;
				$replaced = true;
			}
		}

		// A page being written for the first time is not in the list at all, and
		// belongs at the end where a new page would go.
		if (!$replaced) {
			$pages[] = $this->preview;
		}

		return $pages;
	}

	/** @param array<string,mixed> $data */
	private function render(string $template, string $title, array $data, string $activePath): string
	{
		$view = new View($this->theme->templateDir(), $this->csrf, $this->csp, $this->translator);

		/*
		 * Embeds are expanded in every page, not only the one being rendered.
		 *
		 * A theme that stacks pages — the bundled one-pager puts every top-level
		 * page into a single document — prints $item->page->content directly, and
		 * that had never been through Embed. So [module:blog] appeared on the page
		 * as those words, which is the marker doing exactly nothing.
		 *
		 * Expand() returns immediately when there is no marker in the text, so on
		 * a theme that does not stack anything this costs a strpos per page.
		 */
		$pages = array_map(
			function (Page $page): Page {
				$page->content = $this->expand($page->content);

				return $page;
			},
			$this->storage->allPages(includeHidden: false),
		);
		$siteTitle = (string) $this->storage->getSetting('site_title', 'Pluck');

		// Shared values are global to every template and win over per-render data
		// in View::partial(), so only things that are genuinely the same for the
		// whole request belong here.
		$shared = [
			'urls' => $this->urls,
			'menu' => new Menu($this->withPreview($pages), $activePath),
			'trail' => Menu::trail($this->withPreview($pages), $activePath),
			'siteTitle' => $siteTitle,
			'theme' => $this->theme,
			/*
			 * What the site filled in for this theme's parameters.
			 *
			 * Always the full declared set, with defaults where nothing was
			 * typed, so a template can read $params['x'] without checking whether
			 * anybody has been to the settings screen yet.
			 */
			'params' => (new ThemeParameters($this->storage))->values($this->theme),
			'themeAssets' => $this->urls->asset('themes/' . $this->theme->name . '/assets'),
			'activePath' => $activePath,
			'searchEnabled' => (bool) $this->storage->getSetting('search_enabled', false),
			// Branding a theme can use without being rewritten. A theme that wants
			// none of it simply does not print them.
			'logo' => (string) $this->storage->getSetting('site_logo', ''),
			'tagline' => (string) $this->storage->getSetting('site_tagline', ''),
			'siteDescription' => (string) $this->storage->getSetting('site_description', ''),
			// So a theme does not become a second place to keep an address that is
			// already in Settings and already used by the contact form.
			'contactEmail' => (string) $this->storage->getSetting('contact_email', ''),
			'title' => $title,
			// The window title, assembled here so every template agrees on it and
			// a theme that forgets does not end up titled "Pluck".
			'documentTitle' => $title === '' || $title === $siteTitle ? $siteTitle : $title . ' - ' . $siteTitle,
		];

		foreach ($shared as $key => $value) {
			$view->share($key, $value);
		}

		// Defaults go on the right: `+` keeps the left-hand value, so anything the
		// caller actually passed survives and the rest gets a definition. A theme
		// template must never have to guard against an undefined variable.
		$data += [
			'query' => '',
			'results' => [],
			'noindex' => false,
			'canonical' => null,
			'moduleBreadcrumbs' => [],
			'requestedPath' => '',
			'description' => '',
			'keywords' => '',
			'page' => null,
		];

		$body = $view->partial($template, $data);

		return $view->partial('layout', ['content' => new Raw($body)] + $data);
	}
}
