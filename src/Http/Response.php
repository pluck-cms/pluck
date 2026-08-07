<?php
declare(strict_types=1);

namespace Pluck\Http;

/**
 * Sending a response is the last thing a handler does, so these helpers end the
 * request themselves. That keeps controllers free of "return early" plumbing and
 * removes any chance of output following a redirect header.
 */
final class Response
{
	public static function redirect(string $url, int $status = 302): never
	{
		if (!headers_sent()) {
			// Only relative admin URLs are ever passed here; refuse anything that
			// could turn into an open redirect.
			if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:|^//#', $url)) {
				$url = 'admin.php';
			}
			header('Location: ' . $url, true, $status);
		}
		exit;
	}

	public static function html(string $body, int $status = 200): never
	{
		if (!headers_sent()) {
			header('Content-Type: text/html; charset=utf-8', true, $status);

			/*
			 * Never stored, and never re-shown from history.
			 *
			 * An admin screen is a view of state that has just changed. Without
			 * this a browser may serve the copy it already had after a save, so the
			 * form shows the old values — and the next save posts them back,
			 * undoing the change. The symptom is "that setting does not save",
			 * which is a thing to chase in the wrong place for an hour.
			 *
			 * It also keeps a signed-out session's pages out of the back button.
			 */
			header('Cache-Control: no-store, no-cache, must-revalidate, private');
			header('Pragma: no-cache');
		}
		echo $body;
		exit;
	}

	/** HTMX reads this header and performs a full page navigation. */
	public static function htmxRedirect(string $url): never
	{
		if (!headers_sent()) {
			header('HX-Redirect: ' . $url);
		}
		exit;
	}
}
