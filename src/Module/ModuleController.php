<?php
declare(strict_types=1);

namespace Pluck\Module;

/**
 * The base for a module's admin screens.
 *
 * Deliberately not `Admin\Controller`. That one is handed the admin Context and
 * can reach the storage driver, Auth and Bootstrap through it; this one is handed
 * a ModuleContext and cannot. The two look similar on purpose — someone who has
 * written an admin screen should recognise this — but the narrowing is the whole
 * point, so they do not share a parent that would carry the wider one in.
 */
abstract class ModuleController
{
	public function __construct(protected readonly ModuleContext $c)
	{
	}

	/** @param array<string,string|int|float> $replacements */
	protected function t(string $key, array $replacements = [], ?int $count = null): string
	{
		return $this->c->t($key, $replacements, $count);
	}

	/** @param array<string,mixed> $data */
	protected function render(string $template, array $data = []): never
	{
		$this->c->render($template, $data);
	}

	/** @param array<string,string|int> $params */
	protected function back(string $route, array $params = []): never
	{
		$this->c->back($route, $params);
	}
}
