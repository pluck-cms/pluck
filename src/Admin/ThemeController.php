<?php
declare(strict_types=1);

namespace Pluck\Admin;

use Pluck\Model\Role;
use Pluck\Theme\ThemeParameters;
use Pluck\Theme\ThemeRepository;

/**
 * Everything about how the site looks, in one place.
 *
 * Which theme, what it has been told, and its stylesheet — those three belong
 * together, and having the first under Settings and the third under its own menu
 * entry meant changing the look of a site was a tour of the admin.
 *
 * The parameters are the part that matters. Pluck 4 let somebody edit a theme's
 * PHP from the browser, which is a shell however it is described, and v5 removed
 * it. What went with it was the ability to change the words in a theme without
 * FTP — and most of what that editor was used for was changing words. A theme
 * declares what it wants filled in; this fills it in.
 */
final class ThemeController extends Controller
{
	public function show(): never
	{
		$this->requireTheme();

		$repository = $this->themes();
		$active = $repository->active($this->c->storage);

		$this->render('admin/themes', [
			'title' => $this->t('theme.title.appearance'),
			'themes' => $repository->available(),
			'active' => $active,
			'declared' => $active->parameters(),
			'values' => (new ThemeParameters($this->c->storage))->values($active),
			'problems' => $repository->problems(),
		]);
	}

	public function save(): never
	{
		$this->requireTheme();

		$request = $this->c->request;
		$repository = $this->themes();

		$chosen = $request->post('theme', '');

		if ($chosen !== '' && in_array($chosen, $repository->available(), true)) {
			$this->c->storage->setSetting('theme', $chosen);
		}

		/*
		 * Parameters are saved against the theme now selected, not the one that
		 * rendered the form.
		 *
		 * Changing the theme and typing a value in one go is a thing somebody
		 * will do, and storing the new value under the old theme's name would
		 * lose it silently.
		 */
		$active = $repository->active($this->c->storage);
		$given = [];

		foreach (array_keys($active->parameters()) as $name) {
			$given[$name] = $request->post('param_' . $name, '');
		}

		(new ThemeParameters($this->c->storage))->save($active, $given);

		$this->c->flash->ok($this->t('theme.flash.saved'));
		$this->back('themes');
	}

	/**
	 * Put one parameter back to what the theme ships with.
	 *
	 * Storing an empty value rather than deleting the key: values() reads empty
	 * as "use the default", so a reset never has to know what the default was —
	 * and a theme that changes its default afterwards is followed automatically.
	 */
	public function reset(): never
	{
		$this->requireTheme();

		$active = $this->themes()->active($this->c->storage);
		$name = $this->c->request->post('name', '');

		if (array_key_exists($name, $active->parameters())) {
			$parameters = new ThemeParameters($this->c->storage);
			$values = $parameters->values($active);
			$values[$name] = '';

			$parameters->save($active, $values);
			$this->c->flash->ok($this->t('theme.flash.reset'));
		}

		$this->back('themes');
	}

	private function requireTheme(): void
	{
		$user = $this->c->auth->user();

		if ($user === null || !in_array($user->role, [Role::Owner, Role::Admin], true)) {
			$this->c->flash->stop($this->t('theme.flash.not_allowed'));
			$this->back('dashboard');
		}
	}

	private function themes(): ThemeRepository
	{
		return new ThemeRepository($this->c->app->rootDir . '/themes');
	}
}
