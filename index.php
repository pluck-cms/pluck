<?php
declare(strict_types=1);

/**
 * Site front controller.
 *
 * Resolves an address to one of three things — a module, a page, or nothing —
 * and renders it through the active theme. Everything it needs is a read; the
 * site path never writes, which is what lets a site run from a checkout with
 * only data/ and media/ writable.
 */

require __DIR__ . '/src/autoload.php';

use Pluck\Admin\Probe;
use Pluck\Bootstrap;
use Pluck\Form\Guard;
use Pluck\Module\PublicForm;
use Pluck\Failure;
use Pluck\Module\Modules;
use Pluck\Security\Csp;
use Pluck\Site\Redirects;
use Pluck\Site\Search;
use Pluck\Site\SiteRenderer;
use Pluck\Site\Urls;
use Pluck\Support\Slug;
use Pluck\Theme\ThemeRepository;

/*
 * The rewrite probe, answered before anything else.
 *
 * Before Bootstrap::boot(), not after — boot() creates directories, and on an
 * install whose data/ is not writable it throws. Answering the probe after that
 * meant the one check that tells you whether rewriting works was the check that
 * stopped working first on a misconfigured server, which is exactly when someone
 * is trying to find out.
 *
 * Reaching this line at all is the answer: the settings screen asks for this
 * path without a query string, so a reply can only mean the web server handed a
 * path-style address to index.php.
 */
if (Slug::path(Urls::detectPath($_SERVER)) === Probe::PATH) {
	header('Content-Type: text/plain; charset=utf-8');
	header('Cache-Control: no-store');
	echo Probe::MARKER;
	exit;
}


// A plain 500 page instead of a stack trace, from here on.
Failure::install(__DIR__ . '/data');

$app = Bootstrap::boot(__DIR__);


if (!$app->isInstalled()) {
	header('Location: install.php');
	exit;
}

$storage = $app->storage();

/*
 * The headers, once storage exists to be asked.
 *
 * Frame hosts come from a setting, so a page can embed a video only where an
 * owner has named the service — see Csp::frameHostNames() for what is accepted.
 * Sent here rather than earlier because it needs that setting, and reading it
 * before the driver is built is how this was written the first time.
 */
Csp::send($app->csp()->siteHeaders((array) $storage->getSetting('frame_hosts', [])));
$translator = $app->translator();

$urls = new Urls(
	Urls::detectBase($_SERVER),
	(bool) $storage->getSetting('pretty_urls', false),
);

// Two ways in, one normaliser. With rewriting on, the address is in the path;
// without it, in ?page=. Both go through Slug::path(), so a request can never
// describe something the storage layer would refuse to look up.
$requested = Slug::path((string) ($_GET['page'] ?? '')) ?: Slug::path(Urls::detectPath($_SERVER));
$modules = Modules::registry($translator, $storage);

$themes = new ThemeRepository(__DIR__ . '/themes');
$renderer = new SiteRenderer(
	$themes->active($storage, $storage->findPage($requested)?->theme),
	$storage,
	$urls,
	$app->csrf(),
	$app->csp(),
	$translator,
	$modules,
);

// ---- resolve ------------------------------------------------------------

$body = null;

// Search is the site's own address rather than a module's, so it is answered
// before the registry is consulted — and ModuleRegistry refuses to mount a module
// there, so the two can never disagree about who owns it.
if ($requested === 'search' && (bool) $storage->getSetting('search_enabled', false)) {
	$query = Search::normalise((string) ($_GET['q'] ?? ''));

	echo $renderer->search(
		$query,
		Search::isUsable($query) ? (new Search($storage, $modules))->find($query) : [],
	);
	exit;
}

/*
 * A 4.x address in its query-string form.
 *
 * Checked here rather than at the 404, because ?file=blog&blog=name carries no
 * page at all: Pluck reads that as a request for the front page and serves it
 * happily, so the request never reaches the not-found path. Somebody following
 * an old link would land on the home page and conclude the post was gone.
 */
if (($_GET['file'] ?? '') !== '') {
	$legacy = (new Redirects($storage))->fromQuery($_GET);

	if ($legacy !== null) {
		header('Location: ' . $urls->to($legacy), true, 301);
		exit;
	}
}

/*
 * A visitor sending something.
 *
 * The site path is otherwise entirely read-only, which is what lets Pluck run
 * from a checkout with only data/ and media/ writable. This is the one exception,
 * and it is kept narrow: a POST only ever reaches a module that says it accepts
 * one, and only after the Guard has passed it.
 *
 * The guard runs here rather than inside the module. A module that ran its own
 * checks would be a module that could forget one, and there would be no single
 * place left to read to find out what protects a public form.
 */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	$target = $modules->resolve($requested);
	$module = $target[0] ?? null;

	if (!$module instanceof PublicForm) {
		http_response_code(405);
		header('Allow: GET');
		/*
 * Before giving up: is this somewhere a page used to be?
 *
 * Last, deliberately. A rule can never shadow real content, nothing that exists
 * pays for this lookup, and an old address becomes usable again the moment
 * somebody creates a page there — without anybody remembering to delete a rule.
 */
$movedTo = (new Redirects($storage))->to($requested, $_GET);

if ($movedTo !== null) {
	// 301: these addresses moved when the site was migrated and are not coming
	// back, and a search engine should update rather than keep asking.
	header('Location: ' . $urls->to($movedTo), true, 301);
	exit;
}

echo $renderer->notFound($requestedPath);
		exit;
	}

	$guard = new Guard($storage, $app->session(), $translator);
	$verdict = $guard->check($_POST, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $module->name());

	if (!$verdict->ok) {
		// Back to the form with a reason. Not a bare error page: whoever sent this
		// is far more likely to be a person who typed a sum wrong than a bot.
		header('Location: ' . $urls->to($requested . ($target[1] === '' ? '' : '/' . $target[1]))
			. '?error=' . rawurlencode(str_replace('form.error.', '', $verdict->reason)));
		exit;
	}

	if (!$verdict->stored) {
		// The honeypot caught it. Told it went fine, written nowhere.
		header('Location: ' . $urls->to($requested) . '?sent=1');
		exit;
	}

	$outcome = $module->accept($target[1] ?? '', $_POST, $storage, $urls, $guard);

	header('Location: ' . ($outcome['redirect'] ?? $urls->to($requested))
		. (str_contains((string) $outcome['redirect'], '?') ? '&' : '?')
		. ($outcome['ok'] ? 'said=' : 'error=') . rawurlencode(str_replace(['form.error.', 'blog.', 'contact.'], '', $outcome['message'])));
	exit;
}

/*
 * A page the admin is looking at but has not saved.
 *
 * Put there by the editor's Preview button, in this visitor's own session, and
 * read back only when ?preview=1 says to. Nothing on the site is changed by it:
 * the page is substituted into the render and the store never hears about it.
 *
 * Anybody else asking for the same address gets the published page, because the
 * only copy of the unsaved one lives in the session of the person editing.
 */
$previewing = null;

if (($_GET['preview'] ?? '') === '1') {
	$held = $app->session()->get(\Pluck\Admin\PageController::PREVIEW_KEY);

	if (is_array($held) && ($held['path'] ?? '') === $requested && time() - (int) ($held['at'] ?? 0) < 3600) {
		$previewing = $storage->findPage($requested)
			?? new \Pluck\Model\Page(path: $requested !== '' ? $requested : 'preview', title: '');

		$previewing->title = (string) ($held['title'] ?? $previewing->title);
		$previewing->content = (string) ($held['content'] ?? '');
	}
}

$mounted = $modules->resolve($requested);
if ($mounted !== null) {
	[$module, $rest] = $mounted;

	// Only parameters a module declared interest in reach it, as strings. A
	// module never sees $_GET, so it cannot be handed an array where it expects
	// a string — the shape that produced a decade of type-juggling bugs.
	$query = array_filter(['p' => (string) ($_GET['p'] ?? '')]);

	$view = $module->render($rest, $query, $storage, $urls);
	if ($view !== null) {
		$body = $renderer->module($view, $requested);
	}
} else {
	$page = $requested === ''
		// The front page is the first page in menu order, which is what the admin
		// list shows at the top, so "move it up" and "make it the front page" are
		// the same action rather than two.
		? ($storage->listPages(null, false)[0] ?? null)
		: $storage->findPage($requested);

	// An unsaved page stands in for its saved self, and exists even when the
	// saved one does not — a page being written for the first time is previewable.
	if ($previewing !== null) {
		$renderer->previewing($previewing);
		$page = $previewing;
	}

	// Hidden means out of the menu, not withdrawn: the admin form says the page
	// still works if you know the address, so only a missing page is a 404.
	if ($page !== null) {
		$body = $renderer->page($page);
	}
}

/*
 * A site with nothing in it yet.
 *
 * "Page not found" is true and useless: nothing is missing, nothing has been
 * written. Somebody who has just installed Pluck and typed their own address
 * deserves to be told that, and told where to go — not handed a 404 that reads
 * like a broken install and sends them looking for what they did wrong.
 */
if ($body === null && $requested === '' && $storage->allPages(includeHidden: true) === []) {
	$body = $renderer->emptySite();
} elseif ($body === null) {
	http_response_code(404);
	$body = $renderer->notFound($requested);
}

header('Content-Type: text/html; charset=utf-8');
echo $body;
