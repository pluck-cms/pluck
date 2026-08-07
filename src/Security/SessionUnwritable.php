<?php
declare(strict_types=1);

namespace Pluck\Security;

use RuntimeException;

/**
 * The session directory exists and cannot be written to.
 *
 * Its own type because Failure treats it specially: it is the one failure whose
 * symptom is a redirect loop rather than an error, and a loop tells nobody
 * anything. The usual cause is a migration or an install run from a shell as a
 * different user than the web server runs as.
 */
final class SessionUnwritable extends RuntimeException
{
	public function __construct(public readonly string $path)
	{
		parent::__construct('The session folder is not writable: ' . $path);
	}
}
