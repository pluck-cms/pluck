<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\Media\MediaResult;
use Pluck\Security\Sanitizer;
use Pluck\Support\Slug;

/**
 * Editing photo albums.
 *
 * Two ways to put a picture in an album, and the difference matters. Uploading
 * adds a new file to the shared media library and records that this module put it
 * there, which is what allows removing it again. Picking takes something already
 * in the library — a photo from another album, or an image a page uses — and
 * references it without copying.
 *
 * Removing an image from an album therefore means two different things, and the
 * screen says which: a picked file is only unlinked, because something else is
 * probably still using it, while an uploaded one can be deleted outright.
 */
class AlbumsAdminController extends ModuleController
{
	public function index(): never
	{
		$albums = [];
		foreach ($this->albums() as $slug => $album) {
			$images = $this->images($slug);
			$albums[$slug] = [
				'title' => (string) ($album['title'] ?? $slug),
				'description' => (string) ($album['description'] ?? ''),
				'count' => count($images),
				'cover' => $images === [] ? null : (string) (reset($images)['file'] ?? ''),
			];
		}

		$this->render('admin/albums/index', [
			'title' => $this->t('albums.title.albums'),
			'albums' => $albums,
		]);
	}

	public function edit(): never
	{
		$slug = Slug::path($this->c->request->query('slug', ''));
		$album = $slug === '' ? null : $this->c->get('album:' . $slug);

		if (!is_array($album)) {
			$this->c->flash->stop($this->t('albums.flash.album_gone'));
			$this->back('module.albums.index');
		}

		$images = [];
		foreach ($this->images($slug) as $key => $image) {
			$file = (string) ($image['file'] ?? '');
			$images[] = [
				'key' => $key,
				'id' => substr($key, strrpos($key, ':') + 1),
				'file' => $file,
				'title' => (string) ($image['title'] ?? ''),
				'info' => (string) ($image['info'] ?? ''),
				// Only a file this module uploaded may be deleted from disk. One
				// that was picked out of the library belongs to whoever put it
				// there, and is very likely still in use elsewhere.
				'owned' => $this->c->ownsMedia($file),
			];
		}

		$this->render('admin/albums/form', [
			'title' => (string) ($album['title'] ?? $slug),
			'slug' => $slug,
			'album' => $album,
			'images' => $images,
			'library' => $this->unusedMedia(),
		]);
	}

	public function save(): never
	{
		$request = $this->c->request;

		$title = trim($request->post('title'));
		if ($title === '') {
			$this->c->flash->stop($this->t('albums.flash.album_needs_title'));
			$this->back('module.albums.index');
		}

		$original = Slug::path($request->post('original', ''));
		$existing = $original !== '' ? $this->c->get('album:' . $original) : null;

		$slug = $original !== '' ? $original : Slug::unique(
			Slug::make($title, 'album'),
			fn (string $candidate): bool => $this->c->get('album:' . $candidate) !== null,
		);

		// The description accepts HTML, so it goes through the same sanitiser as
		// a page body. An album description is a place people paste from Word.
		$description = (new Sanitizer())->inspect($request->post('description', ''));

		$this->c->set('album:' . $slug, [
			'title' => $title,
			'description' => $description->html,
			'order' => (int) ($existing['order'] ?? count($this->albums())),
			'legacy_seoname' => $existing['legacy_seoname'] ?? null,
		]);

		if ($description->removedAnything()) {
			$this->c->flash->warn($this->t('albums.flash.description_cleaned', ['what' => $description->summary()]));
		}

		$this->c->flash->ok($this->t('albums.flash.album_saved'));
		$this->back('module.albums.edit', ['slug' => $slug]);
	}

	public function delete(): never
	{
		$slug = Slug::path($this->c->request->post('slug', ''));
		if ($slug === '' || $this->c->get('album:' . $slug) === null) {
			$this->c->flash->stop($this->t('albums.flash.album_gone'));
			$this->back('module.albums.index');
		}

		// The album goes; the pictures stay. They live in the shared media
		// library, where a page may well be using one, and deleting a folder of
		// photographs because someone tidied up an album is not a trade anyone
		// would make on purpose.
		$this->c->transaction(function () use ($slug): void {
			foreach ($this->images($slug) as $key => $ignored) {
				$this->c->delete($key);
			}
			$this->c->delete('album:' . $slug);
		});

		$this->c->flash->ok($this->t('albums.flash.album_deleted'));
		$this->back('module.albums.index');
	}

	public function move(): never
	{
		$slug = Slug::path($this->c->request->post('slug', ''));
		$this->reorder($this->albums(), 'album:', $slug, $this->c->request->post('direction', ''));

		$this->back('module.albums.index');
	}

	// ---- images ---------------------------------------------------------

	public function addImage(): never
	{
		$slug = $this->albumFromPost();
		$file = $this->c->request->file('file');

		if ($file === null) {
			$this->c->flash->stop($this->t('albums.flash.choose_a_picture'));
			$this->back('module.albums.edit', ['slug' => $slug]);
		}

		$result = $this->c->addMedia($file);

		if (!$result->ok) {
			$this->c->flash->stop(match ($result->reason) {
				MediaResult::TOO_LARGE, MediaResult::TOO_LARGE_FOR_SERVER => $this->t('albums.flash.picture_too_large'),
				MediaResult::EXTENSION_REFUSED, MediaResult::NO_EXTENSION => $this->t('albums.flash.not_a_picture'),
				MediaResult::CONTENTS_DISAGREE, MediaResult::UNREADABLE_IMAGE => $this->t('albums.flash.picture_unreadable'),
				default => $this->t('albums.flash.upload_failed'),
			});
			$this->back('module.albums.edit', ['slug' => $slug]);
		}

		$this->attach($slug, $result->name, pathinfo($result->name, PATHINFO_FILENAME));

		$this->c->flash->ok($this->t('albums.flash.picture_added'));
		$this->back('module.albums.edit', ['slug' => $slug]);
	}

	/** Reference something already in the media library, without copying it. */
	public function pickImage(): never
	{
		$slug = $this->albumFromPost();
		$name = basename($this->c->request->post('file', ''));

		if ($name === '' || !in_array($name, $this->c->media(), true)) {
			$this->c->flash->stop($this->t('albums.flash.no_such_picture'));
			$this->back('module.albums.edit', ['slug' => $slug]);
		}

		$this->attach($slug, $name, pathinfo($name, PATHINFO_FILENAME));

		$this->c->flash->ok($this->t('albums.flash.picture_added'));
		$this->back('module.albums.edit', ['slug' => $slug]);
	}

	public function saveImage(): never
	{
		$slug = $this->albumFromPost();
		$key = $this->imageKey($slug);

		$image = $this->c->get($key);
		if (!is_array($image)) {
			$this->c->flash->stop($this->t('albums.flash.no_such_picture'));
			$this->back('module.albums.edit', ['slug' => $slug]);
		}

		$image['title'] = mb_substr(trim($this->c->request->post('title')), 0, 200);
		$image['info'] = (new Sanitizer())->clean($this->c->request->post('info', ''));
		$this->c->set($key, $image);

		$this->c->flash->ok($this->t('albums.flash.caption_saved'));
		$this->back('module.albums.edit', ['slug' => $slug]);
	}

	public function removeImage(): never
	{
		$slug = $this->albumFromPost();
		$key = $this->imageKey($slug);

		$image = $this->c->get($key);
		if (!is_array($image)) {
			$this->c->flash->stop($this->t('albums.flash.no_such_picture'));
			$this->back('module.albums.edit', ['slug' => $slug]);
		}

		$file = (string) ($image['file'] ?? '');
		$alsoDelete = $this->c->request->postBool('delete_file');

		$this->c->delete($key);

		// Deleting the file is offered only for one this module uploaded, and only
		// when no other album still points at it. A picked file is never deleted
		// here at all: something else put it in the library.
		if ($alsoDelete && $this->c->ownsMedia($file) && !$this->fileInUse($file)) {
			$this->c->removeMedia($file);
			$this->c->flash->ok($this->t('albums.flash.picture_removed_and_deleted'));
			$this->back('module.albums.edit', ['slug' => $slug]);
		}

		$this->c->flash->ok($this->t('albums.flash.picture_removed'));
		$this->back('module.albums.edit', ['slug' => $slug]);
	}

	public function moveImage(): never
	{
		$slug = $this->albumFromPost();
		$key = $this->imageKey($slug);

		$images = $this->images($slug);
		$keys = array_keys($images);
		$at = array_search($key, $keys, true);

		if ($at === false) {
			$this->back('module.albums.edit', ['slug' => $slug]);
		}

		$to = $this->c->request->post('direction') === 'up' ? $at - 1 : $at + 1;
		if ($to < 0 || $to >= count($keys)) {
			$this->back('module.albums.edit', ['slug' => $slug]);
		}

		// Positions are renumbered rather than the keys being swapped. The key
		// carries the order, so swapping content is the only move that does not
		// leave two images claiming the same place.
		$values = array_values($images);
		[$values[$at], $values[$to]] = [$values[$to], $values[$at]];

		$this->c->transaction(function () use ($slug, $values): void {
			foreach ($values as $index => $image) {
				$this->c->set(sprintf('image:%s:%04d', $slug, $index), $image);
			}
		});

		$this->back('module.albums.edit', ['slug' => $slug]);
	}

	// ---- internals ------------------------------------------------------

	private function attach(string $slug, string $file, string $title): void
	{
		$next = count($this->images($slug));
		$this->c->set(sprintf('image:%s:%04d', $slug, $next), [
			'file' => $file,
			'title' => $title,
			'info' => '',
		]);
	}

	/** @return array<string,array<string,mixed>> slug => album, in their own order */
	private function albums(): array
	{
		$albums = [];
		foreach ($this->c->list('album:') as $key => $value) {
			if (is_array($value)) {
				$albums[substr($key, 6)] = $value;
			}
		}

		uasort($albums, static fn (array $a, array $b): int => [(int) ($a['order'] ?? 0), (string) ($a['title'] ?? '')]
			<=> [(int) ($b['order'] ?? 0), (string) ($b['title'] ?? '')]);

		return $albums;
	}

	/** @return array<string,array<string,mixed>> key => image, in album order */
	private function images(string $slug): array
	{
		$images = [];
		foreach ($this->c->list('image:' . $slug . ':') as $key => $value) {
			if (is_array($value)) {
				$images[$key] = $value;
			}
		}
		ksort($images);

		return $images;
	}

	/**
	 * Files in the library that no album is using yet, so the picker does not
	 * offer the same photograph twice.
	 *
	 * @return list<string>
	 */
	private function unusedMedia(): array
	{
		$used = [];
		foreach ($this->c->list('image:') as $image) {
			if (is_array($image) && ($image['file'] ?? '') !== '') {
				$used[(string) $image['file']] = true;
			}
		}

		return array_values(array_filter(
			$this->c->media(),
			static fn (string $name): bool => !isset($used[$name]),
		));
	}

	private function fileInUse(string $file): bool
	{
		foreach ($this->c->list('image:') as $image) {
			if (is_array($image) && ($image['file'] ?? '') === $file) {
				return true;
			}
		}

		return false;
	}

	/** @param array<string,array<string,mixed>> $items */
	private function reorder(array $items, string $prefix, string $slug, string $direction): void
	{
		$keys = array_keys($items);
		$at = array_search($slug, $keys, true);

		if ($at === false) {
			return;
		}

		$to = $direction === 'up' ? $at - 1 : $at + 1;
		if ($to < 0 || $to >= count($keys)) {
			return;
		}

		[$keys[$at], $keys[$to]] = [$keys[$to], $keys[$at]];

		$this->c->transaction(function () use ($keys, $items, $prefix): void {
			foreach ($keys as $index => $key) {
				$this->c->set($prefix . $key, array_merge($items[$key], ['order' => $index]));
			}
		});
	}

	private function albumFromPost(): string
	{
		$slug = Slug::path($this->c->request->post('album', ''));

		if ($slug === '' || $this->c->get('album:' . $slug) === null) {
			$this->c->flash->stop($this->t('albums.flash.album_gone'));
			$this->back('module.albums.index');
		}

		return $slug;
	}

	/**
	 * An image key, rebuilt from an album slug and an id rather than taken from
	 * the form, so a tampered request cannot name an arbitrary key in this
	 * module's data and hand it to delete().
	 */
	private function imageKey(string $slug): string
	{
		$id = preg_replace('/[^0-9]/', '', $this->c->request->post('id', '')) ?? '';

		if ($id === '') {
			$this->c->flash->stop($this->t('albums.flash.no_such_picture'));
			$this->back('module.albums.edit', ['slug' => $slug]);
		}

		return 'image:' . $slug . ':' . $id;
	}
}
