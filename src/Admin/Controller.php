<?php
declare(strict_types=1);

namespace Pluck\Admin;

use Pluck\Http\Response;

abstract class Controller
{
	public function __construct(protected readonly Context $c)
	{
	}

	/**
	 * A translated string as plain text.
	 *
	 * Plain, not escaped: this is used for flash messages and page titles, which
	 * templates print through e() or $view->t(). Escaping here as well would show
	 * people "It&#039;s".
	 *
	 * @param array<string,string|int|float> $replacements
	 */
	protected function t(string $key, array $replacements = [], ?int $count = null): string
	{
		return $this->c->app->translator()->get($key, $replacements, $count);
	}

	protected function render(string $template, array $data = []): never
	{
		Response::html($this->c->view->page($template, $data));
	}

	protected function back(string $route = 'dashboard', array $params = []): never
	{
		Response::redirect(self::url($route, $params));
	}

	/** Build an admin URL. Values are encoded here so no template has to remember. */
	public static function url(string $route, array $params = []): string
	{
		$query = ['p' => $route] + $params;

		return 'admin.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	}
}
