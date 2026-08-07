<?php
declare(strict_types=1);

/**
 * Open one admin screen and print what a browser would have received.
 *
 * Its own process because Response::html() ends in exit(), which no test in the
 * same process can survive — and running it this way is the more honest test
 * anyway: it is the path a real request takes, through the real router, with the
 * real views.
 *
 * Usage: php tests/support/open-screen.php <root> <route>
 */

$root = $argv[1] ?? '';
$route = $argv[2] ?? '';

require dirname(__DIR__, 2) . '/src/autoload.php';

use Pluck\Admin\Context;
use Pluck\Admin\Routes;
use Pluck\Auth\Auth;
use Pluck\Auth\Throttle;
use Pluck\Bootstrap;
use Pluck\Http\Request;
use Pluck\Module\Modules;
use Pluck\Http\Flash;
use Pluck\View\View;

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/admin.php';
$_GET = ['p' => $route, 'path' => 'about', 'name' => 'file.txt'];
$_POST = [];

$app = Bootstrap::boot($root);
$storage = $app->storage();

$owner = $storage->findUserByUsername('tester');
if ($owner === null) {
	fwrite(STDERR, "fixture has no owner\n");
	exit(2);
}

$_GET['id'] = $owner->id;

/*
 * Signed in the way Auth expects to find it.
 *
 * Setting some plausible key was not enough: Auth checks a fingerprint of the
 * user agent and a start time as well, and without them every screen quietly
 * redirected to the sign-in page — which the test counted as a pass, because a
 * redirect is a perfectly good answer. A smoke test that signs in wrongly tests
 * the sign-in redirect twenty-seven times.
 */
$app->session()->start();
$app->session()->set('_pluck_uid', $owner->id);
$app->session()->set('_pluck_since', time());
$app->session()->set('_pluck_print', hash('sha256', ''));

$modules = Modules::registry($app->translator());
$found = Routes::table($modules)->find('GET', $route);

if ($found === null) {
	fwrite(STDERR, "no such route\n");
	exit(2);
}

$context = new Context(
	app: $app,
	request: Request::capture(),
	auth: new Auth($storage, $app->session(), new Throttle($storage), $modules),
	view: new View(dirname(__DIR__, 2) . '/views', $app->csrf(), $app->csp(), $app->translator()),
	flash: new Flash($app->session()),
	csrf: $app->csrf(),
	storage: $storage,
	modules: $modules,
);

/*
 * A redirect has no body, and an empty page is not the same as a redirect.
 *
 * Printed on the way out so the test can tell the two apart: a screen that comes
 * back 200 with nothing in it is a fault, and a 302 to somewhere is a screen
 * doing its job.
 */
register_shutdown_function(static function (): void {
	$location = '';
	foreach (headers_list() as $header) {
		if (stripos($header, 'location:') === 0) {
			$location = trim(substr($header, 9));
		}
	}

	$cache = '';
	foreach (headers_list() as $header) {
		if (stripos($header, 'cache-control:') === 0) {
			$cache = trim(substr($header, 14));
		}
	}

	printf(
		"\n[pluck-screen %d%s] %s\n",
		http_response_code() ?: 200,
		$location !== '' ? ' -> ' . $location : '',
		$cache,
	);
});

// A module screen runs with the narrowed context, exactly as admin.php gives it.
$controller = $found->module !== null
	? new ($found->controller)($context->forModule($found->module))
	: new ($found->controller)($context);

$controller->{$found->action}();
