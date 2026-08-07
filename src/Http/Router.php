<?php
declare(strict_types=1);

namespace Pluck\Http;

use Pluck\Admin\Context;
use Pluck\Security\Csrf;

/**
 * A route table, not a URL parser.
 *
 * Admin URLs stay in the shape Pluck has always used — admin.php?p=pages — so no
 * install needs rewrite rules. What is new is that authentication, the
 * permission check and CSRF verification all happen here, once, before a
 * controller is constructed. In version 4 each of the forty-odd files under
 * data/inc was responsible for checking those itself, and some forgot.
 */
final class Router
{
	/** @var array<string, Route> keyed by "METHOD name" */
	private array $routes = [];

	private string $fallback = 'dashboard';

	public function get(string $name, string $controller, string $action, ?string $permission = null, bool $guest = false, ?string $module = null): void
	{
		$this->add(new Route('GET', $name, $controller, $action, $permission, $guest, $module));
	}

	public function post(string $name, string $controller, string $action, ?string $permission = null, bool $guest = false, ?string $module = null): void
	{
		$this->add(new Route('POST', $name, $controller, $action, $permission, $guest, $module));
	}

	public function add(Route $route): void
	{
		$this->routes[$route->method . ' ' . $route->name] = $route;
	}

	public function find(string $method, string $name): ?Route
	{
		return $this->routes[strtoupper($method) . ' ' . $name] ?? null;
	}

	/**
	 * Every route in the table. Used by RouteAccessTest to check the permissions
	 * against each role, so nothing has to restate the table to test it.
	 *
	 * @return list<Route>
	 */
	public function all(): array
	{
		return array_values($this->routes);
	}

	public function has(string $name): bool
	{
		return isset($this->routes['GET ' . $name]) || isset($this->routes['POST ' . $name]);
	}

	public function dispatch(Context $context): void
	{
		$request = $context->request;
		$name = $request->route !== '' ? $request->route : $this->fallback;

		$route = $this->find($request->method, $name);

		if ($route === null) {
			// A name that exists under the other verb is a wrong method, not a
			// missing page — worth saying so, because it is nearly always a bug in
			// a form's method attribute.
			$status = $this->has($name) ? 405 : 404;
			$this->fail($context, $status);
			return;
		}

		if (!$route->guest && !$context->auth->check()) {
			$target = $name === $this->fallback ? 'admin.php?p=signin' : 'admin.php?p=signin&next=' . rawurlencode($name);
			Response::redirect($target);
		}

		if ($route->permission !== null && !$context->auth->can($route->permission)) {
			$this->fail($context, 403);
			return;
		}

		if ($route->method === 'POST') {
			// Every state change is verified here, so no controller can be the one
			// that forgot. Failure sends the user back rather than showing a dead
			// end: an expired token is usually just a tab left open overnight.
			if (!$context->csrf->isValid($request->post(Csrf::FIELD, ''))) {
				$context->flash->stop($context->app->translator()->get('form.flash.expired_nothing_saved'));
				Response::redirect('admin.php?p=' . rawurlencode($name === 'signin' ? 'signin' : $this->fallback));
			}
		}

		// A module route gets the narrowed context. Everything above this line —
		// sign-in, permission, CSRF — has already run identically for both, which
		// is the reason module routes live in this table at all.
		$scope = $route->module === null ? $context : $context->forModule($route->module);

		$controller = new ($route->controller)($scope);
		$controller->{$route->action}();
	}

	private function fail(Context $context, int $status): void
	{
		$titles = [
			403 => 'Not your permission level',
			404 => 'No such screen',
			405 => 'Wrong method',
		];
		$bodies = [
			403 => 'Your account does not have access to this. Ask an administrator if you need it.',
			404 => 'That address does not match anything in the admin. It may have moved between versions.',
			405 => 'This screen expects a different request method. If you got here from a link, that link is wrong.',
		];

		http_response_code($status);
		echo $context->view->page('errors/admin', [
			'title' => $titles[$status] ?? 'Something went wrong',
			'message' => $bodies[$status] ?? '',
			'status' => $status,
		]);
	}
}
