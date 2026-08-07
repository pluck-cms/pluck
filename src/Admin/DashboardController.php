<?php
declare(strict_types=1);

namespace Pluck\Admin;

final class DashboardController extends Controller
{
	public function show(): never
	{
		$storage = $this->c->storage;
		$user = $this->c->auth->user();

		$pages = $storage->allPages();

		// Most recent first, because the reason to open the admin is nearly always
		// to carry on with the thing you were last editing.
		usort($pages, static fn ($a, $b): int => strcmp((string) $b->updatedAt, (string) $a->updatedAt));

		/*
		 * The overview offers shortcuts, and a shortcut to a screen the account
		 * cannot open is worse than no shortcut: it reads as a permission problem
		 * on the account rather than a deliberate limit. An author used to see
		 * "Manage people" here and get a 403 for clicking it.
		 */
		$this->render('admin/dashboard', [
			'title' => $this->t('dashboard.title.overview'),
			'canSeeUsers' => $this->c->auth->can('user.view'),
			'canSeeSettings' => $this->c->auth->can('settings.view'),
			'canCreatePages' => $this->c->auth->can('page.create'),
			'recent' => array_slice($pages, 0, 6),
			'pageCount' => count($pages),
			'hiddenCount' => count(array_filter($pages, static fn ($p): bool => $p->hidden)),
			'userCount' => $storage->countUsers(),
			'storageName' => $storage->name(),
			'twoFactorOn' => $user?->totpSecret !== null && $user?->totpSecret !== '',
			'searchEnabled' => (bool) $storage->getSetting('search_enabled', false),
		]);
	}
}
