<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\Http\Router;

/** The albums module's admin side. */
final class AlbumsAdminModule implements AdminModule
{
	public function name(): string
	{
		return 'albums';
	}

	public function adminRoutes(Router $router): void
	{
		$permission = ModulePermission::manage('albums');

		$router->get('module.albums.index', AlbumsAdminController::class, 'index', $permission, module: 'albums');
		$router->get('module.albums.edit', AlbumsAdminController::class, 'edit', $permission, module: 'albums');
		$router->post('module.albums.save', AlbumsAdminController::class, 'save', $permission, module: 'albums');
		$router->post('module.albums.delete', AlbumsAdminController::class, 'delete', $permission, module: 'albums');
		$router->post('module.albums.move', AlbumsAdminController::class, 'move', $permission, module: 'albums');

		$router->post('module.albums.image.add', AlbumsAdminController::class, 'addImage', $permission, module: 'albums');
		$router->post('module.albums.image.pick', AlbumsAdminController::class, 'pickImage', $permission, module: 'albums');
		$router->post('module.albums.image.save', AlbumsAdminController::class, 'saveImage', $permission, module: 'albums');
		$router->post('module.albums.image.remove', AlbumsAdminController::class, 'removeImage', $permission, module: 'albums');
		$router->post('module.albums.image.move', AlbumsAdminController::class, 'moveImage', $permission, module: 'albums');
	}

	public function navigation(): ?array
	{
		return [
			'route' => 'module.albums.index',
			'label' => 'albums.nav.albums',
			'permission' => ModulePermission::manage('albums'),
		];
	}

	public function viewDir(): ?string
	{
		return null;
	}
}
