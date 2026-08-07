<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\Http\Router;

/**
 * What a module has to provide to appear in the admin.
 *
 * The second half of the module contract. `SiteModule` renders; this one edits,
 * and the split is deliberate: rendering needs no write access and no session, so
 * the read path does not get either. A module may implement one, the other, or
 * both.
 *
 * Routes registered here go into the same table the rest of the admin uses. That
 * is the point rather than a convenience — `CsrfSurfaceTest` and
 * `RouteAccessTest` read that table, so a module route is held to the same rules
 * as a core one: every state change is a POST with a token, behind a named
 * permission. A module cannot opt out of that by registering its routes
 * somewhere else, because there is nowhere else.
 *
 * Controllers reached from these routes are handed a ModuleContext, not the
 * admin Context. See that class for what a module deliberately cannot do.
 */
interface AdminModule
{
	/** Machine name. Must match the SiteModule name where both exist. */
	public function name(): string;

	/**
	 * Register this module's admin routes.
	 *
	 * Route names are namespaced `module.<name>.<screen>` so two modules cannot
	 * collide, and permissions should come from ModulePermission rather than
	 * being written out, so that per-module access control has one place to
	 * change when it arrives.
	 */
	public function adminRoutes(Router $router): void;

	/**
	 * The entry in the admin navigation, or null for a module that is reached
	 * from somewhere else.
	 *
	 * @return array{route:string,label:string,permission:string}|null
	 *         `label` is a translation key, never a finished string
	 */
	public function navigation(): ?array;

	/**
	 * Where this module keeps its admin templates, or null to use the core
	 * `views/` directory. Templates are rendered inside the admin layout.
	 */
	public function viewDir(): ?string;
}
