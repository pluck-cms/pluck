<?php
declare(strict_types=1);

namespace Pluck\Admin;

use Pluck\Media\MediaLibrary;
use Pluck\Media\MediaResult;

use Pluck\Support\Path;
use Pluck\Support\UploadName;

/**
 * The media library.
 *
 * Uploads are the one place where a visitor-supplied file lands inside the
 * document root, so the rules are deliberately narrow: an extension allow-list,
 * a MIME type that has to agree with the extension, images that have to parse as
 * images, and a filename rebuilt from scratch rather than cleaned up. SVG is not
 * on the list — it is a script container wearing an image's clothes.
 */
final class MediaController extends Controller
{
	/** extension => acceptable MIME types */
	private const ALLOWED = [
		'jpg' => ['image/jpeg'],
		'jpeg' => ['image/jpeg'],
		'png' => ['image/png'],
		'gif' => ['image/gif'],
		'webp' => ['image/webp'],
		'avif' => ['image/avif'],
		'pdf' => ['application/pdf'],
		'txt' => ['text/plain'],
		'csv' => ['text/plain', 'text/csv', 'application/csv'],
		'zip' => ['application/zip'],
		'mp3' => ['audio/mpeg'],
		'mp4' => ['video/mp4'],
		'webm' => ['video/webm'],
		'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
		'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
	];

	private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

	public function index(): never
	{
		// The register is name => hash, so showing it costs a single settings read
		// rather than hashing every file on every visit.
		$hashes = $this->library()->register();

		/*
		 * Which files belong to a module, so the list can be narrowed.
		 *
		 * A media folder is one flat directory by design — the same picture can
		 * be in an album and on a page, and giving each module its own copy hides
		 * that relationship and lets the copies drift. But flat also means an
		 * album of forty photos buries the three files somebody uploaded for a
		 * page, so the folder stays flat and the view does the narrowing.
		 */
		$owners = $this->owners();
		$filter = $this->c->request->query('kind', '');
		$files = $this->listFiles($hashes, $owners);

		$this->render('admin/media/index', [
			'duplicate' => basename($this->c->request->query('duplicate', '')),
			'title' => $this->t('media.title.media'),
			'filter' => in_array($filter, ['image', 'file', 'module'], true) ? $filter : '',
			'counts' => [
				'all' => count($files),
				'image' => count(array_filter($files, static fn (array $f): bool => $f['isImage'] && $f['owner'] === '')),
				'file' => count(array_filter($files, static fn (array $f): bool => !$f['isImage'] && $f['owner'] === '')),
				'module' => count(array_filter($files, static fn (array $f): bool => $f['owner'] !== '')),
			],
			'files' => $this->narrow($files, $filter),
			'canUpload' => $this->c->auth->can('file.upload'),
			'maxBytes' => $this->maxBytes(),
			'accept' => '.' . implode(',.', array_keys(self::ALLOWED)),
		]);
	}

	public function upload(): never
	{
		$file = $this->c->request->file('file');
		if ($file === null) {
			$this->c->flash->stop($this->t('media.flash.choose_file_first'));
			$this->back('media');
		}

		// What may be uploaded is decided in one place, so a module adding a
		// picture faces exactly the same policy as this screen does.
		$result = $this->library()->store($file, $this->maxBytes());

		// Not an error: the same bytes are already here. The person decides
		// whether to point at what exists or keep a second copy, and neither
		// answer needs the upload held anywhere while they think.
		if ($result->isDuplicate()) {
			$this->c->flash->warn($this->t('media.flash.already_here', [
				'name' => $result->name,
				'hash' => substr($result->hash, 0, 12),
			]));
			$this->back('media', ['duplicate' => $result->name]);
		}

		if (!$result->ok) {
			// Each arm calls t() itself rather than picking a key for one call
			// afterwards: the catalogue test reads keys straight out of the source,
			// and a key that arrives through a variable is invisible to it.
			$this->c->flash->stop(match ($result->reason) {
				MediaResult::NO_FILE => $this->t('media.flash.choose_file_first'),
				MediaResult::TOO_LARGE_FOR_SERVER => $this->t('media.flash.file_larger_than_server_accepts'),
				MediaResult::TOO_LARGE => $this->t('media.flash.files_limited_to_size', ['size' => $this->humanBytes($this->maxBytes())]),
				MediaResult::CUT_SHORT => $this->t('media.flash.upload_cut_short'),
				MediaResult::NOWHERE_TO_PUT_IT => $this->t('media.flash.server_has_nowhere_to_put_upload'),
				MediaResult::NOT_AN_UPLOAD => $this->t('media.flash.not_upload'),
				MediaResult::NO_EXTENSION => $this->t('media.flash.no_uploads_without_extension'),
				MediaResult::EXTENSION_REFUSED => $this->t('media.flash.extension_not_accepted', ['extension' => $result->detail]),
				MediaResult::CONTENTS_DISAGREE => $this->t('media.flash.not_really_that_kind_of_file', ['extension' => $result->detail]),
				MediaResult::UNREADABLE_IMAGE => $this->t('media.flash.image_could_not_read'),
				MediaResult::COULD_NOT_WRITE => $this->t('media.flash.could_not_write_media_folder_check'),
				default => $this->t('media.flash.upload_failed'),
			});
			$this->back('media');
		}

		$this->c->flash->ok($this->t('media.flash.uploaded_name', ['name' => $result->name]));
		$this->back('media');
	}

	/**
	 * Keep a second copy of a file that is already here.
	 *
	 * Copies what is on disk rather than re-reading an upload, so there is no
	 * temporary file to hold or clean up.
	 */
	public function keepBoth(): never
	{
		$result = $this->library()->duplicate($this->c->request->post('name', ''));

		if (!$result->ok) {
			$this->c->flash->stop($this->t('media.flash.no_such_file'));
			$this->back('media');
		}

		$this->c->flash->ok($this->t('media.flash.uploaded_name', ['name' => $result->name]));
		$this->back('media');
	}

	private function library(): MediaLibrary
	{
		return new MediaLibrary($this->mediaDir(), $this->c->storage);
	}

	public function delete(): never
	{
		if (!$this->c->auth->can('file.delete') && !$this->c->auth->can('file.*')) {
			$this->c->flash->stop($this->t('media.flash.account_cannot_delete_files'));
			$this->back('media');
		}

		$name = basename($this->c->request->post('name'));
		$path = Path::within($this->mediaDir(), $name);

		if ($name === '' || !is_file($path)) {
			$this->c->flash->stop($this->t('media.flash.no_such_file'));
			$this->back('media');
		}

		unlink($path);
		$this->c->flash->ok($this->t('media.flash.deleted_name', ['name' => $name]));
		$this->back('media');
	}

	/** @return list<array{name:string,size:int,modified:int,isImage:bool}> */
	/** @param array<string,string> $hashes name => sha256 */
	/**
	 * Which module claims which file.
	 *
	 * @return array<string,string> media name => the album or module name
	 */
	private function owners(): array
	{
		$owners = [];

		foreach ($this->c->modules?->all() ?? [] as $module) {
			foreach ($this->c->storage->listModuleData($module->name(), 'media:') as $key => $value) {
				$name = substr($key, 6);

				// An album's own name where there is one, so the label reads as
				// "Open dag 4 februari 2012" rather than "albums".
				$owners[$name] = is_array($value) && isset($value['album'])
					? (string) $value['album']
					: $module->name();
			}
		}

		return $owners;
	}

	/**
	 * @param list<array{isImage:bool,owner:string}> $files
	 * @return list<array<string,mixed>>
	 */
	private function narrow(array $files, string $filter): array
	{
		return match ($filter) {
			'image' => array_values(array_filter($files, static fn (array $f): bool => $f['isImage'] && $f['owner'] === '')),
			'file' => array_values(array_filter($files, static fn (array $f): bool => !$f['isImage'] && $f['owner'] === '')),
			'module' => array_values(array_filter($files, static fn (array $f): bool => $f['owner'] !== '')),
			default => $files,
		};
	}

	private function listFiles(array $hashes = [], array $owners = []): array
	{
		$dir = $this->mediaDir();
		Path::ensureDir($dir);

		$files = [];
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
				continue;
			}
			$full = $dir . '/' . $entry;
			if (!is_file($full) || $entry === 'index.html') {
				continue;
			}
			$extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
			$files[] = [
				'name' => $entry,
				'size' => (int) filesize($full),
				'modified' => (int) filemtime($full),
				'isImage' => in_array($extension, self::IMAGE_EXTENSIONS, true),
				// Shown so an upload can be checked against the sender's own
				// checksum, and so a link handed out can be published with one.
				'hash' => $hashes[$entry] ?? '',
				'owner' => $owners[$entry] ?? '',
			];
		}

		usort($files, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

		return $files;
	}

	/** A name built from the slug of the original, never from the original itself. */
	private function uniqueName(string $original, string $extension): string
	{
		$dir = $this->mediaDir();

		return UploadName::unique(
			$original,
			$extension,
			static fn (string $candidate): bool => file_exists($dir . '/' . $candidate),
		);
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
		$mime = finfo_file($finfo, $path);
		finfo_close($finfo);

		return is_string($mime) ? $mime : null;
	}

	private function maxBytes(): int
	{
		$configured = (int) $this->c->storage->getSetting('media_max_bytes', 8 * 1024 * 1024);

		return max(65536, min($configured, 512 * 1024 * 1024));
	}

	private function humanBytes(int $bytes): string
	{
		return $bytes >= 1048576
			? round($bytes / 1048576, 1) . ' MB'
			: round($bytes / 1024) . ' kB';
	}

	private function mediaDir(): string
	{
		return $this->c->app->rootDir . '/media';
	}
}
