<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\Form\Guard;
use Pluck\Site\Urls;
use Pluck\Storage\StorageDriver;

/**
 * A module that accepts something from a visitor.
 *
 * Separate from SiteModule on purpose. Rendering needs no write access and no
 * session; accepting a submission needs both, and the two should not be one
 * interface that every module implements half of.
 *
 * Whatever a module does here has already been through the Guard: honeypot,
 * timing, rate limit and whatever challenge the site has turned on. A module
 * does not repeat those checks and must not be tempted to skip them — the front
 * controller runs the guard, not the module.
 */
interface PublicForm
{
	/**
	 * Deal with a submission.
	 *
	 * $path is the address within this module's mount, the same shape render()
	 * receives. Returning a translation key names what to tell the visitor; the
	 * front controller renders the page again with that message.
	 *
	 * @param array<string,mixed> $post already checked by the Guard
	 * @return array{ok:bool,message:string,redirect:?string}
	 */
	public function accept(string $path, array $post, StorageDriver $storage, Urls $urls, Guard $guard): array;
}
