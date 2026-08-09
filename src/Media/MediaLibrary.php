<?php
declare(strict_types=1);

namespace Pluck\Media;

use Pluck\Storage\StorageDriver;
use Pluck\Support\Path;

/**
 * The media folder, and the single decision about what may go into it.
 *
 * Extracted from the media screen when modules needed to add files too. The
 * alternative was letting each module decide what an acceptable upload is, which
 * is how you end up with a photo album that accepts a .phtml because whoever
 * wrote it copied the list from somewhere and missed one.
 *
 * There is one media folder for the whole site. Pluck 4 kept album pictures under
 * data/settings and served them through a PHP script taking a filename from the
 * query string — the shape of every path traversal bug ever written — and it also
 * meant a photo could not be reused on a page without uploading it a second time.
 * One folder fixes both: the web server serves the file, and everything points at
 * the same copy rather than at a duplicate that will drift.
 */
class MediaLibrary
{
	/**
	 * Extension to the MIME types its bytes are allowed to look like.
	 *
	 * An allow-list, and short on purpose. Anything a web server might decide to
	 * execute is absent, and stays absent: the media folder is served directly, so
	 * the only thing standing between an upload and remote code execution is this
	 * table and the .htaccess beside it.
	 */
	public const ALLOWED = [
		'jpg' => ['image/jpeg'],
		'jpeg' => ['image/jpeg'],
		'png' => ['image/png'],
		'gif' => ['image/gif'],
		'webp' => ['image/webp'],
		'avif' => ['image/avif'],
		'pdf' => ['application/pdf'],
		'txt' => ['text/plain'],
		'csv' => ['text/plain', 'text/csv'],
		'zip' => ['application/zip'],
		'mp3' => ['audio/mpeg'],
		'mp4' => ['video/mp4'],
		'svg' => ['image/svg+xml'],
	];

	/*
	 * What counts as a picture.
	 *
	 * SVG is on it and was not, which is the sort of gap that only shows once
	 * something else starts using the list: the media picker splits pictures from
	 * files by this, while the editor's insert decided the same question with its
	 * own regex in JavaScript — one that did include svg. So an SVG appeared under
	 * files and was inserted as an image.
	 *
	 * One list, and the browser is told what is on it rather than keeping a
	 * second copy.
	 */
	/**
	 * How large an upload may be when nobody has said.
	 *
	 * A constant because three callers each wrote it out: `8 * 1024 * 1024` in
	 * one, `8388608` in two others. They agreed, which is the dangerous version —
	 * a default written three ways is one somebody will change in one place.
	 */
	public const DEFAULT_MAX_BYTES = 8 * 1024 * 1024;

	public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];

	/** Setting holding name => sha256, so a duplicate is recognised without rereading the folder. */
	public const REGISTER = 'media_hashes';

	/**
	 * @param StorageDriver|null $storage where the hash register lives. Without
	 *        one the library still works; it just cannot recognise a file it has
	 *        seen before without reading every file in the folder.
	 */
	public function __construct(
		private readonly string $directory,
		private readonly ?StorageDriver $storage = null,
	) {
	}

	/**
	 * The content hash of a file, as hex.
	 *
	 * SHA-256 rather than MD5, and the reason is not only that MD5 is old. This
	 * hash is shown to people so they can check an upload arrived intact, and it
	 * decides whether two files are treated as the same file. MD5 collisions have
	 * been makeable on a laptop for years, so a hostile upload could be made to
	 * collide with a file already here — and then "this is already on the server"
	 * would be a lie with consequences.
	 */
	public static function hashFile(string $path): ?string
	{
		$hash = @hash_file('sha256', $path);

		return is_string($hash) && $hash !== '' ? $hash : null;
	}

	public function hashOf(string $name): ?string
	{
		$name = basename($name);

		return $this->has($name) ? self::hashFile(Path::within($this->directory, $name)) : null;
	}

	/**
	 * The name a file with this content already has here, if any.
	 *
	 * Consults the register first and falls back to reading the folder, so a file
	 * copied in over FTP is still recognised — at the cost of reading everything
	 * once, which is why the answer is written back.
	 */
	public function nameWithHash(string $hash): ?string
	{
		$register = $this->register();

		$name = array_search($hash, $register, true);
		if (is_string($name) && $this->has($name)) {
			return $name;
		}

		// Either nothing matched or the register is behind. Rebuild it rather than
		// answering "no" from stale bookkeeping and writing a second copy.
		$rebuilt = $this->rebuildRegister();
		$name = array_search($hash, $rebuilt, true);

		return is_string($name) ? $name : null;
	}

	/**
	 * The library, grouped by what put each file there.
	 *
	 * One flat list of two hundred filenames is a list nobody finds anything in,
	 * and the grouping is already recorded: a module writes `media:<name>` into
	 * its own data when it adds a file, which is how ownership is tracked for
	 * deletion. Reading it back costs one pass and turns the picker into
	 * something with an album in it.
	 *
	 * @param array<string,string> $owners media name => the group it belongs to
	 * @return array<string,list<string>> group label => file names
	 */
	public function grouped(array $owners = []): array
	{
		$groups = [];

		foreach ($this->names() as $name) {
			$group = $owners[$name] ?? '';
			$groups[$group][] = $name;
		}

		// Files nobody claimed go last under an empty key, which the caller
		// labels: they are the ordinary uploads, and they are what somebody
		// looking for a picture they added themselves will scroll to.
		if (isset($groups[''])) {
			$loose = $groups[''];
			unset($groups['']);
			ksort($groups);
			$groups[''] = $loose;
		} else {
			ksort($groups);
		}

		return $groups;
	}

	/** @return array<string,string> name => hash */
	public function register(): array
	{
		$stored = $this->storage?->getSetting(self::REGISTER, []);

		return is_array($stored) ? array_filter($stored, 'is_string') : [];
	}

	/**
	 * Read every file and record what it is. Cheap enough at the sizes Pluck runs
	 * at, and the only honest answer when the register and the folder disagree.
	 *
	 * @return array<string,string>
	 */
	public function rebuildRegister(): array
	{
		$register = [];
		foreach ($this->names() as $name) {
			$hash = self::hashFile(Path::within($this->directory, $name));
			if ($hash !== null) {
				$register[$name] = $hash;
			}
		}

		$this->storage?->setSetting(self::REGISTER, $register);

		return $register;
	}

	private function remember(string $name, string $hash): void
	{
		if ($this->storage === null) {
			return;
		}

		$register = $this->register();
		$register[$name] = $hash;
		$this->storage->setSetting(self::REGISTER, $register);
	}

	private function forget(string $name): void
	{
		if ($this->storage === null) {
			return;
		}

		$register = $this->register();
		unset($register[$name]);
		$this->storage->setSetting(self::REGISTER, $register);
	}

	/**
	 * Make a second copy of a file that is already here.
	 *
	 * What "keep both anyway" does after a duplicate is reported. No temporary
	 * storage is involved: the bytes are already on disk, so the upload does not
	 * have to be held anywhere while somebody decides.
	 */
	public function duplicate(string $existing): MediaResult
	{
		$existing = basename($existing);
		if (!$this->has($existing)) {
			return MediaResult::failed(MediaResult::NO_FILE);
		}

		$source = Path::within($this->directory, $existing);
		$extension = strtolower(pathinfo($existing, PATHINFO_EXTENSION));
		$name = $this->uniqueName($existing, $extension);
		$target = Path::within($this->directory, $name);

		if (!@copy($source, $target)) {
			return MediaResult::failed(MediaResult::COULD_NOT_WRITE);
		}

		@chmod($target, 0o644);

		$hash = self::hashFile($target);
		if ($hash !== null) {
			$this->remember($name, $hash);
		}

		return MediaResult::stored($name, $hash ?? '');
	}

	/** @return list<string> filenames, never paths */
	public function names(): array
	{
		$names = [];
		foreach (scandir($this->directory) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..' || $entry === 'index.html' || $entry === '.htaccess') {
				continue;
			}
			if (is_file($this->directory . '/' . $entry)) {
				$names[] = $entry;
			}
		}
		sort($names);

		return $names;
	}

	public function has(string $name): bool
	{
		return $name !== '' && is_file(Path::within($this->directory, basename($name)));
	}

	public function bytes(string $name): int
	{
		$path = Path::within($this->directory, basename($name));

		return is_file($path) ? (int) filesize($path) : 0;
	}

	/**
	 * Accept an uploaded file.
	 *
	 * Returns a result rather than redirecting, so the media screen and a module
	 * can both call it and each report failure in their own way.
	 *
	 * @param array<string,mixed> $file an entry from $_FILES
	 */
	public function store(array $file, int $maxBytes, bool $allowDuplicate = false): MediaResult
	{
		$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
		if ($error !== UPLOAD_ERR_OK) {
			return MediaResult::failed(match ($error) {
				UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => MediaResult::TOO_LARGE_FOR_SERVER,
				UPLOAD_ERR_PARTIAL => MediaResult::CUT_SHORT,
				UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => MediaResult::NOWHERE_TO_PUT_IT,
				UPLOAD_ERR_NO_FILE => MediaResult::NO_FILE,
				default => MediaResult::FAILED,
			});
		}

		$tmp = (string) ($file['tmp_name'] ?? '');
		if (!$this->isUpload($tmp)) {
			// Not something PHP received as an upload. A path pointing anywhere
			// else is either a bug or an attempt to have the server copy a file it
			// chose, and neither is worth guessing at.
			return MediaResult::failed(MediaResult::NOT_AN_UPLOAD);
		}

		if (filesize($tmp) > $maxBytes) {
			return MediaResult::failed(MediaResult::TOO_LARGE);
		}

		$original = (string) ($file['name'] ?? '');
		$extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

		if ($extension === '') {
			return MediaResult::failed(MediaResult::NO_EXTENSION);
		}
		if (!isset(self::ALLOWED[$extension])) {
			return MediaResult::failed(MediaResult::EXTENSION_REFUSED, $extension);
		}

		// The browser's declared type is ignored; only what the bytes look like
		// counts. A .jpg whose contents are a PHP script is the oldest upload
		// trick there is.
		$detected = $this->detectMime($tmp);
		if ($detected !== null && !in_array($detected, self::ALLOWED[$extension], true)) {
			return MediaResult::failed(MediaResult::CONTENTS_DISAGREE, $extension);
		}

		if (in_array($extension, self::IMAGE_EXTENSIONS, true) && @getimagesize($tmp) === false) {
			// AVIF and some WebP variants are unreadable on older builds, so a
			// failure here is only fatal for the formats GD has always handled.
			if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'], true)) {
				return MediaResult::failed(MediaResult::UNREADABLE_IMAGE);
			}
		}

		// Recognise a file we already hold before writing a second copy of it.
		// Byte for byte, never by name: two files called logo.png are routinely two
		// different logos.
		$hash = self::hashFile($tmp);
		if (!$allowDuplicate && $hash !== null) {
			$already = $this->nameWithHash($hash);
			if ($already !== null) {
				return MediaResult::duplicate($already, $hash);
			}
		}

		$name = $this->uniqueName($original, $extension);
		$target = Path::within($this->directory, $name);

		if (!$this->move($tmp, $target)) {
			return MediaResult::failed(MediaResult::COULD_NOT_WRITE);
		}

		chmod($target, 0o644);

		if ($hash !== null) {
			$this->remember($name, $hash);
		}

		return MediaResult::stored($name, $hash ?? '');
	}

	public function delete(string $name): bool
	{
		$name = basename($name);
		if ($name === '' || !$this->has($name)) {
			return false;
		}

		$removed = @unlink(Path::within($this->directory, $name));
		if ($removed) {
			$this->forget($name);
		}

		return $removed;
	}

	/**
	 * A filename nobody has to think about.
	 *
	 * The name is rebuilt from a slug rather than cleaned, because cleaning means
	 * deciding which of a hundred awkward characters to remove and being wrong
	 * about one of them. A number is appended rather than overwriting: two people
	 * uploading photo.jpg an hour apart should end up with two photos.
	 */
	private function uniqueName(string $original, string $extension): string
	{
		$stem = \Pluck\Support\Slug::make(pathinfo($original, PATHINFO_FILENAME), 'file');
		$name = $stem . '.' . $extension;

		$counter = 2;
		while ($this->has($name)) {
			$name = $stem . '-' . $counter . '.' . $extension;
			$counter++;

			if ($counter > 500) {
				$name = $stem . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
				break;
			}
		}

		return $name;
	}

	private function detectMime(string $path): ?string
	{
		if (!function_exists('finfo_open')) {
			return null;
		}

		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		if ($finfo === false) {
			return null;
		}

		$type = finfo_file($finfo, $path);

		// No finfo_close(): deprecated from PHP 8.5, where the resource is freed
		// when it goes out of scope anyway. Calling it emitted a deprecation
		// notice on every upload, which on a server with display_errors on is a
		// notice on the page.
		if (PHP_VERSION_ID < 80500) {
			finfo_close($finfo);
		}

		return is_string($type) && $type !== '' ? $type : null;
	}

	// Seams, so the library can be exercised without a real upload.

	protected function isUpload(string $path): bool
	{
		return is_uploaded_file($path);
	}

	protected function move(string $from, string $to): bool
	{
		return move_uploaded_file($from, $to);
	}
}
