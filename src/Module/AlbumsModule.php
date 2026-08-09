<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\I18n\Translates;
use Pluck\I18n\Translator;
use Pluck\Security\Escaper;
use Pluck\Site\Search;
use Pluck\Site\SearchResult;
use Pluck\Site\Urls;
use Pluck\Storage\StorageDriver;

/**
 * Photo albums, on the site side.
 *
 * One thing changes from 4.x that is worth naming. There, album pictures lived
 * under data/settings and were served by albums_getimage.php — a PHP script
 * taking a filename from the query string, which is the shape of every path
 * traversal bug ever written. Here the pictures are ordinary files in media/,
 * served by the web server, and no PHP is involved in delivering them.
 */
final class AlbumsModule implements SiteModule
{
	use Translates;

	public function __construct(private readonly ?Translator $translator = null)
	{
	}

	public function name(): string
	{
		return 'albums';
	}

	public function mountPath(): string
	{
		return 'albums';
	}

	public function render(string $path, array $query, StorageDriver $storage, Urls $urls): ?ModuleView
	{
		return $path === ''
			? $this->index($storage, $urls)
			: $this->album($path, $storage, $urls);
	}

	/**
	 * One album's pictures, or the list of albums, for a page.
	 *
	 * Parameters: `album` for a particular one, `count` to cap how many pictures
	 * are shown. Naming an album that does not exist returns null rather than
	 * quietly showing a different one — a page that shows the wrong album is
	 * harder to notice than a page that shows none.
	 */
	public function embed(array $parameters, StorageDriver $storage, Urls $urls): ?string
	{
		$count = max(1, min(50, (int) ($parameters['count'] ?? 12)));
		$slug = $parameters['album'] ?? '';

		// [module:albums random=1] — one picture, different on each visit. From
		// one named album when there is one, otherwise from everything.
		if (($parameters['random'] ?? '') !== '' && ($parameters['random'] ?? '0') !== '0') {
			return $this->randomPicture($slug, $storage, $urls);
		}

		if ($slug === '') {
			$albums = $this->albums($storage);

			if ($albums === []) {
				return null;
			}

			$html = '<div class="album-embed"><ul class="album-list">';
			foreach (array_slice($albums, 0, $count, true) as $key => $album) {
				$cover = $this->images($key, $storage)[0] ?? null;
				$html .= '<li class="album-list__item"><a href="' . Escaper::html($urls->to('albums/' . $key)) . '">';
				if ($cover !== null) {
					$html .= '<img src="' . Escaper::html($urls->media((string) $cover['file'])) . '" alt="" loading="lazy">';
				}
				$html .= '<span class="album-list__title">' . Escaper::html((string) ($album['title'] ?? $key)) . '</span>';
				$html .= '</a></li>';
			}

			return $html . '</ul></div>';
		}

		if (!is_array($storage->getModuleData('albums', 'album:' . $slug))) {
			return null;
		}

		$images = array_slice($this->images($slug, $storage), 0, $count);
		if ($images === []) {
			return null;
		}

		$html = '<div class="album-embed album">';
		foreach ($images as $image) {
			$file = (string) ($image['file'] ?? '');
			if ($file === '') {
				continue;
			}
			$caption = (string) ($image['title'] ?? '');
			$html .= '<figure class="album__item"><a href="' . Escaper::html($urls->media($file)) . '">';
			$html .= '<img src="' . Escaper::html($urls->media($file)) . '" alt="' . Escaper::html($caption) . '" loading="lazy">';
			$html .= '</a></figure>';
		}
		$html .= '</div>';

		$html .= '<p class="album-embed__more"><a href="' . Escaper::html($urls->to('albums/' . $slug)) . '">'
			. Escaper::html($this->t('albums.view_album')) . '</a></p>';

		return $html;
	}

	/**
	 * Albums match on their name and description; a picture matches on its
	 * caption, and leads to the album it is in rather than to the file.
	 *
	 * @return list<SearchResult>
	 */
	public function search(string $query, StorageDriver $storage): array
	{
		$results = [];

		foreach ($this->albums($storage) as $slug => $album) {
			$title = (string) ($album['title'] ?? $slug);
			$description = Search::plain((string) ($album['description'] ?? ''));

			$captions = [];
			foreach ($this->images($slug, $storage) as $image) {
				$captions[] = (string) ($image['title'] ?? '');
				$captions[] = Search::plain((string) ($image['info'] ?? ''));
			}
			$text = trim(implode(' ', array_filter($captions)));

			$score = Search::score($query, $title, $description, $text);
			if ($score === 0) {
				continue;
			}

			$results[] = new SearchResult(
				title: $title,
				path: 'albums/' . $slug,
				snippet: Search::snippet($query, $description !== '' ? $description : $text),
				score: $score,
				kindKey: 'search.kind.album',
			);
		}

		return $results;
	}

	/**
	 * One picture, chosen fresh each time.
	 *
	 * Not cached on purpose: a "random" picture that is the same all day is the
	 * thing people report as broken, and the cost here is reading a list that was
	 * read anyway.
	 */
	private function randomPicture(string $slug, StorageDriver $storage, Urls $urls): ?string
	{
		$pool = [];

		foreach ($slug !== '' ? [$slug] : array_keys($this->albums($storage)) as $album) {
			foreach ($this->images((string) $album, $storage) as $image) {
				$file = (string) ($image['file'] ?? '');
				if ($file !== '') {
					$pool[] = ['album' => (string) $album, 'file' => $file, 'title' => (string) ($image['title'] ?? '')];
				}
			}
		}

		if ($pool === []) {
			return null;
		}

		$picked = $pool[random_int(0, count($pool) - 1)];

		return '<figure class="album-random">'
			. '<a href="' . Escaper::html($urls->to('albums/' . $picked['album'])) . '">'
			. '<img src="' . Escaper::html($urls->media($picked['file'])) . '" alt="' . Escaper::html($picked['title']) . '" loading="lazy">'
			. '</a>'
			. ($picked['title'] !== '' ? '<figcaption>' . Escaper::html($picked['title']) . '</figcaption>' : '')
			. '</figure>';
	}

	private function index(StorageDriver $storage, Urls $urls): ModuleView
	{
		$albums = $this->albums($storage);

		if ($albums === []) {
			return new ModuleView(
				title: $this->t('albums.title'),
				html: '<p class="albums-empty">' . Escaper::html($this->t('albums.none')) . '</p>',
			);
		}

		$html = '<ul class="album-list">';
		foreach ($albums as $slug => $album) {
			$images = $this->images($slug, $storage);
			$cover = $images[0] ?? null;

			$html .= '<li class="album-list__item"><a href="' . Escaper::html($urls->to('albums/' . $slug)) . '">';
			if ($cover !== null) {
				$html .= '<img src="' . Escaper::html($urls->media((string) $cover['file'])) . '" alt="" loading="lazy">';
			}
			$html .= '<span class="album-list__title">' . Escaper::html((string) ($album['title'] ?? $slug)) . '</span>';
			$html .= '<span class="album-list__count">'
				. Escaper::html($this->t('albums.count', ['count' => count($images)], count($images))) . '</span>';
			$html .= '</a></li>';
		}
		$html .= '</ul>';

		return new ModuleView(
			title: $this->t('albums.title'),
			html: $html,
			breadcrumbs: [['title' => $this->t('albums.title'), 'path' => 'albums']],
		);
	}

	/**
	 * An album's description, written by the albums-enhancements module in 4.x
	 * and editable in the admin since. Sanitised when it was saved, so it goes out
	 * as markup like page content does.
	 */
	private function albumDescription(array $album): string
	{
		$description = trim((string) ($album['description'] ?? ''));

		return $description === '' ? '' : '<div class="album-description">' . $description . '</div>';
	}

	private function album(string $slug, StorageDriver $storage, Urls $urls): ?ModuleView
	{
		$album = $storage->getModuleData('albums', 'album:' . $slug);
		if (!is_array($album)) {
			return null;
		}

		$images = $this->images($slug, $storage);

		$html = $this->albumDescription($album);
		$html .= '<div class="album">';
		if ($images === []) {
			$html .= '<p class="albums-empty">' . Escaper::html($this->t('albums.empty')) . '</p>';
		}

		foreach ($images as $image) {
			$file = (string) ($image['file'] ?? '');
			if ($file === '') {
				continue;
			}

			$caption = (string) ($image['title'] ?? '');
			$html .= '<figure class="album__item">';
			$html .= '<a href="' . Escaper::html($urls->media($file)) . '">';
			$html .= '<img src="' . Escaper::html($urls->media($file)) . '" alt="' . Escaper::html($caption) . '" loading="lazy">';
			$html .= '</a>';

			$info = (string) ($image['info'] ?? '');
			if ($caption !== '' || $info !== '') {
				$html .= '<figcaption>';
				$html .= $caption !== '' ? '<strong>' . Escaper::html($caption) . '</strong>' : '';
				// Sanitised on the way in.
				$html .= $info !== '' ? ' ' . $info : '';
				$html .= '</figcaption>';
			}

			$html .= '</figure>';
		}
		$html .= '</div>';

		return new ModuleView(
			title: (string) ($album['title'] ?? $slug),
			html: $html,
			breadcrumbs: [['title' => $this->t('albums.title'), 'path' => 'albums']],
			canonical: $urls->to('albums/' . $slug),
		);
	}

	/** @return array<string,array<string,mixed>> slug => album, in their own order */
	private function albums(StorageDriver $storage): array
	{
		$albums = [];
		foreach ($storage->listModuleData('albums', 'album:') as $key => $value) {
			if (is_array($value)) {
				$albums[substr($key, 6)] = $value;
			}
		}

		uasort($albums, static fn (array $a, array $b): int => [(int) ($a['order'] ?? 0), (string) ($a['title'] ?? '')]
			<=> [(int) ($b['order'] ?? 0), (string) ($b['title'] ?? '')]);

		return $albums;
	}

	/** @return list<array<string,mixed>> */
	private function images(string $slug, StorageDriver $storage): array
	{
		$images = [];
		foreach ($storage->listModuleData('albums', 'image:' . $slug . ':') as $value) {
			if (is_array($value)) {
				$images[] = $value;
			}
		}

		return $images;
	}

	/** @param array<string,string|int> $replacements */
}
