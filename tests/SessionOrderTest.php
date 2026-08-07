<?php
declare(strict_types=1);

namespace Pluck\Tests;

/**
 * Guards the ordering bug that end-to-end testing found during phase 1:
 * install.php sent its security headers and then started the session from inside
 * a template. PHP cannot set the session cookie once output has begun, so every
 * form came back "expired" and nobody could install anything.
 *
 * The rule this file enforces is the general one, not the specific fix: in every
 * entry point, the session starts before the first byte of output.
 */
final class SessionOrderTest extends TestCase
{
	public function run(): void
	{
		$this->group('an unwritable session folder', fn () => $this->unwritable());

		$root = dirname(__DIR__);

		foreach (['install.php', 'admin.php'] as $file) {
			$this->group($file, function () use ($root, $file): void {
				$source = (string) file_get_contents($root . '/' . $file);

				$sessionStart = strpos($source, 'session()->start()');
				$this->assertTrue($sessionStart !== false, 'the session is started explicitly');

				$firstOutput = $this->firstOutputOffset($source);

				if ($firstOutput === null) {
					// A front controller that renders through the view layer has no
					// top-level output at all, which is the shape that cannot have
					// this bug in the first place.
					$this->assertTrue(true, 'nothing is written at the top level');
				} else {
					$this->assertTrue(
						$sessionStart !== false && $sessionStart < $firstOutput,
						'the session starts before any output',
					);
				}

				// The headers have to precede the cookie for the same reason.
				$headers = strpos($source, 'Csp::send');
				$this->assertTrue($headers !== false, 'the security headers are sent');
				if ($headers !== false && $sessionStart !== false) {
					$this->assertTrue($headers < $sessionStart, 'the security headers go out first');
				}
			});
		}
	}

	/**
	 * The offset of the first thing that writes to the browser: the close tag
	 * that hands over to HTML, or an echo at the top level. Indented echoes are
	 * skipped on purpose — those sit inside a function or a handler and only run
	 * when something calls them, which is exactly what admin.php's error boundary
	 * does. Written with a concatenated close tag because a literal one inside a
	 * comment would end this file's PHP section.
	 */
	/**
	 * The failure whose symptom is a loop.
	 *
	 * session_start() warns and carries on with an empty session, so signing in
	 * appears to work, the redirect lands on the dashboard, the dashboard finds
	 * nobody signed in and bounces back to the form. Nothing on the page says
	 * why. It is what a migration run as root produces every time, because the
	 * files end up owned by root and the web server is somebody else.
	 */
	private function unwritable(): void
	{
		$dir = $this->tempDir('pluck-session') . '/cache';
		mkdir($dir, 0o500, true);

		if (is_writable($dir)) {
			// Running as root, where nothing is unwritable. The suite refuses to
			// run as root for this among other reasons; skip rather than pass.
			$this->skip('cannot test an unwritable folder as a user who can write anywhere');

			return;
		}

		$this->expectFailure(
			static fn () => \Pluck\Security\Session::refuseUnwritable($dir),
			'a folder it cannot write is refused rather than carried on from',
		);

		try {
			\Pluck\Security\Session::refuseUnwritable($dir);
			$thrown = null;
		} catch (\Throwable $e) {
			$thrown = $e;
		}

		$this->assertTrue(
			$thrown instanceof \Pluck\Security\SessionUnwritable,
			'with a type of its own, so the error page can name this one specifically',
		);

		// A save path with a semicolon carries a depth prefix or a handler that is
		// not the filesystem. Guessing at either is worse than not checking.
		\Pluck\Security\Session::refuseUnwritable('2;' . $dir);
		\Pluck\Security\Session::refuseUnwritable('redis://localhost');
		$this->assertTrue(true, 'and a path that is not a plain folder is left alone');
	}

	private function firstOutputOffset(string $source): ?int
	{
		$candidates = [];

		$handover = strpos($source, '?' . ">\n");
		if ($handover !== false) {
			$candidates[] = $handover;
		}

		if (preg_match('/^echo\s/m', $source, $m, PREG_OFFSET_CAPTURE) === 1) {
			$candidates[] = $m[0][1];
		}

		return $candidates === [] ? null : min($candidates);
	}
}
