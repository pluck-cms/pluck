<?php
declare(strict_types=1);

namespace Pluck\Migrate;

use RuntimeException;

/**
 * A Pluck 4.x installation on disk, read as data.
 *
 * Read-only throughout: the old tree is never written to, so a migration that
 * goes wrong costs time and nothing else. Everything goes through LegacyFile, so
 * no file from the old install is ever included.
 *
 * Layout this understands (4.6 through 4.7.21):
 *   data/settings/pages/<n>.<seoname>.php        a page
 *   data/settings/pages/<parent>/<n>.<seo>.php   a sub-page, folder named after the parent's seoname
 *   data/settings/options.php                    $sitetitle, $email
 *   data/settings/pass.php                       $ww  (unsalted sha512)
 *   data/settings/langpref.php                   $langpref
 *   data/settings/themepref.php                  $themepref
 *   data/settings/modules/blog/posts/<n>.<seo>.php
 *   data/settings/modules/blog/categories/<seo>.php
 *   data/settings/modules/albums/...
 *   files/, images/                              uploads
 */
final class LegacySite
{
	private readonly LegacyFile $reader;

	public function __construct(private readonly string $root)
	{
		if (!is_dir($root)) {
			throw new RuntimeException("No such directory: {$root}");
		}

		$this->reader = new LegacyFile();
	}

	/** A quick sanity check before anything else happens. */
	public function looksLikePluck(): bool
	{
		return is_dir($this->root . '/data/settings')
			&& (is_dir($this->pagesDir()) || is_file($this->root . '/data/settings/options.php'));
	}

	public function version(): ?string
	{
		$security = $this->root . '/data/inc/security.php';
		if (!is_file($security)) {
			return null;
		}

		// Read the constant out of the source rather than defining it.
		if (preg_match("/define\(\s*'PLUCK_VERSION'\s*,\s*'([^']+)'/", (string) file_get_contents($security), $m) === 1) {
			return $m[1];
		}

		return null;
	}

	/** @return list<string> */
	public function notes(): array
	{
		return $this->reader->notes();
	}

	/**
	 * Every page, depth first, in menu order.
	 *
	 * @return list<LegacyPage>
	 */
	public function pages(): array
	{
		$collected = [];
		$this->collectPages('', null, $collected);

		return $collected;
	}

	/**
	 * Every blog post, oldest first, with its reactions attached.
	 *
	 * Post files are named `<order>.<seoname>.php` like pages are, but the number
	 * is a display position the module rewrites on every save rather than
	 * anything stable, so the timestamp inside decides the order here. Reactions
	 * live in a directory named after the post's seoname, one numbered file each.
	 *
	 * @return list<LegacyPost>
	 */
	public function posts(): array
	{
		$dir = $this->root . '/data/settings/modules/blog/posts';
		if (!is_dir($dir)) {
			return [];
		}

		$posts = [];
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..' || !str_ends_with($entry, '.php')) {
				continue;
			}

			$file = $dir . '/' . $entry;
			$stem = substr($entry, 0, -4);
			$dot = strpos($stem, '.');
			if ($dot === false || preg_match('/^\d+$/', substr($stem, 0, $dot)) !== 1) {
				$posts[] = LegacyPost::unreadable($file, 'the filename is not "<number>.<name>.php"');
				continue;
			}

			$order = (int) substr($stem, 0, $dot);
			$seoname = substr($stem, $dot + 1);
			if ($seoname === '') {
				$posts[] = LegacyPost::unreadable($file, 'the filename has no post name');
				continue;
			}

			$values = $this->reader->read($file);

			$posts[] = new LegacyPost(
				seoname: $seoname,
				order: $order,
				title: (string) ($values['post_title'] ?? $seoname),
				content: (string) ($values['post_content'] ?? ''),
				category: (string) ($values['post_category'] ?? ''),
				time: (int) ($values['post_time'] ?? 0),
				allowReaction: !array_key_exists('allow_reaction', $values) || (int) $values['allow_reaction'] === 1,
				reactions: $this->reactions($dir . '/' . $seoname),
				sourceFile: $file,
			);
		}

		// Oldest first, so import order matches publication order. Posts without a
		// usable timestamp sort last rather than landing in 1970.
		usort($posts, static function (LegacyPost $a, LegacyPost $b): int {
			$left = $a->time > 0 ? $a->time : PHP_INT_MAX;
			$right = $b->time > 0 ? $b->time : PHP_INT_MAX;

			return [$left, $a->seoname] <=> [$right, $b->seoname];
		});

		return $posts;
	}

	/**
	 * Blog categories, keyed by their seoname. A post refers to its category by
	 * seoname, so this is what turns that reference back into a title.
	 *
	 * @return array<string,string> seoname => title
	 */
	public function postCategories(): array
	{
		$dir = $this->root . '/data/settings/modules/blog/categories';
		if (!is_dir($dir)) {
			return [];
		}

		$categories = [];
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..' || !str_ends_with($entry, '.php')) {
				continue;
			}

			$seoname = substr($entry, 0, -4);
			$values = $this->reader->read($dir . '/' . $entry);
			$categories[$seoname] = (string) ($values['category_title'] ?? $seoname);
		}

		ksort($categories);

		return $categories;
	}

	/**
	 * Photo albums, with their images.
	 *
	 * An album is a file `<seoname>.php` holding only a title, next to a
	 * directory of the same name holding the pictures. An image contributes two
	 * files: `<n>.<name>.<ext>.php` with the caption and `<name>.<ext>` with the
	 * picture itself.
	 *
	 * @return list<LegacyAlbum>
	 */
	public function albums(): array
	{
		$dir = $this->root . '/data/settings/modules/albums';
		if (!is_dir($dir)) {
			return [];
		}

		$albums = [];
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..' || !str_ends_with($entry, '.php')) {
				continue;
			}

			$file = $dir . '/' . $entry;
			$seoname = substr($entry, 0, -4);
			if ($seoname === '') {
				$albums[] = LegacyAlbum::unreadable($file, 'the album has no name');
				continue;
			}

			$values = $this->reader->read($file);

			$albums[] = new LegacyAlbum(
				seoname: $seoname,
				title: (string) ($values['album_name'] ?? $seoname),
				description: $this->albumDescription($dir . '/' . $seoname),
				images: $this->albumImages($dir . '/' . $seoname, 'data/settings/modules/albums/' . $seoname),
				sourceFile: $file,
			);
		}

		usort($albums, static fn (LegacyAlbum $a, LegacyAlbum $b): int => strnatcasecmp($a->seoname, $b->seoname));

		return $albums;
	}

	/** @return array<string,string> the settings files that were found, flattened */
	public function settings(): array
	{
		$out = [];

		foreach (['options', 'langpref', 'themepref'] as $name) {
			$file = $this->root . '/data/settings/' . $name . '.php';
			if (!is_file($file)) {
				continue;
			}
			foreach ($this->reader->read($file) as $key => $value) {
				if (is_scalar($value)) {
					$out[$key] = (string) $value;
				}
			}
		}

		return $out;
	}

	/**
	 * A module's own settings, from `data/settings/<module>.settings.php`.
	 *
	 * @return array<string,string>
	 */
	public function moduleSettings(string $module): array
	{
		$file = $this->root . '/data/settings/' . $module . '.settings.php';
		if (!is_file($file)) {
			return [];
		}

		$out = [];
		foreach ($this->reader->read($file) as $key => $value) {
			if (is_scalar($value)) {
				$out[$key] = (string) $value;
			}
		}

		return $out;
	}

	/**
	 * Whether a password file exists. The hash itself is deliberately not
	 * returned: 4.x stored an unsalted sha512 of the password, which is not worth
	 * carrying into a new install even as a starting point.
	 */
	public function hasPassword(): bool
	{
		return is_file($this->root . '/data/settings/pass.php');
	}

	/**
	 * Uploads, as relative paths under the old root.
	 *
	 * The albums tree is included because that is where 4.x put album pictures,
	 * but two things in it are not uploads. `thumb/` holds scaled copies the
	 * module generates, which v5 has no use for; and the `.php` files next to
	 * each picture are the module's own captions, which the album migration
	 * reads directly. Both are skipped by name rather than by extension, so a
	 * `.php` file that is *not* recognised album metadata still comes through
	 * here and still gets refused loudly — an old install with a shell dropped
	 * in the photo folder is precisely the thing this sweep exists to surface.
	 *
	 * @return list<string>
	 */
	public function uploads(): array
	{
		$found = [];
		$albumsRoot = 'data/settings/modules/albums';
		$metadata = $this->albumMetadataFiles();

		foreach (['files', 'images', $albumsRoot] as $relative) {
			$dir = $this->root . '/' . $relative;
			if (!is_dir($dir)) {
				continue;
			}
			$this->collectFiles($dir, $relative, $found, 0, $relative === $albumsRoot ? $metadata : []);
		}

		sort($found);

		return $found;
	}

	/**
	 * Relative paths of every file under the albums tree that is the albums
	 * module's own bookkeeping rather than a picture.
	 *
	 * @return array<string,true> path => true, for cheap lookup
	 */
	private function albumMetadataFiles(): array
	{
		$root = 'data/settings/modules/albums';
		$dir = $this->root . '/' . $root;
		if (!is_dir($dir)) {
			return [];
		}

		$known = [];
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			// The album itself: "<seoname>.php" holding nothing but a title.
			if (str_ends_with($entry, '.php') && is_file($dir . '/' . $entry)) {
				$known[$root . '/' . $entry] = true;
				continue;
			}

			if (!is_dir($dir . '/' . $entry) || is_link($dir . '/' . $entry)) {
				continue;
			}

			foreach (scandir($dir . '/' . $entry) ?: [] as $file) {
				// One caption file per picture: "<order>.<name>.<ext>.php".
				if (str_ends_with($file, '.php') && count(explode('.', $file)) === 4) {
					$known[$root . '/' . $entry . '/' . $file] = true;
				}

				// The albums-enhancements module's description file. Without this it
				// was reported as an executable somebody had smuggled into a photo
				// folder, which is alarming and untrue.
				if ($file === '1.album_desc.php') {
					$known[$root . '/' . $entry . '/' . $file] = true;
				}
			}
		}

		return $known;
	}

	/** @return list<string> theme names present in the old install */
	public function themes(): array
	{
		$dir = $this->root . '/data/themes';
		if (!is_dir($dir)) {
			return [];
		}

		$names = [];
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry !== '.' && $entry !== '..' && is_dir($dir . '/' . $entry)) {
				$names[] = $entry;
			}
		}
		sort($names);

		return $names;
	}

	/** @return list<string> module names installed in the old site */
	public function modules(): array
	{
		$dir = $this->root . '/data/modules';
		if (!is_dir($dir)) {
			return [];
		}

		$names = [];
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry !== '.' && $entry !== '..' && is_dir($dir . '/' . $entry)) {
				$names[] = $entry;
			}
		}
		sort($names);

		return $names;
	}

	public function root(): string
	{
		return $this->root;
	}

	// ---- internals ------------------------------------------------------

	private function pagesDir(): string
	{
		return $this->root . '/data/settings/pages';
	}

	/**
	 * The reactions to one post. The filename is the id 4.x assigned, which it
	 * derived from a file count, so duplicates and gaps both exist in the wild;
	 * anything that is not a plain number is skipped rather than guessed at.
	 *
	 * @return list<LegacyReaction>
	 */
	private function reactions(string $dir): array
	{
		if (!is_dir($dir)) {
			return [];
		}

		$reactions = [];
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..' || !str_ends_with($entry, '.php')) {
				continue;
			}

			$id = substr($entry, 0, -4);
			if (preg_match('/^\d+$/', $id) !== 1) {
				continue;
			}

			$values = $this->reader->read($dir . '/' . $entry);

			$reactions[] = new LegacyReaction(
				id: (int) $id,
				name: (string) ($values['reaction_name'] ?? ''),
				email: (string) ($values['reaction_email'] ?? ''),
				website: (string) ($values['reaction_website'] ?? ''),
				message: (string) ($values['reaction_message'] ?? ''),
				time: (int) ($values['reaction_time'] ?? 0),
			);
		}

		usort($reactions, static fn (LegacyReaction $a, LegacyReaction $b): int => [$a->time, $a->id] <=> [$b->time, $b->id]);

		return $reactions;
	}

	/**
	 * An album's description, from the albums-enhancements module.
	 *
	 * That module stored it in `<album>/1.album_desc.php` as `$descripcion` —
	 * Spanish, because that is the language the module was written in. Stock 4.x
	 * has no such file, and an install that never used the module simply has none.
	 */
	private function albumDescription(string $dir): string
	{
		$file = $dir . '/1.album_desc.php';
		if (!is_file($file)) {
			return '';
		}

		$values = $this->reader->read($file);

		return trim((string) ($values['descripcion'] ?? $values['description'] ?? ''));
	}

	/**
	 * The images of one album.
	 *
	 * @return list<LegacyAlbumImage>
	 */
	private function albumImages(string $dir, string $relativeDir): array
	{
		if (!is_dir($dir)) {
			return [];
		}

		$images = [];
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..' || !str_ends_with($entry, '.php')) {
				continue;
			}

			// "<order>.<name>.<ext>.php" — four parts, and the module itself
			// ignores anything that is not.
			$parts = explode('.', $entry);
			if (count($parts) !== 4 || preg_match('/^\d+$/', $parts[0]) !== 1) {
				continue;
			}

			$filename = $parts[1] . '.' . $parts[2];
			$values = $this->reader->read($dir . '/' . $entry);

			$images[] = new LegacyAlbumImage(
				order: (int) $parts[0],
				filename: $filename,
				title: (string) ($values['name'] ?? ''),
				info: (string) ($values['info'] ?? ''),
				relativePath: $relativeDir . '/' . $filename,
				fileExists: is_file($dir . '/' . $filename),
			);
		}

		usort($images, static fn (LegacyAlbumImage $a, LegacyAlbumImage $b): int => [$a->order, $a->filename] <=> [$b->order, $b->filename]);

		return $images;
	}

	/** @param list<LegacyPage> $collected */
	private function collectPages(string $relativeDir, ?string $parentSeoname, array &$collected): void
	{
		$dir = rtrim($this->pagesDir() . '/' . $relativeDir, '/');
		if (!is_dir($dir)) {
			return;
		}

		$entries = [];
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..' || !str_ends_with($entry, '.php')) {
				continue;
			}

			// "<order>.<seoname>.php" — the leading number is the menu position.
			$stem = substr($entry, 0, -4);
			$dot = strpos($stem, '.');
			if ($dot === false) {
				$collected[] = LegacyPage::unreadable($relativeDir . '/' . $entry, 'the filename has no order prefix');
				continue;
			}

			$order = substr($stem, 0, $dot);
			$seoname = substr($stem, $dot + 1);

			// preg rather than ctype_digit: ctype is not in this project's declared
			// requirements, and a migrator is exactly the tool someone runs on a
			// stripped-down shared host.
			if (preg_match('/^\d+$/', $order) !== 1 || $seoname === '') {
				$collected[] = LegacyPage::unreadable($relativeDir . '/' . $entry, 'the filename is not "<number>.<name>.php"');
				continue;
			}

			$entries[] = ['file' => $dir . '/' . $entry, 'order' => (int) $order, 'seoname' => $seoname];
		}

		usort($entries, static fn (array $a, array $b): int => [$a['order'], $a['seoname']] <=> [$b['order'], $b['seoname']]);

		foreach ($entries as $entry) {
			$values = $this->reader->read($entry['file']);

			$page = new LegacyPage(
				seoname: $entry['seoname'],
				parentSeoname: $parentSeoname,
				order: $entry['order'],
				title: (string) ($values['title'] ?? $entry['seoname']),
				content: (string) ($values['content'] ?? ''),
				hidden: LegacyPage::readHidden($values['hidden'] ?? null),
				description: (string) ($values['description'] ?? ''),
				keywords: (string) ($values['keywords'] ?? ''),
				extra: $this->extraValues($values),
				sourceFile: $entry['file'],
			);

			$collected[] = $page;

			// Sub-pages live in a folder named after the parent's seoname.
			$childDir = ltrim($relativeDir . '/' . $entry['seoname'], '/');
			if (is_dir($this->pagesDir() . '/' . $childDir)) {
				$this->collectPages($childDir, $page->path(), $collected);
			}
		}
	}

	/**
	 * Whatever a module wrote into the page beyond the known keys. In 4.x these
	 * arrived as bare `$name = 'value';` lines through $module_additional_data,
	 * with no namespacing, so the best that can be done is keep them together and
	 * say where they came from.
	 *
	 * @param array<string,mixed> $values
	 * @return array<string,string>
	 */
	private function extraValues(array $values): array
	{
		$known = ['title', 'seoname', 'content', 'hidden', 'description', 'keywords'];
		$extra = [];

		foreach ($values as $key => $value) {
			if (!in_array($key, $known, true) && is_scalar($value)) {
				$extra[$key] = (string) $value;
			}
		}

		return $extra;
	}

	/**
	 * @param list<string> $found
	 * @param array<string,true> $skip relative paths that are not uploads
	 */
	private function collectFiles(string $dir, string $relative, array &$found, int $depth, array $skip = []): void
	{
		if ($depth > 6) {
			return;
		}

		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			$path = $dir . '/' . $entry;

			// Never follow a link out of the old tree.
			if (is_link($path)) {
				continue;
			}
			if (is_dir($path)) {
				// Generated thumbnails. v5 keeps one copy of a picture, so carrying
				// these over would double the media folder for no gain.
				if ($skip !== [] && $entry === 'thumb') {
					continue;
				}
				$this->collectFiles($path, $relative . '/' . $entry, $found, $depth + 1, $skip);
				continue;
			}
			if ($entry === 'index.html' || $entry === '.htaccess') {
				continue;
			}
			if (isset($skip[$relative . '/' . $entry])) {
				continue;
			}

			$found[] = $relative . '/' . $entry;
		}
	}
}
