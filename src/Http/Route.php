<?php
declare(strict_types=1);

namespace Pluck\Http;

final class Route
{
	/**
	 * @param class-string $controller
	 */
	public function __construct(
		public readonly string $method,
		public readonly string $name,
		public readonly string $controller,
		public readonly string $action,
		public readonly ?string $permission = null,
		public readonly bool $guest = false,
		/**
		 * The module that owns this route, if any. A route with a module is
		 * dispatched with a ModuleContext instead of the admin Context, which is
		 * the whole of what stops a module reaching the user table. The auth,
		 * permission and CSRF checks in front of it are identical either way —
		 * that is why this is a field on the route rather than a second router.
		 */
		public readonly ?string $module = null,
	) {
	}
}
