<?php
declare(strict_types=1);

namespace Pluck\Admin;

use Pluck\Auth\Auth;
use Pluck\Bootstrap;
use Pluck\Http\Flash;
use Pluck\Http\Request;
use Pluck\Security\Csrf;
use Pluck\Storage\StorageDriver;
use Pluck\Media\MediaLibrary;
use Pluck\Module\ModuleContext;
use Pluck\Module\ModuleIdentity;
use Pluck\Module\ModuleRegistry;
use Pluck\View\View;
use RuntimeException;

/**
 * Everything a controller is allowed to reach for, in one object.
 *
 * A container would be more flexible; this is more legible. Pluck's contributors
 * are often people fixing one screen, and a single constructor argument whose
 * type they can read beats resolving services by name.
 */
final class Context
{
	public function __construct(
		public readonly Bootstrap $app,
		public readonly Request $request,
		public readonly Auth $auth,
		public readonly View $view,
		public readonly Flash $flash,
		public readonly Csrf $csrf,
		public readonly StorageDriver $storage,
		public readonly ?ModuleRegistry $modules = null,
	) {
	}

	/**
	 * The narrowed context a module screen runs with.
	 *
	 * This is the only place a ModuleContext is made, and it is made from the
	 * admin's own Context, so the narrowing is visible in one file rather than
	 * being a promise spread across the module API. What is not passed here is
	 * what a module cannot reach: the storage driver goes in, but every method
	 * ModuleContext exposes is already fixed to this one module's data, and Auth
	 * and Bootstrap do not go in at all.
	 */
	public function forModule(string $module): ModuleContext
	{
		$user = $this->auth->user();
		if ($user === null) {
			// Unreachable through the router, which redirects an anonymous request
			// long before dispatch. Guarded anyway: a module screen that ran
			// without an identity would have to invent one.
			throw new RuntimeException('A module screen cannot run without a signed-in account.');
		}

		$viewDir = $this->modules?->adminModule($module)?->viewDir();

		return new ModuleContext(
			module: $module,
			storage: $this->storage,
			identity: ModuleIdentity::of($user),
			request: $this->request,
			flash: $this->flash,
			csrf: $this->csrf,
			translator: $this->app->translator(),
			adminView: $this->view,
			moduleView: $viewDir === null
				? null
				: new View($viewDir, $this->csrf, $this->app->csp(), $this->app->translator()),
			accessList: $this->auth->accessList(),
			library: new MediaLibrary($this->app->rootDir . '/media', $this->storage),
			mediaMaxBytes: (int) $this->storage->getSetting('media_max_bytes', MediaLibrary::DEFAULT_MAX_BYTES),
		);
	}

}
