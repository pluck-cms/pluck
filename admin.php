<?php
declare(strict_types=1);

/**
 * Admin front controller.
 *
 * Everything under admin.php goes through this file and its route table, so
 * authentication, permissions and CSRF are checked in exactly one place. Version
 * 4 spread that across forty-odd includes in data/inc; several of them were the
 * bugs.
 */

require __DIR__ . '/src/autoload.php';

use Pluck\Admin\Context;
use Pluck\Admin\Routes;
use Pluck\Auth\Auth;
use Pluck\Auth\Throttle;
use Pluck\Backup\BackupManager;
use Pluck\Backup\ScheduledBackup;
use Pluck\Update\Updates;
use Pluck\Bootstrap;
use Pluck\Failure;
use Pluck\Http\Flash;
use Pluck\Http\Request;
use Pluck\Http\Response;
use Pluck\Model\Role;
use Pluck\Module\Modules;
use Pluck\Security\Csp;
use Pluck\View\View;

/*
 * The error boundary. display_errors is off, so without this an exception from
 * the storage layer reaches the browser as a blank 500 and the admin has no idea
 * what happened. Log the detail, show the person something honest.
 */
set_exception_handler(static function (Throwable $e): void {
	error_log('pluck admin: ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

	if (!headers_sent()) {
		http_response_code(500);
		header('Content-Type: text/html; charset=UTF-8');
	}

	echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width, initial-scale=1">'
		. '<title>Something went wrong</title>'
		. '<link rel="stylesheet" href="assets/admin/pluck.css"></head><body><div class="shell">'
		. '<div class="card"><h1>Something went wrong</h1>'
		. '<p>The admin hit an error it could not recover from. Nothing was saved.</p>'
		. '<p class="muted">The details are in the server error log. If this keeps happening, that log entry is'
		. ' the thing to report.</p>'
		. '<p><a href="admin.php">Back to the admin</a></p>'
		. '</div></div></body></html>';
});

Failure::install(__DIR__ . '/data');

$app = Bootstrap::boot(__DIR__);

if (!$app->isInstalled()) {
	Response::redirect('install.php');
}

// Headers first, then the session cookie, then a single byte of output. Starting
// the session from inside a template loses the cookie and every form then
// reports itself as expired.
Csp::send($app->csp()->adminHeaders());
$app->session()->start();

$storage = $app->storage();
$request = Request::capture();
// Modules are built before Auth, because the access list needs to know which
// module permissions exist before it can answer about them.
$modules = Modules::registry($app->translator(), $app->storage());
$auth = new Auth($storage, $app->session(), new Throttle($storage), $modules);
$flash = new Flash($app->session());
// The account's own language wins over the site's, so resolve the user before the
// first string is rendered.
$app->useLocaleFor($auth->user());
$view = new View(__DIR__ . '/views', $app->csrf(), $app->csp(), $app->translator());

$view->share('siteTitle', (string) $storage->getSetting('site_title', 'Pluck'));
$view->share('currentUser', $auth->user());
$view->share('activeRoute', $request->route);
$view->share('flashes', $flash->drain());
$view->share('title', 'Admin');

// Modules contribute their admin routes to the same table as everything else, so
// the sign-in, permission and CSRF checks in front of them are the same code.
$view->share('moduleNav', $modules->navigation($auth->user()?->role ?? Role::Author, $auth->accessList()));

// The layout asks about permissions through this rather than through the user,
// so the navigation obeys the access list like every other check does.
$view->share('can', static fn (string $permission): bool => $auth->can($permission));

/*
 * A badge in the navigation when a newer Pluck is known about.
 *
 * Read from the cached answer, never a fresh call: this runs on every admin page,
 * and asking GitHub each time would spend the rate limit telling one person the
 * same thing forty times.
 */
// The same answer the updates screen gives. These asked storage with different
// fallbacks, and since nothing writes that setting the fallback was the answer.
$view->share('updateAvailable', (new Updates(
	$app->rootDir . '/data',
	$storage,
	Updates::runningVersion($storage),
	null,
	Updates::channelOf($storage),
))->updateAvailable());

$router = Routes::table($modules);

/*
 * The backup nobody has to remember to make.
 *
 * Registered rather than called after dispatch(), because every controller ends
 * in a redirect or a rendered page and both exit. A shutdown function still runs
 * after that, and ScheduledBackup closes the connection before it starts writing,
 * so the person gets their page at the usual speed.
 *
 * Only for a signed-in owner: an archive holds every account on the site, and
 * making one is work nobody else should be able to trigger.
 */
register_shutdown_function(static function () use ($app, $storage, $auth): void {
	if ($auth->user()?->role !== Role::Owner) {
		return;
	}

	(new ScheduledBackup(
		new BackupManager(
			$app->rootDir . '/data',
			$app->rootDir . '/media',
			// The same answer everywhere: this ends up in a backup manifest, and two
			// backups of one install should not disagree about what it was running.
			Updates::runningVersion($storage),
		),
		$storage,
	))->runIfDue();
});

$router->dispatch(new Context(
	app: $app,
	request: $request,
	auth: $auth,
	view: $view,
	flash: $flash,
	csrf: $app->csrf(),
	storage: $storage,
	modules: $modules,
));
