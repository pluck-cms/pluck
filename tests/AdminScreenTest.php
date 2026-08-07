<?php
declare(strict_types=1);

namespace Pluck\Tests;

use Pluck\Admin\Routes;
use Pluck\Model\Page;
use Pluck\Model\Role;
use Pluck\Model\User;
use Pluck\Module\Modules;
use Pluck\Storage\DriverFactory;

/**
 * Every admin screen, opened.
 *
 * This exists because the suite passed with 2862 assertions while the settings
 * screen died on every request — a call to a method that does not exist, found
 * by somebody clicking on it, on a server.
 *
 * Deliberately shallow. It does not check what a screen says, only that asking
 * for it comes back at all, because the faults it catches are the ones where a
 * rename left one caller behind. Those are invisible to a unit test and obvious
 * in a browser, and this is the gap between the two.
 *
 * Each screen runs in a process of its own: Response::html() ends in exit(), so
 * nothing in this process could watch a controller finish. That turns out to be
 * the more honest arrangement anyway — it is the path a real request takes,
 * through the real router, with the real views and the real theme.
 */
final class AdminScreenTest extends TestCase
{
	/** phpinfo() prints its own document; a download streams bytes. */
	private const NOT_A_SCREEN = ['diagnostics.phpinfo', 'backup.download'];

	public function run(): void
	{
		$this->group('every screen renders', fn () => $this->everyScreen());
	}

	private function everyScreen(): void
	{
		$root = $this->buildSite();
		$opened = 0;

		foreach (Routes::table(Modules::registry())->all() as $route) {
			// Screens somebody navigates to. A POST changes something and deserves
			// its own test; this is about what has to come back.
			if ($route->method !== 'GET' || in_array($route->name, self::NOT_A_SCREEN, true)) {
				continue;
			}

			[$output, $status] = $this->open($root, $route->name);

			// A fatal reaches here as a non-zero exit with the message attached,
			// which is exactly what the server log showed.
			$this->assertSame(
				0,
				$status,
				sprintf('%s opens%s', $route->name, $status === 0 ? '' : ': ' . $this->firstLine($output)),
			);

			if ($status !== 0) {
				continue;
			}

			// 200 with a body, or a redirect somewhere. A 200 with nothing in it is
			// a screen that rendered emptiness, which is a fault that looks fine.
			preg_match('/\[pluck-screen (\d+)( -> [^\]]+)?\]/', $output, $m);
			$code = (int) ($m[1] ?? 0);
			$body = trim(preg_replace('/\[pluck-screen [^\]]+\]/', '', $output) ?? '');

			$this->assertTrue(
				in_array($code, [200, 302, 303], true),
				sprintf('%s answers with a usable status, got %d', $route->name, $code),
			);

			$this->assertTrue(
				$code !== 200 || $body !== '',
				$route->name . ' has something on it',
			);

			// Signed in, so a screen that answers by sending somebody to the
			// sign-in page is a fault rather than a pass. This is the assertion
			// that stops the whole test from measuring the sign-in redirect.
			$this->assertFalse(
				str_contains($output, '-> ') && str_contains($output, 'p=signin'),
				$route->name . ' does not bounce a signed-in owner to the sign-in page',
			);

			$opened++;
		}

		// Against this quietly skipping everything, which is how a smoke test
		// becomes decoration.
		$this->assertTrue($opened >= 15, 'a realistic number of screens was opened, not none');
	}

	/** @return array{0:string,1:int} */
	private function open(string $root, string $name): array
	{
		$command = sprintf(
			'%s %s %s %s 2>&1',
			escapeshellarg(PHP_BINARY),
			escapeshellarg(dirname(__DIR__) . '/tests/support/open-screen.php'),
			escapeshellarg($root),
			escapeshellarg($name),
		);

		$output = [];
		$status = 0;
		exec($command, $output, $status);

		return [implode("\n", $output), $status];
	}

	private function firstLine(string $output): string
	{
		foreach (explode("\n", trim($output)) as $line) {
			if (trim($line) !== '') {
				return trim($line);
			}
		}

		return '(no output)';
	}

	private function buildSite(): string
	{
		$root = $this->tempDir('pluck-screens');

		foreach (['data', 'media'] as $directory) {
			@mkdir($root . '/' . $directory, 0o755, true);
		}

		// The real views, themes and wording. A screen that renders against a stub
		// proves nothing about the screen people see.
		foreach (['themes', 'lang'] as $directory) {
			$this->mirror(dirname(__DIR__) . '/' . $directory, $root . '/' . $directory);
		}

		$storage = DriverFactory::make(DriverFactory::FLAT_FILE, $root . '/data');
		$storage->install();
		$storage->setSetting('site_title', 'Screens');
		$storage->savePage(new Page(path: 'about', title: 'About', content: '<p>Hello</p>'));
		$storage->saveUser(User::create('tester', 'a-decent-long-password', Role::Owner));

		return $root;
	}

	private function mirror(string $from, string $to): void
	{
		@mkdir($to, 0o755, true);

		foreach (scandir($from) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			is_dir($from . '/' . $entry)
				? $this->mirror($from . '/' . $entry, $to . '/' . $entry)
				: @copy($from . '/' . $entry, $to . '/' . $entry);
		}
	}
}
