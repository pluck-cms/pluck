<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\Http\Router;

/**
 * The blog's admin side.
 *
 * Separate from BlogModule, which renders the site, because the two halves have
 * different needs: one reads and never writes, the other is the only thing that
 * writes. Both answer to the name "blog", which is what ties them to the same
 * module data.
 */
final class BlogAdminModule implements AdminModule
{
	public function name(): string
	{
		return 'blog';
	}

	public function adminRoutes(Router $router): void
	{
		$permission = ModulePermission::manage('blog');

		$router->get('module.blog.index', BlogAdminController::class, 'index', $permission, module: 'blog');
		$router->get('module.blog.new', BlogAdminController::class, 'create', $permission, module: 'blog');
		$router->get('module.blog.edit', BlogAdminController::class, 'edit', $permission, module: 'blog');
		$router->post('module.blog.save', BlogAdminController::class, 'save', $permission, module: 'blog');
		$router->post('module.blog.delete', BlogAdminController::class, 'delete', $permission, module: 'blog');

		$router->get('module.blog.categories', BlogAdminController::class, 'categories', $permission, module: 'blog');
		$router->post('module.blog.category.save', BlogAdminController::class, 'saveCategory', $permission, module: 'blog');
		$router->post('module.blog.category.delete', BlogAdminController::class, 'deleteCategory', $permission, module: 'blog');

		$router->get('module.blog.reactions', BlogAdminController::class, 'reactions', $permission, module: 'blog');
		$router->post('module.blog.reaction.status', BlogAdminController::class, 'setReactionStatus', $permission, module: 'blog');
		$router->post('module.blog.reaction.delete', BlogAdminController::class, 'deleteReaction', $permission, module: 'blog');

		$router->post('module.blog.settings', BlogAdminController::class, 'saveSettings', $permission, module: 'blog');
	}

	public function navigation(): ?array
	{
		return [
			'route' => 'module.blog.index',
			'label' => 'blog.nav.blog',
			'permission' => ModulePermission::manage('blog'),
		];
	}

	public function viewDir(): ?string
	{
		return null;
	}
}
