<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Admin\Routes;
use Pluck\Http\Route;

/**
 * The "make an admin click a link" surface.
 *
 * Version 4 changed state from GET handlers — deleting a page and reordering the
 * menu both read their target out of the query string — and defended the whole
 * lot with a single Referer check. Version 5's rule is narrower and mechanical:
 * GET reads, POST changes, and every POST carries a session token verified in the
 * router before a controller exists.
 *
 * The rule only holds while the route table obeys it, so the reviewed set of GET
 * routes is written down here. Adding a GET route fails this test, which is the
 * point: it forces someone to say out loud that the new screen only reads.
 */
final class CsrfSurfaceTest extends TestCase
{
	/**
	 * Every GET route, each one reviewed as read-only. A route that changes
	 * anything does not belong in this list — give it a POST instead.
	 */
	/**
	 * The preview endpoint returns sanitised page content to whoever asks.
	 *
	 * It changes nothing, which is why it is easy to think of as harmless — but a
	 * POST with a token is what stops another site from using somebody's admin
	 * session as a sanitiser oracle, and it has to stay behind the permission that
	 * lets you see pages at all.
	 */
	/**
	 * The visible editor is a convenience, never a boundary.
	 *
	 * It is the piece most likely to be rewritten or replaced, and the reason it
	 * is safe to have at all is that everything it produces goes through the
	 * sanitiser on save. Asserted here so that stays true: a save path that
	 * trusted the editor's output because it "already cleaned it" would be the
	 * quiet way to lose the allow-list.
	 */
	/**
	 * No form inside another form.
	 *
	 * HTML does not allow it and browsers do not recover from it gracefully: the
	 * parser closes the outer form where the inner one starts, so every field and
	 * every button after that point silently stops belonging to it. The symptom
	 * is a Save button that does nothing, with no error anywhere — which is what
	 * two <dialog> elements dropped into the page form produced.
	 */
	/**
	 * An admin screen is never stored by the browser.
	 *
	 * Without this a browser may serve the copy it already had after a save, so
	 * the form shows the values from before it — and the next save posts those
	 * back, undoing the change. It presents as "that setting does not save",
	 * which is an hour spent looking at the wrong file.
	 *
	 * Asserted against the source rather than a response, because header() does
	 * nothing under the CLI SAPI and a test that watched for the header there
	 * would pass whether or not it was ever sent.
	 */
	/**
	 * Save and close closes.
	 *
	 * The decision lives in its own method rather than inside the redirect,
	 * because header() does nothing under the CLI SAPI: a test watching for a
	 * Location would pass whether or not one was ever sent. This is the whole of
	 * what that button means.
	 */
	/**
	 * Nothing shipped contains a working webshell.
	 *
	 * Several tests need one — proving an archive with a shell in it is refused
	 * means having a shell to refuse — and written as a literal it is a literal.
	 * A distribution zip full of them trips every scanner every user runs, which
	 * is thirty support calls for thirty clients, and the scanner is not wrong.
	 *
	 * TestCase::webshell() assembles the same bytes at run time, so the tests are
	 * exactly as strong and the pattern is not in the file.
	 */
	/**
	 * Everything picks a theme the way the site picks one.
	 *
	 * ThemeRepository::active() falls back when the stored theme is missing —
	 * which matters, because a theme is the part of an install somebody edits over
	 * FTP at eleven at night, and a typo should cost them their design for a
	 * moment rather than their website.
	 *
	 * Theme::load() by name throws instead. The page editor's preview used it, so
	 * it died on any install whose theme setting named something absent — which
	 * was every fresh install, because the installer stored 'plain', a theme
	 * renamed long ago whose name that line never followed. The site rendered
	 * perfectly the whole time.
	 *
	 * Asserted on the source: AdminScreenTest opens GET routes and the preview is
	 * a POST, so nothing there exercises it. Second best, and said out loud.
	 */
	private function themeIsResolvedOnce(): void
	{
		/*
		 * Nowhere reads the theme setting for itself.
		 *
		 * Three places had grown their own version: the site (correct), the page
		 * preview (threw on a missing theme), and the stylesheet editor (offered
		 * to edit a file in a directory that was not there). All three were the
		 * same rule, and the two copies were the two that broke.
		 */
		$offenders = [];

		foreach (['src/Admin', 'src/Site'] as $directory) {
			foreach (glob(dirname(__DIR__) . '/' . $directory . '/*.php') ?: [] as $file) {
				$source = (string) file_get_contents($file);

				if (str_contains($source, "getSetting('theme'") || str_contains($source, 'Theme::load(')) {
					$offenders[] = basename($file);
				}
			}
		}

		/*
		 * Nothing reads it raw any more.
		 *
		 * SettingsController used to, legitimately: it showed the stored name in a
		 * dropdown. That dropdown moved to Appearance, and ThemeController resolves
		 * through ThemeRepository like everything else — so the exception this test
		 * carried is no longer needed, which is the good direction for an exception
		 * to move in.
		 */
		$this->assertSame(
			[],
			$offenders,
			'every screen resolves the theme the way the site does',
		);

		// The installer names one that exists.
		$installer = (string) file_get_contents(dirname(__DIR__) . '/src/Install/Installer.php');
		preg_match("/setSetting\('theme', '([a-z0-9-]+)'\)/", $installer, $m);

		$this->assertTrue(
			is_dir(dirname(__DIR__) . '/themes/' . ($m[1] ?? 'nothing')),
			'a fresh install stores the name of a theme that is there',
		);
	}

	private function noLiteralShells(): void
	{
		$root = dirname(__DIR__);
		$found = [];

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
		);

		foreach ($iterator as $file) {
			if (!$file->isFile() || $file->getExtension() !== 'php') {
				continue;
			}

			$path = $file->getPathname();
			if (str_contains($path, '/data/') || str_contains($path, '/vendor/')) {
				continue;
			}

			// Assembled here for the same reason it is assembled everywhere else:
			// a test that guards against a pattern must not contain it.
			$danger = '/(?:' . implode('|', ['sys' . 'tem', 'ev' . 'al', 'pass' . 'thru', 'shell_' . 'exec'])
				. ')\s*\(\s*\$_(?:GET|POST|REQUEST)/';

			if (preg_match($danger, (string) file_get_contents($path)) === 1) {
				$found[] = str_replace($root . '/', '', $path);
			}
		}

		$this->assertSame([], $found, 'no shipped file contains a literal webshell');
	}

	private function saveAndClose(): void
	{
		$this->assertSame(
			['pages', []],
			\Pluck\Admin\PageController::whereAfterSaving('close', 'about'),
			'save and close returns to the list',
		);

		$this->assertSame(
			['page.edit', ['path' => 'about']],
			\Pluck\Admin\PageController::whereAfterSaving('', 'about'),
			'while a plain save stays on the page being written',
		);
	}

	private function adminIsNotCached(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/src/Http/Response.php');

		$this->assertTrue(
			str_contains($source, 'no-store'),
			'an HTML response tells the browser not to store it',
		);
		$this->assertTrue(
			str_contains($source, 'private'),
			'and not to let a shared cache keep it either',
		);
	}

	private function noNestedForms(): void
	{
		$views = dirname(__DIR__) . '/views';
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($views, \FilesystemIterator::SKIP_DOTS),
		);

		foreach ($iterator as $file) {
			if (!$file->isFile() || $file->getExtension() !== 'php') {
				continue;
			}

			$source = (string) file_get_contents($file->getPathname());
			$depth = 0;
			$worst = 0;

			foreach (preg_split('/(<form\b|<\/form>)/i', $source, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $piece) {
				if (stripos($piece, '<form') === 0) {
					$depth++;
					$worst = max($worst, $depth);
				} elseif (stripos($piece, '</form>') === 0) {
					$depth--;
				}
			}

			$this->assertSame(
				1,
				max(1, $worst),
				'no form is nested inside another in ' . basename((string) $file->getPathname()),
			);
		}
	}

	private function editorIsNotTrusted(): void
	{
		$controller = (string) file_get_contents(dirname(__DIR__) . '/src/Admin/PageController.php');

		$this->assertTrue(
			str_contains($controller, 'Sanitizer'),
			'the page controller still sanitises what it is given',
		);

		$editor = dirname(__DIR__) . '/assets/admin/editor.js';
		$this->assertTrue(is_file($editor), 'the editor ships as its own file');

		$source = (string) file_get_contents($editor);

		// A CSP with a nonce is worth nothing if a script writes inline handlers.
		$this->assertFalse(str_contains($source, 'onclick='), 'and adds no inline handlers');
		$this->assertFalse(str_contains($source, 'eval('), 'and evaluates nothing');

		// Pasted markup is parsed in a detached document, so nothing in it runs or
		// loads while it is being looked at.
		$this->assertTrue(
			str_contains($source, 'createHTMLDocument'),
			'pasted markup is parsed somewhere it cannot run',
		);
	}

	private function previewIsGuarded(): void
	{
		$route = Routes::table()->find('POST', 'page.preview');

		$this->assertTrue($route !== null, 'the preview is a POST');
		$this->assertSame('page.view', $route?->permission, 'behind the page permission');
		$this->assertSame(null, Routes::table()->find('GET', 'page.preview'), 'and not reachable by a plain GET');
	}

	private const READ_ONLY_GET_ROUTES = [
		'signin',    // renders the form
		'signout',   // asks for confirmation; the POST does the work
		'dashboard', 'pages', 'page.new', 'page.edit',
		'media', 'settings', 'users', 'user.new', 'user.edit', 'account',
		'access',    // renders the permission grid; both writes are POSTs
		'backups',   // lists the archives
		'messages',  // lists what came in through the contact form
		// Both show and change nothing: modules lists what is installed, themes
		// renders the picker and the parameter table. Their saves are POSTs.
		'modules',
		'themes',
		'stylesheet',
		'diagnostics',
		'diagnostics.phpinfo', // prints phpinfo(); owner-only, changes nothing
		// Sends a file. A GET because a browser download is a GET, and it changes
		// nothing — but it is owner-only and streams through the controller, since
		// the archives are not reachable over HTTP at all.
		'backup.download',
		'updates',      // lists releases and downloads
		'update.fetch', // hands over an archive; a browser download is a GET
	];

	public function run(): void
	{
		$this->previewIsGuarded();
		$this->editorIsNotTrusted();
		$this->noNestedForms();
		$this->adminIsNotCached();
		$this->saveAndClose();
		$this->noLiteralShells();
		$this->themeIsResolvedOnce();

		// Core routes only. Module routes go into the same table at runtime and are
		// covered by the same assertions below; they are left out of the reviewed
		// list because that list is a ratchet on what core ships, and a module
		// adding a screen should not need this file edited.
		$routes = Routes::table()->all();

		$get = [];
		$post = [];
		foreach ($routes as $route) {
			if ($route->method === 'GET') {
				$get[] = $route->name;
			} else {
				$post[] = $route->name;
			}
		}
		sort($get);

		$expected = self::READ_ONLY_GET_ROUTES;
		sort($expected);

		$this->assertSame($expected, $get, 'the set of GET routes is the reviewed read-only set');

		// Anything that reads like a change has to be a POST.
		foreach ($get as $name) {
			$looksLikeAChange = (bool) preg_match(
				'/\.(save|delete|remove|move|upload|submit|start|confirm|off|on|run|clear|empty|restore)$/',
				$name,
			);
			$this->assertFalse($looksLikeAChange, $name . ' is a GET, so it must not change anything');
		}

		// And the changes really are all POSTs, with the router verifying the token.
		foreach (['page.save', 'page.delete', 'page.move', 'media.upload', 'media.delete',
			'settings.save', 'user.save', 'user.delete', 'signout', 'signin.submit',
			'account.password', 'account.2fa.confirm'] as $name) {
			$this->assertTrue(in_array($name, $post, true), $name . ' is a POST');
		}

		// Guest routes are the sign-in flow and nothing else: a route reachable
		// without an account is the one place a permission slip costs the most.
		$guests = [];
		foreach ($routes as $route) {
			if ($route->guest) {
				$guests[] = $route->name;
			}
		}
		$guests = array_values(array_unique($guests));
		sort($guests);
		$this->assertSame(
			['signin', 'signin.submit', 'signin.totp', 'signout'],
			$guests,
			'only the sign-in flow is reachable without an account',
		);

		// The router, not a controller, is what checks the token. Assert the check
		// lives there, because moving it into controllers is how version 4 ended up
		// with handlers that forgot.
		$router = (string) file_get_contents(dirname(__DIR__) . '/src/Http/Router.php');
		$this->assertTrue(str_contains($router, 'csrf->isValid'), 'the router verifies the token itself');
		$this->assertTrue(
			str_contains($router, "\$route->method === 'POST'"),
			'the token is verified for every POST, not per route',
		);
		$this->assertFalse(
			str_contains($router, 'HTTP_REFERER'),
			'no referrer check: it fails open when the header is absent, which is trivial to arrange',
		);

		$this->assertTrue(
			$routes !== [] && $routes[0] instanceof Route,
			'the table really is routes',
		);
	}
}
