<?php
declare(strict_types=1);

namespace Pluck\Admin;

use Pluck\Http\Router;
use Pluck\Module\ModuleRegistry;

/**
 * The admin route table.
 *
 * It lives here rather than inline in admin.php so that tests can ask the same
 * table the application uses which permission guards which screen. A copy in a
 * test would drift, and the first thing to drift would be the permission.
 */
final class Routes
{
	/**
	 * @param ModuleRegistry|null $modules modules contribute their own admin
	 *        routes to this same table, so CsrfSurfaceTest and RouteAccessTest
	 *        hold them to the same rules as core routes
	 */
	public static function table(?ModuleRegistry $modules = null): Router
	{
		$router = new Router();

		$router->get('signin', SignInController::class, 'form', guest: true);
		$router->post('signin.submit', SignInController::class, 'submit', guest: true);
		$router->post('signin.totp', SignInController::class, 'submitTwoFactor', guest: true);
		$router->post('signout', SignInController::class, 'signOut', guest: true);
		// GET only asks. Every state change in this table is a POST with a token,
		// and signing out is a state change like any other.
		$router->get('signout', SignInController::class, 'confirmSignOut', guest: true);

		$router->get('dashboard', DashboardController::class, 'show');

		$router->get('pages', PageController::class, 'index', 'page.view');
		$router->get('page.new', PageController::class, 'create', 'page.create');
		$router->get('page.edit', PageController::class, 'edit', 'page.view');
		$router->post('page.slug', PageController::class, 'slug', 'page.view');
		$router->post('page.preview', PageController::class, 'preview', 'page.view');
		$router->post('page.preview.site', PageController::class, 'previewToSite', 'page.view');
		$router->post('page.save', PageController::class, 'save', 'page.view');
		$router->post('page.delete', PageController::class, 'delete', 'page.view');
		$router->post('page.move', PageController::class, 'move', 'page.view');

		$router->get('media', MediaController::class, 'index', 'file.view');
		$router->post('media.upload', MediaController::class, 'upload', 'file.upload');
		$router->post('media.delete', MediaController::class, 'delete', 'file.view');
		$router->post('media.keep-both', MediaController::class, 'keepBoth', 'file.upload');

		$router->get('settings', SettingsController::class, 'show', 'settings.view');
		$router->post('settings.save', SettingsController::class, 'save', 'settings.edit');

		// Owner-only, enforced in the controller as well: an access-list screen
		// guarded by a permission the list itself can grant is a door with its own
		// key hanging on it.
		// Owner-only, enforced in the controller: an archive is a copy of every
		// account on the site.
		$router->get('updates', UpdateController::class, 'index', 'update.run');
		$router->get('update.fetch', UpdateController::class, 'fetch', 'update.run');
		$router->post('update.download', UpdateController::class, 'download', 'update.run');
		$router->post('update.apply', UpdateController::class, 'apply', 'update.run');
		$router->post('update.delete', UpdateController::class, 'delete', 'update.run');

		$router->get('modules', ModulesController::class, 'show', 'page.view');
		$router->get('themes', ThemeController::class, 'show', 'theme.view');
		$router->post('theme.save', ThemeController::class, 'save', 'theme.view');
		$router->post('theme.reset', ThemeController::class, 'reset', 'theme.view');
		$router->get('stylesheet', StylesheetController::class, 'edit', 'theme.view');
		$router->post('stylesheet.save', StylesheetController::class, 'save', 'theme.view');
		$router->post('stylesheet.undo', StylesheetController::class, 'undo', 'theme.view');

		$router->get('diagnostics', DiagnosticsController::class, 'index', 'settings.view');
		$router->get('diagnostics.phpinfo', DiagnosticsController::class, 'phpinfo', 'settings.view');

		$router->get('messages', MessagesController::class, 'index', 'page.view');
		$router->post('messages.read', MessagesController::class, 'markRead', 'page.view');
		$router->post('messages.delete', MessagesController::class, 'delete', 'page.view');
		$router->post('messages.tidy', MessagesController::class, 'tidy', 'page.view');

		$router->get('backups', BackupController::class, 'index', 'user.view');
		$router->get('backup.download', BackupController::class, 'download', 'user.view');
		$router->post('backup.create', BackupController::class, 'create', 'user.view');
		$router->post('backup.delete', BackupController::class, 'delete', 'user.view');
		$router->post('backup.restore', BackupController::class, 'restore', 'user.view');
		$router->post('backup.settings', BackupController::class, 'saveSettings', 'user.view');

		$router->get('access', AccessController::class, 'show', 'user.view');
		$router->post('access.save', AccessController::class, 'save', 'user.view');
		$router->post('access.reset', AccessController::class, 'reset', 'user.view');

		$router->get('users', UserController::class, 'index', 'user.view');
		$router->get('user.new', UserController::class, 'create', 'user.create');
		$router->get('user.edit', UserController::class, 'edit', 'user.edit');
		$router->post('user.save', UserController::class, 'save', 'user.edit');
		$router->post('user.delete', UserController::class, 'delete', 'user.delete');

		// Every signed-in account reaches its own account page, whatever the role.
		$router->get('account', AccountController::class, 'show');
		$router->post('account.profile', AccountController::class, 'saveProfile');
		$router->post('account.password', AccountController::class, 'savePassword');
		$router->post('account.2fa.start', AccountController::class, 'beginTwoFactor');
		$router->post('account.2fa.confirm', AccountController::class, 'confirmTwoFactor');
		$router->post('account.2fa.off', AccountController::class, 'disableTwoFactor');

		$modules?->registerAdminRoutes($router);

		return $router;
	}
}
