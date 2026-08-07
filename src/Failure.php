<?php
declare(strict_types=1);

namespace Pluck;

use Pluck\Security\SessionUnwritable;
use Throwable;

/**
 * What a visitor sees when something goes badly wrong.
 *
 * Three things were wrong without this, all of them found the first time Pluck
 * ran behind a real web server rather than a faked `$_SERVER`.
 *
 * A stack trace reached the page. The php:apache images ship with
 * `display_errors` on, and plenty of shared hosting does too, so an uncaught
 * exception printed absolute filesystem paths and the call chain to whoever
 * asked for the page.
 *
 * It was served as `200 OK`. PHP sets 500 for an uncaught exception, but only
 * while nothing has been sent yet — and a warning printed first counts as
 * output. So a page that had already emitted an `mkdir()` warning went out as a
 * success, which monitoring reads as a healthy site.
 *
 * And the one failure that matters most on the hosting Pluck is for — `data/`
 * not being writable — arrived as a RuntimeException about mkdir, which tells
 * the person nothing about what to do. It has its own message.
 */
final class Failure
{
	/** @var list<string> paths that must be writable for Pluck to run at all */
	private static array $mustWrite = [];

	/**
	 * Take over error reporting for the rest of the request.
	 *
	 * @param string $dataDir named in the message when it turns out to be the
	 *        cause, since it is the cause most of the time
	 */
	public static function install(string $dataDir): void
	{
		self::$mustWrite = [$dataDir];

		// Buffer from here on, so a warning printed by something further down can
		// still be discarded and the status code is still ours to set. PHP flushes
		// what is left at the end of a request that goes well.
		ob_start();

		set_exception_handler(static function (Throwable $e): void {
			self::send($e);
		});

		// A fatal that is not an exception — running out of memory, a call to
		// something undefined — reaches this and nothing else.
		register_shutdown_function(static function (): void {
			$error = error_get_last();
			if ($error !== null && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) !== 0) {
				self::send(null);
			}
		});
	}

	private static function send(?Throwable $e): void
	{
		// Discard whatever was already printed. A warning above a stack trace is
		// still a warning on somebody's website, and leaving it there is also what
		// stops the 500 from being sent at all.
		while (ob_get_level() > 0) {
			ob_end_clean();
		}

		if (!headers_sent()) {
			http_response_code(500);
			header('Content-Type: text/html; charset=utf-8');
			header('Cache-Control: no-store');
		}

		echo self::page(self::explain($e));

		// Still worth having in the server log, where it belongs and where the
		// visitor cannot read it.
		if ($e !== null) {
			error_log('Pluck: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
		}
	}

	/**
	 * A sentence about what went wrong, for a person rather than a developer.
	 *
	 * Deliberately vague about anything except the one cause that is both common
	 * and actionable. Naming a class or a line number helps an attacker map the
	 * install and helps the site owner not at all.
	 */
	/*
	 * Escaper is deliberately not used in this file.
	 *
	 * Failure runs before Pluck is known to work — that is its whole job — and
	 * reaching for a class is how an error handler becomes a second error. The
	 * duplication is one function call, and it is the only place in the codebase
	 * where writing htmlspecialchars out is the right answer.
	 */

	private static function explain(?Throwable $e): string
	{
		// Named on purpose. Everything else here is deliberately vague, because
		// naming a class helps an attacker and not the site owner — but an
		// unwritable session folder produces a sign-in that loops silently, and a
		// person who is told "something went wrong" will look everywhere except at
		// file ownership.
		if ($e instanceof SessionUnwritable) {
			return 'Pluck cannot write to its session folder, so signing in cannot work. '
				. 'Give the web server write access to '
				. htmlspecialchars(basename(dirname($e->path)) . '/' . basename($e->path), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
				. '/ — if you installed or migrated from a shell, the files are probably owned by the wrong user.';
		}

		foreach (self::$mustWrite as $dir) {
			if (is_dir($dir) && !is_writable($dir)) {
				return 'Pluck cannot write to its data folder. Give the web server write access to '
					. htmlspecialchars(basename($dir), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
					. '/ and reload this page.';
			}

			if (!is_dir($dir)) {
				return 'Pluck cannot create its data folder. Give the web server write access to the '
					. 'folder Pluck is installed in, or create '
					. htmlspecialchars(basename($dir), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
					. '/ yourself and make it writable.';
			}
		}

		return 'Something went wrong and this page could not be shown. The details are in the server log.';
	}

	private static function page(string $message): string
	{
		return <<<HTML
			<!DOCTYPE html>
			<html lang="en">
			<head>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<meta name="robots" content="noindex">
			<title>This page is not available</title>
			<style>
			body { margin: 0; padding: 2rem 1.5rem; font: 1rem/1.6 system-ui, sans-serif; color: #1c1c1c; background: #fdfdfc; }
			.box { max-width: 34rem; margin-inline: auto; }
			h1 { font-size: 1.3rem; margin-top: 0; }
			p { color: #4a4a4a; }
			@media (prefers-color-scheme: dark) {
				body { color: #e8e6e1; background: #1a1917; }
				p { color: #a09c94; }
			}
			</style>
			</head>
			<body>
			<div class="box">
			<h1>This page is not available</h1>
			<p>{$message}</p>
			</div>
			</body>
			</html>
			HTML;
	}
}
