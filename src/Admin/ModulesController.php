<?php
declare(strict_types=1);

namespace Pluck\Admin;

/**
 * The modules this install has, and where to manage each.
 *
 * They used to be appended to the main navigation, one entry each. With the two
 * bundled ones that was a short list; with a site's own modules added it was the
 * navigation being decided by whatever happened to be installed, and the things
 * somebody uses every day sank further down each time.
 *
 * Which modules load is a setting rather than a screen of its own, because
 * enabling one is a security decision and belongs where the rest of those are.
 * This screen is for using them.
 */
final class ModulesController extends Controller
{
	public function show(): never
	{
		$user = $this->c->auth->user();

		$this->render('admin/modules', [
			'title' => $this->t('nav.modules'),
			// The same list the navigation used to build, unchanged: it has
			// already dropped the ones this account may not manage.
			'modules' => $user === null
				? []
				: $this->c->modules->navigation($user->role, $this->c->auth->accessList()),
		]);
	}
}
