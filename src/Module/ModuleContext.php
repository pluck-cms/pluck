<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\Auth\AccessList;
use Pluck\Http\Flash;
use Pluck\Http\Request;
use Pluck\Http\Response;
use Pluck\I18n\Translator;
use Pluck\Media\MediaLibrary;
use Pluck\Media\MediaResult;
use Pluck\Model\Role;
use Pluck\Security\Csrf;
use Pluck\Storage\StorageDriver;
use Pluck\View\View;

/**
 * What a module admin screen is allowed to reach.
 *
 * The admin's own Context carries the storage driver, the Auth object and the
 * whole Bootstrap. Handing that to a module would mean a photo album could edit
 * user accounts, delete pages and rewrite the site settings — not because it
 * wants to, but because nothing stops it. That unboundedness is the shape Pluck 4
 * had and the reason its security model had to be a blacklist.
 *
 * So a module gets this instead. It can read and write its own module data and
 * nothing else's: the module name is fixed at construction and never passed as an
 * argument, which means there is no call a module can make to reach another's
 * data, correct or otherwise. It can read the media library. It can ask who is
 * signed in and what they may do, but cannot sign anyone in or out.
 *
 * Deliberately absent, and each of these was a decision rather than an oversight:
 *
 *   - the StorageDriver, so pages, users and site settings are out of reach
 *   - Auth, so the session cannot be touched
 *   - Bootstrap, so config.php and the filesystem layout are out of reach
 *   - any path, so a module cannot read or write files directly
 *
 * A module that genuinely needs one of those is a core change, discussed in the
 * open, rather than a widening of this class.
 */
final class ModuleContext
{
	public function __construct(
		private readonly string $module,
		private readonly StorageDriver $storage,
		private readonly ModuleIdentity $identity,
		public readonly Request $request,
		public readonly Flash $flash,
		public readonly Csrf $csrf,
		private readonly Translator $translator,
		private readonly View $adminView,
		private readonly ?View $moduleView = null,
		private readonly ?MediaLibrary $library = null,
		private readonly ?AccessList $accessList = null,
		private readonly int $mediaMaxBytes = 8388608,
	) {
	}

	public function module(): string
	{
		return $this->module;
	}

	// ---- this module's data ---------------------------------------------

	public function get(string $key, mixed $default = null): mixed
	{
		return $this->storage->getModuleData($this->module, $key, $default);
	}

	public function set(string $key, mixed $value): void
	{
		$this->storage->setModuleData($this->module, $key, $value);
	}

	/** @return array<string,mixed> */
	public function list(string $keyPrefix = ''): array
	{
		return $this->storage->listModuleData($this->module, $keyPrefix);
	}

	public function delete(string $key): void
	{
		$this->storage->deleteModuleData($this->module, $key);
	}

	/**
	 * Run several writes as one unit, so a half-finished save is not a state the
	 * site can be left in.
	 *
	 * @template T
	 * @param callable():T $work
	 * @return T
	 */
	public function transaction(callable $work): mixed
	{
		return $this->storage->transaction($work);
	}

	// ---- media ----------------------------------------------------------

	/**
	 * The files in the media library, by name.
	 *
	 * Names, never paths: a module that is handed a path is a module that can be
	 * talked into reading one.
	 *
	 * There is one media folder for the whole site, shared with pages. That is a
	 * deliberate choice over giving each module its own: a photo in an album and
	 * the same photo on a page are then one file rather than two copies that
	 * drift apart, and reusing it costs nothing. Pluck 4 kept them separate and
	 * you had to upload the picture twice.
	 *
	 * @return list<string>
	 */
	public function media(): array
	{
		return $this->library?->names() ?? [];
	}

	/**
	 * Add a file to the media library on this module's behalf.
	 *
	 * The policy is the media screen's policy, applied by the same code — a
	 * module does not get to decide what an acceptable upload is. What it does
	 * get is ownership: the name is recorded in this module's own data, which is
	 * what makes removeMedia() safe to offer.
	 *
	 * @param array<string,mixed> $file an entry from $_FILES
	 * @param string $group what the media screen files it under — an album's own
	 *        name where a module has one, so the picker reads as "Open dag 2019"
	 *        rather than as the module's name nine times
	 */
	public function addMedia(array $file, string $group = ''): MediaResult
	{
		if ($this->library === null) {
			return MediaResult::failed(MediaResult::COULD_NOT_WRITE);
		}

		$result = $this->library->store($file, $this->mediaMaxBytes);

		if ($result->ok) {
			$this->set('media:' . $result->name, [
				'added_at' => gmdate('c'),
				'by' => $this->identity->id,
				/*
				 * Grouping the picker by "albums" would be true and useless on a
				 * site with nine of them, so a module may name the group itself.
				 *
				 * $group was used here without being a parameter, so every upload
				 * through a module raised a warning and filed the picture under
				 * nothing — and on a server with display_errors on, printed that
				 * warning into the response.
				 */
				'album' => $group !== '' ? $group : $this->module,
			]);
		}

		return $result;
	}

	/**
	 * Remove a file this module added.
	 *
	 * Only its own: the ownership record written by addMedia() is the whole
	 * check. Sharing one media folder means a module could otherwise delete the
	 * site's logo, or another module's photographs, and neither is something a
	 * photo album has any business doing.
	 *
	 * Returns false for a file it does not own, which is deliberately the same
	 * answer as for a file that does not exist — a module has no reason to learn
	 * which of the two it hit.
	 */
	public function removeMedia(string $name): bool
	{
		$name = basename($name);

		if ($this->library === null || $this->get('media:' . $name) === null) {
			return false;
		}

		$removed = $this->library->delete($name);
		if ($removed) {
			$this->delete('media:' . $name);
		}

		return $removed;
	}

	/** Whether this module added the file, and may therefore remove it. */
	public function ownsMedia(string $name): bool
	{
		return $this->get('media:' . basename($name)) !== null;
	}

	// ---- who is asking --------------------------------------------------

	public function user(): ModuleIdentity
	{
		return $this->identity;
	}

	/**
	 * Whether the signed-in account may manage this module.
	 *
	 * Routes are already guarded, so this is for decisions inside a screen —
	 * showing a delete button, say. It goes through ModulePermission so that
	 * per-module access control has one implementation to change.
	 */
	public function can(): bool
	{
		return ModulePermission::allows($this->identity->role, $this->module, $this->accessList);
	}

	public function isAtLeast(Role $role): bool
	{
		return $this->identity->role === $role || $this->identity->role === Role::Owner;
	}

	// ---- output ---------------------------------------------------------

	/** @param array<string,string|int|float> $replacements */
	public function t(string $key, array $replacements = [], ?int $count = null): string
	{
		return $this->translator->get($key, $replacements, $count);
	}

	/**
	 * The language this screen is speaking.
	 *
	 * A module is handed a translator and may reasonably ask which language it
	 * is: how a date reads, which separator a number takes, whether a name goes
	 * first or last. Those are the module's own decisions and it needs the
	 * language to make them.
	 *
	 * Not a way into site settings. It answers one question, and the module still
	 * decides what to do about the answer — a currency, for instance, does not
	 * follow from a language: English is the pound, the dollar and the euro.
	 */
	public function locale(): string
	{
		return $this->translator->locale()->code;
	}

	/**
	 * Render a template inside the admin layout and send it.
	 *
	 * The template comes from the module's own view directory when it has one,
	 * and the layout always comes from core — a module supplies the middle of the
	 * page, never the frame around it.
	 *
	 * @param array<string,mixed> $data
	 */
	public function render(string $template, array $data = []): never
	{
		$view = $this->moduleView ?? $this->adminView;
		$body = $view->partial($template, $data);

		Response::html($this->adminView->wrap($body, $data));
	}

	/** @param array<string,string|int> $params */
	public function back(string $route, array $params = []): never
	{
		Response::redirect('admin.php?' . http_build_query(['p' => $route] + $params, '', '&', PHP_QUERY_RFC3986));
	}
}
