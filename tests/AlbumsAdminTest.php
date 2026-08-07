<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Http\Flash;
use Pluck\Http\Request;
use Pluck\I18n\Locale;
use Pluck\I18n\Translator;
use Pluck\Media\MediaLibrary;
use Pluck\Model\Role;
use Pluck\Module\AlbumsAdminController;
use Pluck\Module\AlbumsModule;
use Pluck\Module\ModuleContext;
use Pluck\Module\ModuleIdentity;
use Pluck\Security\Csp;
use Pluck\Security\Csrf;
use Pluck\Security\Session;
use Pluck\Site\Urls;
use Pluck\Storage\DriverFactory;
use Pluck\Storage\StorageDriver;
use Pluck\View\View;

/**
 * Editing photo albums, and the boundary that lets them write files at all.
 *
 * The interesting half is not the CRUD. It is that albums share one media folder
 * with pages and with every other module, so "a module may add files" had to be
 * narrowed by something. That something is ownership, and these assertions are
 * what stop it being narrowed by nothing.
 */
final class AlbumsAdminTest extends TestCase
{
	public function run(): void
	{
		$this->group('the hash register', fn () => $this->hashes());
		$this->group('albums', fn () => $this->albums());
		$this->group('pictures', fn () => $this->pictures());
		$this->group('the ownership boundary', fn () => $this->ownership());
		$this->group('what a tampered form cannot do', fn () => $this->tampering());
	}

	/**
	 * Content hashes: what makes "this file is already here" answerable, and what
	 * someone checks an upload against.
	 */
	private function hashes(): void
	{
		[$storage, , $media] = $this->install();
		$library = new TestMediaLibrary($media, $storage);

		$this->put($media, 'foto.jpg');
		$hash = $library->hashOf('foto.jpg');

		$this->assertTrue(is_string($hash), 'a file has a hash');
		$this->assertSame(64, strlen((string) $hash), 'sha256, not md5 — this is shown to people to verify against');
		$this->assertSame(
			hash('sha256', (string) file_get_contents($media . '/foto.jpg')),
			$hash,
			'and it really is the hash of the contents',
		);

		$this->assertSame(null, $library->hashOf('nietbestaand.jpg'), 'a file that is not here has none');

		// The register can be behind — someone copies a file in over FTP — so a
		// miss rebuilds rather than answering "no" from stale bookkeeping and
		// writing a second copy.
		$this->assertSame('foto.jpg', $library->nameWithHash((string) $hash), 'a known hash finds its file');
		$this->put($media, 'buitenom.jpg');
		$outside = (string) $library->hashOf('buitenom.jpg');
		$this->assertSame('buitenom.jpg', $library->nameWithHash($outside), 'including one that arrived outside the library');

		$this->assertSame(null, $library->nameWithHash(str_repeat('0', 64)), 'and a hash nothing has is not found');

		// Keeping both makes a copy of what is on disk. No upload is held anywhere
		// while somebody decides, because the bytes are already here.
		$copy = $library->duplicate('foto.jpg');
		$this->assertTrue($copy->ok, 'a second copy can be made deliberately');
		$this->assertSame('foto-2.jpg', $copy->name, 'under a name of its own');
		$this->assertSame($hash, $copy->hash, 'with the same contents');

		$library->delete('foto-2.jpg');
		$this->assertFalse(
			array_key_exists('foto-2.jpg', $library->register()),
			'deleting a file forgets its hash, so the register does not point at nothing',
		);
	}

	private function albums(): void
	{
		[$storage, $run] = $this->install();

		$run('save', post: ['title' => 'Vakantie 2011']);
		$this->assertSame(
			'Vakantie 2011',
			$storage->getModuleData('albums', 'album:vakantie-2011')['title'] ?? null,
			'an album is created under a slug from its name',
		);

		$run('save', post: ['original' => 'vakantie-2011', 'title' => 'Vakantie', 'description' => '<p>Mooi weer</p><script>alert(1)</script>']);
		$album = $storage->getModuleData('albums', 'album:vakantie-2011');
		$this->assertSame('Vakantie', $album['title'] ?? null, 'renaming keeps the address');
		$this->assertFalse(str_contains((string) $album['description'], '<script'), 'the description goes through the sanitiser');
		$this->assertTrue(str_contains((string) $album['description'], 'Mooi weer'), 'and keeps the text');

		$run('save', post: ['title' => 'Tweede']);
		$run('move', post: ['slug' => 'tweede', 'direction' => 'up']);
		$this->assertTrue(
			(int) $storage->getModuleData('albums', 'album:tweede')['order']
			< (int) $storage->getModuleData('albums', 'album:vakantie-2011')['order'],
			'albums can be reordered',
		);
	}

	private function pictures(): void
	{
		[$storage, $run, $media] = $this->install();

		$run('save', post: ['title' => 'Album']);
		$this->put($media, 'strand.jpg');
		$this->put($media, 'bergen.jpg');

		$run('pickImage', post: ['album' => 'album', 'file' => 'strand.jpg']);
		$run('pickImage', post: ['album' => 'album', 'file' => 'bergen.jpg']);

		$this->assertSame(
			'strand.jpg',
			$storage->getModuleData('albums', 'image:album:0000')['file'] ?? null,
			'a picture from the library is referenced by name',
		);

		$run('saveImage', post: ['album' => 'album', 'id' => '0000', 'title' => 'Op het strand', 'info' => '<p>Dag een</p>']);
		$this->assertSame(
			'Op het strand',
			$storage->getModuleData('albums', 'image:album:0000')['title'] ?? null,
			'a caption is saved',
		);

		$run('moveImage', post: ['album' => 'album', 'id' => '0000', 'direction' => 'down']);
		$this->assertSame(
			'bergen.jpg',
			$storage->getModuleData('albums', 'image:album:0000')['file'] ?? null,
			'pictures can be reordered within an album',
		);
		$this->assertSame(
			'Op het strand',
			$storage->getModuleData('albums', 'image:album:0001')['title'] ?? null,
			'and their captions move with them rather than staying at a position',
		);

		// End to end: what the site renders.
		$html = (string) (new AlbumsModule())->render('album', [], $storage, new Urls('/', false))?->html;
		$this->assertTrue(str_contains($html, 'media/bergen.jpg'), 'the site shows the pictures from the media folder');
		$this->assertTrue(str_contains($html, 'Op het strand'), 'with their captions');

		// Deleting an album must not delete photographs a page may be using.
		$run('delete', post: ['slug' => 'album']);
		$this->assertSame(null, $storage->getModuleData('albums', 'album:album'), 'the album is gone');
		$this->assertSame(null, $storage->getModuleData('albums', 'image:album:0000'), 'and so are its entries');
		$this->assertTrue(is_file($media . '/strand.jpg'), 'but the pictures stay in the media library');
	}

	private function ownership(): void
	{
		[$storage, $run, $media] = $this->install();

		$run('save', post: ['title' => 'Album']);

		// A file somebody else put in the library: uploaded through the media
		// screen, or used by a page. The albums module did not add it.
		$this->put($media, 'logo.png');
		$run('pickImage', post: ['album' => 'album', 'file' => 'logo.png']);

		$run('removeImage', post: ['album' => 'album', 'id' => '0000', 'delete_file' => '1']);
		$this->assertTrue(
			is_file($media . '/logo.png'),
			'a module cannot delete a file it did not add, even when asked to',
		);
		$this->assertSame(
			null,
			$storage->getModuleData('albums', 'image:album:0000'),
			'though it is unlinked from the album',
		);

		// A file this module did add, through addMedia(), it may remove.
		$this->assertTrue($this->upload($storage, $media, 'album', 'eigen.png'), 'a module can add a file');
		$this->assertTrue(is_file($media . '/eigen.png'), 'which really lands in the shared media folder');
		$this->assertTrue(
			is_array($storage->getModuleData('albums', 'media:eigen.png')),
			'and ownership is recorded in the module\'s own data',
		);

		// Offering the same bytes again is recognised rather than written twice.
		$before = count(glob($media . '/*') ?: []);
		$this->assertFalse($this->upload($storage, $media, 'album', 'eigen.png'), 'the same file offered twice is not stored twice');
		$this->assertSame($before, count(glob($media . '/*') ?: []), 'so the media folder does not grow');

		// The same bytes offered as a .jpg are refused: the policy reads the file
		// rather than believing its name, and a module gets exactly that policy.
		$this->assertFalse(
			$this->upload($storage, $media, 'album', 'gelogen.jpg'),
			'a module cannot get a file past the type check by renaming it',
		);
		$this->assertFalse(is_file($media . '/gelogen.jpg'), 'so nothing is written');

		$storage->setModuleData('albums', 'image:album:0000', ['file' => 'eigen.png', 'title' => '', 'info' => '']);
		$run('removeImage', post: ['album' => 'album', 'id' => '0000', 'delete_file' => '1']);
		$this->assertFalse(is_file($media . '/eigen.png'), 'and can delete what it added');
		$this->assertSame(
			null,
			$storage->getModuleData('albums', 'media:eigen.png'),
			'clearing the ownership record with it',
		);
	}

	private function tampering(): void
	{
		[$storage, $run, $media] = $this->install();

		$run('save', post: ['title' => 'Album']);
		$this->put($media, 'foto.jpg');
		$run('pickImage', post: ['album' => 'album', 'file' => 'foto.jpg']);

		// Image keys are rebuilt from an album slug and a numeric id, so a form
		// naming an album key cannot have it handed to delete().
		$run('removeImage', post: ['album' => 'album', 'id' => '../album:album']);
		$this->assertTrue(
			is_array($storage->getModuleData('albums', 'album:album')),
			'a picture delete cannot be aimed at the album itself',
		);

		// Picking is limited to what is actually in the library, so a path cannot
		// be smuggled in as a filename.
		$run('pickImage', post: ['album' => 'album', 'file' => '../data/settings/config.php']);
		$this->assertSame(
			1,
			count($storage->listModuleData('albums', 'image:album:')),
			'a file outside the media library cannot be added to an album',
		);
	}

	// ---- fixture --------------------------------------------------------

	/**
	 * A picture whose bytes depend on its name.
	 *
	 * Distinct on purpose: identical content is now recognised and not written
	 * twice, so a fixture where every picture is the same pixel would collapse
	 * into one file and stop testing anything else.
	 */
	private function put(string $media, string $name): void
	{
		file_put_contents($media . '/' . $name, $this->pixel($name));
	}

	private function pixel(string $name): string
	{
		$png = base64_decode(
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==',
		) ?: 'x';

		return $png . $name;
	}

	/** Add a file the way a module does, through addMedia(). */
	private function upload(StorageDriver $storage, string $media, string $album, string $name): bool
	{
		$source = sys_get_temp_dir() . '/' . bin2hex(random_bytes(6));
		file_put_contents($source, $this->pixel($name));

		$context = $this->context($storage, $media);
		$result = $context->addMedia(['name' => $name, 'tmp_name' => $source, 'error' => UPLOAD_ERR_OK]);

		@unlink($source);

		return $result->ok;
	}

	private function context(StorageDriver $storage, string $media): ModuleContext
	{
		$root = dirname(__DIR__);
		$session = new Session();
		$csrf = new Csrf($session);
		$translator = new Translator(Locale::tryFrom('en') ?? Locale::fallback(), $root . '/lang');

		return new ModuleContext(
			module: 'albums',
			storage: $storage,
			identity: new ModuleIdentity('u1', 'Test', Role::Owner),
			request: Request::fake(method: 'POST'),
			flash: new Flash($session),
			csrf: $csrf,
			translator: $translator,
			adminView: new View($root . '/views', $csrf, new Csp(), $translator),
			library: new TestMediaLibrary($media, $storage),
			mediaMaxBytes: 8388608,
		);
	}

	/** @return array{0:StorageDriver,1:callable,2:string} */
	private function install(): array
	{
		$dir = $this->tempDir('pluck-albums-admin');
		$media = $dir . '/media';
		mkdir($media, 0o755, true);

		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $dir);
		$storage->install();

		$root = dirname(__DIR__);

		$run = function (string $action, array $post = [], array $query = []) use ($storage, $media, $root): void {
			$this->withoutSessionWarnings(function () use ($action, $post, $query, $storage, $media, $root): void {
				$session = new Session();
				$csrf = new Csrf($session);
				$translator = new Translator(Locale::tryFrom('en') ?? Locale::fallback(), $root . '/lang');

				$context = new ModuleContext(
					module: 'albums',
					storage: $storage,
					identity: new ModuleIdentity('u1', 'Test', Role::Owner),
					request: Request::fake(method: 'POST', post: $post, query: $query),
					flash: new Flash($session),
					csrf: $csrf,
					translator: $translator,
					adminView: new View($root . '/views', $csrf, new Csp(), $translator),
					library: new TestMediaLibrary($media, $storage),
					mediaMaxBytes: 8388608,
				);

				try {
					(new StoppingAlbumsAdminController($context))->{$action}();
				} catch (ControllerStopped) {
					// Every action ends in a redirect or a render, both of which exit.
				}
			});
		};

		return [$storage, $run, $media];
	}
}

/**
 * The real library, with the two calls that need a genuine HTTP upload replaced.
 *
 * Only `is_uploaded_file()` and `move_uploaded_file()` are swapped — the policy,
 * the naming and the MIME check are the code that ships.
 */
final class TestMediaLibrary extends MediaLibrary
{
	protected function isUpload(string $path): bool
	{
		return is_file($path);
	}

	protected function move(string $from, string $to): bool
	{
		return rename($from, $to);
	}
}

final class StoppingAlbumsAdminController extends AlbumsAdminController
{
	protected function render(string $template, array $data = []): never
	{
		throw new ControllerStopped('rendered ' . $template);
	}

	protected function back(string $route, array $params = []): never
	{
		throw new ControllerStopped('redirected to ' . $route);
	}
}
